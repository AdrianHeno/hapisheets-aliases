<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * View model for a parsed MIME message (display only; raw MIME is stored separately).
 * Use sanitizedHtmlBody for safe rendering; chosenBodyHtml is the unsanitized preferred body (HTML or text-as-html).
 */
final readonly class ParsedMimeMessage
{
    public function __construct(
        public string $subject,
        public string $fromName,
        public string $fromEmail,
        public string $to,
        public ?\DateTimeImmutable $date,
        public ?string $textBody,
        public ?string $htmlBody,
        public string $chosenBodyHtml,
        /** Safe HTML for display: script/iframe/object stripped, dangerous attributes removed. */
        public string $sanitizedHtmlBody,
    ) {
    }
}
