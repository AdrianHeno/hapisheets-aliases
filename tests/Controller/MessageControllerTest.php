<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alias;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MessageControllerTest extends WebTestCase
{
    public function testInboxOwnerSeesList(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'inbox-owner-' . uniqid('', true));
        $client->loginUser($user);

        $client->request('GET', '/aliases/' . $alias->getId() . '/inbox');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Inbox');
        self::assertSelectorTextContains('body', $alias->getLocalPart() . '@hapisheets.com');
    }

    public function testInboxNonOwnerGets404(): void
    {
        $client = static::createClient();
        $owner = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($owner, 'inbox-other-' . uniqid('', true));
        $otherUser = $this->createAndPersistUser($client);
        $client->loginUser($otherUser);

        $client->request('GET', '/aliases/' . $alias->getId() . '/inbox');

        self::assertResponseStatusCodeSame(404);
    }

    public function testShowMessageOwnerSeesMessage(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'show-owner-' . uniqid('', true));
        $message = $this->createMessageForAlias($alias, 'Test subject', 'from@example.com', 'Body text');
        $client->loginUser($user);

        $client->request('GET', '/messages/' . $message->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Test subject');
        self::assertSelectorTextContains('body', 'from@example.com');
        self::assertSelectorTextContains('body', 'Body text');
    }

    public function testShowMessageNonOwnerGets404(): void
    {
        $client = static::createClient();
        $owner = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($owner, 'show-other-' . uniqid('', true));
        $message = $this->createMessageForAlias($alias, 'Secret', 'a@b.com', 'Secret body');
        $otherUser = $this->createAndPersistUser($client);
        $client->loginUser($otherUser);

        $client->request('GET', '/messages/' . $message->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteMessageOwnerRedirectsAndRemoves(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'del-owner-' . uniqid('', true));
        $message = $this->createMessageForAlias($alias, 'To delete', 'x@y.com', 'Content');
        $messageId = $message->getId();
        $aliasId = $alias->getId();
        $client->loginUser($user);

        $client->request('GET', '/messages/' . $messageId);
        $form = $client->getCrawler()->selectButton('Delete message')->form();
        $client->submit($form);

        self::assertResponseRedirects();
        self::assertStringContainsString('/aliases/' . $aliasId . '/inbox', (string) $client->getResponse()->headers->get('Location'));
        $client->followRedirect();
        self::assertSelectorExists('.alert-success');

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $messageReloaded = $em->find(Message::class, $messageId);
        self::assertNull($messageReloaded);
    }

    public function testDeleteMessageNonOwnerGets404(): void
    {
        $client = static::createClient();
        $owner = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($owner, 'del-other-' . uniqid('', true));
        $message = $this->createMessageForAlias($alias, 'Not yours', 'a@b.com', 'Body');
        $otherUser = $this->createAndPersistUser($client);
        $client->loginUser($otherUser);

        $client->request('GET', '/aliases/' . $alias->getId() . '/inbox');
        self::assertResponseStatusCodeSame(404);

        $client->request('POST', '/messages/' . $message->getId() . '/delete', ['_token' => 'dummy']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteMessageInvalidCsrfReturns403(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'del-csrf-' . uniqid('', true));
        $message = $this->createMessageForAlias($alias, 'CSRF test', 'a@b.com', 'Body');
        $client->loginUser($user);

        $client->request('POST', '/messages/' . $message->getId() . '/delete', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
    }

    private function createAliasForUser(User $user, string $localPart): Alias
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $alias = new Alias();
        $alias->setUser($user);
        $alias->setLocalPart($localPart);
        $em->persist($alias);
        $em->flush();
        return $alias;
    }

    private function createMessageForAlias(Alias $alias, string $subject, string $fromAddress, string $body): Message
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $message = new Message();
        $message->setAlias($alias);
        $message->setReceivedAt(new \DateTimeImmutable());
        $message->setSubject($subject);
        $message->setFromAddress($fromAddress);
        $message->setBody($body);
        $em->persist($message);
        $em->flush();
        return $message;
    }

    private function createAndPersistUser($client, ?string $email = null): User
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->ensureSchema($em);
        $user = new User();
        $user->setEmail($email ?? 'msg-test-' . uniqid('', true) . '@example.com');
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'password'));
        $em->persist($user);
        $em->flush();
        return $user;
    }

    private function ensureSchema(EntityManagerInterface $em): void
    {
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return;
        }
        $schemaTool = new SchemaTool($em);
        $schemaTool->updateSchema($metadata);
    }
}
