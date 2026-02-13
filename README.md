# Hapisheets Alias Manager

A web app for generating and managing email aliases under **hapisheets.com** (e.g. for form signups and online accounts). Built with Symfony and Twig

---

## Features

| Feature | Description |
|--------|-------------|
| **Register** | Create an account with email and password (with confirmation). Passwords must be at least 6 characters. If the email is already taken, you get a clear error and can try another. After success you are logged in automatically and sent to the dashboard. |
| **Login** | Sign in with email and password. The login page links to Register; the register page links back to Login. |
| **Dashboard** | View all your aliases in a table: full address, status (Enabled/Disabled), created date, and a **Create alias** link. Both `/` and `/dashboard` show the same dashboard. |
| **Create alias** | Generate a new alias with one click (no fields to fill). Format: word + short suffix (e.g. `river-9k2f@hapisheets.com`). You are redirected to the dashboard with a success message showing the new address. |
| **Disable alias** | Turn off an alias from the dashboard via the **Disable** button in the Actions column. Disabled aliases show status “Disabled” and will reject mail when inbound email is added later. Only your own aliases can be disabled (others return 404). |
| **Inbox** | From the dashboard, open **Inbox** for an alias to see messages (From, Subject, **Preview** snippet, Received, **Type** badge when HTML, View). Only the alias owner can access an inbox; others get 404. |
| **View / delete message** | Open a message to read it in an email-client layout: subject (h1), From (name + email), To, Date, collapsible **Raw source** (raw MIME in `<pre>`), and **body** rendered as safe HTML (parsed from MIME when available; script/iframe/object stripped). **Delete message** redirects back to that alias's inbox. Only the alias owner can view or delete; others get 404. |
| **Log out** | A **Log out** link is shown in the global nav when you are logged in; it points to `/logout` and ends your session. When not authenticated, the nav shows **Log in** and **Register** instead. |
| **Mailgun inbound webhook** | `POST /inbound/mailgun/raw-mime` accepts Mailgun’s inbound payload (multipart/form-data). It reads `recipient` and `body-mime`, looks up the alias by the local part (before `@`, lowercased) with `enabled=true`, stores the raw MIME in `InboundRaw`, and creates a `Message` (subject, from, body, optional preview snippet and HTML flag from parsed MIME). Signature verification is optional: if `MAILGUN_WEBHOOK_SIGNING_KEY` is set, the app verifies Mailgun’s `timestamp`, `token`, and `signature` (HMAC-SHA256); if unset, verification is skipped and a warning is logged. Returns 200 OK, 400/403/404 as above. Public (no login). |

All alias and message actions are scoped to the logged-in user; you only see and can change your own aliases and their messages.

**Email parsing and display:** Inbound raw MIME is stored unchanged. For display, the app parses it with [zbateson/mail-mime-parser](https://github.com/zbateson/php-mime-parser) and builds a view model (subject, from, to, date, text/HTML bodies). The **chosen body** prefers HTML when present (e.g. multipart/alternative); otherwise plain text is escaped and converted with `nl2br`. Before rendering, HTML is sanitized with [Symfony HtmlSanitizer](https://symfony.com/doc/current/html_sanitizer.html) (safe elements allowed; script, iframe, object, form dropped). See `docs/decisions.md` for more.

---

## How to use it

### Prerequisites

- PHP 8.4+
- Composer
- SQLite (local dev) or PostgreSQL (see `.env` / `config`)

### 1. Install and configure

```bash
composer install
cp .env .env.local   # optional: override DATABASE_URL, etc.
```

For local dev, the app can use SQLite (e.g. in `.env.local`):

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/app.db"
```

Apply migrations:

```bash
php bin/console doctrine:migrations:migrate
```

### 2. Run the app

```bash
symfony serve
```

Or use your own web server with the `public/` directory as document root.

### 3. Register or log in

- Open the app in the browser (e.g. `https://127.0.0.1:8000`).
- If not authenticated, you are redirected to **Login**.
- **Register:** Click “Don’t have an account? Register”, enter email and password (with “Repeat Password”). Passwords must be at least 6 characters. If the email is already registered, you’ll see “This email is already registered.” and can correct it. On success you are logged in and redirected to the dashboard.
- **Log in:** Enter email and password and submit. You can use “Already have an account? Log in” from the register page to switch.

### 4. Dashboard (`/` or `/dashboard`)

After login you land on the **Dashboard**:

- See all your aliases in a table: **Address** (`localPart@hapisheets.com`), **Status** (Enabled/Disabled), **Created** (date/time), **Actions** (Inbox, Disable when enabled).
- Use **Create alias** to add a new one.
- Use **Inbox** to see messages for that alias; use **Disable** next to an enabled alias to turn it off (success flash and list updates).

### 5. Create alias (`/aliases/new`)

- From the dashboard, click **Create alias**.
- On the “Create alias” page, click the **Create alias** button (no fields to fill).
- The app generates a unique local part (e.g. `river-9k2f`), creates the alias, and redirects you to the dashboard with a success message showing the full address (e.g. `river-9k2f@hapisheets.com`).

### 6. Inbox (`/aliases/{id}/inbox`)

- On the dashboard, click **Inbox** for an alias.
- You see messages for that alias: **From**, **Subject**, **Preview** (first ~120 chars of text body, or —), **Received**, **Type** (HTML badge when the message has an HTML part), and **View**. Click **View** to open a message.

### 7. View and delete message (`/messages/{id}`)

- From an inbox, click **View** to open the message in an email-client layout: **Subject** (h1), **From** (name + email), **To**, **Date**, a collapsible **Raw source** (raw MIME in a `<pre>` block), and the **body** rendered as safe HTML (when raw MIME exists it is parsed; HTML is preferred over plain text; script/iframe/object and dangerous attributes are stripped). If the message has no stored raw MIME, the stored body is shown as plain text.
- Use **Delete message** to remove it; you are redirected to that alias's inbox with a success flash.

### 8. Disable alias

- On the dashboard, find the alias and click **Disable** in the Actions column.
- The alias is disabled and a confirmation message is shown. Disabled aliases show status "Disabled" and no Disable button.

### 9. Log out

- Click **Log out** in the global navigation (or go to `/logout`) to end your session. When logged out, the nav shows **Log in** and **Register** again. You will need to log in again to access the dashboard or alias actions.

---

## Routes

| Route | Methods | Purpose |
|-------|---------|--------|
| `/` | GET | Dashboard: list your aliases, link to create. |
| `/dashboard` | GET | Same as `/` (dashboard). |
| `/register` | GET, POST | Register (email + password); then redirect to `/dashboard`. |
| `/login` | GET, POST | Log in (email + password). |
| `/logout` | GET | Log out (link in nav when authenticated). |
| `/aliases/new` | GET, POST | Create alias page and form submission. |
| `/aliases/{id}/inbox` | GET | Inbox: list messages for an alias (owner only; 404 otherwise). |
| `/aliases/{id}/disable` | POST | Disable an alias (must own it; returns 404 otherwise). |
| `/messages/{id}` | GET | View a message (owner of the message’s alias only; 404 otherwise). |
| `/messages/{id}/delete` | POST | Delete a message (owner only, CSRF required; 404 otherwise). |
| `/inbound/mailgun/raw-mime` | POST | Mailgun inbound webhook: multipart/form-data with `recipient`, `body-mime`. Optional: `timestamp`, `token`, `signature` (verified when `MAILGUN_WEBHOOK_SIGNING_KEY` is set). Stores raw MIME for the alias identified by the recipient’s local part (enabled only). Public (no login). Returns 200 OK, 400/403/404 as above. |

Protected routes (dashboard, alias and message actions) require a logged-in user; otherwise you are redirected to `/login`. Registration, login, and `/inbound/mailgun/*` (webhook) are public. When the signing key is set, the webhook verifies Mailgun’s signature; when unset, verification is skipped. Attempting to access another user’s alias or message returns 404.

---

## Configuration

| What | Where |
|------|--------|
| **Alias domain** | `config/services.yaml`: parameter `app.alias_domain` (default: `hapisheets.com`). Used for display and future mail handling. |
| **Database** | `DATABASE_URL` in `.env` / `.env.local`. |
| **Mailgun webhook signing key** | `MAILGUN_WEBHOOK_SIGNING_KEY` in `.env` / `.env.local` (optional). When set, the app verifies Mailgun’s webhook signature (HMAC-SHA256 of `timestamp` + `token`) for `POST /inbound/mailgun/raw-mime`; when unset, verification is skipped and a warning is logged. Use your Mailgun HTTP webhook signing key from the Mailgun dashboard if Mailgun signs requests. |
| **Security** | `config/packages/security.yaml`: password hashing, form login, access control. Only `/inbound/mailgun/` is public (for the webhook); all other routes require login unless explicitly listed (e.g. `/login`, `/register`). |

---

## Development commands

| Command | Purpose |
|---------|---------|
| `symfony serve` | Run the local web server. |
| `php bin/phpunit` | Run the test suite. |
| `php bin/console doctrine:schema:validate` | Check mapping vs database. |
| `php bin/console doctrine:migrations:migrate` | Run pending migrations. |
| `php bin/console make:migration` | Generate a new migration (after entity changes). |

More detail: `docs/dev-commands.md`. To seed inbox messages locally, use `POST /dev/inbound` (dev/test only); see dev-commands.md.

---

## Project docs

- `docs/spec.md` — Product goal and MVP scope.
- `docs/mvp-design.md` — Data model and routes design.
- `docs/decisions.md` — Tech and product decisions (domain, alias format, etc.).
- `docs/acceptance-criteria.md` — Acceptance criteria for inbox, view message, and webhook.
- `docs/working-agreement.md` — How we work (tasks, acceptance criteria, security). 
