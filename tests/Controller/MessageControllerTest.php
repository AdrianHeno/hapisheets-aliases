<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alias;
use App\Entity\InboundRaw;
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

    public function testInboxShowsFromSubjectReceivedPreviewAndHtmlBadge(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'inbox-preview-' . uniqid('', true));
        $this->createMessageForAlias(
            $alias,
            'Preview test subject',
            'Jane Doe <jane@example.com>',
            'Full body content here.',
            'First ~120 chars of text body for the preview snippet in the list.',
            true,
        );
        $client->loginUser($user);

        $client->request('GET', '/aliases/' . $alias->getId() . '/inbox');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Inbox');
        self::assertSelectorTextContains('body', 'Jane Doe <jane@example.com>');
        self::assertSelectorTextContains('body', 'Preview test subject');
        self::assertSelectorTextContains('body', 'First ~120 chars of text body');
        self::assertSelectorTextContains('body', 'HTML');
    }

    public function testInboxShowsPlaceholderWhenNoPreview(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'inbox-nopreview-' . uniqid('', true));
        $this->createMessageForAlias($alias, 'No preview', 'a@b.com', 'Body', null, false);
        $client->loginUser($user);

        $client->request('GET', '/aliases/' . $alias->getId() . '/inbox');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No preview');
        self::assertSelectorTextContains('body', '—');
    }

    public function testInboxOnlyShowsMessagesForThatAlias(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $aliasA = $this->createAliasForUser($user, 'inbox-a-' . uniqid('', true));
        $aliasB = $this->createAliasForUser($user, 'inbox-b-' . uniqid('', true));
        $this->createMessageForAlias($aliasA, 'Only in A', 'x@y.com', 'Body A', 'Preview A', false);
        $this->createMessageForAlias($aliasB, 'Only in B', 'z@w.com', 'Body B', 'Preview B', false);
        $client->loginUser($user);

        $client->request('GET', '/aliases/' . $aliasA->getId() . '/inbox');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Only in A');
        self::assertSelectorTextContains('body', 'Preview A');
        self::assertSelectorTextNotContains('body', 'Only in B');
        self::assertSelectorTextNotContains('body', 'Preview B');
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

    private const SAMPLE_MULTIPART_ALTERNATIVE = "MIME-Version: 1.0\r\n"
        . "From: Jane Doe <jane@example.com>\r\n"
        . "To: test@hapisheets.com\r\n"
        . "Subject: Parsed multipart subject\r\n"
        . "Date: Fri, 13 Feb 2026 12:00:00 +0000\r\n"
        . "Content-Type: multipart/alternative; boundary=\"_bound_\"\r\n"
        . "\r\n"
        . "--_bound_\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "\r\n"
        . "Plain part only.\r\n"
        . "\r\n--_bound_\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "\r\n"
        . "<p>Hello,</p><p>This is the <strong>HTML</strong> part.</p>\r\n"
        . "\r\n--_bound_--\r\n";

    public function testMessageDetailRendersParsedChosenBodyWhenRawMimeExists(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'detail-parsed-' . uniqid('', true));
        $message = $this->createMessageWithInboundRaw($alias, self::SAMPLE_MULTIPART_ALTERNATIVE, 'Parsed multipart subject', 'Jane Doe <jane@example.com>');
        $client->loginUser($user);

        $client->request('GET', '/messages/' . $message->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Parsed multipart subject');
        self::assertSelectorTextContains('body', 'Jane Doe');
        self::assertSelectorTextContains('body', 'jane@example.com');
        self::assertSelectorTextContains('body', 'This is the');
        self::assertSelectorTextContains('body', 'HTML');
        self::assertSelectorTextContains('body', 'part.');
    }

    public function testMessageDetailRendersSanitizedBodyScriptRemoved(): void
    {
        $rawMime = "From: x@y.com\r\nTo: a@b.com\r\nSubject: XSS test\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
            . '<p>Safe content</p><script>alert(1)</script><p>After script</p>';
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'detail-sanitized-' . uniqid('', true));
        $message = $this->createMessageWithInboundRaw($alias, $rawMime, 'XSS test', 'x@y.com');
        $client->loginUser($user);

        $client->request('GET', '/messages/' . $message->getId());

        self::assertResponseIsSuccessful();
        $bodyContent = $client->getCrawler()->filter('.email-view__body-content')->html();
        self::assertStringNotContainsString('alert(1)', $bodyContent, 'Rendered body must not contain script payload');
        self::assertStringNotContainsString('<script>', $bodyContent);
        self::assertSelectorTextContains('.email-view__body-content', 'Safe content');
        self::assertSelectorTextContains('.email-view__body-content', 'After script');
    }

    private function createMessageWithInboundRaw(Alias $alias, string $rawMime, string $subject, string $fromAddress): Message
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $receivedAt = new \DateTimeImmutable();

        $inbound = new InboundRaw();
        $inbound->setAlias($alias);
        $inbound->setReceivedAt($receivedAt);
        $inbound->setRawMime($rawMime);
        $em->persist($inbound);

        $message = new Message();
        $message->setAlias($alias);
        $message->setReceivedAt($receivedAt);
        $message->setSubject($subject);
        $message->setFromAddress($fromAddress);
        $message->setBody($rawMime);
        $message->setInboundRaw($inbound);
        $em->persist($message);
        $em->flush();

        return $message;
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

    private function createMessageForAlias(
        Alias $alias,
        string $subject,
        string $fromAddress,
        string $body,
        ?string $previewSnippet = null,
        bool $hasHtmlBody = false,
    ): Message {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $message = new Message();
        $message->setAlias($alias);
        $message->setReceivedAt(new \DateTimeImmutable());
        $message->setSubject($subject);
        $message->setFromAddress($fromAddress);
        $message->setBody($body);
        if ($previewSnippet !== null) {
            $message->setPreviewSnippet($previewSnippet);
        }
        $message->setHasHtmlBody($hasHtmlBody);
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
