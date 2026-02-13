<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Message;
use App\Repository\AliasRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
/**
 * Dev/test only. Route POST /dev/inbound is registered in config/routes.yaml (when@dev and when@test)
 * so it is not available in prod.
 */
class DevInboundController extends AbstractController
{
    private const MAX_SUBJECT_LENGTH = 255;
    private const MAX_FROM_LENGTH = 255;
    private const MAX_BODY_LENGTH = 65535;

    public function __construct(
        private readonly AliasRepository $aliasRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $aliasDomain = 'hapisheets.com',
    ) {
    }

    public function inbound(Request $request): JsonResponse
    {
        if (!$request->headers->contains('Content-Type', 'application/json')
            && str_starts_with((string) $request->headers->get('Content-Type', ''), 'application/json') === false) {
            return new JsonResponse(['error' => 'Content-Type must be application/json.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Invalid JSON.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $to = $data['to'] ?? '';
        $from = $data['from'] ?? '';
        $subject = $data['subject'] ?? '';
        $body = $data['body'] ?? '';

        if ($to === '' || $from === '' || ($subject === '' && $body === '')) {
            return new JsonResponse(['error' => 'Missing required fields: to, from, and at least one of subject or body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (strlen($from) > self::MAX_FROM_LENGTH) {
            return new JsonResponse(['error' => sprintf('from must be at most %d characters.', self::MAX_FROM_LENGTH)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            return new JsonResponse(['error' => sprintf('subject must be at most %d characters.', self::MAX_SUBJECT_LENGTH)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (strlen($body) > self::MAX_BODY_LENGTH) {
            return new JsonResponse(['error' => sprintf('body must be at most %d characters.', self::MAX_BODY_LENGTH)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $parts = explode('@', $to, 2);
        if (count($parts) !== 2) {
            return new JsonResponse(['error' => 'Invalid recipient email.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        [$localPart, $domain] = $parts;
        $localPart = trim($localPart);
        $domain = trim($domain);
        if ($localPart === '' || $domain === '' || strtolower($domain) !== strtolower($this->aliasDomain)) {
            return new JsonResponse(['error' => 'Recipient domain must match alias domain.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $alias = $this->aliasRepository->findOneBy(['localPart' => $localPart]);
        if ($alias === null) {
            throw new NotFoundHttpException('Alias not found for this recipient.');
        }

        $message = new Message();
        $message->setAlias($alias);
        $message->setReceivedAt(new \DateTimeImmutable());
        $message->setSubject($subject);
        $message->setFromAddress($from);
        $message->setBody($body);
        $preview = mb_substr(preg_replace('/\s+/', ' ', trim($body)) ?: '', 0, 120);
        if ($preview !== '') {
            $message->setPreviewSnippet($preview);
        }
        $message->setHasHtmlBody(false);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $message->getId()], Response::HTTP_CREATED);
    }
}
