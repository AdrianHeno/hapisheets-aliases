<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AliasRepositoryInterface;

final class AliasGenerator
{
    /** @var non-empty-list<string> */
    private const DEFAULT_WORDS = [
        'river', 'cloud', 'stream', 'meadow', 'forest', 'summit', 'haven', 'spark',
    ];

    private const SUFFIX_LENGTH = 4;

    /** @var non-empty-list<string> */
    private array $words;

    public function __construct(
        private readonly AliasRepositoryInterface $aliasRepository,
        private readonly int $maxAttempts = 10,
        ?array $words = null,
    ) {
        $words = $words ?? self::DEFAULT_WORDS;
        if ($words === []) {
            throw new \InvalidArgumentException('Word list cannot be empty.');
        }
        $this->words = $this->normalizeWords($words);
    }

    /**
     * Generates a unique local part (word-suffix format, lowercase, email-safe).
     * Retries up to maxAttempts if collision occurs; throws if all attempts exhausted.
     */
    public function generate(): string
    {
        $attempt = 0;
        while ($attempt < $this->maxAttempts) {
            $localPart = $this->generateOne();
            if (!$this->aliasRepository->existsByLocalPart($localPart)) {
                return $localPart;
            }
            $attempt++;
        }

        throw new \RuntimeException(sprintf(
            'Could not generate unique local part after %d attempt(s).',
            $this->maxAttempts
        ));
    }

    /**
     * Produces one candidate local part (word + hyphen + suffix). Lowercase, email-safe.
     */
    private function generateOne(): string
    {
        $word = $this->words[array_rand($this->words)];
        $suffix = $this->randomSuffix();
        $localPart = $word . '-' . $suffix;
        return $this->sanitize($localPart);
    }

    private function randomSuffix(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $length = strlen($chars);
        $result = '';
        for ($i = 0; $i < self::SUFFIX_LENGTH; $i++) {
            $result .= $chars[random_int(0, $length - 1)];
        }
        return $result;
    }

    /**
     * Lowercase and restrict to [a-z0-9-] for email/URL safety.
     */
    private function sanitize(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\-]/', '', $value) ?? $value;
        return $value;
    }

    /**
     * @param list<string> $words
     * @return non-empty-list<string>
     */
    private function normalizeWords(array $words): array
    {
        $normalized = [];
        foreach ($words as $w) {
            $w = $this->sanitize((string) $w);
            if ($w !== '') {
                $normalized[] = $w;
            }
        }
        if ($normalized === []) {
            throw new \InvalidArgumentException('Word list yielded no valid words after normalization.');
        }
        return array_values(array_unique($normalized));
    }
}
