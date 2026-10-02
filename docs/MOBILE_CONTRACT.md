# Mobile Contract (backend → ineascents-app)

Source of truth for how the Flutter client integrates with this API.
Backend owns the contract; the app adapts to it, never the reverse.

## Environments & ports

| Env | Backend | Mobile web | API_URL for app |
|-----|---------|-----------|-----------------|
| local | http://127.0.0.1:8080 (`composer dev`) | `flutter run -d chrome --web-port=62409` | http://127.0.0.1:8080 (debug fallback in `core_providers.dart`) |
| production | https://ineascents.onrender.com | Vercel (`API_URL` via `--dart-define`, see `vercel_build.sh`) | https://ineascents.onrender.com |

Two envs only, no cross-env references (ADR-0009). Local Flutter port is
pinned — an ephemeral port breaks the landing "Book in App" link.

## Integration rules

- **Envelope:** all API payloads are wrapped in `"data"` (Laravel API Resources).
- **Spec:** generate/verify the networking layer against the OpenAPI JSON
  (`/api/documentation`, `/docs?api-docs.json`), not by hand (ADR-0001, ADR-0005).
- **Auth:** Sanctum Bearer token; admin surfaces never exist in the app
  (ADR-0008).
- **Images:** `Package.images` / `gallery_images` are absolute URLs pointing
  at `GET /api/packages/{package}/images/{collection}/{index}` (`images` |
  `gallery_images`, positional index). The endpoint streams bytes with
  `Access-Control-Allow-Origin: *` — required because Flutter web fetches
  images via XHR and static `/storage/*` files bypass Laravel (no CORS
  headers) under `php artisan serve`. External CDN URLs pass through as-is.
  Never construct `/storage/…` URLs client-side.
- **Cache:** package index/show responses are cached 300s with ETag;
  Admin writes bust them. The app keeps a 5-minute in-memory catalog cache
  for offline fallback only.
- **Inquiries:** landing creates Inquiries only (`POST /api/inquiries`,
  `throttle:10,1`); only the app/admin create Bookings.
- **Payments:** PayMongo exclusively via backend; local uses test keys and a
  public tunnel for webhooks (see `docs/ops/env-setup-runbook.md`).

## Local prerequisites (backend side)

`composer setup` (install + `.env` + key + migrate + `storage:link --force` +
frontend build). Uploaded images require the `public/storage` symlink — if a
stray empty `public/storage` directory blocks it, remove the directory and
re-run `php artisan storage:link --force`.
