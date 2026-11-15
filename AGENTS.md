# Codex Instructions — ProspectOS

Before every work session, read these files in order:

1. `AGENTS.md`
2. `PROJECT_CONTEXT.md`
3. `CODEX_PROGRESS.md`
4. `ProspectOS_CODEX_MASTER_BUILD_PROMPT.md` (the repository's actual master specification)
5. Relevant files under `docs/`
6. The source code and tests relevant to the requested task

## Permanent rules

- `ProspectOS_CODEX_MASTER_BUILD_PROMPT.md` is the detailed product and implementation specification. Preserve the original filename and content.
- `PROJECT_CONTEXT.md` is the canonical current-state handoff for future sessions. Keep it current after every substantial phase and at the end of every session.
- `CODEX_PROGRESS.md` is an append-only implementation log. Never erase prior entries.
- Preserve the supplied references at `docs/reference/app-ui-ux-skill.md` and `docs/reference/dashboard-visual-reference.png`.
- Do not replace Symfony with WordPress, Laravel or a JavaScript backend without explicit owner approval.
- Use Symfony AI and the official PHP MCP SDK through the Symfony MCP Bundle. Verify current API and Composer compatibility instead of guessing.
- Use Upsun as the primary deployment target and GitHub as the public source repository.
- The frontend is a Symfony-rendered responsive PWA (Twig + Symfony UX + Stimulus + Turbo) unless an ADR documents a justified change.
- Never claim an integration works until its real API operation and error path have been tested. Mark demo/simulated behaviour clearly.
- External communication requires policy checks, suppression/opt-out checks and approval by default.
- Never commit secrets, tokens, credentials or private customer data.
- Run relevant tests after changes and record exact commands/results in `CODEX_PROGRESS.md`.
- If scope is too large, finish the core vertical slice and clearly document deferred work. Do not create superficial nonfunctional stubs to inflate feature count.

At the end of each session, update `PROJECT_CONTEXT.md` with completed work, actual architecture, package versions, run/deploy instructions, known limitations and the exact next task. Update `CODEX_PROGRESS.md` with date, work performed, tests run, results, failures and next actions.
