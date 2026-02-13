<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Alias;
use App\Entity\Message;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testIdIsNullWhenNew(): void
    {
        $message = new Message();
        self::assertNull($message->getId());
    }

    public function testAliasGetterAndSetter(): void
    {
        $message = new Message();
        $alias = new Alias();
        $alias->setLocalPart('test-xyz');

        $message->setAlias($alias);
        self::assertSame($alias, $message->getAlias());

        $message->setAlias(null);
        self::assertNull($message->getAlias());
    }

    public function testReceivedAtGetterAndSetter(): void
    {
        $message = new Message();
        $at = new \DateTimeImmutable('2025-02-13 12:00:00');
        $message->setReceivedAt($at);
        self::assertSame($at, $message->getReceivedAt());
    }

    public function testSubjectGetterAndSetter(): void
    {
        $message = new Message();
        $message->setSubject('Test subject');
        self::assertSame('Test subject', $message->getSubject());
    }

    public function testFromAddressGetterAndSetter(): void
    {
        $message = new Message();
        $message->setFromAddress('sender@example.com');
        self::assertSame('sender@example.com', $message->getFromAddress());
    }

    public function testBodyGetterAndSetter(): void
    {
        $message = new Message();
        $message->setBody('Email body text');
        self::assertSame('Email body text', $message->getBody());
    }

    public function testMessageBelongsToAlias(): void
    {
        $user = new User();
        $user->setEmail('owner@example.com');
        $alias = new Alias();
        $alias->setLocalPart('my-alias-1');
        $alias->setUser($user);

        $message = new Message();
        $message->setAlias($alias);
        $message->setReceivedAt(new \DateTimeImmutable());
        $message->setSubject('Hi');
        $message->setFromAddress('a@b.com');
        $message->setBody('Hello');

        self::assertSame($alias, $message->getAlias());
    }
}
