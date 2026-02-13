# Acceptance criteria

Short acceptance criteria for features that have non-trivial behaviour. See also `spec.md` and `decisions.md`.

## Inbox (`/aliases/{id}/inbox`)

- Only the alias owner can access the inbox; others receive 404.
- List shows, per message: **From** (email/name), **Subject**, **Preview** (first ~120 chars of text body, or "—" when none), **Received** (formatted date/time), **Type** (HTML badge when the message has an HTML part, otherwise "—"), **View** action.
- Only messages for that alias are shown (scoped by alias and current user).

## View message (`/messages/{id}`)

- Only the owner of the message’s alias can view; others receive 404.
- When the message has stored raw MIME (linked InboundRaw): show **Subject**, **From** (name + email), **To**, **Date** from parsed MIME; show a collapsible **Raw source** with the raw MIME in a `<pre>`; show **body** as sanitized HTML (chosen body: HTML preferred when present, else plain text as escaped + nl2br; script/iframe/object/form stripped).
- When the message has no stored raw MIME: show subject, from, date from the Message entity; show body as plain text (no raw source toggle).
- **Delete message** is available (CSRF-protected); after delete, redirect to that alias’s inbox.

## Mailgun inbound webhook

- On success: store raw MIME in InboundRaw, create Message with subject/from/body and optional preview snippet and HTML flag (from parsed MIME when parsing succeeds).
- When MIME parsing fails: message is still created and stored; preview/hasHtmlBody remain default; failure is logged.
