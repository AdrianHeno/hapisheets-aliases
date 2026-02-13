<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InboundRaw;
use App\Repository\AliasRepository;
use App\Service\MailgunWebhookSignatureVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly MailgunWebhookSignatureVerifier $signatureVerifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/raw-mime', name: 'app_inbound_mailgun_raw_mime', methods: ['POST'])]
    public function rawMime(Request $request): Response
    {
        if (!$this->signatureVerifier->isConfigured()) {
            $this->logger->error('Mailgun webhook signature verification skipped: MAILGUN_WEBHOOK_SIGNING_KEY is not set.');
            return new Response('Webhook signing key not configured.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $timestamp = $request->request->get('timestamp');
        $token = $request->request->get('token');
        $signature = $request->request->get('signature');
        $timestamp = $timestamp !== null ? (string) $timestamp : null;
        $token = $token !== null ? (string) $token : null;
        $signature = $signature !== null ? (string) $signature : null;

        if ($timestamp === null || $token === null || $signature === null
            || !$this->signatureVerifier->isTimestampFresh($timestamp)
            || !$this->signatureVerifier->verify($timestamp, $token, $signature)) {
            return new Response('Invalid signature.', Response::HTTP_FORBIDDEN);
        }

        $recipient = $request->request->get('recipient');
        $bodyMime = $request->request->get('body-mime');

        if ($recipient === null || $bodyMime === null) {
            return new Response('Missing required fields: recipient, body-mime.', Response::HTTP_BAD_REQUEST);
        }

        $recipient = trim((string) $recipient);
        $bodyMime = (string) $bodyMime;

        if ($recipient === '' || $bodyMime === '') {
            return new Response('Missing required fields: recipient, body-mime.', Response::HTTP_BAD_REQUEST);
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

        $inbound = new InboundRaw();
        $inbound->setAlias($alias);
        $inbound->setReceivedAt(new \DateTimeImmutable());
        $inbound->setRawMime($bodyMime);

        $this->entityManager->persist($inbound);
        $this->entityManager->flush();

        return new Response('OK', Response::HTTP_OK);
    }
}
