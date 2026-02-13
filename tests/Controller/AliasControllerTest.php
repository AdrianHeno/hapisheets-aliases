<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Alias;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AliasControllerTest extends WebTestCase
{
    public function testNewGetRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/aliases/new');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testNewGetShowsCreateAliasPageWhenAuthenticated(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $client->request('GET', '/aliases/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Create alias');
        self::assertSelectorExists('form[method="post"]');
        self::assertSelectorExists('input[name="_token"]');
    }

    public function testNewPostCreatesAliasAndRedirectsWithFlash(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $client->request('GET', '/aliases/new');
        $form = $client->getCrawler()->selectButton('Create alias')->form();
        $client->submit($form);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorExists('.alert-success');
        $flash = $client->getCrawler()->filter('.alert-success')->first();
        self::assertStringContainsString('@hapisheets.com', $flash->text());
    }

    public function testDisableOwnAliasRedirectsWithFlashAndDisables(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($user, 'disable-me-' . uniqid('', true));
        $client->loginUser($user);

        $client->request('GET', '/');
        $form = $client->getCrawler()->selectButton('Disable')->first()->form();
        $client->submit($form);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorExists('.alert-success');
        self::assertSelectorTextContains('.alert-success', 'disabled');

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $em->clear();
        $aliasReloaded = $em->find(Alias::class, $alias->getId());
        self::assertFalse($aliasReloaded->isEnabled());
    }

    public function testDisableOtherUserAliasReturns404(): void
    {
        $client = static::createClient();
        $owner = $this->createAndPersistUser($client);
        $alias = $this->createAliasForUser($owner, 'other-alias-' . uniqid('', true));
        $otherUser = $this->createAndPersistUser($client);
        $client->loginUser($otherUser);

        $token = static::getContainer()->get(CsrfTokenManagerInterface::class)->getToken('disable_alias')->getValue();
        $client->request('POST', '/aliases/' . $alias->getId() . '/disable', ['_token' => $token]);

        self::assertResponseStatusCodeSame(404);
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

    private function createAndPersistUser($client, ?string $email = null): User
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->ensureSchema($em);
        $user = new User();
        $user->setEmail($email ?? 'alias-test-' . uniqid('', true) . '@example.com');
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
