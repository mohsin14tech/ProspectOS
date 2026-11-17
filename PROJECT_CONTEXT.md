# ProspectOS — Project Context / Session Handoff

## Current authoritative checkpoint — Phases 1–3 complete, 2026-11-17

All four planned phases to compete for first place are done in code except the final video (Phase 4), which is in production from the owner's new voiceover (`var/video/v4/voiceover.mp3`, 85.8 s, script v3.1 in `docs/VIDEO_SCRIPT_V3.md`).

- **Phase 1 (MCP depth)**: elicitation ruling, progress, resources, prompts, MCP App (see the checkpoint below and `docs/ARCHITECTURE.md`).
- **Conversation view**: three-turn plain-language chat (Advocate opens, Skeptic replies per point, Advocate answers back).
- **Phase 2**: live hearings (`/court/{id}/live` fragment + `court_live_controller.js`; `CourtCase::recordProgress()` saves the thread and "who is typing" after every turn and tool call); similar-lead precedents through Symfony AI Store (`PrecedentRetriever`, in-memory `Store` + `Retriever`, key-free `LexicalVectorizer`, pure vector ranking with `semanticRatio` 1.0).
- **Phase 3**: zero-key demo (`RecordedHearings` replays recorded Groq hearings for the three demo leads through `MockPlatformFactory`, evidence referenced by content fingerprint; `app:court:record-demo` re-records; cases labelled "Recorded demo"); CI step "Zero-key demo hearing on a fresh clone"; `docs/JUDGES.md`. Upstream Symfony AI issues were not filed (external posting needs the owner's approval).
- Tests: `composer test` 113 tests / 597 assertions / 1 skip.

- **Phase 4 (final video)**: `var/video/prospectos-final.mp4` (81.8 s, real data only, demo line cut), published on YouTube as https://youtu.be/LHNodY0Z-YU (description rewritten to match the film; custom thumbnail set; the previous video is private). The Symfony hackathon submission (status Submitted) now uses that video, an updated description (conversation, live hearings, Store precedents, MCP elicitation/progress/resources/prompts/App) and tags Elicitation, MCP Apps, Symfony AI Store.
- PR #2 merged into `main` (bb0e829). YouTube has a manual English caption track. Upstream issues filed: symfony/ai#2976 (InMemory hybrid score scaling) and symfony/ai#2977 (provider error code on BadRequestException).

### Exact next task

Nothing is required before the deadline (**22 Nov 2026, 18:00 CET**). Optional: verify the elicitation form and MCP App inside Claude Desktop; follow up on symfony/ai#2976 and #2977 if maintainers respond (drop the `semanticRatio: 1.0` workaround in `PrecedentRetriever` once #2976 is fixed).

## Current authoritative checkpoint — Phase 1 "MCP depth", 2026-11-17

Context: the project is submitted to the Symfony AI & MCP Hackathon 2026 (video published, repo public, main merged at `fa7cf8f`). A competitor review ranked ProspectOS about 5th of 20 complete entries, mainly for shallow MCP use. The owner approved a four-phase plan to compete for first place and asked to start Phase 1. Work is on branch `evidence-court` (not yet committed in this checkpoint).

Phase 1 delivered (details in `docs/ARCHITECTURE.md`, Evidence Court section):

- Human ruling over MCP **elicitation** (`request_ruling`), live **progress notifications** during hearings (one per counsel tool call), **resources** + resource templates (`prospectos://court/docket`, `prospectos://court/{caseId}`, `prospectos://lead/{leadId}/evidence`), **prompts** (`cross_examine`, `brief_me`) and an **MCP App** (`show_court_case` → `ui://evidence-court/hearing`), on both the STDIO demo server and the authenticated HTTP server.
- New code: `src/Service/Court/McpCourtroom.php`, `src/Service/Court/CourtDocket.php`, `src/Mcp/Demo/DemoCourtRecords.php`, `src/Mcp/Demo/DemoCourtApp.php`, `src/Mcp/Workspace/CourtRecords.php`, `src/Mcp/Workspace/CourtApp.php`, `templates/mcp/*`; `config/packages/mcp.yaml` now registers tools, resources, resource_templates, prompts and apps per server.
- Verified live: Groq gpt-oss-120b hearing #18 over STDIO streamed 9 progress notifications; ruling accepted through the elicitation form was saved as decision #7 "Evidence Court #18 (ruled in an MCP client): …"; decline paths verified on STDIO (protocols 2025-06-18, 2025-11-25, 2026-07-28) and authenticated HTTP (2025-11-25, 2026-07-28). The MCP App rendered in a minimal local MCP Apps host harness (handshake, size, open-link, tools/call all observed). **Not yet verified inside Claude Desktop/Claude Code's own UI** (elicitation form and MCP App rendering there).
- Tests: `composer test` 106 tests / 511 assertions / 1 skip.

Court as a conversation (same day, owner request): the hearing page is now a chat thread in plain language (Advocate left, Skeptic right, replies quote the point they answer, the Advocate answers back, the clerk's verdict under each message, a neutral "Where it landed" tally, then "Your turn"). Backend: three agent turns with reply links; see ARCHITECTURE. Live Groq cases #19–#21 checked; `composer test` 109 tests / 539 assertions / 1 skip.

### Exact next task

Owner reviews Phase 1 and the conversation view, then commit and push to `evidence-court` and open a PR. Ideally verify `request_ruling` and `show_court_case` inside Claude Desktop (owner's machine) and record a short clip. Then Phase 2 (cross-examination round, semantic precedents with Symfony AI Store, live Turbo Stream hearings) when the owner approves.

## Current authoritative checkpoint — final hackathon video produced, 2026-11-16

This supersedes prior next-task statements about recording the video. The final submission video, captions, thumbnail and QA sheets are in ignored `var/video/`:

- `var/video/prospectos-hackathon-demo.mp4` — 69.90 s, 1920×1080 H.264 High / 30 fps / yuv420p, AAC-LC 48 kHz, 14,314,505 bytes. It is within the 90 s and 100 MB limits. Loudness check measured −16.43 LUFS / −1.50 dBTP.
- `var/video/prospectos-hackathon-demo.srt` — English captions for all 17 narration beats.
- `var/video/thumbnail.png` — 1280×720, mascot, ProspectOS, “Evidence Court for sales leads” and the hackathon entry badge.
- `var/video/contact-sheet-2s.png` and `var/video/contact-sheet-beats.png` — export QA sheets; both and the thumbnail were visually reviewed.
- `CODEX_PROGRESS.md` has the exact capture, render, mux, probe and loudness commands/results. The video uses fresh authenticated 1920×1080 captures from the fictional demo workspace plus the provided light motion kit. No provider keys, real mailbox, browser chrome or Ollama label appear in the final cut. MCP terminal motion is an animation based on the verified successful STDIO case #9 response and current `tools/list` / `get_court_case` results, including the human-only ruling guidance.
- For the real human-ruling shot, demo cases #9 and #7 were ruled Pursue in the local workspace. Two new MCP hearing attempts hit Groq's token-per-minute limit and are retained as failed local cases #11 and #12; those failures are excluded from the video. They do not change the previously verified successful Groq hearing/MCP evidence.
- Architecture and run/deploy details are unchanged: Symfony 7.4.23 / PHP 8.4.27, Symfony AI/MCP Bundle 1.0.0-BETA1 and `mcp/sdk` 0.9.0; run locally with `docker compose up --build -d` at `http://127.0.0.1:8000`. Upsun deployment remains unverified. No source commit, push, deployment or hackathon submission occurred; media and production helpers remain ignored under `var/video/`.

### Exact next task

Owner reviews the final video. Then review and commit/push the intended source changes and submit the public GitHub repository plus this video at the Symfony AI & MCP Hackathon portal before **22 Nov 2026, 18:00 CET**.

## Current authoritative checkpoint — Evidence Court and hackathon readiness, 2026-11-16

This section supersedes the "exact next task" statements below. The owner asked for a creative differentiator using Symfony AI and MCP, a clearer UI/UX, an easy setup, and help submitting to the Symfony AI & MCP Hackathon (deadline **22 Nov 2026, 18:00 CET**). Jurors judge creativity, a working demo (ideally a clone-and-run), what Symfony AI and MCP unlocked, and craft (a README that gets a stranger from clone to demo in five minutes).

- **Evidence Court (new headline feature).** Advocate and Skeptic Symfony AI agents argue one lead using read-only `CaseFileTools` (`read_evidence`, `score_breakdown`, `team_precedents`). The deterministic `CitationClerk` admits or strikes each claim, and repeated Skeptic claims are struck. A human rules from `/court/{id}`, and the ruling is saved as a `ProspectDecision` (team memory / precedent). Async via Messenger `HearCourtCase`. CLI: `app:court:hear <prospectId>`. Pages: `/court`, `/court/{id}`, plus a CTA on every prospect report and a dashboard workflow strip. Migration `Version20261116150034` (`court_case`) is applied to dev and test (14 migrations).
- **MCP.** The STDIO server (`php bin/console mcp:server`) now exposes `list_demo_leads`, `open_evidence_court`, `get_court_case` (demo workspace only) plus `demo-workspace-summary`. The authenticated HTTP `/mcp/workspace` adds `open_evidence_court` and `get_court_case`. There is no ruling tool by design, and this is asserted in tests. Verified with raw JSON-RPC over STDIO: initialize, tools/list, list_demo_leads, and a full `open_evidence_court` hearing (case #5) with Ollama.
- **Real AI verification (local).** The demo workspace now has Ollama `qwen2.5:1.5b` active and tested (10.9 s). Real hearings completed from the UI (case #4, worker, 28 s), the CLI (#3, #6) and MCP (#5). Two early attempts failed on a 30 s HTTP idle timeout. Ollama now gets 150 s idle / 180 s total. Limitation: this 1.5B model never calls tools and argues weakly (the Skeptic parroted the Advocate, which the clerk now strikes). The machine has 8 GB RAM, so a larger local model is not viable. **A cloud key (Groq recommended, or Hugging Face) is needed for a convincing demo, and only the owner can create and paste it.** Not yet verified: a cloud provider hearing with real tool calls, and a live Claude Desktop/Code session.
- **Easy setup.** The opt-in `DemoLogin` (`DEMO_LOGIN_PASSWORD`, never in prod; `.env.example` sets `prospectos-demo`) creates `demo@prospectos.test` as owner of the fictional demo workspace during `app:demo:seed`. The login page shows a one-click **Enter the demo workspace**. Fresh clone path: `cp .env.example .env && docker compose up --build -d`. Previously a fresh clone needed a setup token and then showed an empty workspace, because demo data lived in a workspace no one could access. The owner's ignored `.env` got `DEMO_LOGIN_PASSWORD` appended. `/setup` ignores the demo account when deciding whether the first real owner exists.
- **Fixes.** The sign-in theme button was dead (no handler after the appearance redesign) and labelled wrongly. It is now a sun/moon icon toggle on sign-in and in the app top bar, sharing the `prospectos-preferences` key. Removed stale ignored `public/assets/` (a prod `asset-map:compile` output that overrode live dev CSS). The missing `chevron-left` icon now exists. Memory and Prospects had the same icon. The PHP built-in server was single-threaded, so slow AI calls froze the UI; Compose now sets `PHP_CLI_SERVER_WORKERS=4`.
- **UI/UX.** Dashboard four-step story (Research → Review evidence → Evidence Court → Team memory) with an AI-not-connected callout. Evidence Court nav item (desktop and mobile). Clearer sign-in copy. Court pages checked at desktop and 375 px (no horizontal overflow) in dark mode.
- **Checks.** See CODEX_PROGRESS.md (2026-11-16 entry) for exact commands and results.
- **README** was rewritten around the five-minute juror path, provider choice, MCP client config (Claude Code / Claude Desktop), and what Symfony AI/MCP enabled. `docs/HACKATHON_DEMO.md` has the 90-second script. `docs/ARCHITECTURE.md` has the court design.
- **Email setup simplified.** One-click **Connect and test**, provider auto-detection with step guides, Gmail app-password whitespace stripping (a plausible cause of the earlier Gmail `AUTHENTICATIONFAILED`), defaults for username and test recipient, specific failure hints, and the hub leads with the app-password path (OAuth is advanced). See docs/MAIL.md. The owner's saved Gmail connection is still unverified: they must re-enter the app password in their own workspace.
- Final checks: `composer test` passes with 99 tests / 452 assertions (1 expected opt-in skip). lint:container, lint:twig (20 files), dev schema validation, `node --check` and `git diff --check` pass.
- **Later the same day:** Groq `openai/gpt-oss-120b` is active in the demo workspace (hearings run in about 4 s with real tool calls). The owner's Gmail is connected and tested in the demo workspace (IMAP/SMTP login passed, 10 messages synced). The UI was redesigned (premium.css, mascot logo, collapsible sidebar, View Transitions) and a Turbo listener bug was fixed. See the CODEX_PROGRESS.md entry 'Live integrations and premium redesign'. Work up to the court/demo-login commit is in PR mohsin14tech/ProspectOS#1 (branch evidence-court); the redesign is uncommitted at the time of writing.
- Nothing was committed, pushed, deployed or submitted in this session.

### Exact next task

0. The owner signs in to their own workspace and re-enters the Gmail address plus app password at /outreach/mailbox → **Save and test again** (spaces are now stripped automatically).
1. The owner creates a free Groq (or Hugging Face) API key and pastes it into Integrations → AI **in the demo workspace**, then runs **Send test prompt**. Then run one hearing and confirm `Tools called` shows real calls.
2. Connect Claude Code or Claude Desktop to the STDIO server (see README) and run "list the demo leads and put the best one on trial".
3. Review the diff and commit/push to the public GitHub repo, then confirm CI is green on that commit.
4. Record the 90-second video (script in docs/HACKATHON_DEMO.md) and submit at symfony.com/hackathon before 22 Nov 18:00 CET.


## Local video tooling — 2026-11-15

- The owner clarified that “video engine” means FFmpeg for local video processing. FFmpeg and FFprobe **9.0.2-full_build-www.gyan.dev** were already installed through WinGet (`Gyan.FFmpeg`) and available on the Windows user PATH. Verified executable directory: `C:\Users\Mohsin\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-9.0.2-full_build\bin`. No duplicate installation, relocation or PATH change was needed.
- Real local verification passed: generated a one-second 640x360, 24-frame H.264/AAC MP4 from synthetic video/audio, inspected both streams with FFprobe, and decoded the entire file without errors. Output is ignored at `var/verification/ffmpeg-smoke-1f61fc51ff7c4870aa8ef8efbb121d17.mp4` on D:. Run `ffmpeg -version` or `ffprobe -version` in PowerShell to check the tools.
- This is Windows host tooling; FFmpeg was not added to Symfony, Docker or Upsun, and no product demo was recorded. Application architecture, packages, migrations, run/deploy instructions and provider limitations below remain current. No application code changed or application tests were rerun. The exact next product task remains the Gmail credential correction and controlled verification described below.

## Current authoritative checkpoint — Gmail/IMAP/SMTP, 2026-11-15

This section supersedes all older provider/email/next-task statements below. The owner explicitly chose Gmail plus standard IMAP/SMTP and asked to skip MailOS, Cloudflare and Resend. They report Upsun verification is still pending.

- Finished the previously partial email slice: `/outreach` connection hub, `/outreach/mailbox` encrypted IMAP/SMTP setup with Gmail preset and separate login test, `/inbox` bounded manual sync, escaped plain-text message view, Gmail reply drafts, and `/outreach/tests` immutable test approvals with explicit send. Gmail OAuth now requests readonly + compose scopes; existing grants must reconnect. No Gmail send endpoint is used. IMAP messages are read-only; SMTP permits labelled tests only to the configured controlled recipient. Full setup and limitations: `docs/MAIL.md`.
- Added/applied `Version20261115160000` to dev/test: 13 migrations, zero pending; both schemas validate. Empty disposable database migration passed all 13 migrations/121 SQL statements; after the required Messenger setup, its schema validated and the disposable database was dropped. No production database exists or was modified.
- Sending defaults paused through `OUTBOUND_EMAIL_PAUSED=1` (including missing-env fallback) plus workspace pause. Admin approval expires in 15 minutes; immutable payload hash, original connection identity/revision, workspace suppression and five-attempt hourly cap are rechecked before a committed one-attempt claim. SMTP acceptance is distinct from delivery. Errors become uncertain and never auto-retry. Mail actions have a per-user 30/hour limiter. Plaintext/private-IP mail servers are blocked and TLS hostname verification survives DNS pinning.
- Fixed Google client's invalid refresh-only `setAccessToken` call, stale approval reuse after reconnect, SMTP login false positives without AUTH, missing `.env` Docker exclusion, mailbox privacy headers, and mobile focused-input overlap with sticky Save.
- Real owner-workspace AI test passed through the browser: active Ollama `qwen2.5:1.5b` at `http://host.docker.internal:11434/v1`, 12,328 ms. Database confirms active/tested. This is real Symfony AI inference in the owner's workspace. Cloud AI/Gemini/Hugging Face remain unverified. Prior 4B model OOM remains a limit.
- Latest Gmail connection check (2026-11-15): the owner saved Gmail using the IMAP/SMTP form. Gmail's real IMAP endpoint rejected the saved login with `AUTHENTICATIONFAILED` / `Invalid credentials`, both through the app test and a redacted service diagnostic. TLS/network access reached Gmail; SMTP was not reached because IMAP authentication failed. One IMAP/SMTP connection is saved but unverified and paused; no OAuth Gmail connection, cached emails or test actions exist. No email was read, drafted or sent. The owner must enter the full Gmail username and a valid Google app password directly in the app, then save and retest. Do not print/reuse the credential outside the configured mailbox operation or look for MailOS/Resend keys.
- Current checks: full suite **82 tests / 369 assertions, one expected opt-in Ollama skip**, plus the real owner-workspace Ollama browser test; SMTP scripted wire and Gmail mocked HTTP tests pass. Composer audit: no advisories; Composer/container/Twig (18 templates) lint, schemas, JS and Compose pass. Docker build succeeded and final image `.env` is empty with no `.env.local`; closing Gitleaks scan of 227 publication-candidate files (~1.79 MB) and all 16 Git commits found no leaks. Mobile 390px and desktop 1280px browser review passed for setup/inbox; Gmail preset works, fields are 48px, keyboard focus clearance fixed, both appearances checked. Screenshots are ignored under `var/verification/`.
- Earlier closing runtime check: `/healthz` HTTP 200; unauthenticated email/inbox and MCP workspace routes return HTTP 302. PostgreSQL accepts connections, all 13 dev migrations are applied, async queue count is zero, and app/worker/search/database containers are running. Ollama is active and tested. The later Gmail authentication failure above supersedes the earlier zero-connection state. SMTP still needs a total time/byte budget before public-service operation, as recorded in `docs/MAIL.md`.
- Architecture remains Symfony 7.4.23 / PHP 8.4.27 / Twig + Stimulus + Turbo / PostgreSQL Messenger; Symfony AI/MCP Bundle 1.0.0-BETA1 and SDK 0.9.0 unchanged. New locked email packages are `directorytree/imapengine` 1.25.9 and Symfony Mailer 7.4.22. No PHP IMAP extension is required. `MAILER_DSN` is deliberately not used for approved workspace sends; default framework mailer is `null://null`.
- Run: `docker compose up --build -d`; preserve existing `.env` and `APP_SECRET`. Fresh setup and email configuration are in README and `docs/MAIL.md`. Local app remains `http://127.0.0.1:8000`. No release, commit, push, deployment or submission occurred in this session; substantial prior/current work remains uncommitted on `main` (base `cfa4837`).
- Release status: **not production/submission ready**. See `docs/RELEASE_AUDIT.md`: real mailbox operation/delivery proof, full AI agent/tool demonstration, remote MCP client/public auth/invocation logging, declared generic integration/webhook slice, production deployment, video and submission remain. No completeness percentage is claimed for the much broader master spec.

### Exact next task

The owner must correct the saved Gmail login directly at `/outreach/mailbox` using the full mailbox address and a valid app password (not the ordinary Google password). Retest once updated; the current failure is Gmail IMAP `AUTHENTICATIONFAILED`, not proof of an SMTP failure. Then verify inbox sync and a controlled separately approved SMTP test with actual arrival. Keep the global send pause enabled until that test is ready. Next close the release-audit AI/MCP gaps and complete Upsun onboarding when support ticket #553971 clears. Refresh CI on the reviewed publication commit before recording/submitting the video. Do not treat passing mocks as a live integration, or bypass any approval with MCP/AI.

## Current state as of 2026-11-15 (supersedes historical handoff below)

### Provider connection pass — 2026-11-15

This addendum supersedes conflicting provider/Ollama/browser state below; the remaining implementation notes are historical.

- `AiProviderSetting` now stores one row per workspace/provider, with an explicit active flag. The AI setup page lists saved connections, tests each one separately, retains credentials when another provider is added, and lets an owner/admin switch the active route. Editing one provider resets only that provider's test timestamp. Migration `Version20261115140000` is applied to dev and test schemas (12 migrations, 0 pending).
- Corrected OpenAI-compatible request URLs. Symfony AI's Generic Platform appends the configured completion path to its base URL; versioned endpoints now append `/chat/completions`, avoiding an invalid duplicated `/v1/v1/chat/completions` path.
- The prior partial `qwen2.5:1.5b` pull resumed and completed. Exact-marker inference passed on the Windows host, from the ProspectOS app container to `host.docker.internal:11434`, and through `AiProviderConfiguration::test()` / Symfony AI in an opt-in isolated test workspace. `qwen3.5:4b` remains an OOM failure. No AI connection is yet saved in the owner's actual application workspace (AI settings table is still empty).
- Fixed Compose so the app and worker read `APP_SECRET` from the ignored local `.env` file instead of the checked-in development placeholder. Verified the running container receives 64 characters without printing the value. The app/worker were recreated and `/healthz` returns HTTP 200.
- Chrome access is available in this session, but the currently open Google profile does not match the project owner identity. No provider dashboard was opened and no external account, credential or permission was changed. The owner must open the correct account profile before browser-based Gemini/Gmail/Resend/Cloudflare setup.
- No Gemini, Gmail OAuth/draft, Resend or Cloudflare Worker/webhook operation has passed. Resend and Cloudflare inbound remain unimplemented; Gmail remains configured nowhere. No external email was sent. No commits or pushes were made.
- Current validation: full suite 53 tests / 284 assertions passed with one expected opt-in Ollama test skipped by default. The opt-in live Ollama test passed separately (1 test / 2 assertions). Container lint, 14 Twig templates, dev/test schema/migration checks, Tailwind build, JavaScript syntax, Compose validation, health check and `git diff --check` passed.

### Exact next task

Sign in to the local app as the workspace owner/admin and persist/test Ollama in the owner's workspace through `/integrations/ai`; then enter the owner's Gemini key through the same UI, verify the correct model available to that key, and run its real provider test. Browser setup must use the owner's account, not the current mismatched Chrome profile. Next implement Resend direct connections/send safety and Cloudflare Worker inbound with replay protection, size limits and explicit workspace mapping. Configure Gmail OAuth and verify only `users.drafts.create`. Do not send prospect email or publish/deploy.

The project is a local evidence-first prospect research and team-memory workspace. During the 2026-11-15 implementation session:

- `Prospect` has workspace-scoped lifecycle states (`candidate`, `needs_review`, `finalized`, `rejected`, `archived`) and a nullable normalized canonical domain. Repeated search/website discoveries with a known host reuse the prospect in that workspace and append evidence rather than making a new company record. Website-only inspection no longer fabricates an opportunity score from a fixed evidence-quality value.
- `ProspectDecision` persists each human decision with actor, reason and timestamp. `/memory` shows workspace status counts, leads and decision history. Prospect reports accept CSRF-protected decision changes. `ProspectMemory` retrieves up to five recent reviewed decisions with a specific matching category or verified location and provides them as clearly non-evidentiary preference context to optional AI analysis. This is transparent retrieval, not model retraining or automatic preference edits.
- AI provider setup has a workspace-scoped screen at `/integrations/ai`. Owners/admins can retain multiple connections across Gemini, OpenAI, Groq, OpenRouter, Hugging Face, Ollama or disabled, and explicitly select one active provider. Each encrypted key is never re-displayed; provider hosts are fixed and Ollama is restricted to local Docker host/service names on port 11434. Each connection must pass its own test after changes before it can power summaries. Keys use `SecretVault` purpose-separated authenticated encryption derived from `APP_SECRET`; rotating APP_SECRET requires re-entry. Ollama installation/model downloads remain an explicit operator action, with UI guidance for storing model weights on D:.
- MailOS inbox/JMAP code, UI, tests, env variables, and local cached-message table were removed. The local table contained zero rows immediately before the migration. Gmail is the first outreach adapter: separate OAuth credentials/scopes from Google sign-in; encrypt refresh tokens in the database using `GmailTokenVault` (key derived from APP_SECRET); draft-only Gmail API operation; draft creation requires a finalized prospect, CSRF, user-entered recipient/subject/body, recipient-basis attestation and workspace suppression check. ProspectOS has no send operation. It persists provider draft ID, recipient, subject, actor and time, not body. Gmail consent/key/API is not configured or live-tested; `gmail.compose` is a restricted, broader-than-draft OAuth grant and may require Google verification. User must review/send in Gmail themselves.
- Compose no longer defines Redis, binds app HTTP to `127.0.0.1:8000`, publishes no database port, and uses PostgreSQL as Messenger transport. `compose.override.yaml` (which exposed PostgreSQL) was removed. App and worker have now been recreated with `docker compose up -d --force-recreate --remove-orphans app worker`; the old Redis container was removed while its named volume was retained. No volume deletion was done.
- Migrations `Version20261115054000` (memory/Gmail cleanup), `Version20261115105434` (initial AI provider settings) and `Version20261115140000` (multiple AI connections and active route) are applied to local dev and test DBs. Both schemas validate and have 12 migrations applied, 0 pending.
- Lockfile/package versions unchanged. Tests cover lead decision/memory, workspace isolation, domain normalization, Gmail suppression/finalization gates, encrypted provider tokens and AI keys, provider endpoint allowlisting, owner/admin access, multi-connection retention and active-route selection. A live local Ollama test passes; Gmail and Gemini remain untested.
- AI provider setup UI was redesigned as a specific three-step route flow: local/cloud choice, a live workspace-to-provider route preview with data disclosure, provider-dependent key/model/local endpoint guidance, inline save errors, saved-connection list with per-provider test and activation controls, and a separate test/verification panel. Responsive styles stack the verification panel below 820px and make the save action full-width on phones. Selecting an unsaved provider clears the previous model; selecting a saved provider restores that provider's model and local endpoint. Dedicated page styling is served via Asset Mapper. This pass has source/template/test verification but no authenticated visual browser review.
- Product UI was given a unified Apple-inspired system-interface treatment across authenticated screens: browser platform system font stack (`-apple-system` / BlinkMacSystemFont on Apple devices; native fallback elsewhere, with no bundled SF font), neutral surfaces, simpler type hierarchy, thinner dividers, restrained blue-accented actions, and coordinated dark styles. Header Appearance controls now persist system/light/dark mode, blue/indigo/teal/graphite accent, comfortable/compact spacing and expanded/compact desktop sidebar in browser local storage. The compact navigation preserves a mobile bottom bar; the appearance popover supports Escape, outside-click close, focus return and a reset action. Prior `prospectos-theme` choice is migrated. This update remains browser-unreviewed; local storage is per browser/device, not synced to a user account.
- Historical verification at the prior AI settings pass: the suite passed 52 tests / 273 assertions before real Ollama inference and multi-provider persistence were added. The current validation and inference state is in the provider connection pass at the top of this file. App is published only at `127.0.0.1:8000`; database has no host port. Gmail's absent credentials are handled as unconfigured. Upsun deployment remains unverified; `/mcp` stays edge-blocked until remote auth is implemented.
- Current git branch remains `main`; changes are local and uncommitted/unpushed.
- Initial provider-readiness snapshot before the connection pass: the database had zero encrypted AI provider keys and zero Gmail connections; AI was off. Local `.env` selected `AI_PROVIDER=disabled` and the `qwen3.5:4b` model, which failed inference due to memory limits. The existing `AI_API_KEY` was not printed or confirmed as a Gemini credential. The app/worker Compose override was later fixed to use the local secret already in ignored `.env`. No encrypted tokens were invalidated. Gmail-specific OAuth credentials, Resend API key/from address, and Cloudflare account/token remain missing. Gmail OAuth/draft is the only shipped email adapter; Resend and Cloudflare inbound are not implemented. Four unused `MAILOS_*` entries were removed from ignored `.env` and example/test env files; MailOS remains removed.
- Ollama model storage was moved from the original C: cache to `D:\OllamaModels`; the D: copy was verified and the duplicate source blob removed. `qwen3.5:4b` remains registered but failed inference with an out-of-memory error. The `qwen2.5:1.5b` partial later resumed and completed; host, app-container and isolated Symfony AI inference all passed. Ollama is running from the D: model store.

### Previous next task (superseded by the provider pass above)

Use authenticated browser access to finish provider onboarding. First resume/verify the Ollama small-model pull and run real inference from Docker via `host.docker.internal:11434/v1`. Then change AI provider storage to preserve multiple provider connections per workspace (the current single `AiProviderSetting` row means Gemini would replace Ollama), with explicit active provider and per-connection test status. Enter Gemini key only into the local admin UI, never chat or source files. Implement direct email connections: existing Gmail OAuth draft-only, Resend for explicitly approved single-recipient sends, and a Cloudflare Email Routing Worker/webhook inbound path; MailOS stays removed. Use browser access when available; if unavailable, finish code-side vertical slices and request only account details that cannot be inspected. Do not send prospect messages. Keep changes local until the owner asks to publish; Upsun remains separately blocked on account verification.

> **This is the canonical memory file for the repository.** Every Codex session must read it first and update it before ending. Do not rely on chat history as the only source of project context.

## Historical handoff before the 2026-11-15 redesign

The numbered product and journey sections below describe earlier intended/implemented scope. They are retained as historical notes; the current state above takes precedence where they conflict (especially inbox/mail, outreach, lead lifecycle and AI memory).

## 1. Product identity

- **Name:** ProspectOS
- **Owner:** 14 Technologies
- **Category:** Business research, evidence-backed prospect reports, configurable AI analysis and a safe email workflow.
- **Positioning:** Early-access business research workspace.
- **Product promise:** Find businesses worth understanding, inspect the evidence and decide what to do next.
- **Primary domain target:** `prospectos.14technologies.com` (configurable; do not assume DNS is already configured).
- **Hackathon:** Symfony AI & MCP Hackathon 2026.

ProspectOS currently supports research candidates, evidence inspection, explainable scores and optional AI analysis. Email inbox sync is implemented as a disconnected adapter/UI awaiting a safe MailOS read-only credential. CRM pipeline, outreach drafting, approvals, sending, replies and multi-channel communication are not shipped.

It is not merely a lead scraper, bulk email sender, chatbot or static dashboard.

## 2. Primary end-to-end user journey

1. User enters a natural-language business development/research objective.
2. ProspectOS plans and runs a research job asynchronously.
3. Search adapters discover candidate businesses.
4. Safe website inspection extracts public, observable signals.
5. Every important finding is stored with source URL, excerpt/value, timestamp and confidence.
6. A deterministic scoring service calculates an explainable 0–100 opportunity score.
7. An AI agent creates a prospect report grounded in stored evidence.
8. The application drafts personalised communication based on verified findings.
9. The user reviews and explicitly approves external outreach by default.
10. The selected official provider adapter sends the message, if permissions and policy allow it.
11. Provider webhooks/APIs synchronise supported replies and delivery events.
12. The prospect, conversation, activity timeline, campaign and analytics update.

## 3. Fixed architecture decisions

| Area | Decision |
|---|---|
| Backend | Symfony full-stack |
| PHP target | PHP 8.4 if compatible with pinned Symfony AI/MCP packages and Upsun runtime |
| Symfony framework | Prefer stable 8.1 where compatible; fallback to maintained 7.4 LTS if the prerelease packages require it; verify and record exact choice |
| UI | Twig + Symfony UX + Stimulus + Turbo |
| CSS | Tailwind CSS with central design tokens |
| Database | PostgreSQL + Doctrine ORM/Migrations |
| Background processing | Symfony Messenger with separate worker process |
| Queue | PostgreSQL/Doctrine as reliable initial transport; Redis transport when properly provisioned/tested |
| Cache/limits | Redis where available; don't make nonessential Redis a hard blocker |
| AI | Symfony AI Platform abstraction; AI disabled by default, explicit local/cloud provider selection |
| MCP | Official `mcp/sdk` through `symfony/mcp-bundle`, after checking exact compatibility |
| Integrations | OAuth, API credentials, webhooks, generic REST/OpenAPI, remote MCP and native adapters |
| Hosting | Upsun |
| Source / CI | Public GitHub monorepo + GitHub Actions |
| Mobile | Installable responsive PWA from same Symfony app |
| WordPress | Not the app framework; existing 14 Technologies WordPress website remains separate |
| Cloudflare Pages | Not required for the core app; Upsun hosts Symfony |

The Symfony AI and MCP packages are in the hackathon pre-release period. Pin compatible versions in `composer.lock`, preserve reproducible installation, and record versions here after implementation.

## 4. Required integrations and extensibility

The Integration Hub must make connections straightforward but must not pretend every provider accepts just an API key. Support multiple connection mechanisms:

- OAuth 2.0 (Authorization Code, PKCE where applicable, state validation, scopes, refresh/revoke).
- API credentials (Bearer, header API key, Basic, provider-specific fields, OAuth client credentials or HMAC where relevant).
- Inbound webhooks (verification/challenge, signatures, replay protection, idempotency, async handling and event logs).
- Outbound signed webhooks (event selection, HMAC, retries and delivery logs).
- Generic REST/OpenAPI connector (operation allowlist, schemas, field mapping, test requests and SSRF protection).
- Remote MCP client (discover tools/resources/prompts, allowlist selected capabilities, authenticate and log invocations).
- Native provider adapters.

Provider targets:

- Email: SMTP outbound; Gmail API; Microsoft Graph; optional IMAP/provider inbound events.
- WhatsApp: official WhatsApp Business Platform Cloud API.
- Facebook Pages/Messenger: official Graph API and only granted capabilities.
- Instagram Professional: official Meta APIs and only permitted operations.
- LinkedIn: authorised organisation publishing/data where approved; no scraping or unauthorised personal DMs.
- Telegram Bot API: only permitted bot conversations.
- Search: pluggable business/web search providers such as Brave Search, Tavily or SerpAPI.
- Productivity/CRM extensions: Google Sheets, Google Calendar, HubSpot, Pipedrive, Slack.
- AI: explicitly selected local Ollama or configured cloud provider; Hugging Face remains optional. Verify provider terms, data use, cost and quota before use.

Never claim a provider is live until a real operation is tested. Display connection state, granted scopes, supported capabilities and limitations honestly. Native integrations require provider permission/account prerequisites. Generic API/OpenAPI support is an extensibility layer, not a guarantee that every API can be automatically understood.

## 5. AI and scoring rules

- Use Symfony AI agent/platform components for actual model inference and tool calling.
- Do not assume hackathon credits, free-tier access, pricing or data handling. Verify current official provider terms before configuration and disclose what prospect evidence leaves ProspectOS.
- Track model/provider, run ID, latency and usage/cost where available.
- Keep prompt sizes and tool loops bounded.
- Use deterministic code for score calculation.
- Initial scoring weights: ICP fit 25%, observable opportunity 30%, evidence quality 20%, contactability 15%, intent/recency 10%.
- Unknowns must not silently count as negative evidence; show confidence, evidence coverage and freshness separately.
- AI claims must refer to evidence records; never invent contact details, facts, source URLs or excerpts.
- Treat web pages, emails, uploaded content and remote MCP responses as untrusted data and possible prompt injection.

## 6. MCP requirements

ProspectOS must be both:

- **MCP server:** expose curated, authenticated, workspace-scoped tools/resources to compatible external clients such as Claude Desktop.
- **MCP client:** connect to remote MCP servers, discover capabilities, allowlist tools and log invocations.

Suggested tools include search/get prospect, get evidence, analyse website, score prospect, create outreach draft, request approval, get approval status, create follow-up task, search conversations and get campaign metrics.

A sending tool must require a valid, unexpired human approval record bound to an immutable action payload. A model/client cannot approve its own action. No unauthenticated MCP endpoint, arbitrary SQL, filesystem, shell or generic unrestricted HTTP tool.

## 7. Visual/UI direction

Two supplied reference files are mandatory:

- `docs/reference/app-ui-ux-skill.md` — UX authority. Follow its one-screen/one-job/one-primary-action philosophy, progressive disclosure, mobile-first 390px baseline, spacing/radius/type scales, accessibility and interaction-state rules.
- `docs/reference/dashboard-visual-reference.png` — visual reference for pale powder-blue canvas, white cards, navy/cobalt charts, stacked horizontal bars, donut/ring chart, line trend, positive/negative diverging bars and compact ranked comparisons.

Starting estimated palette:

- Canvas `#E9F3FF`
- Surface `#FFFFFF`
- Muted surface `#F5F7FB`
- Ink `#17233D`
- Navy `#061C75`
- Primary cobalt `#001DBF`
- Secondary blue `#204DDE`
- Soft blue `#A9C5F5`
- Border `#DCE4F0`
- Negative `#E84B55`
- Positive `#23865A`
- Rare highlight `#D5A52B`

Centralise tokens and verify contrast. Default light theme. No decorative gradients/glass effects. Use chart colours consistently, label chart values, support accessible summaries and make data responsive.

## 8. Core modules

- Command Centre.
- AI Research Workspace.
- Prospects CRM and detail/evidence report.
- Campaigns and sequences.
- Unified Inbox.
- Social Studio (only supported publishing operations).
- Approvals.
- Tasks/follow-ups.
- Analytics.
- Integration Hub.
- AI Workbench/agent run logs.
- Settings/team/workspace.

Mobile PWA prioritises dashboard summary, inbox, prospect detail, approvals and tasks.

## 9. Security and outreach policy

- Workspace-scoped data access and role checks on every route/service/tool.
- Encrypt credentials at rest with key stored outside source control.
- Never re-display stored secrets or log credentials.
- OAuth state/PKCE as supported, strict redirect URI checks and token refresh/revoke.
- Webhook signature verification, idempotency, replay protection and event logs.
- SSRF protection for website inspection and generic API base URLs; block private/internal/metadata IPs, unsafe redirects, arbitrary ports and unsafe schemes.
- Outbound communications require approval by default, provider policy checks, sending limits, suppression/opt-out checks and audit logs.
- WhatsApp/Meta/LinkedIn constraints must be checked in official live documentation during implementation.
- No unofficial WhatsApp Web automation, social scraping, CAPTCHA bypass or unauthorised cold DMs.
- Global outbound pause/kill switch.

## 10. Hosting and operations

- Upsun hosts the Symfony app.
- GitHub is the public source and CI repository.
- PostgreSQL is the primary database.
- Dedicated worker for Messenger; use scheduler/cron only for scheduled tasks.
- Redis is optional depending on selected service/plan and should not be a production queue dependency unless persistence/availability is configured.
- Local Docker Compose setup.
- `.env.example` with placeholders only.
- Health check, safe migrations, logs, backups and deployment rollback notes.
- PWA uses offline cache only for shell/static non-sensitive assets; never cache secrets or private conversations by default.

## 11. Hackathon scope and deadline

Hackathon deliverables: public GitHub repository and 90-second real-product demo video. Submission deadline: 22 November 2026 at 18:00 CET / 22:00 Pakistan time.

Priority demo flow:

Research objective → real Symfony AI tool execution → evidence-backed prospect report → deterministic score → outreach draft → approval → safe/test send → activity history → MCP external client call.

Complete this vertical slice before expanding every named provider integration. Mark simulated records/actions clearly and keep live and demo analytics separate.

## 12. Continuity and current state

**Status as of 2026-11-14 (supersedes earlier status below):** The early-access foundation includes persisted business research, saved evidence, deterministic explainable scoring, provider-neutral AI configuration, workspace-scoped reports, disconnected MailOS inbox adapter/UI and an implemented-but-unconfigured Google OIDC sign-in/link flow. Owner setup is token-gated. Nothing is deployed or published. Git is on `main` with substantial staged, unstaged and untracked files; no commit or remote is configured. No AI/mail credentials, GitHub repository, Upsun project, production domain/DNS, or Google OAuth client is configured.

- **Framework/runtime:** PHP 8.4 in Docker; host PHP is 8.3 and should not run the project suite. Symfony 7.4.23; Symfony AI Bundle, Generic Platform and Hugging Face Platform are 1.0.0-BETA1; Symfony MCP Bundle and AI MCP Tool are 1.0.0-BETA1; `mcp/sdk` 0.9.0, Nyholm PSR-7 1.8.2 and Symfony HttpClient 7.4.23. These are the lockfile versions; AI/MCP packages are prerelease APIs.
- **Authentication:** `User`, `WorkspaceMembership`, and unique `GoogleIdentity` records support token-gated first-owner setup, email/password login, Google OIDC and workspace scoping. Google uses `google/apiclient` 2.20.1 with `google/auth` 1.55.3. OIDC requests only `openid email`, uses state/nonce, verifies signature/audience/issuer/expiry/verified email, identifies by `sub`, and requires an existing linked member. Linking requires login, CSRF and current-password reauthentication; matching email never auto-links. OAuth credentials are absent, so live Google login has not been tested. No public registration.
- **Additional packages:** Symfony RateLimiter 7.4.23; Nyholm PSR-7 1.8.2 supplies PSR-17 factories needed by the MCP Bundle's HTTP transport; BrowserKit 7.4.23 and CssSelector 7.4.22 are dev dependencies for functional tests.
- **Implemented UI:** Twig Command Centre, Research Workspace, prospect report, Integration Hub, inbox/thread, login/setup and workspace identity-link screens. Plus Jakarta Sans is bundled locally with its OFL license, inline SVG icons are used consistently, and central tokens establish larger type, focus visibility, stronger text contrast, mobile targets and reduced-motion support. The powder-blue/cobalt direction follows the supplied reference. A light/dark toggle is available in both authenticated and signed-out shells; it follows system preference until a browser-local choice is saved and applies before page styles load. Dashboard summary/activity are queried from PostgreSQL. The Command Centre's source panel has one primary **Start research** action that opens `/research`; search status identifies local SearXNG and its upstream limits. `docs/UI_AUDIT.md` records screen purpose, actions, states, mobile behavior and remaining review gaps. Manifest, local-static-only service worker, offline page, app icon and `/healthz` are present.
- **Data and asynchronous research:** Doctrine entities/migrations cover workspaces, research runs, prospects, evidence, score snapshots, users/memberships, Google identities, website host gates, summaries, and cached MailOS headers. Nine migrations are executed in dev/test, none pending. Research dispatches `ProcessResearchRun` through Doctrine Messenger `async`. Search mode returns source-linked, explicitly unverified candidates; direct URLs use the bounded robots-aware inspector. Demo records remain synthetic. `app:demo:seed` is idempotent and Compose-only; production configuration does not exist.
- **Scoring:** `OpportunityScorer` uses the prompt's 25/30/20/15/10 weights, renormalizes across known dimensions, exposes data coverage and rejects out-of-range dimensions. Unknown contactability/intent remain unknown rather than counting as zero.
- **Prospect report and AI:** `/prospects/{id}` is workspace-filtered and renders evidence and deterministic score/coverage. CSRF-protected report analysis uses the explicitly selected Symfony AI `PlatformInterface`, supports disabled/local Ollama/Gemini-compatible/Hugging Face/generic OpenAI-compatible configuration, has no silent fallback, validates evidence citations and records the selected provider/model. Summary and harmless provider-test requests share a 10/hour per-user limit. AI defaults to disabled. Qwen3.5:4b is installed in local Ollama but did not pass the exact harmless response test; no provider is verified in the app. No AI key is configured.
- **MCP:** Synthetic `demo-workspace-summary` is STDIO-only. Authenticated Streamable HTTP at `/mcp/workspace` exposes read-only `get_workspace_summary` and `get_prospect_report`; both resolve authenticated membership and prospect lookup enforces workspace. A real MCP initialize + `tools/call` was tested. The SDK default DNS-rebinding guard is active. Session-cookie auth is for local use; OAuth bearer auth, external client onboarding, invocation audit logs and all MCP write tools remain unimplemented.
- **Search/integrations:** Internal Compose SearXNG has successfully returned public search candidates; requests go to upstream engines. MailOS's actual JMAP API was inspected. The adapter requests at most 25 header-only messages and thread state; inbox `/inbox` and detail routes are workspace-scoped. It is disabled because current MailOS `jmap` API-key scope also enables submission, and no read-only scope/key or mailbox is configured. No send/draft/approval path exists. No external integrations are connected.
- **Website inspection:** `PublicUrlPolicy` permits HTTP/HTTPS on standard ports, rejects credentials and local/metadata names, rejects any DNS result outside PHP's global-address range, and pins requests to a validated IP. `RobotsTxtFetcher` checks the `ProspectOS-Research` rules before page fetch, safely follows up to five robots redirects, caps rules at 512 KB and fails closed on network/server errors, 401/403/429, malformed/oversized text and unsafe redirects; ordinary unavailable 4xx such as 404 are treated as no robots file under RFC 9309. `RobotsTxtPolicy` chooses the most specific matching user-agent and path, with `Allow` winning equal-length matches. Website redirects remain manual (max three), revalidated and checked against destination robots rules. `WebsiteInspector` allows HTML only, caps page bodies at 1 MB and the whole inspection at 20 seconds, and does not fetch subresources or execute scripts. `PostgreSqlHostAccessGate` uses a dedicated database session with PostgreSQL advisory locks (one concurrent inspection per hostname) and a persistent two-second minimum start interval shared by web/worker. Stale gate rows (>30 days) are removed during subsequent inspections. Live `https://example.com/` inspection passed its robots check and returned `Example Domain`. Robots is not authorization; users must verify site terms allow automated access before submitting a URL. This does not perform business verification.
- **Infrastructure:** Docker Desktop's existing WSL data disk was moved using its supported disk-location setting to `D:\Docker\DockerDesktopWSL`; verified `docker_data.vhdx` is present there and the former C: location is empty. Existing PostgreSQL volumes and records survived. `docker compose --progress plain build` succeeded for app and worker; `docker compose up -d --force-recreate` succeeded. App, PostgreSQL 16, Redis 7 and worker are running. Migration `Version20261113185415` is applied; database schema is in sync. Last check (2026-11-15): C: has about 0.05 GB free and D: about 16.53 GB free. Windows user `TEMP` and `TMP` now point to `D:\Temp\Mohsin`; 13 ProspectOS-related scan temp directories (~21 MB) were moved from C: to D:. The already-running Codex process still inherits C: temp values until restart; future shell commands can override them explicitly. Docker Desktop storage remains on D:.
- **AI/providers:** No AI, email, social or CRM credentials are configured. A direct Ollama CLI attempt did not produce the exact harmless response and was stopped; the ProspectOS test action is implemented but no configured provider passed it. MailOS was inspected but no account/API call was made. SearXNG is the only external discovery provider actually called successfully.
- **Quality checks (latest):** final `docker compose exec -T app composer test` — pass, 47 tests / 248 assertions (three full-suite passes during the UI session), including mocked OIDC state, linked sign-in, no email auto-merge, password reauthentication, duplicate subject, cancellation and unconfigured/failure paths. Final `lint:twig templates templates/macros` (13 templates), dev/test `doctrine:schema:validate`, `composer validate --no-check-publish`, `tailwind:build --minify`, Compose config and `git diff --check` — pass (Git emitted expected LF-to-CRLF notices). `lint:container` passed. Dev and test migration status — 9 executed, zero new. Earlier non-disclosing scan covered 165 staged/index, non-ignored worktree and archived files with 0 findings. Pinned Gitleaks 8.30.1 scanned ~51.24 MB and reported 3 generic-api-key matches: one random-looking 32-character `APP_SECRET` assignment in ignored `.scaffold/.env.dev`, and two vendor-source matches in Symfony password-validation code. Findings were triaged from a redacted report; no secret values were printed, and no matching file is tracked/staged. The local scaffold file was left untouched; this is not a public exposure. There are no commits for Git history scanning. Composer's container-side `composer diagnose --no-interaction` passed Packagist HTTPS/HTTP and GitHub rate-limit connectivity. Authenticated browser reviewed Command Centre, Research, Integrations and Inbox at 390px in both themes with no document-width overflow; desktop dashboard was also reviewed, and the new primary research action was followed successfully. `/login` redirects to the active owner session, so it was not logged out; no saved prospect exists in the workspace, so the populated report remains unreviewed visually. A signed-out setup page and complete accessibility audit remain outstanding. Latest clean-image build was canceled after 7m42s at `RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts`. A separate disposable Composer install probe showed intermittent GitHub `codeload` timeouts (`curl error 28`) that recover on retry, including repeated retries on the large Google API services archive. Direct archive GET returned HTTP 200 and anonymous API rate limit still had 54/60 requests; this points to intermittent/slow archive transfer rather than exhausted API quota. The 149-package probe did not finish before cancellation. No Dockerfile change was made. The app/database/Redis/SearXNG/worker services remain up; no volume command was run.
- **Deployment:** Not deployed. Public source is at [mohsin14tech/ProspectOS](https://github.com/mohsin14tech/ProspectOS), latest application configuration commit `5d9a852` is pushed to `main`, and the remote is configured. GitHub Actions run [43672342866](https://github.com/mohsin14tech/ProspectOS/actions/runs/43672342866) passed after the Upsun route correction: secret scans, Compose/Composer checks, clean image build, PostgreSQL readiness, all 9 dev/test migrations, demo seed, Messenger setup, 47 PHPUnit tests / 248 assertions, container/Twig lint, schema/migration validation, and production Tailwind build. The deployment files passed the official Upsun Fixed CLI 5.16.0 config validator. No Upsun project, hosted build, production secrets, domain, or deployment exists; account verification is pending support request `#553971`.
- **Upsun follow-up (2026-11-15):** Deployment YAML passed local parsing and the official Upsun Fixed CLI 5.16.0 `platform app:config-validate` check after removing the rejected `sticky` route property. Environment mapping was tested with fake values in the PHP 8.4 container; `docker compose config --quiet`, `sh -n .environment`, `git diff --check`, and `composer test` passed (47 tests / 248 assertions). The CLI validates schema only; hosted build and live deployment remain pending. Upsun account verification is blocked on support request `#553971` after Google sign-in; no project exists.
- **Latest CI checkpoint (2026-11-15):** commit `5d9a852` and run `43672342866` are green. Gitleaks scanned the exact staged publication tree (~1.42 MB) with zero findings. Local `composer test` passed (47 tests / 248 assertions); `docker compose config --quiet` and `platform app:config-validate` passed. Git worktree was clean immediately after the push; this handoff update is being recorded in the current docs commit.
- **Current limits:** AI remains disabled and no provider is verified. Search in the drafted Upsun configuration is explicitly set to demo mode. MailOS is disconnected pending a truly read-only scope/key; no draft/approval/send flow, email verification or password reset exists. Google OIDC is implemented but unconfigured and untested; branding/domain review is outstanding. `/setup` requires a random environment token and becomes unavailable after first-owner creation. Upsun account verification is pending ticket `#553971`; no Upsun project/deployment/domain exists. The deployment assumes one web instance; scale beyond one instance requires shared session storage. The Upsun CLI validator rejected the `sticky` route property, so it was removed from the draft. Migrations run in an Upsun deploy hook, but platform deploy-hook failures do not stop deployment, so activity logs must be reviewed. MCP is explicitly blocked at the Upsun router pending a suitable public authentication path.
- **Next tasks:** (1) receive/complete Upsun account verification; (2) create the project, connect the public GitHub repo and check any cost/plan choice before provisioning; (3) validate the configuration in an Upsun build and fix any runtime differences; (4) set the initial owner token safely and complete owner setup; (5) smoke-test HTTPS, health, database, worker and migrations before exposing real users; (6) decide/configure real search and AI providers securely; (7) configure Google OIDC and transactional email after domain/account setup; (8) complete populated-report and accessibility QA and prepare the demo.

At each work session, Codex must replace this paragraph with actual implementation status and retain a concise history. Record:

- Exact framework/package versions and why selected.
- Current branch/commit if available.
- Completed modules and important file paths.
- Database migration state.
- Tests executed and results.
- Deployment state and URL if verified.
- Connected live providers and the exact capabilities actually tested.
- Missing credentials / app-review prerequisites.
- Known bugs and limitations.
- Next 3–7 concrete tasks in priority order.

Do not state “deployed”, “connected”, “working” or “complete” unless verified. When a Codex session ends, update this file and `CODEX_PROGRESS.md`.

## 13. Canonical build brief

Read and execute `ProspectOS_CODEX_MASTER_BUILD_PROMPT.md`. `AGENTS.md` now points at this actual filename; the older filename was not present and the real specification has been preserved.
