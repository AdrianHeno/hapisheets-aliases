# Hapisheets Alias Manager

A web app for generating and managing email aliases under **hapisheets.com** (e.g. for form signups and online accounts). Built with Symfony and Twig.

---

## Features

| Feature | Description |
|--------|-------------|
| **Login** | Sign in with email and password. |
| **Dashboard** | View all your aliases (address, status, created date) and a link to create more. |
| **Create alias** | Generate a new alias with one click. Format: word + short suffix (e.g. `river-9k2f@hapisheets.com`). No input required. |
| **Disable alias** | Turn off an alias from the dashboard. Disabled aliases are marked and will reject mail when inbound email is added later. |

All alias actions are scoped to the logged-in user; you only see and can change your own aliases.

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

### 3. Log in

- Open the app in the browser (e.g. `https://127.0.0.1:8000`).
- You will be redirected to **Login** if not authenticated.
- Sign in with an existing user (email + password).  
  *User accounts must exist in the database; a registration flow is not part of the current MVP.*

### 4. Dashboard (`/`)

After login you land on the **Dashboard**:

- See all your aliases in a table: **Address** (`localPart@hapisheets.com`), **Status** (Enabled/Disabled), **Created** (date/time).
- Use **Create alias** to add a new one.
- Use **Disable** next to an enabled alias to turn it off; you get a success flash and the list updates.

### 5. Create alias (`/aliases/new`)

- From the dashboard, click **Create alias**.
- On the “Create alias” page, click the **Create alias** button (no fields to fill).
- The app generates a unique local part (e.g. `river-9k2f`), creates the alias, and redirects you to the dashboard with a success message showing the full address (e.g. `river-9k2f@hapisheets.com`).

### 6. Disable alias

- On the dashboard, find the alias and click **Disable** in the Actions column.
- The alias is disabled and a confirmation message is shown. Disabled aliases show status “Disabled” and no Disable button.

---

## Routes

| Route | Methods | Purpose |
|-------|---------|--------|
| `/` | GET | Dashboard: list your aliases, link to create. |
| `/login` | GET, POST | Log in (email + password). |
| `/logout` | any | Log out. |
| `/aliases/new` | GET, POST | Create alias page and form submission. |
| `/aliases/{id}/disable` | POST | Disable an alias (must own it; returns 404 otherwise). |

Protected routes (dashboard and alias actions) require a logged-in user; otherwise you are redirected to `/login`.

---

## Configuration

| What | Where |
|------|--------|
| **Alias domain** | `config/services.yaml`: parameter `app.alias_domain` (default: `hapisheets.com`). Used for display and future mail handling. |
| **Database** | `DATABASE_URL` in `.env` / `.env.local`. |
| **Security** | `config/packages/security.yaml` (e.g. password hashing, form login). |

---

## Development commands

| Command | Purpose |
|---------|---------|
| `symfony serve` | Run the local web server. |
| `php bin/phpunit` | Run the test suite. |
| `php bin/console doctrine:schema:validate` | Check mapping vs database. |
| `php bin/console doctrine:migrations:migrate` | Run pending migrations. |
| `php bin/console make:migration` | Generate a new migration (after entity changes). |

More detail: `docs/dev-commands.md`.

---

## Project docs

- `docs/spec.md` — Product goal and MVP scope.
- `docs/mvp-design.md` — Data model and routes design.
- `docs/decisions.md` — Tech and product decisions (domain, alias format, etc.).
- `docs/working-agreement.md` — How we work (tasks, acceptance criteria, security).
