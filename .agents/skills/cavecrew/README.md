# cavecrew

Decision guide. When to delegate to compressed-output subagent roles instead of doing the work inline.

## What it does

Tells main thread when to spawn a contract-bound subagent. Compact return
contracts can reduce repeated prose when results return to main context, but
effect depends on task, agent, and delegation count. This skill publishes no
universal reduction rate.

Three roles (not worker names — see Runtime wiring in `SKILL.md`):

| Role | Job | Use when |
|----------|-----|----------|
| `investigator` | Locate code (read-only) | "Where is X defined / what calls Y / list uses of Z" |
| `builder` | Surgical edit, 1-2 files | Scope is obvious, ≤2 files. Refuse 3+ file scope. |
| `reviewer` | Diff/file review | One-line findings with severity emoji |

Use plain `explore` or the `code-review` skill when you want prose, architecture commentary, or rationale. Use main thread directly for one-line answers and 3+ file refactors.

This skill is a decision guide, not a slash command. It activates when the conversation mentions delegation.

## How to invoke

Triggers on phrases like "delegate to subagent", "use cavecrew", "spawn investigator", "save context", "compressed agent output". Map roles to workers per `SKILL.md` §Runtime wiring — never pass role names as worker names.

## Example chaining

Locate → fix → verify (most common):

1. `investigator` returns site list (`path:line`, symbol, note)
2. Main thread picks 1-2 sites, edits inline (or `general` + builder contract)
3. `reviewer` audits the resulting diff (`general` + contract, or `code-review` skill pre-merge)

Parallel scout: spawn 2-3 `investigator` calls in one message with different angles (defs, callers, tests). Aggregate in main.

## Runtime notes

No model overrides exist on this runner: there is no agent frontmatter to pin, so there are no `CAVECREW_*_MODEL` env vars. If the runner gains worker options later, add them here — not as env-var patches.

## See also

- [`SKILL.md`](./SKILL.md): runtime wiring, decision matrix, output contracts
