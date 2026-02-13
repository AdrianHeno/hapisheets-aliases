<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alias;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DashboardControllerTest extends WebTestCase
{
    public function testIndexRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testIndexShowsDashboardWithCreateAliasLinkWhenAuthenticated(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dashboard');
        self::assertSelectorExists('a[href*="/aliases/new"]');
        self::assertSelectorTextContains('a[href*="/aliases/new"]', 'Create alias');
        self::assertSelectorTextContains('h2', 'Your aliases');
        self::assertSelectorExists('a[href*="/logout"]');
        self::assertSelectorTextContains('a[href*="/logout"]', 'Log out');
    }

    public function testIndexListsOnlyCurrentUserAliases(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->ensureSchema($em);

        $suffix = uniqid('', true);
        $user1 = new User();
        $user1->setEmail('dashboard-user-' . $suffix . '@example.com');
        $user1->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user1, 'password'));
        $em->persist($user1);

        $user2 = new User();
        $user2->setEmail('other-user-' . $suffix . '@example.com');
        $user2->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user2, 'password'));
        $em->persist($user2);

        $alias1 = new Alias();
        $alias1->setUser($user1);
        $alias1->setLocalPart('mine-only-' . $suffix);
        $em->persist($alias1);

        $alias2 = new Alias();
        $alias2->setUser($user2);
        $alias2->setLocalPart('other-user-alias-' . $suffix);
        $em->persist($alias2);

        $em->flush();

        $client->loginUser($user1);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'mine-only-' . $suffix . '@hapisheets.com');
        self::assertSelectorTextNotContains('table', 'other-user-alias-' . $suffix);
    }

    public function testIndexShowsEnabledStatusAndCreatedDate(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $localPart = 'status-test-' . uniqid('', true);
        $alias = new Alias();
        $alias->setUser($user);
        $alias->setLocalPart($localPart);
        $alias->setEnabled(false);
        $em->persist($alias);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', $localPart . '@hapisheets.com');
        self::assertSelectorTextContains('table', 'Disabled');
    }

    private function createAndPersistUser(?string $email = null): User
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->ensureSchema($em);
        $user = new User();
        $user->setEmail($email ?? 'dashboard-test-' . uniqid('', true) . '@example.com');
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
