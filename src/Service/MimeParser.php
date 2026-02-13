<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ParsedMimeMessage;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\Message;

class MimeParser
{
    public function __construct(
        private readonly HtmlSanitizerInterface $messageBodySanitizer,
    ) {
    }

    public function parse(string $rawMime): ParsedMimeMessage
    {
        $message = Message::from($rawMime, false);

        $subject = $message->getSubject() ?? '';

        $fromName = '';
        $fromEmail = '';
        $fromHeader = $message->getHeader(HeaderConsts::FROM);
        if ($fromHeader instanceof AddressHeader) {
            $fromName = $fromHeader->getPersonName() ?? '';
            $fromEmail = $fromHeader->getEmail() ?? '';
        }

        $to = $message->getHeaderValue(HeaderConsts::TO) ?? '';

        $date = null;
        $dateHeader = $message->getHeaderAs(HeaderConsts::DATE, DateHeader::class);
        if ($dateHeader instanceof DateHeader) {
            $date = $dateHeader->getDateTimeImmutable();
        }

        $textBody = $message->getTextContent();
        $htmlBody = $message->getHtmlContent();

        $chosenBodyHtml = $this->buildChosenBodyHtml($htmlBody, $textBody);
        $sanitizedHtmlBody = $this->messageBodySanitizer->sanitize($chosenBodyHtml);

        return new ParsedMimeMessage(
            subject: $subject,
            fromName: $fromName,
            fromEmail: $fromEmail,
            to: $to,
            date: $date,
            textBody: $textBody,
            htmlBody: $htmlBody,
            chosenBodyHtml: $chosenBodyHtml,
            sanitizedHtmlBody: $sanitizedHtmlBody,
        );
    }

    private function buildChosenBodyHtml(?string $htmlBody, ?string $textBody): string
    {
        if ($htmlBody !== null && $htmlBody !== '') {
            return $htmlBody;
        }
        if ($textBody !== null && $textBody !== '') {
            return nl2br(htmlspecialchars($textBody, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'), false);
        }
        return '';
    }
}
