# ADR-0013: RLS Posture (Record Only, No Code Change)

## Status

Accepted (record only).

## Context

- Local dev uses SQLite/PG dev DB; all tables have RLS disabled (see `docs/ops/env-setup-runbook.md:106-110`).
- Production uses single Supabase project `ineascents-db` (see `docs/ops/env-setup-runbook.md:100` and `render.yaml` `DATABASE_URL`).

## Decision

- No code change in this ticket. RLS stays disabled locally.
- Do not auto-apply RLS without policies — that would block all access.

## Remediation Pattern

When the DB becomes shared/remote, add RLS plus policies deliberately:

```sql
ALTER TABLE public.bookings ENABLE ROW LEVEL SECURITY;
```

## Consequences

- Local access unblocked; prod trust deferred to deliberate RLS + policies.
