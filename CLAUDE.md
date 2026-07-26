# CaseTrack

## Project knowledge base
This repo has a companion Obsidian vault at the path configured in
.claude/settings.json (additionalDirectories). Before implementing or
modifying anything related to the database schema, the WCS assignment
logic, or architecture decisions, read wiki/_master-index.md in that
vault first, then follow links to the relevant page(s) if one exists.
Treat the wiki as authoritative project context — don't re-derive
decisions that are already documented there. Never write to the vault;
it's read-only from this repo. If you notice the wiki is missing or
out of date on something you just built, tell me instead of updating
it yourself — I'll hand that off to Cowork.

## Session logging
When asked to "log this session," write the session note directly to
raw/sessions/YYYY-MM-DD.md in the vault (create the file if it doesn't
exist yet today, append if a note for today already exists from an
earlier session). Follow the format already established in prior
session notes there. This is the one write exception to the read-only
vault rule — raw/ is a capture zone, not curated knowledge, so it's
fine for Claude Code to own it directly. Still don't write to wiki/
directly: keep proposing the wiki/log.md append line and any
sprint-checklist.md changes at the bottom of the session note, for
Cowork to apply — exactly as before.

### Note for teammates
`.claude/settings.json` is committed and team-shared, but the vault path in it is
machine-specific. It currently points at this machine's location:

```
C:/Users/admin/Downloads/Capstone_CaseTrack/CaseTrack_Vault/CaseTrack/wiki
```

If your local vault lives somewhere else, update `permissions.additionalDirectories`
in `.claude/settings.json` to your own absolute path to the vault's
`CaseTrack/wiki` folder. (Avoid committing your local path change unless the whole
team moves — or override it in `.claude/settings.local.json`, which is gitignored.)
