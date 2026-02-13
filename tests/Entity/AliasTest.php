<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Alias;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class AliasTest extends TestCase
{
    public function testIdIsNullWhenNew(): void
    {
        $alias = new Alias();
        self::assertNull($alias->getId());
    }

    public function testUserGetterAndSetter(): void
    {
        $alias = new Alias();
        $user = new User();
        $user->setEmail('u@example.com');

        $alias->setUser($user);
        self::assertSame($user, $alias->getUser());

        $alias->setUser(null);
        self::assertNull($alias->getUser());
    }

    public function testLocalPartGetterAndSetter(): void
    {
        $alias = new Alias();
        $alias->setLocalPart('river-9k2f');
        self::assertSame('river-9k2f', $alias->getLocalPart());
    }

    public function testEnabledDefaultsToTrue(): void
    {
        $alias = new Alias();
        self::assertTrue($alias->isEnabled());
    }

    public function testSetEnabled(): void
    {
        $alias = new Alias();
        $alias->setEnabled(false);
        self::assertFalse($alias->isEnabled());
        $alias->setEnabled(true);
        self::assertTrue($alias->isEnabled());
    }

    public function testCreatedAtIsSetInConstructor(): void
    {
        $before = new \DateTimeImmutable();
        $alias = new Alias();
        $after = new \DateTimeImmutable();
        self::assertInstanceOf(\DateTimeImmutable::class, $alias->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $alias->getCreatedAt());
        self::assertLessThanOrEqual($after, $alias->getCreatedAt());
    }

    public function testBidirectionalRelationWithUser(): void
    {
        $user = new User();
        $user->setEmail('owner@example.com');
        $alias = new Alias();
        $alias->setLocalPart('my-alias-x1');

        $user->addAlias($alias);

        self::assertSame($user, $alias->getUser());
        self::assertCount(1, $user->getAliases());
        self::assertSame($alias, $user->getAliases()->first());
    }
}
