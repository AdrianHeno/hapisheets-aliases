<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\MimeParser;
use App\Service\OwnerResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly OwnerResolver $ownerResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly MimeParser $mimeParser,
        private readonly string $aliasDomain = 'hapisheets.com',
    ) {
    }

    #[Route('/{id}', name: 'app_message_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }
        // Type-narrow to User for OwnerResolver (getUser() returns UserInterface).

        $message = $this->ownerResolver->getMessageForUser($this->messageRepository, $id, $user);

        $parsed = null;
        $rawMime = null;
        $inboundRaw = $message->getInboundRaw();
        if ($inboundRaw !== null) {
            $raw = $inboundRaw->getRawMime();
            if ($raw !== null && $raw !== '') {
                $rawMime = $raw;
                $parsed = $this->mimeParser->parse($raw);
            }
        }
        if ($rawMime === null) {
            $rawMime = $message->getBody();
        }

        $bodySafeHtml = $parsed !== null ? $parsed->sanitizedHtmlBody : null;

        return $this->render('message/show.html.twig', [
            'message' => $message,
            'parsed' => $parsed,
            'raw_mime' => $rawMime,
            'body_safe_html' => $bodySafeHtml,
            'alias_domain' => $this->aliasDomain,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_message_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }
        // Type-narrow to User for OwnerResolver.

        $message = $this->ownerResolver->getMessageForUser($this->messageRepository, $id, $user);
        $alias = $message->getAlias();

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_message', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $aliasId = $alias->getId();
        $this->entityManager->remove($message);
        $this->entityManager->flush();

        $this->addFlash('success', 'Message deleted.');

        return $this->redirectToRoute('app_alias_inbox', ['id' => $aliasId], Response::HTTP_SEE_OTHER);
    }
}
