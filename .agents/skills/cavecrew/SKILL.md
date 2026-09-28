---
name: cavecrew
description: >
  When to delegate locate-code, 1-2 file edit, or diff review work to
  compressed-output subagents (cavecrew roles: investigator, builder, reviewer)
  instead of working inline. Their output contracts keep main context small.
---

Cavecrew = three roles with fixed output contracts. Same jobs as the default
`explore` worker and inline editing; difference is the result shape, so main
context shrinks per delegation.

## Runtime wiring (read first)

This runner exposes exactly two subagent workers: `explore` and `general`.
There are no `cavecrew-investigator`, `cavecrew-builder`, or
`cavecrew-reviewer` worker names — never pass those strings as an agent type
(unknown names do not route anywhere valid). Map each role as follows:

| Role | Run as | How |
|---|---|---|
| `investigator` (locate code, read-only) | `explore` subagent | Paste the investigator output contract into the prompt; demand findings only |
| `builder` (surgical edit, ≤2 files) | Main thread inline (default) | Sites already known from investigator; edit + re-read to verify |
| `builder` (hands-off wanted) | `general` subagent | Paste the builder output contract + exact path:line + change into the prompt |
| `reviewer` (diff/file audit) | `general` subagent | Paste the reviewer output contract into the prompt; findings only |
| `reviewer` (pre-merge gate) | `code-review` skill | Full two-axis review; required by repo merge rules, not optional |

## When to use cavecrew vs alternatives

| Task | Use |
|---|---|
| "Where is X defined / what calls Y / list uses of Z" | `investigator` (`explore` + contract) |
| Same but you also want suggestions/architecture commentary | `explore` without contract (prose) |
| Surgical edit, ≤2 files, scope obvious | `builder` (inline, or `general` + contract) |
| New feature / 3+ files / cross-cutting refactor | Main thread |
| Review diff, branch, or file for bugs | `reviewer` (`general` + contract) |
| Deep code review with rationale + alternatives | `code-review` skill |
| One-line answer you already know | Main thread, no subagent |

Rule of thumb: **if you'd want the subagent's output in 1/3 the tokens, pick a cavecrew role with its contract. If you'd want prose, pick vanilla.**

## Why this exists (the real win)

Subagent tool results get injected into main context verbatim. A vanilla `explore` that returns 2k tokens of prose costs 2k tokens of main-context budget every time. The same finding through the `investigator` contract returns ~700 tokens. Across 20 delegations in one session that's the difference between context exhaustion and finishing the task.

## Output contracts

What main thread can rely on per role. Paste the relevant block into the subagent prompt verbatim.

**`investigator`**
```
<Header>:
- path:line — `symbol` — short note
totals: <counts>.
```
Or `No match.` Always file-path-first, line-number-attached, backticked symbols. Safe to grep with `path:\d+`.

**`builder`**
```
<path:line-range> — <change ≤10 words>.
verified: <re-read OK | mismatch @ path:line>.
```
Or one of: `too-big.` / `needs-confirm.` / `ambiguous.` / `regressed.` (terminal first token).

**`reviewer`**
```
path:line: <emoji> <severity>: <problem>. <fix>.
totals: N🔴 N🟡 N🔵 N❓
```
Or `No issues.` Findings sorted file → line ascending.

## Chaining patterns

**Locate → fix → verify** (most common):
1. `investigator` (`explore` + contract) returns site list.
2. Main thread picks 1-2 sites, edits inline (or hands paths to `general` + builder contract).
3. `reviewer` (`general` + contract, or `code-review` skill pre-merge) audits the diff.

**Parallel scout** (when investigation is broad):
Spawn 2-3 `investigator` calls in one message (different angles: defs vs callers vs tests). Aggregate in main thread.

**Single-shot edit** (when site is already known):
Skip investigator. Edit inline, or hand exact path:line to `general` + builder contract.

## What NOT to do

- Don't pass `cavecrew-investigator`, `cavecrew-builder`, or `cavecrew-reviewer` as worker names. Those are role names, not workers. See Runtime wiring.
- Don't use the `builder` role when you don't already know the file. Spawn investigator first or main thread will eat tokens passing context.
- Don't chain `investigator → builder` for a 5-file refactor. Refuse it yourself (`too-big.`) and work in main thread instead — you've wasted a turn otherwise.
- Don't ask the `reviewer` role for "general feedback" — it returns findings only, no architecture opinions. Use the `code-review` skill for that.
- Don't expect prose. Contract output is structured, sometimes terse to the point of cryptic. If a human will read it directly, paraphrase.

## Auto-clarity (inherited)

Subagents drop caveman → normal English for security warnings, irreversible-action confirmations, and any output where fragment ambiguity could be misread. Resume caveman after.
