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

class DevInboundControllerTest extends WebTestCase
{
    public function testPostValidPayloadCreatesMessageAndReturnsId(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $alias = $this->createAliasForUser($user, 'inbound-' . uniqid('', true));

        $payload = [
            'to' => $alias->getLocalPart() . '@hapisheets.com',
            'from' => 'sender@example.com',
            'subject' => 'Test subject',
            'body' => 'Test body',
        ];
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type', 'application/json');
        $json = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $json);
        self::assertIsInt($json['id']);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $message = $em->find(Message::class, $json['id']);
        self::assertNotNull($message);
        self::assertSame($alias->getId(), $message->getAlias()?->getId());
        self::assertSame('Test subject', $message->getSubject());
        self::assertSame('sender@example.com', $message->getFromAddress());
        self::assertSame('Test body', $message->getBody());
    }

    public function testPostUnknownRecipientReturns404(): void
    {
        $client = static::createClient();
        $payload = [
            'to' => 'nonexistent@hapisheets.com',
            'from' => 'a@b.com',
            'subject' => 'Hi',
            'body' => 'Hi',
        ];
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testPostWrongDomainReturns422(): void
    {
        $client = static::createClient();
        $payload = [
            'to' => 'someone@other.com',
            'from' => 'a@b.com',
            'subject' => 'Hi',
            'body' => 'Hi',
        ];
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
        $json = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $json);
    }

    public function testPostMissingRequiredFieldsReturns422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['to' => 'a@hapisheets.com'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testPostEmptySubjectAndBodyReturns422(): void
    {
        $client = static::createClient();
        $payload = [
            'to' => 'any@hapisheets.com',
            'from' => 'a@b.com',
            'subject' => '',
            'body' => '',
        ];
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testPostInvalidJsonReturns422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not json'
        );

        self::assertResponseStatusCodeSame(422);
        $json = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $json);
        self::assertStringContainsString('JSON', $json['error']);
    }

    public function testPostNonJsonContentTypeReturns415(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            '{"to":"a@hapisheets.com","from":"b@c.com","subject":"x","body":"y"}'
        );

        self::assertResponseStatusCodeSame(415);
    }

    public function testPostSubjectTooLongReturns422(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $alias = $this->createAliasForUser($user, 'long-subject-' . uniqid('', true));
        $payload = [
            'to' => $alias->getLocalPart() . '@hapisheets.com',
            'from' => 'a@b.com',
            'subject' => str_repeat('x', 256),
            'body' => 'body',
        ];
        $client->request(
            'POST',
            '/dev/inbound',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
        $json = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $json);
        self::assertStringContainsString('subject', $json['error']);
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

    private function createAndPersistUser(?string $email = null): User
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->ensureSchema($em);
        $user = new User();
        $user->setEmail($email ?? 'dev-inbound-' . uniqid('', true) . '@example.com');
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
