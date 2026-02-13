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
