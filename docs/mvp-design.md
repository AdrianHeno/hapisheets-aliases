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

## Routes / pages

| Route                 | Method | Purpose |
|-----------------------|--------|--------|
| `/`                   | GET    | Dashboard: list current user's aliases; link to create. |
| `/register`           | GET, POST | Register. |
| `/login`              | GET, POST | Login. |
| `/logout`             | any    | Logout. |
| `/aliases/new`        | GET, POST | Create alias. |
| `/aliases/{id}/disable` | POST  | Disable alias (owner only); redirect back. |

Domain for display and mail is read from config, not DB.
