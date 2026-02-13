<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class RegistrationFormTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);
        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData(): void
    {
        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit([
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'password123',
            ],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('test@example.com', $user->getEmail());
    }

    public function testSubmitMismatchedPasswords(): void
    {
        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit([
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'password123',
                'second' => 'different',
            ],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        self::assertTrue($form->get('plainPassword')->has('first'));
    }

    public function testSubmitShortPassword(): void
    {
        $user = new User();
        $form = $this->factory->create(RegistrationFormType::class, $user);
        $form->submit([
            'email' => 'test@example.com',
            'plainPassword' => [
                'first' => 'short',
                'second' => 'short',
            ],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
    }
}
