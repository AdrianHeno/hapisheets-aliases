# MVP design: Alias entity and routes

## Alias entity

| Field        | Type     | Notes |
|-------------|----------|--------|
| `id`        | integer  | PK. |
| `user_id`   | integer  | FK to User; required. |
| `local_part`| string   | e.g. `river-9k2f`. Full address = `{local_part}@{domain}` with domain from config. |
| `enabled`   | boolean  | Default `true`. Disabled = reject mail later (see decisions.md). |
| `created_at`| datetime | For ordering and display. |

**Indexes:** Unique on `local_part` (global uniqueness; domain is single and from config). Non-unique on `user_id` for listing by user.

**Relationships:** Many-to-one to User. User one-to-many to Alias.

## User entity (minimal)

`id`, `email` (unique), `password` (hashed), `roles`, `created_at`.

## Message entity

| Field         | Type     | Notes |
|---------------|----------|--------|
| `id`          | integer  | PK. |
| `alias_id`    | integer  | FK to Alias; required. |
| `received_at` | datetime | When the message was received. |
| `subject`     | string   | 255 chars. |
| `from_address`| string   | 255 chars. |
| `body`        | text     | Message body. |

**Indexes:** Non-unique on `(alias_id, received_at)` for listing by alias by date.

**Relationships:** Many-to-one to Alias.

## InboundRaw entity (raw MIME storage)

| Field         | Type     | Notes |
|---------------|----------|--------|
| `id`          | integer  | PK. |
| `alias_id`    | integer  | FK to Alias; required. |
| `received_at` | datetime | When the raw message was received. |
| `raw_mime`    | text     | Full raw MIME payload from the provider (e.g. Mailgun). |

**Indexes:** Non-unique on `(alias_id, received_at)` for listing by alias by date.

**Relationships:** Many-to-one to Alias.

## Routes / pages

| Route                   | Method | Purpose |
|-------------------------|--------|--------|
| `/`                     | GET    | Dashboard: list current user's aliases; link to create, inbox, disable. |
| `/register`             | GET, POST | Register (email + password, then redirect to /dashboard). |
| `/login`                | GET, POST | Login. |
| `/logout`               | any    | Logout. |
| `/aliases/new`          | GET, POST | Create alias. |
| `/aliases/{id}/inbox`   | GET    | Inbox: list messages for alias (owner only). |
| `/aliases/{id}/disable` | POST   | Disable alias (owner only); redirect back. |
| `/messages/{id}`        | GET    | View message (owner of message's alias only). |
| `/messages/{id}/delete` | POST   | Delete message (owner only, CSRF). |
| `/inbound/mailgun/raw-mime` | POST | Mailgun webhook: accepts multipart/form-data with `recipient`, `body-mime`. Optionally verifies `timestamp`, `token`, `signature` when `MAILGUN_WEBHOOK_SIGNING_KEY` is set; otherwise skips verification. Stores raw MIME for the alias identified by the recipient’s local part (enabled only). Public (no login); used for inbound email. |

Domain for display and mail is read from config, not DB.
