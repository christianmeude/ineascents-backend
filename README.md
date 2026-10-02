# Inea Scents — Backend API & Admin Dashboard

> Laravel API + Inertia/Vue admin dashboard for the Inea Scents perfume bar booking platform.

## Live

Production: https://ineascents.onrender.com

Runs on Render's free plan (`render.yaml`), so the first request after idle can
take ~a minute or return a cold-start 503 — retry and it recovers. Only
production lives in the cloud; local dev runs on your own machine
(`render.yaml:1-2`).

## Ecosystem

Three repos, one platform. This repo is the backend that powers the other two:

| Repo | URL |
| --- | --- |
| Backend + admin (this repo) | https://ineascents.onrender.com |
| Customer app | https://ineascents-app.vercel.app |
| Landing site | https://ineascents.vercel.app |

Production wiring: `APP_URL` / `FRONTEND_URL` / `LANDING_URL` are set to the
above in `render.yaml:20-30`.

## Prerequisites

Pinned in `composer.json` / `package.json`:

- PHP `^8.3` (`composer.json:9`)
- Composer
- Node.js + npm (Vite `^8`, Vue `^3.4` in `package.json`)
- Docker + Supabase CLI (local Postgres via `supabase start`)

## Quick Start

Own scripts live in `composer.json:41-57`. Prefer them over raw commands:

```bash
# Full setup (install, .env, key, migrate, frontend build)
composer setup

# Dev loop: serve + queue listener + log tail + Vite, concurrently
composer dev

# Regenerate API docs, clear config, run suite
composer test
```

Manual equivalent:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
supabase start          # local Postgres (needs Docker)
php artisan migrate --seed
php artisan storage:link --force   # serve uploaded images via /storage
php artisan serve --port=8080   # http://127.0.0.1:8080
npm run dev             # Vite
```

### Local ports & tunnel

Backend is pinned to port 8080 via `composer dev`
(`php artisan serve --port=8080`, `composer.json:51`) — the ngrok tunnel and
the PayMongo webhook URL registered in the dashboard both point at 8080, so
serving on the default 8000 silently breaks local webhooks.

- Tunnel stays manual: `ngrok http 8080`. Free ngrok URLs rotate — if the
  forwarding URL changes, re-register it as the webhook URL in the PayMongo
  dashboard.
- Secrets per env: local `.env` uses the PayMongo TEST secret key;
  production env uses the LIVE secret key. Never mix them.
- App `API_URL` per platform (build-time `--dart-define`, rebuild after
  changing): Android emulator `http://10.0.2.2:8080`, Chrome/desktop
  `http://localhost:8080`, physical device `http://<LAN-IP>:8080`.

## Two-Environment Model

Local + production only, no staging (`render.yaml:2`):

- **Local:** Supabase CLI Postgres on `127.0.0.1:54322` (see `.env.example:24-33`),
  app at `http://127.0.0.1:8080` (`.env.example:6`).
- **Production:** Render web service + Supabase Cloud project `ineascents-db`
  (`render.yaml:50-51`). Real secrets live in Render dashboard Env Groups —
  never committed (`render.yaml:3`).

Never point local code at production data or vice versa. Config comes from the
environment per env (12-Factor); there is no code fallback to another env's
URL/DB (`render.yaml:1`).

## Environment

Key production values (`render.yaml:20-30`):

```bash
APP_URL=https://ineascents.onrender.com
FRONTEND_URL=https://ineascents-app.vercel.app
LANDING_URL=https://ineascents.vercel.app
```

Local defaults (`.env.example`):

```bash
APP_URL=http://127.0.0.1:8080
FRONTEND_URL=http://127.0.0.1:5173,http://localhost:5173
LANDING_URL=http://localhost:5173
```

### Admin bootstrap & PayMongo (`.env.example:88-98`)

```bash
# PayMongo: test keys locally, live keys in prod — never share across envs
PAYMONGO_PUBLIC_KEY=
PAYMONGO_SECRET_KEY=
PAYMONGO_WEBHOOK_SECRET=
CRON_TOKEN=

# Admin seed (min 12 chars for password)
ADMIN_NAME=Admin
ADMIN_EMAIL=admin@ineascents.com
ADMIN_PASSWORD=   # set via env
```

`PAYMONGO_WEBHOOK_SECRET` and `DATABASE_URL` are `sync: false` in
`render.yaml` — set them in the Render dashboard, not the blueprint.

## API Docs

- Generated spec: `storage/api-docs/api-docs.json` (committed copy also flows
  to clients via CI — see `.github/workflows/push_api_docs.yml`).
- Regenerate: `php artisan l5-swagger:generate` (runs first in `composer test`,
  `composer.json:54`; package `darkaonline/l5-swagger`, `composer.json:10`).
- Swagger UI route: `/docs` (`config/l5-swagger.php:61`); JSON filename
  `api-docs.json` (`config/l5-swagger.php:35`).
- Tests validate responses against the spec (`tests/TestCase.php` uses
  `api-docs.json` via Spectator).

## Queue & Webhooks

- Queue driver is database-backed (`QUEUE_CONNECTION=database`,
  `.env.example:59`). `composer dev` already runs
  `php artisan queue:listen --tries=1 --timeout=0` (`composer.json:51`) — keep a
  listener running wherever queued jobs must process.
- PayMongo webhook endpoint: `POST /api/webhooks/paymongo`
  (`routes/api.php:30`, throttled `60,1`). It verifies the
  `Paymongo-Signature` header against `PAYMONGO_WEBHOOK_SECRET` before any DB
  write, dedupes via `webhook_events.event_id`, and only acts on
  `link.payment.paid` events.

## Logging on Render

`LOG_CHANNEL=stderr` in production (`render.yaml:42-45`) so logs stream to the
Render dashboard. File channels (`stack`/`single`) are invisible inside the
container (ephemeral storage) — never set them in prod. Local default stays
`LOG_CHANNEL=stack` (`.env.example:19`).

## Scripts & Tests

```bash
composer setup   # install + .env + key + migrate + frontend build
composer dev     # serve + queue:listen + pail + vite (concurrently)
composer test    # l5-swagger:generate + config:clear + phpunit
npm run dev      # Vite only
npm run build    # frontend production build
```

Test stack: PHPUnit `^12` + Spectator (spec-driven API assertions) +
`hotmeteor/spectator`. Suite runs against the generated `api-docs.json`, so
`composer test` regenerates docs first.

## Troubleshooting

- `vendor/autoload.php` missing (any `php artisan` fails): `vendor/` is
  git-ignored — rebuild with `composer install`.
- `SQLSTATE` connection refused on `127.0.0.1:54322`: local Supabase stack is
  down — run `supabase start` (needs Docker).
- CORS error from Flutter web: `CORS_ALLOWED_ORIGIN_PATTERNS` must cover the
  app origin (Flutter web uses a random localhost port; the `.env.example`
  default covers local ports). In prod, origins are exact-matched against
  `FRONTEND_URL` — no trailing slash (`render.yaml:27-28`).
- Cold-start 503 on Render: free-plan sleep — wait/retry; check Render logs
  (`LOG_CHANNEL=stderr` streams there).
- Stale config after `.env` edits: `php artisan config:clear`.
- Webhook 401s: `PAYMONGO_WEBHOOK_SECRET` mismatch — check per-env value
  (test vs live).

## License

Proprietary — all rights reserved. No license file is shipped with this repo.
