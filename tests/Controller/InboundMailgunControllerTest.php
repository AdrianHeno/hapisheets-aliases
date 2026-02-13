<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alias;
use App\Entity\InboundRaw;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InboundMailgunControllerTest extends WebTestCase
{
    private const TEST_SIGNING_KEY = 'test-webhook-signing-key';

    public function testPostValidPayloadReturns200AndStoresRawMime(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $localPart = 'mailgun-' . uniqid('', true);
        $alias = $this->createAliasForUser($user, $localPart);

        $rawMime = "From: sender@example.com\r\nTo: {$localPart}@in.example.com\r\nSubject: Hi\r\n\r\nBody here.";
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => $localPart . '@in.example.com',
                    'body-mime' => $rawMime,
                ]
            )
        );

        self::assertResponseStatusCodeSame(200);
        self::assertSame('OK', $client->getResponse()->getContent());

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $raws = $em->getRepository(InboundRaw::class)->findBy(['alias' => $alias], ['receivedAt' => 'DESC'], 1);
        self::assertCount(1, $raws);
        self::assertSame($rawMime, $raws[0]->getRawMime());
        self::assertSame($alias->getId(), $raws[0]->getAlias()?->getId());
    }

    public function testPostValidPayloadWithBodyPlainFallbackReturns200WhenBodyMimeAbsent(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $localPart = 'bodyplain-' . uniqid('', true);
        $alias = $this->createAliasForUser($user, $localPart);

        $bodyPlain = 'Plain text body when Mailgun does not send body-mime';
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => $localPart . '@example.com',
                    'body-plain' => $bodyPlain,
                ]
            )
        );

        self::assertResponseStatusCodeSame(200);
        self::assertSame('OK', $client->getResponse()->getContent());

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $raws = $em->getRepository(InboundRaw::class)->findBy(['alias' => $alias], ['receivedAt' => 'DESC'], 1);
        self::assertCount(1, $raws);
        self::assertSame($bodyPlain, $raws[0]->getRawMime());
    }

    public function testPostRecipientLocalPartIsLowercased(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $localPart = 'uppercase-' . uniqid('', true);
        $alias = $this->createAliasForUser($user, strtolower($localPart));

        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => strtoupper($localPart) . '@mail.example.com',
                    'body-mime' => 'MIME content',
                ]
            )
        );

        self::assertResponseStatusCodeSame(200);
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $raws = $em->getRepository(InboundRaw::class)->findBy(['alias' => $alias], ['receivedAt' => 'DESC'], 1);
        self::assertCount(1, $raws);
    }

    public function testPostMissingRecipientReturns400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge($this->makeMailgunSignatureParams(), ['body-mime' => 'Some MIME'])
        );

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('recipient', $client->getResponse()->getContent());
    }

    public function testPostMissingBodyMimeReturns400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge($this->makeMailgunSignatureParams(), ['recipient' => 'someone@example.com'])
        );

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('body', $client->getResponse()->getContent());
    }

    public function testPostEmptyRecipientReturns400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => '   ',
                    'body-mime' => 'MIME',
                ]
            )
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testPostEmptyBodyMimeReturns400(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $alias = $this->createAliasForUser($user, 'empty-body-' . uniqid('', true));

        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => $alias->getLocalPart() . '@example.com',
                    'body-mime' => '',
                ]
            )
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testPostUnknownAliasReturns404(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => 'nonexistent-' . uniqid('', true) . '@example.com',
                    'body-mime' => 'MIME',
                ]
            )
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testPostDisabledAliasReturns404(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $alias = $this->createAliasForUser($user, 'disabled-' . uniqid('', true));
        $alias->setEnabled(false);
        $container = static::getContainer();
        $container->get(EntityManagerInterface::class)->flush();

        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => $alias->getLocalPart() . '@example.com',
                    'body-mime' => 'MIME',
                ]
            )
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testPostRecipientWithoutAtReturns404(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => 'noaddress',
                    'body-mime' => 'MIME',
                ]
            )
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testPostRecipientEmptyLocalPartReturns404(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge(
                $this->makeMailgunSignatureParams(),
                [
                    'recipient' => '@example.com',
                    'body-mime' => 'MIME',
                ]
            )
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testPostInvalidSignatureReturns403(): void
    {
        $client = static::createClient();
        $params = $this->makeMailgunSignatureParams();
        $params['signature'] = 'invalid-hex-signature';

        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            array_merge($params, [
                'recipient' => 'any@example.com',
                'body-mime' => 'MIME',
            ])
        );

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Invalid signature', $client->getResponse()->getContent());
    }

    public function testPostMissingTimestampTokenOrSignatureReturns403(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            [
                'recipient' => 'any@example.com',
                'body-mime' => 'MIME',
            ]
        );

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * When the signing key is not set, the webhook accepts requests with only recipient and body-mime (no signature).
     *
     * @runInSeparateProcess
     */
    public function testPostValidPayloadWithoutSignatureSucceedsWhenSigningKeyNotSet(): void
    {
        putenv('MAILGUN_WEBHOOK_SIGNING_KEY=');
        $_ENV['MAILGUN_WEBHOOK_SIGNING_KEY'] = '';
        $_SERVER['MAILGUN_WEBHOOK_SIGNING_KEY'] = '';

        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $localPart = 'no-sig-' . uniqid('', true);
        $alias = $this->createAliasForUser($user, $localPart);

        $client->request(
            'POST',
            '/inbound/mailgun/raw-mime',
            [
                'recipient' => $localPart . '@example.com',
                'body-mime' => 'Raw MIME content without signature',
            ]
        );

        self::assertResponseStatusCodeSame(200);
        self::assertSame('OK', $client->getResponse()->getContent());

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $raws = $em->getRepository(InboundRaw::class)->findBy(['alias' => $alias], ['receivedAt' => 'DESC'], 1);
        self::assertCount(1, $raws);
        self::assertSame('Raw MIME content without signature', $raws[0]->getRawMime());
    }

    /** @return array{timestamp: string, token: string, signature: string} */
    private function makeMailgunSignatureParams(): array
    {
        $timestamp = (string) time();
        $token = bin2hex(random_bytes(25));
        $signed = $timestamp . $token;
        $signature = hash_hmac('sha256', $signed, self::TEST_SIGNING_KEY);
        return [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => $signature,
        ];
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
        $user->setEmail($email ?? 'inbound-mailgun-' . uniqid('', true) . '@example.com');
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
