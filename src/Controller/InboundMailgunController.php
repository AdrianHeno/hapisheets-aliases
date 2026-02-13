<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InboundRaw;
use App\Entity\Message;
use App\Repository\AliasRepository;
use App\Service\MimeParser;
use App\Service\MailgunWebhookSignatureVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inbound/mailgun')]
class InboundMailgunController extends AbstractController
{
    public function __construct(
        private readonly AliasRepository $aliasRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MimeParser $mimeParser,
        private readonly MailgunWebhookSignatureVerifier $signatureVerifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/raw-mime', name: 'app_inbound_mailgun_raw_mime', methods: ['POST'])]
    public function rawMime(Request $request): Response
    {
        $params = $this->getRequestParams($request);

        if ($this->signatureVerifier->isConfigured()) {
            $timestamp = $params['timestamp'] ?? null;
            $token = $params['token'] ?? null;
            $signature = $params['signature'] ?? null;
            $timestamp = $timestamp !== null ? (string) $timestamp : null;
            $token = $token !== null ? (string) $token : null;
            $signature = $signature !== null ? (string) $signature : null;

            if ($timestamp === null || $token === null || $signature === null
                || !$this->signatureVerifier->isTimestampFresh($timestamp)
                || !$this->signatureVerifier->verify($timestamp, $token, $signature)) {
                return new Response('Invalid signature.', Response::HTTP_FORBIDDEN);
            }
        } else {
            $this->logger->warning('Mailgun webhook signature verification skipped: MAILGUN_WEBHOOK_SIGNING_KEY is not set.');
        }

        $recipient = $params['recipient'] ?? null;
        $bodyMime = $this->getBodyParamOrFile($params, $request, 'body-mime');
        $bodyPlain = $this->getBodyParamOrFile($params, $request, 'body-plain');
        $bodyHtml = $this->getBodyParamOrFile($params, $request, 'body-html');

        // Mailgun: body-mime when URL ends with raw-mime; otherwise body-plain / body-html (multipart may send as file parts)
        $body = $bodyMime !== '' ? $bodyMime : ($bodyPlain !== '' ? $bodyPlain : $bodyHtml);

        if ($recipient === null || $recipient === '') {
            $this->logBadRequest($request, $params, 'missing_recipient');
            return new Response('Missing required fields: recipient, body.', Response::HTTP_BAD_REQUEST);
        }

        $recipient = trim((string) $recipient);
        if ($recipient === '') {
            $this->logBadRequest($request, $params, 'empty_recipient');
            return new Response('Missing required fields: recipient, body.', Response::HTTP_BAD_REQUEST);
        }

        if ($body === '') {
            $this->logBadRequest($request, $params, 'missing_body');
            return new Response('Missing required fields: recipient, body.', Response::HTTP_BAD_REQUEST);
        }

        $atPos = strpos($recipient, '@');
        if ($atPos === false || $atPos === 0) {
            throw new NotFoundHttpException('Alias not found.');
        }
        $localPart = strtolower(trim(substr($recipient, 0, $atPos)));
        if ($localPart === '') {
            throw new NotFoundHttpException('Alias not found.');
        }

        $alias = $this->aliasRepository->findOneBy(['localPart' => $localPart, 'enabled' => true]);
        if ($alias === null) {
            throw new NotFoundHttpException('Alias not found.');
        }

        $receivedAt = new \DateTimeImmutable();

        $inbound = new InboundRaw();
        $inbound->setAlias($alias);
        $inbound->setReceivedAt($receivedAt);
        $inbound->setRawMime($body);
        $this->entityManager->persist($inbound);

        // Create a Message so the email appears in the inbox UI (inbox lists Message, not InboundRaw)
        $subject = $params['subject'] ?? $params['Subject'] ?? '(No subject)';
        $fromAddress = $params['sender'] ?? $params['from'] ?? $params['From'] ?? '(Unknown)';
        $message = new Message();
        $message->setAlias($alias);
        $message->setReceivedAt($receivedAt);
        $message->setSubject(mb_substr((string) $subject, 0, 255));
        $message->setFromAddress(mb_substr((string) $fromAddress, 0, 255));
        $message->setBody($body);
        $message->setInboundRaw($inbound);

        try {
            $parsed = $this->mimeParser->parse($body);
            $textForPreview = $parsed->textBody ?? '';
            $preview = mb_substr(preg_replace('/\s+/', ' ', trim($textForPreview)) ?: '', 0, 120);
            if ($preview !== '') {
                $message->setPreviewSnippet($preview);
            }
            $message->setHasHtmlBody($parsed->htmlBody !== null && $parsed->htmlBody !== '');
        } catch (\Throwable $e) {
            $this->logger->warning('MIME parsing failed for inbound message', [
                'exception' => $e,
                'alias_id' => $alias->getId(),
            ]);
        }

        $this->entityManager->persist($message);

        $this->entityManager->flush();

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * Get POST parameters from the request. If Symfony did not parse the body (e.g. wrong Content-Type),
     * attempt to parse application/x-www-form-urlencoded from raw content.
     *
     * @return array<string, mixed>
     */
    private function getRequestParams(Request $request): array
    {
        $params = $request->request->all();
        if ($params !== []) {
            return $params;
        }
        $content = $request->getContent();
        $contentType = $request->headers->get('Content-Type', '');
        if ($content !== '' && $content !== false && !str_contains($contentType, 'boundary')) {
            parse_str($content, $parsed);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
        return [];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function logBadRequest(Request $request, array $params, string $reason): void
    {
        $this->logger->warning('Mailgun raw-mime webhook bad request.', [
            'reason' => $reason,
            'content_type' => $request->headers->get('Content-Type'),
            'request_keys' => array_keys($params),
            'file_keys' => array_keys($request->files->all()),
            'content_length' => $request->headers->get('Content-Length'),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function getBodyParamOrFile(array $params, Request $request, string $key): string
    {
        $value = $params[$key] ?? null;
        if ($value !== null && $value !== '') {
            return (string) $value;
        }
        if ($request->files->has($key)) {
            $file = $request->files->get($key);
            if ($file instanceof UploadedFile) {
                $content = file_get_contents($file->getPathname());
                return $content !== false ? $content : '';
            }
        }
        return '';
    }
}
