# Hapisheets Alias Manager

A web app for generating and managing email aliases under **hapisheets.com** (e.g. for form signups and online accounts). Built with Symfony and Twig.

---

## Features

| Feature | Description |
|--------|-------------|
| **Register** | Create an account with email and password (with confirmation). Passwords must be at least 6 characters. If the email is already taken, you get a clear error and can try another. After success you are logged in automatically and sent to the dashboard. |
| **Login** | Sign in with email and password. The login page links to Register; the register page links back to Login. |
| **Dashboard** | View all your aliases in a table: full address, status (Enabled/Disabled), created date, and a **Create alias** link. Both `/` and `/dashboard` show the same dashboard. |
| **Create alias** | Generate a new alias with one click (no fields to fill). Format: word + short suffix (e.g. `river-9k2f@hapisheets.com`). You are redirected to the dashboard with a success message showing the new address. |
| **Disable alias** | Turn off an alias from the dashboard via the **Disable** button in the Actions column. Disabled aliases show status “Disabled” and will reject mail when inbound email is added later. Only your own aliases can be disabled (others return 404). |
| **Log out** | A **Log out** link is shown in the global nav when you are logged in; it points to `/logout` and ends your session. When not authenticated, the nav shows **Log in** and **Register** instead. |

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

### 3. Register or log in

- Open the app in the browser (e.g. `https://127.0.0.1:8000`).
- If not authenticated, you are redirected to **Login**.
- **Register:** Click “Don’t have an account? Register”, enter email and password (with “Repeat Password”). Passwords must be at least 6 characters. If the email is already registered, you’ll see “This email is already registered.” and can correct it. On success you are logged in and redirected to the dashboard.
- **Log in:** Enter email and password and submit. You can use “Already have an account? Log in” from the register page to switch.

### 4. Dashboard (`/` or `/dashboard`)

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

### 7. Log out

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
| `/aliases/{id}/disable` | POST | Disable an alias (must own it; returns 404 otherwise). |

Protected routes (dashboard and alias actions) require a logged-in user; otherwise you are redirected to `/login`. Registration and login are public. Attempting to disable an alias you don’t own returns 404.

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
