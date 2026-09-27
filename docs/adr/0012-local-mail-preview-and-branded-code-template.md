# Local mail preview (Mailpit), [LOCAL] provenance marker, branded code template

Date: 2026-09-26. Decided with owner in grill session (Mailpit default, subject-prefix marker, full template now, text brand).

## Context
Verification-code mail (forgot/reset, password change, email change) flows through one notification, `App\Notifications\VerificationCode::toMail()`. Local `.env` used `MAIL_MAILER=log`, so codes landed in `storage/logs/laravel.log` and never reached an inbox — correct for unit runs, useless for visual/delivery testing. Gmail SMTP works (proven 2026-09-26 with an App Password) but burns the owner's personal inbox on every test send, and prod Render was missing the A6/A7/A8 routes entirely.

## Decision
1. **Mailpit is the local default.** `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025` in `.env.example`; rendered mail is inspected in the Mailpit web UI. Zero inbox burn, zero log-scraping.
2. **Gmail SMTP stays for delivery tests + prod only.** App Password (never the account password) in local `.env` when a real-delivery check is needed, and in the Render dashboard `MAIL_PASSWORD` for prod. `render.yaml` mirrors every mail key except the secret (`sync: false`); dead keys are removed so the two envs match 1:1.
3. **`[LOCAL]` subject prefix, gated on `APP_ENV=local`.** Any mail produced by a local stack carries a visible provenance marker that survives forwarding and inbox-list views. Prod (`production`) and tests (`testing`) never see it, so `VerificationCodeMailTest` subject pins hold.
4. **One branded Blade view for all code mail**, rendered via `MailMessage->view()` inside the existing `VerificationCode` notification (no Mailable conversion, no call-site churn). Text brand, no images (most inboxes block them by default). Same copy, same three purposes (`password reset`, `password change`, `email change`); table layout + inline styles for client compatibility.

## Consequences
- `.env.example` is the contract: Mailpit defaults, Gmail override documented in `docs/ops/`.
- Rotating the App Password exposed in chat history; secrets never enter the repo (`.env` ignored, `render.yaml` holds no secret values).
- Template change updates `VerificationCodeMailTest` render assertions (subject pin stays, body assertions move to rendered HTML).
- Mailpit is a standalone binary (no compose file in `docker/`); setup is a documented manual step, not a container dependency.
