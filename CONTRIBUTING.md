# Contributing to CaseTrack

## Branching strategy

- **`main`** — deployment-ready checkpoints only. Advances only via an explicit, approved
  promotion from `develop`, tied to a specific milestone (adviser review, submission). Every
  promotion strips dev/AI-tooling files (`AGENTS.md`, `CLAUDE.md`, `.claude/`,
  `.codex/`, `.agents/`) so `main` never carries
  anything but the deployable app. Tagged at each checkpoint (e.g. `v0.1-adviser-review`).
- **`develop`** — the working trunk. Always green. Carries dev tooling (`AGENTS.md`,
  `CLAUDE.md`, `.claude/`, `.codex/`, `.agents/`) for the team.
- **`feature/*`** — one topic per branch, cut from `develop`, merged back with `--no-ff`
  (never squashed, never fast-forwarded) so the branch's own commits survive as a
  distinguishable unit in history.

### Day to day

```bash
git checkout develop && git pull
git checkout -b feature/my-thing
# ... commits ...
git checkout develop && git pull
git merge --no-ff feature/my-thing
php artisan test
git push
```

Keep the feature branch after merging — it's the record of that unit of work, not scratch
space to delete.

### Promoting `develop` → `main` (checkpoint only, approved each time)

```bash
git checkout main
git merge --no-ff develop
git rm CLAUDE.md
git rm AGENTS.md
git rm -r .claude/
git rm -r --ignore-unmatch .codex/ .agents/
git commit -m "chore: remove dev-only tooling files for deployment checkpoint"
git tag v0.X-checkpoint-name
git push origin main --tags
```

`main` must never contain `CLAUDE.md`, `AGENTS.md`, `.claude/`, `.codex/`, or `.agents/` — they're project-internal
tooling with no place in a deployed or submitted artifact.

The Codex workflow and detailed usage manual live in `.codex/README.md`.
These promotion commands require explicit checkpoint approval; the workflow
does not run them automatically. Exclude the same tooling from manually prepared
deployment/submission packages, including untracked local tooling directories.
