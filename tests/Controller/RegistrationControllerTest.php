<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
{
    public function testGetRegisterShowsFormWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Register');
        self::assertSelectorExists('input[name="registration_form[email]"]');
        self::assertSelectorExists('input[name="registration_form[plainPassword][first]"]');
        self::assertSelectorExists('input[name="registration_form[plainPassword][second]"]');
        self::assertSelectorExists('button[type="submit"]');
    }

    public function testPostValidDataCreatesUserAndRedirectsToDashboard(): void
    {
        $client = static::createClient();
        $email = 'newuser-' . uniqid('', true) . '@example.com';

        $client->request('GET', '/register');
        $form = $client->getCrawler()->selectButton('Register')->form([
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/dashboard');
        $client->followRedirect();
        self::assertSelectorExists('.alert-success');
        self::assertSelectorTextContains('.alert-success', 'Welcome');
        self::assertSelectorTextContains('h1', 'Dashboard');

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertNotSame('password123', $user->getPassword());
    }

    public function testPostDuplicateEmailShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $existingEmail = $user->getEmail();

        $client->request('GET', '/register');
        $form = $client->getCrawler()->selectButton('Register')->form([
            'registration_form[email]' => $existingEmail,
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'already registered');
    }

    public function testPostInvalidDataReRendersFormWithErrors(): void
    {
        $client = static::createClient();

        $client->request('GET', '/register');
        $form = $client->getCrawler()->selectButton('Register')->form([
            'registration_form[email]' => 'not-an-email',
            'registration_form[plainPassword][first]' => 'short',
            'registration_form[plainPassword][second]' => 'short',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('h1', 'Register');
    }

    public function testGetRegisterRedirectsToDashboardWhenAuthenticated(): void
    {
        $client = static::createClient();
        $user = $this->createAndPersistUser();
        $client->loginUser($user);

        $client->request('GET', '/register');

        self::assertResponseRedirects('/dashboard');
    }

    private function createAndPersistUser(?string $email = null): User
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->ensureSchema($em);
        $user = new User();
        $user->setEmail($email ?? 'reg-test-' . uniqid('', true) . '@example.com');
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
