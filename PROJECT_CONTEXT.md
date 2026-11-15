# ProspectOS — Project Context / Session Handoff

> **This is the canonical memory file for the repository.** Every Codex session must read it first and update it before ending. Do not rely on chat history as the only source of project context.

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
- **Infrastructure:** Docker Desktop's existing WSL data disk was moved using its supported disk-location setting to `D:\Docker\DockerDesktopWSL`; verified `docker_data.vhdx` is present there and the former C: location is empty. Existing PostgreSQL volumes and records survived. `docker compose --progress plain build` succeeded for app and worker; `docker compose up -d --force-recreate` succeeded. App, PostgreSQL 16, Redis 7 and worker are running. Migration `Version20261113185415` is applied; database schema is in sync. Last check: C: has about 5.16 GB free and D: about 21.67 GB free.
- **AI/providers:** No AI, email, social or CRM credentials are configured. A direct Ollama CLI attempt did not produce the exact harmless response and was stopped; the ProspectOS test action is implemented but no configured provider passed it. MailOS was inspected but no account/API call was made. SearXNG is the only external discovery provider actually called successfully.
- **Quality checks (latest):** final `docker compose exec -T app composer test` — pass, 47 tests / 248 assertions (three full-suite passes during the UI session), including mocked OIDC state, linked sign-in, no email auto-merge, password reauthentication, duplicate subject, cancellation and unconfigured/failure paths. Final `lint:twig templates templates/macros` (13 templates), dev/test `doctrine:schema:validate`, `composer validate --no-check-publish`, `tailwind:build --minify`, Compose config and `git diff --check` — pass (Git emitted expected LF-to-CRLF notices). `lint:container` passed. Dev and test migration status — 9 executed, zero new. Earlier non-disclosing scan covered 165 staged/index, non-ignored worktree and archived files with 0 findings. Pinned Gitleaks 8.30.1 scanned ~51.24 MB and reported 3 generic-api-key matches: one random-looking 32-character `APP_SECRET` assignment in ignored `.scaffold/.env.dev`, and two vendor-source matches in Symfony password-validation code. Findings were triaged from a redacted report; no secret values were printed, and no matching file is tracked/staged. The local scaffold file was left untouched; this is not a public exposure. There are no commits for Git history scanning. Composer's container-side `composer diagnose --no-interaction` passed Packagist HTTPS/HTTP and GitHub rate-limit connectivity. Authenticated browser reviewed Command Centre, Research, Integrations and Inbox at 390px in both themes with no document-width overflow; desktop dashboard was also reviewed, and the new primary research action was followed successfully. `/login` redirects to the active owner session, so it was not logged out; no saved prospect exists in the workspace, so the populated report remains unreviewed visually. A signed-out setup page and complete accessibility audit remain outstanding. Latest clean-image build was canceled after 7m42s at `RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts`. A separate disposable Composer install probe showed intermittent GitHub `codeload` timeouts (`curl error 28`) that recover on retry, including repeated retries on the large Google API services archive. Direct archive GET returned HTTP 200 and anonymous API rate limit still had 54/60 requests; this points to intermittent/slow archive transfer rather than exhausted API quota. The 149-package probe did not finish before cancellation. No Dockerfile change was made. The app/database/Redis/SearXNG/worker services remain up; no volume command was run.
- **Deployment:** Not deployed. Public source is at [mohsin14tech/ProspectOS](https://github.com/mohsin14tech/ProspectOS), commit `a637e3b` is pushed to `main`, and the remote is configured. GitHub Actions run `43669837591` is green: both Gitleaks scans, Compose/Composer checks, clean production image build, PostgreSQL readiness, all 9 dev migrations and demo seed, Doctrine Messenger transport setup, isolated `app_test` creation and migrations, 47 PHPUnit tests / 248 assertions, container/Twig lint, schema/migration validation, and minified production Tailwind build all passed. Upsun remains unconfigured.
- **Current limits:** AI is opt-in but no provider is verified. Gemini free tier is limited and Google states free-tier content may be used to improve products; owner must decide whether to disclose data there and configure credentials securely. MailOS is disconnected pending a truly read-only mailbox API scope/key. No draft/approval/send flow, email verification or password reset exists. Google OIDC is implemented but unconfigured and not live-tested; Google branding/domain review remains owner/provider work. Registration is closed; `/setup` requires a random environment token and becomes unavailable after first owner creation. Upsun account/project, production configuration and deployment are absent. Website inspection is one page; upstream search has no production SLA. MCP uses local session auth and is not ready for public deployment.
- **Next tasks:** (1) create/access the Upsun account and project, connect the GitHub repository, and inspect its generated service/runtime configuration before production deployment; (2) configure database and worker plus environment secrets, custom domain and health checks, with a rollback plan; (3) choose/configure an AI provider securely and test real inference; (4) decide whether MailOS is deferred or a separate read-only scope/provider is obtained; (5) configure Google OIDC and transactional email only after owner accounts and domains are ready; (6) complete populated-report and accessibility QA and prepare the hackathon demo.

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
