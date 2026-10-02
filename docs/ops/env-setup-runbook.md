# Environment Setup — Operations Runbook (local + production)

Purpose: 12-Factor isolation with only **two** environments — `local` (developer machine) and
`production` (the sole cloud env). Backing config: `render.yaml` (Render), Vercel project vars,
Supabase project `ineascents-db`, local `.env` (gitignored). Runtime on Render is **Docker**
(`./Dockerfile`, `php:8.4-apache`). See `docs/adr/0009-two-environments.md`.

> Status note: items in **"Applied"** were done in-session via the opencode Render/Vercel MCP
> servers. Items in **"Manual (dashboard/CLI)"** could not be reached by the installed MCP tool
> subset (no Vercel env-var tool, no Render env-group tool, no Docker service creation) and must
> be done by hand.

---

## Environment matrix

| Env | Backend (Render) | URL | Supabase | Client (Vercel) | API_URL |
|-----|------------------|-----|----------|-----------------|---------|
| local | — (dev machine) | http://127.0.0.1:8080 | Supabase CLI (DB 54322) | `flutter run` | http://127.0.0.1:8080 |
| production | `ineascents` | https://ineascents.onrender.com | `ineascents-db` | Production + Preview | https://ineascents.onrender.com |

No env references another env's URL/DB. `core_providers.dart` requires `API_URL` in release;
there is no code fallback to another env. Vercel Preview has no dedicated backend and passes the
prod `API_URL` (accepted limitation, see ADR-0009).

---

## Local

### Mailpit mail preview (default, manual binary)
Local mail never touches an inbox. `.env.example` ships Mailpit defaults (`MAIL_MAILER=smtp`,
`MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`); rendered mail is inspected in the Mailpit web UI at
http://127.0.0.1:8025. Setup (standalone binary, no container, no `docker/` compose file):

1. Download the Mailpit release for your OS (https://github.com/axllent/mailpit/releases),
   extract, run `mailpit` (SMTP on 1025, UI on 8025).
2. Copy `.env.example` → `.env`; keep the Mailpit mail block as-is.
3. Trigger any code-mail flow (register, password reset); the message appears in the UI.
   Local subjects carry a `[LOCAL]` prefix (see `docs/adr/0012-local-mail-preview-and-branded-code-template.md`).

Gmail SMTP override (delivery tests only): set `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`,
`MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, `MAIL_USERNAME=ineascents.app@gmail.com`,
`MAIL_PASSWORD=<16-letter App Password, spaces removed>` in local `.env` (gitignored, never
committed) when a real-delivery check is needed. Default day-to-day stays Mailpit.

---

## Render

### Applied: production service
`ineascents` (`srv-d9t811ijobas73c9h50g`, workspace `tea-d9t7ggajobas73c885mg`, Docker,
free, oregon, repo `christianmeude/ineascents-backend`) is live. Applied vars (dashboard wins
over `render.yaml`): `APP_ENV=production`, `APP_DEBUG=false`,
`APP_URL=https://ineascents.onrender.com`, `FRONTEND_URL=https://ineascents-app.vercel.app`,
`LANDING_URL=https://ineascents.vercel.app`,
`CORS_SUPPORTS_CREDENTIALS=false`, `SANCTUM_STATEFUL_DOMAINS=ineascents-app.vercel.app,ineascents-app-christianmeude1.vercel.app`,
`SESSION_DOMAIN=""`, `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER=database`, `LOG_CHANNEL=stderr`,
`APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en`, `BCRYPT_ROUNDS=12`.

### Manual (dashboard): secrets
Secrets are service-level env vars on `ineascents` (no env group attached),
managed in dashboard or via Render CLI/API. Keep
secrets out of `render.yaml` (`sync: false`):
- PayMongo **live** keys
  (`PAYMONGO_PUBLIC_KEY`, `PAYMONGO_SECRET_KEY`), `PAYMONGO_WEBHOOK_SECRET`, `CRON_TOKEN`,
  `DATABASE_URL` → Supabase `ineascents-db`, `APP_KEY`.
- Gmail SMTP: `MAIL_USERNAME` = `ineascents.app@gmail.com`, `MAIL_PASSWORD` = App Password
  (Security → 2-Step Verification → App passwords; strip spaces). Non-secret mail shape
  (`MAIL_MAILER=smtp`, host/port/TLS, from address/name) mirrors `render.yaml`.
- `APP_KEY` must be unique to prod and never shared with local.

---

## Vercel

### Manual (dashboard): client project env vars + build
The installed Vercel MCP has **no set-env-var tool**, so do this in the Vercel dashboard or CLI.
Project `ineascents-app` is linked to `christianmeude/ineascents-app`.
- Set project env var **`API_URL`** for both Production and Preview →
  `https://ineascents.onrender.com` (only cloud backend; see ADR-0009).
- Set **Build Command** to `bash vercel_build.sh` (the client build requires `API_URL` at build
  time; see `vercel_build.sh`). Framework "Other". Output dir = Flutter web `build/web`.

### Manual (dashboard): landing project + git link
`create_git_project` for `christianmeude/ineascents-landing` failed with `repo_not_found` —
Vercel's GitHub app cannot see the (private) repo. To fix:
1. In Vercel Dashboard → your GitHub installation settings, grant the Vercel GitHub app access
   to `christianmeude/ineascents-landing` (Settings → Install GitHub App, or Vercel → Add New →
   Project → Import and authorize the repo).
2. Create project linked to `christianmeude/ineascents-landing @ main`. Framework auto-detects
   Vite/React (landing is React+Vite). Production = main.
3. Landing posts Inquiries: `VITE_API_URL=https://ineascents.onrender.com` is set on the
   landing project (production + preview).

---

## Supabase

### Manual (dashboard): cloud production project
- Production DB: project `ineascents-db` → copy **Connection string** into the prod env group
  on Render as `DATABASE_URL` (or DB_HOST/PORT/USER/PASS).
- Local: Supabase CLI (`supabase start`) — DB 54322, Studio 54323, MCP 54321. Never share cloud
  creds with local.

### Security note (from Supabase advisor)
All tables have **RLS disabled** (this is the local SQLite/PG dev DB currently exposed to the
anon key). Do not auto-apply RLS without policies — that would block all access. If this becomes
a shared/remote DB, add RLS + policies deliberately. Remediation SQL pattern:
```
ALTER TABLE public.bookings ENABLE ROW LEVEL SECURITY;
```

---

## Local dev

- Backend: `php artisan serve` (or `composer dev`), `php artisan queue:listen`, `npm run dev`.
- Mobile web (pinned port): `flutter run -d chrome --web-port=62409` — the port
  must stay fixed; the landing "Book in App" link and local API CORS setup
  assume it. Debug `API_URL` fallback is `http://127.0.0.1:8080`
  (`core_providers.dart`); release requires `--dart-define=API_URL=…`.
- Landing: `npm run dev` (typically http://localhost:5173) with
  `VITE_FRONTEND_URL=http://localhost:62409` in `.env.local`.
- Uploaded images: `composer setup` runs `php artisan storage:link --force`.
  If `/storage/*` 404s locally, check `public/storage` is a symlink — a stray
  empty directory of the same name blocks link creation; delete it and re-run.
  Package images are served to the app via the CORS-enabled
  `GET /api/packages/{package}/images/{collection}/{index}` endpoint
  (see `docs/MOBILE_CONTRACT.md`), never raw `/storage/…` URLs.

### PHP CA bundle (Windows, machine-only state)
Local PHP ships without a CA bundle, so TLS to PayMongo/Supabase fails
(`cURL error 60`). One-time per machine:
1. Download `cacert.pem` (curl.se CA extract) to `C:\php\cacert.pem`.
2. In `C:\php\php.ini` set `curl.cainfo = "C:\php\cacert.pem"` and
   `openssl.cafile = "C:\php\cacert.pem"`.
3. Restart the serve process so the new `php.ini` loads
   (`php --ini` must show `Loaded Configuration File: C:\php\php.ini`).
4. Probe: `curl_init('https://api.paymongo.com')` must report `errno=0`
   (HTTP 401 is fine — reachable, unauthenticated). Verified 2026-09-08 on
   PHP 8.5.9: `errno=0 http=401`.
- Tests: `php artisan test` runs against the dedicated `postgres_test` DB; `tests/TestCase.php`
  refuses any other target so dev data in `postgres` is never wiped.
- PayMongo: local uses **test** keys (`pk_test_*` / `sk_test_*`); webhooks must reach
  `POST /api/webhooks/paymongo` through a public tunnel (e.g. ngrok) since there is no remote
  dev backend.

---

## Verification checklist
1. `curl https://ineascents.onrender.com/api/availability` returns 200 (prod backend up).
2. Prod `FRONTEND_URL` = `https://ineascents-app.vercel.app`, prod `LANDING_URL` = `https://ineascents.vercel.app`.
3. Vercel Production and Preview builds both use `API_URL=https://ineascents.onrender.com`.
4. `php artisan migrate` against prod only after local verification (no remote checkpoint).
5. No `FRONTEND_URL`/`DATABASE_URL`/PayMongo value appears in more than one env.

---

## PITR + breach response

See `docs/ops/pitr-breach-runbook.md` for PITR restore-point verification and
the ordered breach runbook (DPO contact + NPC notification).