<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Alias;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\AliasRepository;
use App\Repository\MessageRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves alias or message for the current user; throws 404 if not found or not owner.
 */
final class OwnerResolver
{
    public function getAliasForUser(AliasRepository $aliasRepository, int $id, User $user): Alias
    {
        $alias = $aliasRepository->find($id);
        if ($alias === null || $alias->getUser()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Alias not found.');
        }
        return $alias;
    }

    public function getMessageForUser(MessageRepository $messageRepository, int $id, User $user): Message
    {
        $message = $messageRepository->find($id);
        if ($message === null) {
            throw new NotFoundHttpException('Message not found.');
        }
        $alias = $message->getAlias();
        if ($alias === null || $alias->getUser()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Message not found.');
        }
        return $message;
    }
}
