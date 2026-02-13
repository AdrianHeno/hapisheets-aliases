<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\ParsedMimeMessage;
use App\Service\MimeParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class MimeParserTest extends TestCase
{
    private const SAMPLE_MULTIPART_ALTERNATIVE = "MIME-Version: 1.0\r\n"
        . "From: Jane Doe <jane@example.com>\r\n"
        . "To: summit-y8nt@hapisheets.com\r\n"
        . "Subject: Test multipart/alternative\r\n"
        . "Date: Fri, 13 Feb 2026 12:00:00 +0000\r\n"
        . "Content-Type: multipart/alternative; boundary=\"_boundary_abc_\"\r\n"
        . "\r\n"
        . "--_boundary_abc_\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "\r\n"
        . "Hello,\r\n\r\nThis is the plain text part.\r\n\r\n-- \r\nJane\r\n"
        . "\r\n--_boundary_abc_\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "\r\n"
        . "<p>Hello,</p><p>This is the <strong>HTML</strong> part.</p><p>-- <br>Jane</p>\r\n"
        . "\r\n--_boundary_abc_--\r\n";

    private MimeParser $parser;

    protected function setUp(): void
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->dropElement('script')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('form');
        $this->parser = new MimeParser(new HtmlSanitizer($config));
    }

    public function testParseMultipartAlternativeReturnsDtoWithBothBodies(): void
    {
        $result = $this->parser->parse(self::SAMPLE_MULTIPART_ALTERNATIVE);

        self::assertInstanceOf(ParsedMimeMessage::class, $result);
        self::assertSame('Test multipart/alternative', $result->subject);
        self::assertSame('Jane Doe', $result->fromName);
        self::assertSame('jane@example.com', $result->fromEmail);
        self::assertSame('summit-y8nt@hapisheets.com', $result->to);
        self::assertNotNull($result->date);
        self::assertSame('2026-02-13', $result->date->format('Y-m-d'));

        self::assertNotNull($result->textBody);
        self::assertStringContainsString('plain text part', $result->textBody);
        self::assertStringContainsString('Hello,', $result->textBody);

        self::assertNotNull($result->htmlBody);
        self::assertStringContainsString('<p>Hello,</p>', $result->htmlBody);
        self::assertStringContainsString('<strong>HTML</strong>', $result->htmlBody);

        self::assertSame($result->htmlBody, $result->chosenBodyHtml);
        self::assertStringContainsString('<p>Hello,</p>', $result->sanitizedHtmlBody);
        self::assertStringContainsString('<strong>HTML</strong>', $result->sanitizedHtmlBody);
    }

    public function testMultipartAlternativePrefersHtmlForChosenBody(): void
    {
        $result = $this->parser->parse(self::SAMPLE_MULTIPART_ALTERNATIVE);

        self::assertNotNull($result->textBody);
        self::assertNotNull($result->htmlBody);
        self::assertSame($result->htmlBody, $result->chosenBodyHtml, 'chosenBodyHtml must prefer HTML when multipart/alternative has both text/plain and text/html');
        self::assertStringContainsString('<p>', $result->chosenBodyHtml);
        self::assertStringNotContainsString('plain text part', $result->chosenBodyHtml, 'plain text should not be used when HTML part exists');
    }

    public function testParseTextOnlyEscapesAndNl2brForChosenBodyHtml(): void
    {
        $raw = "From: a@b.com\r\n"
            . "To: c@d.com\r\n"
            . "Subject: Plain only\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "\r\n"
            . "Line one\nLine two & <script>";

        $result = $this->parser->parse($raw);

        self::assertSame('Plain only', $result->subject);
        self::assertNotNull($result->textBody);
        self::assertNull($result->htmlBody);
        self::assertStringContainsString('Line one<br>', $result->chosenBodyHtml);
        self::assertStringContainsString('Line two &amp; &lt;script&gt;', $result->chosenBodyHtml);
        self::assertStringContainsString('Line one', $result->sanitizedHtmlBody);
        self::assertStringContainsString('Line two &amp; &lt;script&gt;', $result->sanitizedHtmlBody);
    }

    public function testParseSimpleMessageExtractsFromNameAndEmail(): void
    {
        $result = $this->parser->parse(self::SAMPLE_MULTIPART_ALTERNATIVE);

        self::assertSame('Jane Doe', $result->fromName);
        self::assertSame('jane@example.com', $result->fromEmail);
    }

    public function testParseEmptyBodyReturnsEmptyChosenBodyHtml(): void
    {
        $raw = "From: a@b.com\r\nTo: c@d.com\r\nSubject: No body\r\n\r\n";
        $result = $this->parser->parse($raw);
        self::assertSame('', $result->chosenBodyHtml);
        self::assertSame('', $result->sanitizedHtmlBody);
    }

    public function testSanitizedHtmlBodyRemovesScriptTag(): void
    {
        $raw = "From: a@b.com\r\n"
            . "To: c@d.com\r\n"
            . "Subject: XSS attempt\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "\r\n"
            . "<p>Hello</p><script>alert(1)</script><p>World</p>";

        $result = $this->parser->parse($raw);

        self::assertStringContainsString('<script>alert(1)</script>', $result->chosenBodyHtml);
        self::assertStringNotContainsString('script', $result->sanitizedHtmlBody);
        self::assertStringNotContainsString('alert(1)', $result->sanitizedHtmlBody);
        self::assertStringContainsString('<p>Hello</p>', $result->sanitizedHtmlBody);
        self::assertStringContainsString('<p>World</p>', $result->sanitizedHtmlBody);
    }

    public function testSanitizedHtmlBodyPreservesSafeAnchor(): void
    {
        $raw = "From: a@b.com\r\n"
            . "To: c@d.com\r\n"
            . "Subject: Link test\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "\r\n"
            . '<p>See <a href="https://example.com">example</a> for more.</p>';

        $result = $this->parser->parse($raw);

        self::assertStringContainsString('href="https://example.com"', $result->sanitizedHtmlBody);
        self::assertStringContainsString('<a ', $result->sanitizedHtmlBody);
        self::assertStringContainsString('>example</a>', $result->sanitizedHtmlBody);
        self::assertStringContainsString('See ', $result->sanitizedHtmlBody);
        self::assertStringContainsString(' for more.', $result->sanitizedHtmlBody);
    }

    public function testSanitizationRemovesScriptIframeAndObject(): void
    {
        $raw = "From: a@b.com\r\nTo: c@d.com\r\nSubject: Dangerous\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
            . '<p>Safe</p><script>alert(1)</script><iframe src="evil"></iframe><object data="x"></object><p>End</p>';

        $result = $this->parser->parse($raw);

        self::assertStringNotContainsString('script', $result->sanitizedHtmlBody);
        self::assertStringNotContainsString('iframe', $result->sanitizedHtmlBody);
        self::assertStringNotContainsString('object', $result->sanitizedHtmlBody);
        self::assertStringNotContainsString('alert(1)', $result->sanitizedHtmlBody);
        self::assertStringContainsString('<p>Safe</p>', $result->sanitizedHtmlBody);
        self::assertStringContainsString('<p>End</p>', $result->sanitizedHtmlBody);
    }
}
