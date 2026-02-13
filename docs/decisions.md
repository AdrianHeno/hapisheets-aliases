# Decisions

## Framework
Symfony latest stable + Twig.

## Local development
No Docker. SQLite for local dev. **SQLite 3.35+** is required for migration rollbacks that use `DROP COLUMN` (e.g. reverting message.inbound_raw_id or message.preview_snippet / has_html_body).

## Alias format (tentative)
Readable word + short suffix, e.g. river-9k2f@hapisheets.com

## Domain
Domain (e.g. hapisheets.com) is a config value, not stored in DB. Single-domain MVP; `local_part` is unique globally.

## Alias enabled flag
Keep `enabled` as boolean. When disabled: alias is hidden/disabled in UI and **reject mail later** (inbound handling will use this).

## Inbound email provider
For MVP, inbound email is implemented via a **Mailgun HTTP webhook**:

- Webhook endpoint: `POST /inbound/mailgun/raw-mime` (public). Signature verification is optional: when `MAILGUN_WEBHOOK_SIGNING_KEY` is set, requests are verified; when unset, verification is skipped.
- Stores the full raw MIME in a separate `InboundRaw` entity linked to the alias. A `Message` is also created so the email appears in the inbox (subject, from, body; optional preview snippet and HTML flag from parsed MIME).
- Alias lookup is by recipient local part and `enabled=true`; disabled or unknown aliases return 404.

Other providers (e.g. AWS SES) and richer parsing can be added later without changing the public dashboard / inbox UI.

## MIME parsing and HTML sanitization
- **Parsing:** Raw MIME is parsed with **zbateson/mail-mime-parser**. We extract subject, from (name + email), to, date, text/plain and text/html parts. For **chosen body** we prefer HTML when present (e.g. multipart/alternative); otherwise we use plain text converted to safe HTML (escape + nl2br). Raw MIME is never modified in storage.
- **Sanitization:** Before rendering the body on the message detail page, we run **Symfony HtmlSanitizer** (config: `message_body` in `config/packages/html_sanitizer.yaml`). We allow safe elements (p, br, div, span, a, img, ul/ol/li, strong/em, etc.) and drop script, iframe, object, form. Links get `rel="noopener noreferrer"`. The parser returns both unsanitized `chosenBodyHtml` and `sanitizedHtmlBody`; the UI renders only the sanitized body.
- **Inbox list:** Each message row shows From, Subject, a short **Preview** (first ~120 chars of text body, when available from parsing at ingest), Received, and an **HTML** badge when the message has an HTML part.
