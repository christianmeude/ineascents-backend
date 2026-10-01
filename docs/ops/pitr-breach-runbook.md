# PITR Verify + Breach Runbook

Scope: Supabase `ineascents-db` (production) backups and personal-data breach
response. Companion to `docs/ops/env-setup-runbook.md` (env isolation).

## 1. PITR verify

Supabase point-in-time recovery (PITR) window depends on plan. Confirm current
plan window in dashboard before relying on it.

How to verify a restore point:

1. Supabase dashboard → project `ineascents-db` → Database → Backups →
   Point-in-time: pick target timestamp, confirm a restore point exists.
2. Test restore to a separate staging/ref project first — never restore over
   production without owner approval.
3. Record RTO/RPO note: time to restore (RTO) and max data-loss window (RPO)
   observed during the staging restore; keep alongside deploy log.

Schedule: verify restore-point availability before any risky migration and
monthly otherwise.

## 2. Breach runbook

Ordered steps — follow in order, do not skip notification deadlines.

1. Contain — revoke exposed keys/sessions, block offending access, snapshot
   logs. Keep production running; isolate affected surface first.
2. Assess scope — count affected rows via `php artisan privacy:purge --dry-run`
   (retention windows: inquiries 24mo, bookings 36mo, webhook_events 12mo)
   plus `audit_logs` queries filtered by time range and actor.
3. DPO contact — DPO (Data Protection Officer): ineascents.app@gmail.com.
   Escalate assessment within 24h; DPO decides notification scope.
4. Notify NPC within 72h + affected users without undue delay — per Privacy
   Policy §8 (notify user + NPC). File NPC breach notification; email affected
   users from official address with scope + mitigations.
5. Eradicate + rotate secrets — remove cause, rotate `APP_KEY`, `DATABASE_URL`,
   PayMongo keys, `CRON_TOKEN`, mail App Password on Render `ineascents`
   service; redeploy and verify.
6. Post-mortem + retention review — root cause, timeline, re-run
   `privacy:purge --dry-run`, confirm retention windows still enforced,
   file follow-up actions.
