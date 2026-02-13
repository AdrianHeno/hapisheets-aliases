<?php

declare(strict_types=1);

namespace App\Service;

class MailgunWebhookSignatureVerifier
{
    public function __construct(
        private readonly string $signingKey,
        private readonly int $maxSkewSeconds = 900, // 15 minutes
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->signingKey !== '';
    }

    public function isTimestampFresh(string $timestamp): bool
    {
        if (!ctype_digit($timestamp)) {
            return false;
        }

        $ts = (int) $timestamp;
        $now = time();

        return abs($now - $ts) <= $this->maxSkewSeconds;
    }

    public function verify(string $timestamp, string $token, string $signature): bool
    {
        $signed = $timestamp . $token;
        $expected = hash_hmac('sha256', $signed, $this->signingKey);

        if ($expected === false) {
            return false;
        }

        return hash_equals($expected, $signature);
    }
}

