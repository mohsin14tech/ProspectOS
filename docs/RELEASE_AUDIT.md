# Release readiness audit — 2026-11-15

**Verdict: local prototype is substantially functional; production deployment and hackathon submission are not ready.** The latest owner-approved slice is Gmail plus standard IMAP/SMTP. Do not describe these adapters as live-connected yet. No message was sent in this session. No release, commit, push or submission was made.

## Implemented and checked

| Area | Evidence | Remaining limit |
|---|---|---|
| Symfony foundation | Auth/workspace tests, running PostgreSQL/worker and HTTP app | Account invites, recovery and scale-out session storage absent |
| Research/evidence/scoring | Existing functional/security suite passes; prior real SearXNG and website checks recorded | Search results are unverified; deterministic fixtures remain labelled |
| Lead lifecycle/team memory | Persisted decisions, workspace isolation and retrieval tests pass | Exact category/location retrieval, no vector search or training |
| Symfony AI | Actual owner-workspace Ollama `qwen2.5:1.5b` provider test passed in 12,328 ms; active route verified | Agent planning/tool-execution demo and cloud/Hugging Face provider verification incomplete |
| MCP server | Existing authenticated workspace read tests pass; official SDK/bundle pinned | Session auth is local-only; no remote client, bearer auth or invocation audit ledger |
| Email | Gmail inbox/draft adapters, IMAP inbox, controlled SMTP approval flow, migration and safety tests; real Gmail IMAP error path exercised | One saved Gmail IMAP/SMTP connection failed authentication (`Invalid credentials`); remains unverified/paused. No real inbox read, draft or delivery yet |
| UI | Mailbox setup and inbox reviewed in browser; 390px and 1280px checks; Gmail preset and focus clearance verified | Populated provider inbox, full screen-reader/200% zoom/PWA-install review still needed |
| Packaging | Final app and worker Docker builds passed; 13 migrations applied from empty disposable DB plus Messenger setup; dev/test schemas valid | CI on the reviewed publication commit is still required |
| Security checks | 82 tests / 369 assertions; Composer audit clean; Gitleaks clean across 227 publication-candidate files and all 16 Git commits | Public-service SMTP total deadline, provider OAuth review and broader production audit still required |
| Hosting | Existing Upsun config and GitHub repository documented | Upsun verification ticket #553971 still pending per owner; no hosted deployment |

The full master specification includes many future modules. A completion percentage would hide material missing capabilities. The research/memory/local-AI foundation and basic email workflow are implemented; production integrations, the remaining AI/MCP demonstration and publication deliverables are separate unfinished milestones.

Closing runtime check: local `/healthz` returned HTTP 200; unauthenticated email/inbox and MCP workspace routes returned HTTP 302. PostgreSQL accepted connections, all 13 dev migrations were applied, and the async queue was empty. The database initially confirmed zero Gmail/IMAP connections, cached emails and test actions, and one active/tested Ollama route. After the owner saved Gmail, a real IMAP login test reached Gmail and failed with `AUTHENTICATIONFAILED`. The saved connection remains paused and unverified; SMTP was not reached and no email was read or sent.

## Defects fixed in this session

- Unmigrated Gmail inbox flag and missing mailbox/approval tables; applied `Version20261115160000` to dev and test.
- Broken legacy `EmailActionTest` constructor usage.
- Gmail draft creation rejected by the official client before API invocation because a refresh-only value was passed to `setAccessToken`; now refreshes correctly.
- Stale approvals after mailbox disconnect/reconnect; actions bind to the original connection ID and revision.
- SMTP anonymous acceptance could be reported as login verification; authenticated ESMTP is now required, including when the server falls back from EHLO.
- Missing `.env` Docker exclusion, which could retain runtime credentials in image layers. `.env` is excluded; build restarted, final image environment checked without printing values. No image was pushed.
- Sensitive mailbox pages lacked explicit no-store/referrer/frame headers; headers are now enforced.
- Mobile sticky Save button obscured a focused input; sticky positioning is disabled while editing fields and verified in the browser.

## Required before claiming a release

1. Connect an owner-authorized Gmail or standard IMAP/SMTP mailbox through the app. Test a real inbox read, error path and Gmail draft; approve and verify exactly one clearly labelled SMTP test to an owner-controlled address. Never infer delivery from SMTP acceptance alone.
2. Finish the master-spec AI/MCP slice: inspectable Symfony AI tool execution, compatible remote MCP connection/discovery, authenticated invocation logs, and approval enforcement for any new write tools. Keep `/mcp` blocked at the public edge until public authentication is implemented.
3. Complete the declared generic integration/webhook slice, or explicitly narrow the published MVP description. No generic connector, webhook ingestion, campaigns, bounce handling or social integrations are currently implemented.
4. Complete Upsun account verification, provision within an owner-approved budget, configure production secrets/domain/HTTPS and real providers, then smoke-test migrations, web and worker. Local Ollama's Docker-host address is not a production inference endpoint.
5. Review the final public source diff, run CI on the publication commit and validate a stranger's clone/start instructions. Do not publish local environment files, cached mail, screenshots with private data or logs.
6. Record the 90-second real-product video and submit via the owner's hackathon dashboard. The [official event page](https://symfony.com/hackathon), checked on 2026-11-15, confirms 22 November 2026 at 18:00 CET (22:00 Pakistan time), a public runnable repository and a 90-second video. It also calls for Hugging Face Inference Providers use; account coupon/token availability and an actual HF inference remain outstanding. No video or submission receipt exists.

## Deliberately deferred in this email slice

Microsoft Graph/OAuth, arbitrary SMTP relay credentials, multiple IMAP accounts per workspace, background/incremental sync, attachment handling, provider flag changes, automatic replies, general prospect sends, campaigns and delivery/opt-out webhooks. MailOS/Resend/Cloudflare were explicitly skipped. These are not represented by nonfunctional controls.

## Reproduce the checks

Use PHP 8.4 in Docker. Normal tests need no live credentials:

```text
docker compose exec -T app composer test
docker compose exec -T app composer audit --locked --no-interaction
docker compose exec -T app composer validate --no-check-publish
docker compose exec -T app php bin/console lint:container
docker compose exec -T app php bin/console lint:twig templates
docker compose exec -T app php bin/console doctrine:schema:validate
docker compose exec -T -e APP_ENV=test app php bin/console doctrine:schema:validate
docker compose exec -T app php bin/console tailwind:build --minify
node --check assets/controllers/mailbox_controller.js
docker compose config --quiet
git diff --check
```

Exact command results, failures and repairs are recorded in `CODEX_PROGRESS.md`. Read [MAIL.md](MAIL.md) for setup, capability boundaries and security controls.
