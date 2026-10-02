# Cookie / Session Record

Source: `config/session.php`, `config/sanctum.php`, `render.yaml`.

- Session driver: `database` (`SESSION_DRIVER`, `config/session.php:21`, `render.yaml:40-41`).
- Cookie name: `SESSION_COOKIE` (`config/session.php:130-133`).
- Cookie domain: `SESSION_DOMAIN` (`config/session.php:159`, `render.yaml:36-37`, host-only empty string in prod).
- Secure: `true` in prod (`SESSION_SECURE_COOKIE`, `config/session.php:172`, `render.yaml:38-39`).
- Http-only: `true` (`SESSION_HTTP_ONLY`, `config/session.php:185`).
- Same-site: `lax` (`SESSION_SAME_SITE`, `config/session.php:202`).
- Sanctum: stateful SPA domains via `SANCTUM_STATEFUL_DOMAINS` (`config/sanctum.php:21-25`, `render.yaml:33-35`); cookie encryption via `EncryptCookies` middleware (`config/sanctum.php:80-84`).
- CORS: `supports_credentials` is `false` (`CORS_SUPPORTS_CREDENTIALS`, `config/cors.php:41`, `render.yaml:31-32`).
