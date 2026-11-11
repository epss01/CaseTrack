# CaseTrack Codex workflow

This is the development workflow approved on November 11, 2026. It uses native
Codex delegation and existing project checks. It does not add agents to the
CaseTrack application.

## Quick start

1. Open the CaseTrack project in Codex and start a new chat so the saved project
   configuration can load. The project default is GPT-6.1 Sol at medium reasoning;
   an explicit model selection can override it. Existing chats may retain theirs.
2. Describe the outcome you need. No special command is required. The repository
   `AGENTS.md` asks Codex to choose the proportional workflow.
3. Approve concrete consequential decisions when asked. Ordinary fixes proceed
   within your existing authorization.
4. Read the completion report for changes, checks actually run, and remaining
   issues. Say **"Log this session"** if you want an Obsidian session entry.

Useful prompts:

| Intent | Example |
| --- | --- |
| Small fix | "Fix this validation message. Work solo." |
| Feature | "Implement this report filter using the project workflow. Have the reviewer check the final diff." |
| Investigate | "Have the researcher trace how FIR deadlines are calculated. Do not change code." |
| Plan only | "Assess this schema change and propose the smallest solution. Do not implement yet." |
| Review | "Have the reviewer inspect the current diff for authorization and audit regressions. Do not edit files." |
| Manuscript | "Use the manuscript writer to draft Chapter III's Case profiles methods from the current implementation and CASE_PROFILES_PILOT.md. Return a draft only." |
| Record work | "Log this session, including verified results, unfinished work, and proposed wiki updates." |
| Bypass delegation | "Work solo for this task; keep the normal checks." |
| Skip a role | "Do not use the researcher for this task." |

Naming a role requests it; it does not prove that the client loaded a custom agent
definition. See the fallback and validation sections below.

## Who does what

| Role | Model / reasoning | Responsibility |
| --- | --- | --- |
| Main agent | `gpt-6.1-sol` / `medium` | Understand, plan, implement, integrate, verify, report; write requested session notes |
| `researcher` | `gpt-6-luna` / `high` | Bounded code/vault investigation and factual documentation drafts |
| `reviewer` | `gpt-6.1-sol` / `high` | Independent findings on a stable diff; no code edits |
| `manuscript_writer` | `gpt-6.1-sol` / `medium` | Academic draft prose, evidence references, unresolved claims |

These are starting defaults, not benchmarked rankings. Astra (`gpt-6-astra`) is
available for an occasional difficult architecture question or substantive review
dispute. There is no permanent architect or separate implementation agent: the
main agent already owns those responsibilities. Terra is available locally but
is not assigned a role in this setup.

At most two subagents should run at once alongside the main agent. Specialists
must not spawn further agents. Research can run alongside independent coordinator
work; implementation must finish before review of the final diff. Do not run all
roles for every task.

## How a task progresses

### Simple work

For wording, an isolated low-risk fix, or a minor refactor: inspect the relevant
code and callers, make the smallest complete change, run applicable checks, and
report. No planning ceremony or extra agent is needed.

### Moderate work

For a bounded feature or nontrivial bug: make a short plan, investigate only what
is needed, implement, and verify. Request independent review when application
behavior changes or the task carries material risk. Address significant findings
and request one focused recheck where needed.

### Consequential work

For authorization, schema, WCS/deadlines, broad architecture, or destructive work:
consult the vault and current implementation, identify consequences and unresolved
decisions, and present a concrete proposal before implementing consequential
decisions. Approval already given stays valid. Proceed with ordinary implementation
choices without repeatedly asking. Finish with checks and independent review.

One review pass plus one focused recheck is the normal limit. Remaining significant
findings are reported as unresolved; the limit never means silently accepting a
security defect or claiming completion.

### Example lifecycle

For a report-filter request, the main agent traces the current query and policy,
defines the expected behavior, implements it, and runs the required tests. A
reviewer checks the stable diff for authorization and filtering regressions. The
main agent fixes findings and verifies the fix. If requested, the manuscript
writer drafts the corresponding explanation from the verified behavior. A session
note is appended only when you request logging.

## Ownership, handoffs, and fallback

All agents share the working directory and inherit parent permissions. A role's
"do not edit" instruction is a behavioral boundary, not a separate read-only
sandbox. Only the coordinator writes by default. If you explicitly delegate
implementation, it must have exclusive ownership of named files until it hands
them back. No two agents should edit the same file concurrently.

A delegation should state:

- The bounded question and required result.
- Relevant paths, authoritative project rules, and known conflicts.
- Permitted reads/writes and file ownership.
- Acceptance criteria and verification already performed.
- The required concise output: evidence, findings, draft text, or blockers.

Prefer fresh review contexts so the reviewer checks the code rather than repeating
the author's reasoning. Research summaries should cite files and symbols instead
of pasting long logs. Never include secrets or identifying case data in handoffs.

If the client supports named custom agents, use the matching definition. This
chat's collaboration tool has no named-agent selector: in that case the coordinator
reads the corresponding TOML file and explicitly passes its instructions and
model to native delegation. With this tool, model overrides require a fresh
context (`fork_turns="none"`), so the coordinator must include the relevant project
instructions. This fallback uses the files as instruction sources; it is not
automatic named-agent discovery.

If an agent/model is unavailable, report it, use an available model or continue
serially, and preserve the task boundaries. Do not retry indefinitely. If review
cannot be independent, disclose that limitation; self-review is not independent
verification. Stop for input only where an unresolved decision or required access
actually blocks progress.

## Manuscript walkthrough

For a draft, give the writer the chapter/section, desired change, source material,
and any length or style requirements. For edits, identify the exact current
document/version and authorize the specific edit scope.

The writer starts with the existing vault manuscript index, structure, writing
guide, and change checklist. Exact layout questions go back to the original EVSU
template and outline. The July WIP PDF is a baseline, not proof of the current
Google Doc contents; its adjacent Markdown contains metadata, not manuscript prose.

The writer returns:

1. Proposed replacement prose.
2. A compact mapping of factual claims to source material.
3. Missing evidence and unresolved adviser/CHR questions.

The main agent verifies technical claims before applying authorized edits.
Synthetic test or benchmark results must never be described as CHR user evaluation.
Planned rollout must not become completed deployment. Citation formatting does not
make an unverified source valid. Preserve the specific-objective mapping through
results and conclusions. Do not expose real case identifiers in academic examples.

The writer does not automatically update a live manuscript, publish text, maintain
the wiki, or turn every code change into a manuscript revision.

## Obsidian and documentation

The existing vault is:

```text
C:/Users/admin/Downloads/Capstone_CaseTrack/CaseTrack_Vault/CaseTrack
```

Direct Markdown access is sufficient; no additional Obsidian integration is
installed. The repository owns executable workflow configuration and developer
instructions. The vault owns curated project/manuscript context and session
history. Link to existing notes instead of duplicating them.

On **"Log this session"**, the coordinator uses the Asia/Singapore date and appends
to `raw/sessions/YYYY-MM-DD.md`, creating it only if absent. Follow the existing
format: implemented work, verification, next work, and proposed wiki updates.
Distinguish attempted work, actual results, assumptions, and incomplete tasks.
Do not overwrite existing notes or rewrite their front matter when appending.

`wiki/` stays read-only unless separately authorized. Proposed changes to
`wiki/log.md` and `wiki/roadmap/sprint-checklist.md` go at the bottom of the session
note. Logging is an explicit coordinator action, not a Stop hook. Manuscript
editing is separate from session logging.

## Verification and safety

For generated application code, run the existing Laravel suite with patched PHP:

```powershell
rtk proxy php artisan test
```

For changed PHP, use the installed Pint check on the changed files. CI also checks
the full PHP tree. For Sass/JavaScript changes, run:

```powershell
rtk npm run build
```

For Blade changes, perform Impeccable accessibility and separate usability/workflow
review, consulting PRODUCT.md and DESIGN.md. No JavaScript type-check or lint
command is invented where the project has none. Configuration/docs-only changes
need configuration and diff checks rather than an application test run.

No agent may automatically commit, push, deploy, mutate live data, or edit external
documents outside your authorization. Existing Claude configuration and Impeccable
hooks are preserved. Claude-specific hook payloads are not treated as Codex hooks.
Codex hooks require trust review; their presence is not proof that they executed.

Known authority conflict: the current AGENTS.md closure rule says ordinary-form
closure, but code and the wiki describe a dedicated closure endpoint. Report this
when relevant rather than changing behavior to reconcile it silently. Older wiki
stack descriptions and Claude audit exceptions also require current evidence.

## Change models or disable delegation

The project main-model defaults are in `.codex/config.toml`. Each specialist has
its own `model` and `model_reasoning_effort` in `.codex/agents/<name>.toml`. If you
change a model permanently, also update the model summary in AGENTS.md and this
manual so fallback instructions stay consistent. Keep global settings separate.

In an interactive CLI session, `/model` selects the active model. The app's model
selector may override file defaults for a chat. Configuration changes do not
guarantee an already-running chat changes model; start a fresh chat and verify.

To bypass delegation for one task, say **"Work solo."** To disable it at project
level, set `enabled = false` in the `[agents]` section of `.codex/config.toml` and
start a new chat. Keep normal verification requirements in either case.

To disable one role durably, add an explicit instruction in AGENTS.md naming the
disabled role and move its TOML file outside `.codex/agents/`. This avoids fallback
delegation recreating the role merely because a filename is absent. There is no
invented per-role `enabled` key. Restore both when re-enabling it.

## Files and maintenance

- `AGENTS.md`: task routing, delegation request, boundaries, project rules.
- `.codex/config.toml`: project model/reasoning and concurrency defaults.
- `.codex/agents/*.toml`: three focused specialist definitions.
- `.codex/README.md`: this manual and validation record.
- `CONTRIBUTING.md`: deployment packaging excludes internal tooling.

Do not add permanent agents, scripts, hooks, or dependencies unless repeated work
shows a concrete need. The existing `.codex/hooks.json`, Claude tooling, and skills
remain separate. Dev-tooling directories are excluded from deployment/submission
packages under the existing checkpoint policy; this setup does not perform a
commit, promotion, push, or deployment.

## Validation record

Checked on November 11, 2026, with installed Codex CLI 0.148.0:

| Check | Result |
| --- | --- |
| Native strict project configuration parsing | Passed in an isolated temporary Codex home with this project trusted |
| Effective project values through local `config/read` | Sol, medium reasoning, agents enabled, maximum two concurrent subagents |
| Three role files | Required name/description strings checked; model, reasoning, and instruction fields passed the native strict configuration parser in isolation |
| Configured models and reasoning levels | Present in the local model catalog; actual Sol and Luna delegation completed |
| Researcher smoke test | Correctly identified SQLite `:memory:` and the `build`/`dev` npm scripts; no writes |
| Reviewer smoke test / independent review | Reviewed all seven changed/new workflow files; no findings |
| Writer smoke test | Produced a bounded scope draft and explicitly left CHR pilot usefulness unverified; no manuscript edits |
| Vault session-directory write/read | A uniquely named temporary probe was written, read back, and removed; existing notes unchanged |
| Diff whitespace check | Passed |

The three delegation tests explicitly loaded role instructions and selected their
models through the current collaboration tool. They do **not** establish automatic
named-agent discovery. The CLI prompt preview loaded the project routing text but
did not expose the three custom descriptions, so automatic discovery remains
unverified. The instruction-driven fallback is the tested path in this chat.

Full validation against the real user-level CLI environment was limited: strict
mode rejected an existing global Ponytail hook-state `trusted_content_hash` field;
a non-strict app-server attempt could not initialize its state database under the
managed sandbox. Neither issue was repaired by changing global settings. The
isolated checks validate this project's configuration without those global side
effects. The existing running desktop chat and native delegation remained usable.

Start a fresh project chat to pick up the saved defaults. If warnings appear,
report their exact text rather than disabling protections or editing global hook
trust data. Existing Impeccable hook execution/trust, automatic named-agent
selection, and model persistence across a desktop restart were not tested.

No application code changed, so Laravel tests, Pint, asset builds, and browser
checks were not run for this configuration/documentation-only change. No session
note, wiki page, live manuscript, commit, push, or deployment was created. The
original untracked `.agents/` content and `.codex/hooks.json` were preserved.

## Official references

- [Custom agents and inherited permissions](https://learn.chatgpt.com/docs/agent-configuration/subagents)
- [Configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference)
- [Hooks and trust review](https://learn.chatgpt.com/docs/hooks)
- [CLI model commands](https://learn.chatgpt.com/docs/developer-commands?surface=cli)
- [Model selection](https://developers.openai.com/api/docs/guides/model-selection)
