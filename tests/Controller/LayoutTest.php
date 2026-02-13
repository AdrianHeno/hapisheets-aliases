<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LayoutTest extends WebTestCase
{
    public function testLoginPageShowsLogInAndRegisterLinksWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="/login"]');
        self::assertSelectorTextContains('a[href*="/login"]', 'Log in');
        self::assertSelectorExists('a[href*="/register"]');
        self::assertSelectorTextContains('a[href*="/register"]', 'Register');
    }

    public function testDashboardShowsLogoutLinkWhenAuthenticated(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="/logout"]');
        self::assertSelectorTextContains('a[href*="/logout"]', 'Log out');
    }

    private function createAndPersistUser(?string $email = null): User
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->ensureSchema($em);
        $user = new User();
        $user->setEmail($email ?? 'layout-test-' . uniqid('', true) . '@example.com');
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
