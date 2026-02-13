<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Alias;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testIdIsNullWhenNew(): void
    {
        $user = new User();
        self::assertNull($user->getId());
    }

    public function testEmailGetterAndSetter(): void
    {
        $user = new User();
        $user->setEmail('jane@example.com');
        self::assertSame('jane@example.com', $user->getEmail());
        self::assertSame('jane@example.com', $user->getUserIdentifier());
    }

    public function testPasswordGetterAndSetter(): void
    {
        $user = new User();
        $user->setPassword('hashed');
        self::assertSame('hashed', $user->getPassword());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();
        $roles = $user->getRoles();
        self::assertContains('ROLE_USER', $roles);
    }

    public function testSetRolesAndGetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        $roles = $user->getRoles();
        self::assertContains('ROLE_USER', $roles);
        self::assertContains('ROLE_ADMIN', $roles);
        self::assertCount(2, $roles);
    }

    public function testCreatedAtIsSetInConstructor(): void
    {
        $before = new \DateTimeImmutable();
        $user = new User();
        $after = new \DateTimeImmutable();
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $user->getCreatedAt());
        self::assertLessThanOrEqual($after, $user->getCreatedAt());
    }

    public function testAliasesCollectionIsEmptyByDefault(): void
    {
        $user = new User();
        self::assertCount(0, $user->getAliases());
    }

    public function testAddAliasAddsToCollectionAndSetsUserOnAlias(): void
    {
        $user = new User();
        $alias = new Alias();
        $alias->setLocalPart('test-abc1');

        $user->addAlias($alias);

        self::assertCount(1, $user->getAliases());
        self::assertTrue($user->getAliases()->contains($alias));
        self::assertSame($user, $alias->getUser());
    }

    public function testAddAliasTwiceDoesNotDuplicate(): void
    {
        $user = new User();
        $alias = new Alias();
        $alias->setLocalPart('test-abc1');

        $user->addAlias($alias);
        $user->addAlias($alias);

        self::assertCount(1, $user->getAliases());
    }

    public function testRemoveAliasClearsUserOnAlias(): void
    {
        $user = new User();
        $alias = new Alias();
        $alias->setLocalPart('test-abc1');
        $user->addAlias($alias);

        $user->removeAlias($alias);

        self::assertCount(0, $user->getAliases());
        self::assertNull($alias->getUser());
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();
        $user->eraseCredentials();
        self::expectNotToPerformAssertions();
    }
}
