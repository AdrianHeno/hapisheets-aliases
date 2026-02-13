<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\AliasRepositoryInterface;
use App\Service\AliasGenerator;
use PHPUnit\Framework\TestCase;

class AliasGeneratorTest extends TestCase
{
    private const WORD = 'river';

    /**
     * Format: single known word + hyphen + 4-char alphanumeric suffix; lowercase and email-safe.
     */
    public function testGeneratedLocalPartIsLowercaseAndEmailSafe(): void
    {
        $repo = $this->createStub(AliasRepositoryInterface::class);
        $repo->method('existsByLocalPart')->willReturn(false);

        $generator = new AliasGenerator($repo, 10, [self::WORD]);

        $localPart = $generator->generate();

        self::assertSame(strtolower($localPart), $localPart);
        self::assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $localPart, 'Must be email/URL safe');
        self::assertMatchesRegularExpression('/^river-[a-z0-9]{4}$/', $localPart, 'Must be word-suffix format');
    }

    public function testRetriesOnCollisionUntilSuccess(): void
    {
        $callCount = 0;
        $repo = $this->createStub(AliasRepositoryInterface::class);
        $repo->method('existsByLocalPart')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return $callCount < 3; // exists on 1st and 2nd call, free on 3rd
            });

        $generator = new AliasGenerator($repo, 10, [self::WORD]);

        $localPart = $generator->generate();

        self::assertMatchesRegularExpression('/^river-[a-z0-9]{4}$/', $localPart);
        self::assertSame(3, $callCount, 'Repository should be called until a free local part is found');
    }

    public function testThrowsWhenMaxAttemptsExhausted(): void
    {
        $repo = $this->createStub(AliasRepositoryInterface::class);
        $repo->method('existsByLocalPart')->willReturn(true);

        $generator = new AliasGenerator($repo, 3, [self::WORD]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not generate unique local part after 3 attempt(s)');

        $generator->generate();
    }

    public function testRepositoryCalledExactlyMaxAttemptsTimesWhenAllCollide(): void
    {
        $repo = $this->createMock(AliasRepositoryInterface::class);
        $repo->expects($this->exactly(3))
            ->method('existsByLocalPart')
            ->willReturn(true);

        $generator = new AliasGenerator($repo, 3, [self::WORD]);

        try {
            $generator->generate();
        } catch (\RuntimeException) {
            // expected
        }
    }

    public function testWordsAreNormalizedToLowercaseAndSafe(): void
    {
        $repo = $this->createStub(AliasRepositoryInterface::class);
        $repo->method('existsByLocalPart')->willReturn(false);

        $generator = new AliasGenerator($repo, 10, ['RIVER']);

        $localPart = $generator->generate();
        self::assertStringStartsWith('river-', $localPart);
    }
}
