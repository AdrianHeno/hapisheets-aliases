# Dev Commands

## Run server
symfony serve

## Tests
php bin/phpunit

## Doctrine
php bin/console doctrine:schema:validate
php bin/console make:migration
php bin/console doctrine:migrations:migrate

## Seed inbox messages (dev/test only)

The route `POST /dev/inbound` exists only when `APP_ENV=dev` or `APP_ENV=test`. It creates a `Message` for an alias so you can test the inbox UI without real email.

Send a JSON body with `to` (recipient address, e.g. `localPart@hapisheets.com`), `from`, `subject`, and `body`. At least one of `subject` or `body` is required. The alias must exist (create one via the app first).

Example (curl):

```bash
curl -X POST http://127.0.0.1:8000/dev/inbound \
  -H "Content-Type: application/json" \
  -d '{"to":"YOUR_ALIAS_LOCALPART@hapisheets.com","from":"sender@example.com","subject":"Test","body":"Hello"}'
```

Response: `201` with `{"id": <message_id>}`.

## Mailgun inbound webhook (prod)

In production, inbound email is received via a **Mailgun HTTP webhook**:

- URL: `POST /inbound/mailgun/raw-mime`
- Content type: `multipart/form-data`
- Required fields:
  - `recipient` — full recipient address (e.g. `localPart@hapisheets.com` or another configured domain).
  - `body-mime` — raw MIME of the message.
- Optional (when `MAILGUN_WEBHOOK_SIGNING_KEY` is set): `timestamp`, `token`, `signature` — Mailgun webhook signature fields. If the key is set, the app verifies the signature (HMAC-SHA256 of `timestamp` + `token`) and a timestamp freshness check; if the key is not set, verification is skipped and a warning is logged.
- On success it stores the raw MIME payload in the `InboundRaw` entity linked to the alias (by recipient local part, `enabled=true`).

Responses:

- `200 OK` with plain text body `OK` when the payload is accepted.
- `400` plain text when required fields (`recipient` or `body-mime`) are missing or empty.
- `403` plain text when the signing key is set and the signature or timestamp window is invalid.
- `404` when the alias is not found or disabled.
