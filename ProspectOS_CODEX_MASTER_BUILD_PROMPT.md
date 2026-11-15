# ProspectOS — Codex Master Build Prompt

> **Product:** ProspectOS  
> **Owner:** 14 Technologies  
> **Category:** AI business development intelligence, CRM, omnichannel engagement and automation  
> **Hackathon:** Symfony AI & MCP Hackathon 2026  
> **Primary deployment target:** Upsun  
> **Source control:** Public GitHub repository  
> **Mobile delivery:** Installable Progressive Web App (PWA)  
> **Prompt version:** 1.0 · 13 November 2026

---

## IMPORTANT: READ THIS BEFORE YOU START

You are Codex acting as the principal software architect, senior Symfony/PHP engineer, AI agent engineer, integration engineer, UX engineer, security engineer, test engineer and deployment engineer for this project.

Your task is to **build the actual working application**, not merely describe how it could be built. Inspect the repository first, make a plan, then implement in verified phases. Do not stop after generating a plan, wireframe, component library or mock dashboard.

### Mandatory project reference files

These files are part of the product specification. Read both completely before designing or coding the user interface:

1. `docs/reference/app-ui-ux-skill.md` — the authoritative UX and interaction rules.
2. `docs/reference/dashboard-visual-reference.png` — the visual reference for dashboard colour, chart treatment, data visualisation and general visual mood.

Do not replace, rewrite or silently disregard these references. If they conflict, follow the explicit product decisions in this prompt for product architecture, and follow the UX skill for interaction/layout rules. Treat the dashboard image as a visual reference, not as a screen to copy pixel-for-pixel.

### The project must remember itself between Codex sessions

Create and maintain these repository files from the first implementation phase:

- `AGENTS.md` — short, persistent instructions for future coding-agent sessions.
- `PROJECT_CONTEXT.md` — canonical product, architecture, decisions, current state and next actions. A new Codex session must be able to start by reading this file and understand the project without the original chat.
- `CODEX_PROGRESS.md` — append-only dated implementation log: completed work, tests run, failures, known issues and next actions.
- `docs/ARCHITECTURE.md` — system architecture and data flow.
- `docs/INTEGRATIONS.md` — connector framework, supported providers, capabilities, setup requirements and known restrictions.
- `docs/SECURITY.md` — threat model and security decisions.
- `docs/DEPLOYMENT.md` — local, staging and production deployment instructions.
- `docs/HACKATHON_DEMO.md` — deterministic demo walkthrough and 90-second video script.
- `docs/decisions/` — architecture decision records (ADRs) for important choices.

**Update `PROJECT_CONTEXT.md`, `CODEX_PROGRESS.md`, and relevant documentation after every substantial phase and at the end of every Codex session.** Never write that a feature is complete unless it is implemented and tested. Record partial implementation honestly. Preserve the previous decisions and history; do not reset the context file to a generic template.

If the project already contains files or implementation, inspect and preserve useful work. Do not overwrite working code blindly.

---

# 1. PRODUCT VISION

## 1.1 Name and positioning

**ProspectOS**  
**AI Business Development & Omnichannel Command Centre**  
**By 14 Technologies**

Product promise:

> Discover opportunities. Understand businesses. Build relationships. Grow intelligently.

ProspectOS is an AI-assisted business development operating system. It discovers businesses, researches their public digital presence, identifies evidence-backed commercial opportunities, qualifies prospects, manages the sales pipeline, prepares personalised communications, coordinates approved outreach across connected channels and measures what happens afterward.

It is not just a lead scraper, bulk email sender, chatbot or static CRM dashboard.

## 1.2 Core user journey

A user can enter a natural-language objective such as:

> Find 25 independent restaurants in Islamabad and Rawalpindi that may benefit from website development, online ordering or digital marketing. Research their public websites, identify verifiable opportunities, collect publicly available business contact details, score each opportunity, and prepare personalised outreach drafts for my review.

The application should:

1. Interpret the objective and build a research plan.
2. Search configured business/web discovery providers.
3. Discover and deduplicate candidate businesses.
4. Inspect public websites safely.
5. identify observable digital/business signals.
6. Store findings with source evidence and timestamps.
7. Calculate explainable opportunity scores using deterministic code.
8. Create a prospect intelligence report.
9. Identify contact information only from legitimate, documented sources.
10. Draft relevant outreach grounded in the prospect's evidence.
11. Select an eligible channel based on configured provider capabilities and permissions.
12. Request human approval before sending external communications by default.
13. Send only through a connected, authorised provider.
14. Ingest supported replies, delivery events and webhook events.
15. Update the prospect timeline, pipeline and analytics.
16. Recommend the next useful action.

The user must be able to complete the core workflow from one dashboard.

## 1.3 Product principles

- Evidence before conclusions.
- Human approval before consequential external actions.
- One prospect record across every channel.
- One activity timeline across the whole workspace.
- One connector architecture, not one-off integrations.
- AI reasons and recommends; deterministic services enforce business rules.
- All displayed metrics come from persisted events and records.
- Capability availability is determined by actual credentials, permissions and provider APIs.
- A failed or unsupported operation must be explained, never disguised as success.

---

# 2. HACKATHON REQUIREMENTS AND TECHNICAL PRIORITIES

The official hackathon brief is at `https://symfony.com/hackathon`.

The hackathon asks participants to use Symfony AI and the official PHP MCP SDK (`mcp/sdk`) to build something involving AI, agents, RAG and/or MCP servers and clients. Its deliverables are a **public repository that a stranger can clone and run** and a **90-second video demonstrating the real product**, before **22 November 2026 at 18:00 CET**.

The project must make genuine use of:

- Symfony AI Platform / Agent / AI Bundle as appropriate.
- The official PHP MCP SDK through the Symfony MCP Bundle where compatible.
- ProspectOS as both an MCP server and an MCP client, not just a product that mentions MCP.
- Hugging Face Inference Providers credits in actual application inference during development/demo.

The application should demonstrate a compelling end-to-end scenario:

**Research objective → agent tool execution → evidence-backed opportunity report → deterministic score → outreach draft → human approval → authorised provider action → event/activity history.**

Do not bury the Symfony AI and MCP work under large amounts of dashboard polish. It must be easy for judges to see what Symfony AI and MCP enable.

## 2.1 Version selection rule

Before installing dependencies, inspect the current official Symfony AI hackathon documentation, Composer package metadata, release tags and compatibility constraints.

As of this prompt's date, Symfony 8.1 is the current stable framework release, Symfony 8.2 is under development, and Symfony AI / MCP packages are in the hackathon pre-release period. Use **Symfony 8.1 on PHP 8.4 if the current Symfony AI beta packages support it cleanly**. If a dependency constraint makes that unsafe, use the latest compatible maintained Symfony 7.4 LTS release instead and record the reason in an ADR.

Use the hackathon-compatible Symfony AI 1.0 pre-release line where required. Pin compatible dependency versions in `composer.lock`. Never guess class names, package names, service IDs, attributes, configuration keys or method signatures. Consult the official documentation and installed package source before implementing.

Official technical starting points:

- `https://symfony.com/doc/current/ai/index.html`
- `https://symfony.com/doc/current/ai/components/agent.html`
- `https://symfony.com/doc/current/ai/components/platform.html`
- `https://symfony.com/doc/current/ai/components/platform/huggingface.html`
- `https://symfony.com/doc/current/ai/bundles/mcp-bundle.html`
- `https://symfony.com/doc/current/messenger.html`
- `https://github.com/modelcontextprotocol/php-sdk`
- `https://symfony.com/releases`

The hackathon packages are pre-release software. Expect APIs to change. When a dependency behaves unexpectedly, create a minimal reproduction, consult release notes/source, write a regression test and record the issue rather than patching around it blindly.

---

# 3. FINAL ARCHITECTURE DECISIONS

These are the agreed baseline decisions. Do not reopen them without a technical reason that is documented in an ADR.

| Area | Decision |
|---|---|
| Backend / application framework | Symfony full-stack |
| PHP | PHP 8.4 target, subject to dependency/host verification |
| UI | Twig + Symfony UX + Stimulus + Turbo |
| CSS / design system | Tailwind CSS, tokens derived from supplied references |
| Charts | Chart.js or another accessible, lightweight chart library; select one and document |
| Database | PostgreSQL |
| ORM / migrations | Doctrine ORM + Doctrine Migrations |
| Background processing | Symfony Messenger with a dedicated worker process |
| Queue transport | Prefer reliable PostgreSQL/Doctrine transport initially; use Redis transport when properly provisioned and verified |
| Cache / rate limits / locks | Redis where available; provide graceful database-backed fallback where sensible |
| AI | Symfony AI + Hugging Face Inference Providers, with provider abstraction |
| MCP | Symfony MCP Bundle + official `mcp/sdk`, if current compatible versions resolve |
| Search | Provider-based business/web search adapters; no hard-coded search provider |
| Integrations | OAuth + API credentials + webhooks + generic REST/OpenAPI + remote MCP |
| Main hosting | Upsun |
| Source control / CI | Public GitHub repository + GitHub Actions |
| Domain target | `prospectos.14technologies.com` (configurable) |
| Mobile | Responsive installable PWA from the same Symfony application |
| Existing WordPress site | Keep `14technologies.com` untouched; WordPress is not the application framework |

## 3.1 Deployment shape

Use one Symfony full-stack application for the MVP. Do not introduce a separate React application or Cloudflare Pages requirement for this hackathon unless a concrete technical need emerges.

Production topology:

```text
Browser / Installed PWA
          |
          | HTTPS
          v
prospectos.14technologies.com
          |
          v
Symfony application (Upsun web container)
   |          |            |
   |          |            +---- Official MCP HTTP endpoint
   |          +---------------- OAuth callbacks / Webhooks
   +--------------------------- HTML / Turbo / JSON endpoints
          |
          +---- PostgreSQL
          +---- Redis (optional cache / rate-limit / queue service)
          +---- Symfony Messenger worker
          +---- Scheduled tasks / Symfony Scheduler or Upsun cron
          +---- External providers (AI, search, email, Meta, etc.)
```

Local development must use Docker Compose with PHP, PostgreSQL, Redis (optional if database fallback is selected), and a worker. Provide a one-command or very short setup path.

## 3.2 Upsun

Use Upsun as the main hackathon deployment target because it supports PHP/Symfony applications, PostgreSQL, Redis and dedicated background workers. Use the current Upsun project configuration format documented at deployment time. Do not assume the legacy `.platform.app.yaml` format if the current project uses `.upsun/config.yaml`.

Create and maintain:

- Upsun app configuration.
- PostgreSQL service and relationship.
- Redis service only if needed and supported by the selected plan/configuration.
- Worker instance for Messenger consumers.
- Cron/scheduled task configuration only for scheduled tasks; do not use cron instead of a continuously running worker.
- Build, deploy and post-deploy hooks.
- Health-check endpoint.
- Environment variable documentation.
- Database migration procedure.

Read current Upsun documentation before writing production config:

- `https://upsun.com/symfony-paas/`
- `https://upsun.com/php-paas/`
- `https://docs.upsun.com/create-apps/workers.html`
- `https://docs.upsun.com/add-services/postgresql.html`
- `https://docs.upsun.com/add-services/redis.html`

The free trial is temporary and must not be presented as permanent free hosting. Document what happens when the trial expires and how to monitor/limit provisioned resources.

## 3.3 Why not WordPress for the core

Do not build this as a WordPress plugin, theme or custom WordPress application. The application is being built for a Symfony AI + MCP hackathon. WordPress may be integrated later through its REST API, but it must remain separate from the core runtime.

## 3.4 Why not Cloudflare Pages for the backend

Cloudflare Pages is not a conventional PHP/Symfony runtime. The primary Symfony application runs on Upsun. Cloudflare may optionally be used later for DNS, proxying, caching of public static assets or other compatible services, but it is not required for the MVP.

---

# 4. DESIGN SYSTEM — USE THE TWO PROVIDED REFERENCES

## 4.1 UX authority

Read `docs/reference/app-ui-ux-skill.md` in full. Apply its rules to every screen, component, flow and responsive breakpoint.

Important rules from that reference include:

- One screen, one job, one primary action.
- Progressive disclosure; do not show every feature at once.
- Mobile design baseline: 390px.
- Desktop dashboard content max width: 1120px; dense tables may use up to 1280px.
- Spacing scale only: 4, 8, 12, 16, 20, 24, 32, 48, 64px.
- Web side padding 24px; mobile side padding 16–20px.
- Card padding 16px (20px for a large card).
- Button and input radius 12px; card radius 16px; chips fully rounded.
- Prefer one visual elevation method per screen: border OR shadow, not both. Default to a thin border.
- No decorative gradients, blobs, floating ornaments or unnecessary background effects.
- Maximum two font families and restrained type weights.
- Primary touch targets at least 48px; all touch targets at least 44px.
- Motion should communicate state, not decorate.
- Design default, selected, disabled, loading, empty, error and success states.
- Respect keyboard navigation, screen readers, reduced motion and 200% zoom.

Do not dump every metric, chart and control onto the Command Centre. The UX skill's progressive-disclosure rule takes precedence over a desire to display every possible datum.

## 4.2 Dashboard image reference

Study `docs/reference/dashboard-visual-reference.png`.

Use it as the reference for:

- Cool, pale blue application canvas.
- White or near-white content surfaces.
- Strong navy/cobalt blue as the primary data-visualisation colour.
- Restrained light-blue secondary series.
- Red for negative values / declines in diverging charts.
- Small amber/gold highlight only where useful.
- Clean chart labels and subtle separators.
- Compact horizontal segmented bars.
- Donut/ring charts.
- Small line / area trend charts.
- Horizontal comparison bars.
- Vertical positive/negative diverging bars with a clear zero baseline.
- Compact ranked data tables with red/blue inline comparison bars.

Suggested starting tokens sampled/estimated from the image (treat as initial values, verify visually, centralise in CSS variables):

| Token | Starting value | Purpose |
|---|---|---|
| `--app-canvas` | `#E9F3FF` | Pale cool-blue overall background |
| `--surface` | `#FFFFFF` | Main cards and panels |
| `--surface-muted` | `#F5F7FB` | Secondary panel / subtle grouping |
| `--ink` | `#17233D` | Main text |
| `--navy` | `#061C75` | Navigation, deep chart segments |
| `--blue-primary` | `#001DBF` | Primary cobalt / key data series |
| `--blue-secondary` | `#204DDE` | Secondary blue series |
| `--blue-soft` | `#A9C5F5` | Lighter comparison series |
| `--line` | `#DCE4F0` | Borders and chart gridlines |
| `--negative` | `#E84B55` | Negative / declining values |
| `--positive` | `#23865A` | Positive status and success |
| `--highlight` | `#D5A52B` | Rare highlight / small chart segment |

These values are design starting points, not a requirement to reproduce every pixel. Verify text and chart contrast. Use a colour-blind-friendly distinction and labels/patterns in addition to colour. Do not communicate status by colour alone.

## 4.3 Visual language

The intended product should feel like a calm, credible business intelligence application, not a neon AI dashboard.

- Light theme is the default, based on the blue/white reference.
- A dark theme may be added later only if it can be implemented consistently without delaying the MVP.
- Use a restrained navy/cobalt sidebar or navigation accent.
- White cards sit on the pale-blue canvas.
- Use whitespace and grouping instead of nested bordered cards.
- Avoid gradients, glassmorphism, excessive shadows, 3D visuals and decoration.
- Avoid tiny unreadable chart labels.
- Do not reproduce the Japanese placeholder text shown in the reference image. Use real product terminology and English UI copy.

## 4.4 Chart standards

Build a reusable chart component layer. Charts must use real API/database data or clearly marked demo fixtures.

Provide these chart patterns where meaningful:

1. KPI hero value + compact trend indicator.
2. Donut chart for channel mix / pipeline distribution.
3. Horizontal stacked segments for channel or campaign composition.
4. Line chart for discovery and outreach trends over time.
5. Positive/negative vertical bar chart for period-over-period results, with a visible zero baseline.
6. Ranked horizontal bars for prospect/service opportunity comparisons.
7. Compact table with inline diverging bars for channel or campaign comparisons.

Do not add charts merely to fill space. Every chart must answer a business question and have a text summary/accessibility label. Include loading, empty and error states. Tooltips must be usable on touch screens.

---

# 5. APPLICATION NAVIGATION AND SCREENS

Create a consistent desktop sidebar and a simplified mobile bottom navigation with no more than five primary destinations. Use secondary navigation or a More menu for the rest.

Required modules:

1. Command Centre
2. Research
3. Prospects
4. Campaigns
5. Unified Inbox
6. Social Studio
7. Approvals
8. Tasks / Follow-ups
9. Analytics
10. Integrations
11. AI Workbench / Agent Runs
12. Settings / Team

## 5.1 Command Centre — `/dashboard`

The dashboard should answer: **What is happening in business development, and what needs attention now?**

Show one primary hero metric (e.g. qualified opportunities this period) and no more than three secondary metrics in the first visual group. Use progressive disclosure for the rest.

Potential dashboard modules:

- Qualified prospects.
- Outreach activity.
- Reply / positive-reply counts.
- Meetings booked.
- Pipeline value only when real deal values exist.
- Channel mix donut.
- Campaign delivery / reply trend.
- Discovery trend.
- Priority opportunities.
- Recent activity timeline.
- Follow-ups due.
- Pending approvals.
- Integration health.
- Agent run status.

Add filters for date range, workspace, owner and campaign where relevant.

### Metric definitions

Every KPI must have a documented query/definition. Distinguish:

- Drafted.
- Approved.
- Queued.
- Accepted by provider.
- Sent.
- Delivered (only if provider confirms).
- Bounced / failed.
- Read (only if the channel provides a reliable event).
- Replied.
- Positive reply (AI suggestion, user-verifiable).
- Meeting booked (recorded event, not inferred from a sent message).

Never use “sent” to mean “delivered”. Never make up conversion or revenue values.

## 5.2 Research Workspace — `/research`

Natural-language research objective input plus structured optional filters:

- Industry / vertical.
- Country, city and region.
- Business type / size.
- Number of prospects.
- Website required / optional.
- Services offered by the user's company.
- Observable opportunity types.
- Minimum score.
- Search provider.

Allow saved research profiles and repeatable runs.

Run research asynchronously through Messenger. Show progress events such as:

- Planning the search.
- Discovering candidates.
- Deduplicating businesses.
- Inspecting public websites.
- Extracting signals.
- Saving evidence.
- Scoring prospects.
- Preparing reports.
- Completed / partially completed / failed.

Support cancellation, retries, partial results and error explanations. Save every run with provider/model, query, timestamps, result counts, tool calls and status.

## 5.3 Prospects — `/prospects`

Functional CRM list with:

- Search, filters, sorting and pagination.
- Saved views.
- Bulk selection and safe bulk actions.
- CSV import/export.
- Duplicate detection.
- Tags and assignment.
- Status and next-action fields.
- Source and last-verified timestamp.

Suggested columns:

- Business.
- Industry.
- Location.
- Website.
- Primary known contact.
- Email / phone where available.
- Opportunity score.
- Confidence / evidence coverage.
- Status.
- Owner.
- Last activity.
- Next follow-up.

Lead statuses: New, Researching, Qualified, Contact Identified, Ready for Outreach, Contacted, Replied, Interested, Meeting Scheduled, Proposal Sent, Won, Lost, Nurture, Suppressed.

## 5.4 Prospect intelligence profile — `/prospects/{id}`

Include:

- Business overview and public company details.
- Website and available public social URLs.
- Public contact methods and provenance.
- Opportunity score and score breakdown.
- Evidence coverage and confidence.
- Digital presence observations.
- Identified business needs.
- Suggested 14 Technologies service(s).
- Recommended approach.
- Source links and supporting excerpts.
- Last checked / freshness.
- Contact preferences, lawful basis/consent records where applicable, opt-out/suppression status.
- Complete activity / conversation timeline.
- Notes, owner and follow-up tasks.

Actions:

- Research again.
- Analyse website.
- Recalculate score.
- Generate report.
- Draft email.
- Draft WhatsApp template/message where eligible.
- Add to campaign.
- Create task.
- Assign.
- Change status.
- Suppress / unsubscribe.

## 5.5 Campaigns — `/campaigns`

Create a campaign sequence builder for supported and eligible channels.

A campaign can define:

- Target segment or saved view.
- Goal and service offer.
- Channel(s).
- Message templates.
- Personalisation fields.
- Schedule / send window and timezone.
- Per-channel rate limits.
- Delay between steps.
- Stop conditions.
- Approval mode.
- Campaign owner.

MVP sequence types:

- One-off approved email campaign.
- Email follow-up sequence.
- WhatsApp template campaign only for opted-in/eligible contacts and approved templates.
- Task-based LinkedIn/social follow-up rather than unsupported automated DMs.

Stop a sequence when a reply is received (configurable), a prospect opts out, an address bounces, a number is invalid, a prospect is suppressed, or an operator pauses the campaign.

Include dry-run preview and recipient eligibility summary before activation.

## 5.6 Unified Inbox — `/inbox`

Consolidate conversations from channels that genuinely support inbound synchronisation under the connected account's permissions.

Desktop layout:

- Conversation list.
- Active thread.
- Context panel with prospect details, score, notes, owner and history.

Filters:

- All.
- Unread.
- Assigned to me.
- Email.
- WhatsApp.
- Instagram (when authorised and implemented).
- Facebook/Messenger (when authorised and implemented).
- Needs reply.
- Waiting.
- Closed.

Conversation features:

- Reply through the correct provider adapter.
- Show provider/channel and delivery states.
- Assign conversation.
- Add note.
- Create follow-up.
- Mark unread / resolved.
- Link to prospect.
- Show thread and message chronology.

Do not claim SMTP can receive messages. SMTP is outbound-only; inbound email requires IMAP or a provider's inbound webhook/API (e.g. Gmail/Graph subscriptions or a configured email service inbound route). Declare channel capabilities honestly.

## 5.7 Social Studio — `/social`

Create a simple content workspace:

- Connected social accounts.
- Content drafts.
- Asset attachments.
- Platform-specific preview.
- Publishing calendar.
- Approval state.
- Publishing result and provider ID.
- Engagement data only when the API exposes it.

Implement only official, permitted API operations. Unsupported operations must show “Not available with current permissions” and a route to the provider's setup instructions. Do not fake post publishing or engagement metrics.

## 5.8 Approvals — `/approvals`

Central queue for actions requiring explicit human permission:

- External email sends.
- WhatsApp sends.
- Social posts.
- Bulk campaign activation.
- Destructive bulk operations.
- High-risk MCP tool actions.
- Generic API operations classified as write/destructive.

Show exact target, channel, content, selected sender, policy checks, evidence and reason. Approval must be bound to an immutable action payload/hash and expire after a configurable period. Any edit after approval invalidates the prior approval.

## 5.9 Tasks / Follow-ups — `/tasks`

Task list, due dates, assignments, reminders, completion status and links to prospects/campaigns/conversations. Support today, upcoming, overdue and completed views.

## 5.10 Analytics — `/analytics`

Use actual events. Show discovery funnel, qualification rate, approval rate, channel sends, delivery (when known), reply, positive reply, meetings and outcomes. Provide date filters, channel comparisons and CSV export.

## 5.11 Integrations — `/integrations`

Build the Integration Hub described in Section 7. This is a first-class product module, not a developer-only settings page.

## 5.12 AI Workbench — `/ai`

Show:

- Configured AI providers/models.
- Test connection.
- Default model and task-specific model selection.
- Agent runs.
- Tool invocations.
- Usage/tokens/cost if returned by the provider.
- Failures and retries.
- Prompt/configuration version.
- Model limitations.

Never expose API tokens in HTML, JSON responses, logs or browser storage.

## 5.13 Settings / Team — `/settings`

Workspace, user profile, team members, roles, API credentials, security, timezone, locale, notification preferences, sending policies and retention settings.

Implement at least owner/admin and member roles. Scope all business data by workspace from day one, even if the initial demo uses one workspace.

---

# 6. PROSPECT DISCOVERY, WEBSITE ANALYSIS AND EVIDENCE

## 6.1 Provider-based discovery

Create interfaces for:

- Business search.
- General web search.
- Website fetch/extraction.
- Contact discovery.
- Optional SEO/performance enrichment.

Potential search providers include Brave Search, Tavily and SerpAPI. Do not make one provider mandatory. Each provider adapter must implement only the operations it actually supports.

A provider connection is configured in Integration Hub. The application must provide a test-connection action and a useful error if the key is missing, invalid, out of quota or lacks permission.

If the provider is not configured, the user may import CSV data or use clearly labelled synthetic demo data. Do not display synthetic data as researched real prospects.

## 6.2 Website inspection

Inspect public pages only. Extract observable signals such as:

- HTTPS availability.
- Page title and metadata.
- Mobile viewport declaration.
- Page structure and navigation.
- Visible contact methods.
- Booking/reservation links.
- Ordering/shop links.
- Menu/download links.
- Social links.
- Clear calls to action.
- Basic performance metrics when a configured source such as PageSpeed Insights is available.

Do not conclude that a feature does not exist merely because a crawler failed to find it. Use calibrated language:

- Verified.
- Strong indication.
- Observed.
- Not detected in pages examined.
- Unknown / unable to verify.

Never fabricate website issues, business facts, email addresses, phone numbers, decision-maker names, source URLs or quotes.

## 6.3 Evidence schema

Each finding stores:

- Workspace ID.
- Prospect ID.
- Finding type.
- Claim / observation.
- Source URL.
- Source title.
- Supporting excerpt or measured value.
- Retrieval timestamp.
- Retrieval provider / method.
- Confidence.
- Verification state.
- Freshness / expiry policy.
- Agent run ID.

Every AI-generated conclusion should reference evidence IDs. Let users open the source. Distinguish model interpretation from direct observation.

## 6.4 Safe crawling / SSRF defence

Treat website URLs as hostile input. Implement:

- URL parsing and scheme allowlisting (HTTP/HTTPS only).
- DNS resolution checks.
- Block localhost, loopback, link-local, private and reserved IP ranges, IPv4 and IPv6.
- Block cloud metadata IPs/hostnames.
- Re-check redirects and resolved IPs at every hop.
- Disallow arbitrary ports unless explicitly permitted.
- Strict connection/read timeouts.
- Maximum response size and content type allowlist.
- Maximum redirect count.
- Per-host rate limit and concurrency limit.
- No execution of scripts, downloaded binaries or browser extensions.
- No authentication bypass, CAPTCHA bypass or access-control circumvention.
- Robots/terms-aware behaviour and conservative crawl limits.

External web page content is untrusted. It may contain prompt-injection instructions. Store it as quoted source data; never let it override agent system instructions or authorise tools.

---

# 7. UNIVERSAL INTEGRATION HUB — CORE ARCHITECTURE

This is one of the most important requirements.

The user wants integrations to be as easy as entering API credentials, configuring a webhook, authorising an OAuth connection or connecting an MCP server. Build a consistent setup experience around these methods.

**Do not promise that every service can be connected with only an API key.** Some providers require OAuth application registration, permission approval, account eligibility, verified business assets, webhook subscription or provider review. Make the setup flow simple, but be truthful about provider prerequisites.

## 7.1 Integration connection methods

Support these connection types through a shared Integration Hub:

### A. OAuth 2.0

Support provider-specific OAuth flows where the external provider supports them:

- Authorization Code.
- PKCE where supported/required.
- `state` validation.
- Redirect URI validation.
- Least-privilege scopes.
- Refresh token handling.
- Token expiry and reauthorization.
- Revocation/disconnect where supported.
- Per-workspace account connection.

Application-level OAuth client ID/secret are deployment-level secrets. Individual account tokens are stored encrypted per workspace/provider connection.

### B. API credentials

Provide a secure setup form supporting the auth patterns required by a connector:

- Bearer token.
- Header API key.
- Query API key only where provider requires it (warn against exposing query secrets in logs/URLs).
- Basic authentication.
- OAuth client credentials where applicable.
- HMAC-signed requests.
- Provider-specific credential fields and account IDs.

Credentials are write-only after save: never return plaintext secrets to the browser. Provide replace/test/revoke controls.

### C. Webhooks

Support both inbound and outbound webhooks.

Inbound:

- Generate a unique URL per connection/subscription.
- Generate and display a signing secret once.
- Support provider challenge/verification handshakes.
- Verify raw-body signatures before processing.
- Validate timestamps/replay windows when provider supports them.
- Rate-limit requests.
- Store event ID and idempotency key.
- Reject duplicates safely.
- Persist webhook event receipt, processing status and errors.
- Process event asynchronously.
- Provide a test event and event log.

Outbound:

- Allow workspace admins to configure destination URL, selected events and signing secret.
- Sign outgoing payloads (HMAC-SHA256 or provider-compatible signature).
- Retry transient failures with bounded exponential backoff.
- Store attempt history, response status and next retry.
- Provide disable/pause and test event.

If the provider can register webhooks through its API, automate registration. If not, show a copyable callback URL, verification token and exact provider dashboard instructions. Never claim automatic subscription when it is manual.

### D. Generic REST / OpenAPI connector

Allow the workspace admin to add an external REST API using:

- Base URL.
- Authentication method.
- API key/token/credentials.
- Optional custom headers.
- Optional OpenAPI JSON/YAML URL or uploaded spec.
- Allowed endpoints/operations selected by the admin.
- Request/response mapping into supported ProspectOS entities.
- Test request.

Support common JSON REST patterns, pagination, timeouts, rate limits, error handling and idempotency where possible.

Do not automatically expose every imported OpenAPI operation as an unrestricted AI tool. Require an admin to review/allow operations, classify them as read/write/destructive, define scopes and map their schemas. Read-only operations can be available to an agent when scoped. Write/destructive operations must pass the approval system.

Enforce SSRF protections on base URLs and all request destinations. Require HTTPS in production. Block private/internal IPs and cloud metadata destinations. Do not allow arbitrary header injection, local file access, shell execution or unrestricted URL redirects.

### E. Remote MCP connector

Allow users/admins to configure a remote MCP server where its supported transport and authentication are compatible with the current official PHP MCP SDK/Symfony MCP Bundle.

Setup fields may include:

- Connection name.
- Server endpoint.
- Transport (only those truly supported by the resolved SDK/bundle version).
- Authentication mode and credential.
- Allowed tools/resources/prompts.
- Workspace scope.
- Tool risk category.
- Connection timeout.

The application must be able to discover available capabilities, show schemas/descriptions, test the connection, selectively enable tools and log every invocation.

For STDIO, never let a normal workspace user submit arbitrary shell commands. Restrict process/command configuration to trusted administrator-controlled deployment settings. Remote connections must not become a remote shell.

### F. Native provider connectors

Native adapters should use the provider's official API and implement provider-specific authentication, capability discovery, webhook handling, pagination, rate limits and error mapping.

## 7.2 Connector architecture

Implement a connector registry with typed contracts. Example design (adapt names to Symfony conventions):

```php
interface ConnectorInterface
{
    public function definition(): ConnectorDefinition;
    public function validateConfiguration(array $configuration): ValidationResult;
    public function testConnection(ConnectionContext $context): ConnectionTestResult;
    public function capabilities(ConnectionContext $context): ConnectorCapabilities;
}
```

Use separate contracts/interfaces for operations that do not apply to every connector, such as:

- `MessageSenderInterface`
- `MessageReceiverInterface`
- `ContentPublisherInterface`
- `WebhookSubscriberInterface`
- `WebhookReceiverInterface`
- `RestOperationExecutorInterface`
- `McpRemoteClientInterface`
- `SearchProviderInterface`
- `WebsiteInspectorInterface`

Do not force every integration to implement every interface. Use capability objects and optional interfaces.

A connector definition/manifest should contain:

- Stable connector ID and version.
- Display name and category.
- Short description and icon key.
- Documentation URL.
- Authentication types.
- Credential/configuration schema.
- OAuth metadata where relevant.
- Required scopes/permissions.
- Setup instructions.
- Test-connection method.
- Available capabilities.
- Webhook requirements.
- Supported operations.
- Known limitations.
- Risk level for each operation.

Store connection instances separately from connector definitions. Multiple workspaces may connect multiple accounts of the same provider.

## 7.3 Secret handling

- Encrypt tokens and secrets at rest using a strong application encryption key and authenticated encryption (e.g. libsodium secretbox or an equivalent reviewed mechanism).
- Store encryption key in deployment secrets, not the database or repository.
- Use key versioning to support rotation.
- Never log secrets, authorization headers, webhook signatures or token responses.
- Redact sensitive values in exception messages and traces.
- Do not put secrets in frontend variables or local storage.
- Use server-side secret retrieval only.
- Restrict who can view connection metadata or replace credentials.
- Provide disconnect/revoke and token rotation paths.

## 7.4 Connection health and capability truthfulness

Each connection should report:

- Not connected.
- Setup required.
- Awaiting authorisation.
- Connected.
- Connected with limited permissions.
- Reauthorization required.
- Provider error.
- Disabled.
- Demo/simulated mode (visibly labelled).

Track last successful health check and last successful sync.

A feature is enabled only when its adapter and granted permissions support it. For example, an SMTP connection supports outbound email, not inbound inbox sync unless a separate inbound protocol/provider is configured.

---

# 8. NATIVE CONNECTOR PRIORITIES AND PLATFORM LIMITS

Implement the connector framework first. Then complete only the high-value native integrations that can be properly tested within the hackathon timeframe.

## Priority 1 — real MVP

### 8.1 Email

Provide:

- SMTP outbound connector.
- Gmail API connector (OAuth; minimum necessary permissions).
- Microsoft Graph Outlook connector (OAuth; minimum necessary delegated permissions).
- Optional IMAP/inbound connector where appropriate.
- Provider inbound webhook/subscription support where available.

Features:

- Sender identity.
- Compose/save draft.
- HTML and plaintext.
- Personalisation variables.
- Attachments only with safe type/size limits.
- Send approved email.
- Reply threading where provider supports it.
- Inbound sync where provider supports it.
- Bounce/unsubscribe/suppression handling.
- Provider message ID and delivery events.
- Test connection / test send to the account owner.

Do not ask users for Gmail or Microsoft account passwords. Use OAuth. Document provider consent-screen/app verification requirements and exact scopes based on current official docs.

### 8.2 WhatsApp Business Platform Cloud API

Use only the official Meta WhatsApp Business Platform Cloud API.

Support where the connected account is eligible and permissioned:

- Business account and phone number configuration.
- Access token and phone number/account identifiers.
- Webhook challenge/verification.
- Incoming messages.
- Outgoing text and supported media.
- Approved message templates and variables.
- Delivery/read/failure status events where exposed.
- Conversation history.
- Opt-in and opt-out records.
- Test connection and test event.

Enforce current Meta/WhatsApp rules. Business-initiated messages generally require valid opt-in and approved templates outside the applicable customer-service window. Verify exact live rules from official Meta documentation at implementation time.

Do not use WhatsApp Web scraping, session-cookie extraction, QR automation, unofficial libraries or automated browser clicks.

### 8.3 Search / website research

At least one provider adapter must work end-to-end with a real API key. Keep additional providers pluggable. Include a manual CSV import fallback and synthetic demo mode.

### 8.4 Hugging Face Inference Providers

Use the official Symfony AI Hugging Face platform bridge if compatible. Route actual inference requests through Hugging Face Inference Providers so the hackathon coupon can apply. Do not substitute a direct custom provider key if the intention is to consume the HF-sponsored credits.

## Priority 2 — scaffold and implement when access permits

### 8.5 Meta Facebook Pages / Messenger

Use official Graph API operations only. Support connected Page metadata, authorised publishing, permitted conversations and webhook events where granted. Display app-review / asset-role prerequisites. Do not pretend a standard personal Facebook account has Page permissions.

### 8.6 Instagram Professional accounts

Use official Meta APIs for eligible professional accounts. Implement supported publishing and supported conversation features only. Expose permission limitations in the UI. No scraping, mass unsolicited DMs or automation of personal accounts.

### 8.7 LinkedIn

Prioritise authorised organisation-page publishing and permitted post data. Do not implement unauthorised profile scraping, automated connection requests or cold personal DMs. Where private outreach is not supported by approved API access, create a manual follow-up task with a link to the prospect.

### 8.8 Telegram Bot API

Implement bot notifications and messages only in permitted bot conversations. Do not send arbitrary unsolicited messages to users who have not initiated/authorised the bot interaction.

## Priority 3 — extensible provider support

Create manifests/adapters or generic connector support for:

- Google Sheets.
- Google Calendar.
- HubSpot.
- Pipedrive.
- Slack.
- Brevo / Mailgun / SendGrid where API credentials are available.
- Twilio where a suitable approved use case exists.
- Generic signed webhooks.
- Additional REST/OpenAPI APIs.
- Remote MCP servers.

Do not mark a provider as supported/connected until a real operation and its error handling have been tested. A documented scaffold is not a working integration.

---

# 9. UNIFIED COMMUNICATION AND CHANNEL NORMALISATION

All channels should map into a common internal communication model without discarding provider-specific data.

Suggested core entities:

- `ChannelConnection`
- `ExternalAccount`
- `Conversation`
- `ConversationParticipant`
- `Message`
- `MessageAttachment`
- `MessageDeliveryEvent`
- `WebhookEvent`
- `ConsentRecord`
- `SuppressionEntry`
- `MessageTemplate`
- `Campaign`
- `CampaignStep`
- `CampaignEnrollment`
- `OutboundAction`
- `ApprovalRequest`

Store provider identifiers and channel-specific metadata in a structured, validated metadata field or dedicated extension entity. Do not make a giant generic JSON column the only source of truth.

Deduplicate conversations using provider IDs and stable participant/account identifiers. Use idempotency keys for outgoing sends and webhook processing.

A single prospect may have multiple email addresses, phone numbers and social identities. Track their source and verification state; never assume two identities belong to the same person solely because a display name matches.

---

# 10. AUTOMATION ENGINE

Build a reliable event-driven automation engine. Separate AI reasoning from deterministic workflow execution.

## 10.1 Workflow concepts

A workflow contains:

- Trigger.
- Conditions.
- Actions.
- Delays/schedules.
- Stop rules.
- Approval requirements.
- Retry policy.
- Execution history.

MVP triggers:

- Prospect created.
- Prospect qualified.
- Score crosses a configured threshold.
- Research run completed.
- Message received.
- Message bounced/failed.
- Prospect replies.
- Approval granted/rejected.
- Task due.
- Campaign event.
- Webhook event.

MVP actions:

- Create a task.
- Assign prospect.
- Add tag/change status.
- Generate research report.
- Create an outreach draft.
- Request approval.
- Send approved email.
- Send eligible WhatsApp template.
- Trigger webhook.
- Notify workspace user.
- Enrol prospect in an eligible sequence.

## 10.2 Example workflow

```text
Trigger: New prospect qualifies
   ↓
Research website and collect evidence
   ↓
Calculate deterministic opportunity score
   ↓
Condition: score >= configured threshold
   ↓ yes
Generate personalised email draft
   ↓
Create ApprovalRequest
   ↓ user approves
Check suppression, sender, provider capability and rate limits
   ↓
Send through email adapter
   ↓
Persist provider ID and send result
   ↓
Wait for reply / configured delay
   ↓
If reply: stop sequence and update inbox
If no reply: create follow-up task or approved next step
```

AI may propose a workflow or choose among permitted actions, but cannot bypass deterministic validation, permissions, opt-outs or approval checks.

## 10.3 Reliability

- Symfony Messenger for asynchronous tasks.
- Idempotent handlers.
- Retry transient errors only.
- Dead-letter/failed message inspection.
- Bounded exponential backoff with jitter.
- Per-provider rate limits and concurrency controls.
- Explicit send quotas.
- Pause/resume campaign.
- Global emergency stop for outbound actions.
- Audit log for every external write.
- Do not hold long-running HTTP requests open for research jobs.

---

# 11. AI AGENT DESIGN

Use Symfony AI components properly. Build one well-defined agent first; do not create a collection of loosely connected AI classes without an execution model.

Suggested agents/services:

1. `ProspectResearchAgent` — plans research and orchestrates approved read tools.
2. `WebsiteAnalysisService` — deterministic extraction and measurable checks.
3. `OpportunityAnalysisAgent` — turns evidence into cautious business opportunity hypotheses.
4. `OutreachDraftAgent` — drafts messages from prospect evidence and a configured offer.
5. `CampaignReviewAgent` — summarises real campaign activity and suggests next steps.

A shared orchestration service can route tasks to specialised agents/models where useful.

## 11.1 AI tool rules

- Tools have clear schemas and typed input/output.
- Tool descriptions state constraints.
- Restrict the tools available to each agent.
- Limit tool-call loops and wall-clock execution time.
- Keep tool results small and relevant.
- Store tool calls/results for audit and debugging.
- Treat web page content, emails, external documents and remote MCP output as untrusted data.
- Prevent prompt injection from changing system policies or granting permissions.
- Never expose unrestricted SQL, filesystem, shell or arbitrary HTTP tools to the LLM.
- Never allow the model to invent evidence or contact details.
- Require structured outputs for prospect reports and campaign summaries.

## 11.2 AI provider/model configuration

Use Symfony AI's provider abstraction. Initial providers:

- Hugging Face Inference Providers (hackathon credit path).
- OpenAI (optional BYOK).
- Anthropic (optional BYOK).

Design so further supported Symfony AI providers can be added without rewriting agent logic.

Allow model selection per task where the installed Symfony AI version permits it. Maintain a model capability registry (chat, tool calling, structured output, vision, embeddings) and do not use a model for a task it does not support.

## 11.3 Hugging Face credit usage

The hackathon provides a $20 Hugging Face Inference Providers coupon per developer after hackathon registration (check the current team dashboard and coupon terms).

Use it for real inference inside ProspectOS:

- Agent planning/tool selection.
- Evidence-grounded opportunity summaries.
- Structured prospect report generation.
- Outreach draft creation.
- Evaluation of output quality.

Requirements:

1. Retrieve the coupon through the registered hackathon account/team dashboard.
2. Create a Hugging Face access token with the required Inference Providers access.
3. Configure it server-side as `HUGGINGFACE_TOKEN` (or an explicitly documented secret name).
4. Use the official Symfony AI Hugging Face bridge and a model/provider combination verified to support the needed task/tool calling.
5. Route requests through Hugging Face's hosted/routed inference path so eligible credits apply. Do not configure a custom third-party provider key if that bypasses HF billing for the request.
6. Do not hardcode a model name until the live model catalog and tool-calling support are checked.
7. Track inference request count, model, provider, latency, token usage/cost if available, errors and run ID.
8. Set application-level request budgets, prompt-size limits and maximum tool calls to conserve credits.
9. Do not assume the $20 pays for hosting, search APIs, email, WhatsApp or Codex.
10. If credits are unavailable/exhausted, return a useful provider/budget warning and allow another configured provider or a clearly labelled local/demo mode.

Official references:

- `https://huggingface.co/docs/inference-providers/pricing`
- `https://symfony.com/doc/current/ai/components/platform/huggingface.html`

Do not promise a fixed number of calls from $20; inference cost varies by model, provider, input/output tokens and task.

## 11.4 Deterministic lead scoring

Never ask an LLM to make up a score. Build a transparent scoring service with editable weights.

Suggested initial weights:

| Dimension | Weight |
|---|---:|
| Ideal customer profile fit | 25% |
| Observable business opportunity | 30% |
| Evidence quality | 20% |
| Contactability | 15% |
| Intent / recency | 10% |

Score 0–100. Unknown inputs must not silently count as zero evidence; renormalise across available dimensions and show data coverage. Keep separate values for opportunity score, confidence, evidence coverage, freshness and contactability. Explain score contributions and save score snapshots.

---

# 12. MCP: SERVER AND CLIENT MUST BOTH WORK

This is a central hackathon feature.

Use the official `mcp/sdk` through `symfony/mcp-bundle` if the current compatible release is available. Confirm API and version constraints in Composer and official docs. The Symfony MCP Bundle can make the application both an MCP server and client over supported HTTP/STDIO transports.

## 12.1 ProspectOS MCP server

Expose a deliberately curated set of tools. Suggested names (adapt to MCP naming/API conventions):

### Read / research tools

- `search_prospects`
- `get_prospect`
- `get_prospect_evidence`
- `analyze_prospect_website`
- `score_prospect`
- `list_campaigns`
- `get_campaign_metrics`
- `search_conversations`
- `get_workspace_activity_summary`

### Draft / workflow tools

- `create_outreach_draft`
- `create_followup_task`
- `request_outreach_approval`
- `get_approval_status`
- `get_research_run_status`

### Carefully gated write tool

- `send_approved_message`

`send_approved_message` must require an existing, valid, unexpired approval record bound to the exact action payload, prospect, channel, sender and target. A model must not be able to create an approval and approve it itself. The human approval must originate from the authenticated dashboard/workspace actor or an explicitly configured trusted approval mechanism.

Never expose a generic `send_anything`, arbitrary HTTP, SQL, shell or filesystem tool.

Expose useful read-only MCP resources where appropriate, for example:

- Workspace overview.
- Prospect intelligence report.
- Prospect timeline.
- Campaign summary.
- Integration capability/status summary (never credentials).

Use clear tool schemas, descriptions, structured error results and scope checks.

## 12.2 MCP authorization

- Do not expose an unauthenticated public MCP endpoint.
- Use secure bearer/OAuth-compatible authentication supported by the selected implementation.
- Issue revocable, scoped MCP credentials per workspace/client.
- Enforce tenant isolation on every tool call.
- Rate limit and audit tool calls.
- Provide read/write scope separation.
- Do not let a connected MCP client see another workspace's data.
- Provide a local development STDIO connection only where safe and documented.

## 12.3 ProspectOS MCP client

Provide an admin interface to add a remote MCP server, authenticate, discover its tools/resources/prompts, inspect schemas, allowlist selected capabilities, test calls and view logs.

The agent may use approved remote MCP tools as research capabilities. Remote MCP tool results are untrusted. They cannot override ProspectOS policies or approvals.

Do not let workspace users configure arbitrary STDIO shell commands. For hosted remote connections, support only secure network transports actually supported by the pinned official SDK. Verify current protocol transport naming and avoid deprecated modes.

## 12.4 Required demonstration

Document and test a real MCP client such as Claude Desktop or another compatible client:

1. Start ProspectOS and configure an MCP token.
2. Connect the client to the ProspectOS MCP HTTP endpoint using the documented configuration.
3. Discover ProspectOS tools.
4. Ask the external client to search/inspect prospects and prepare an outreach draft.
5. Show that the tool call is recorded in ProspectOS.
6. Show that sending is blocked until a human approves it in the dashboard.
7. Approve the action and demonstrate the gated send in the app or with a safe test recipient/provider sandbox.

Include example configuration in `docs/MCP.md`. Never place a real token in the repository.

---

# 13. UNIVERSAL INTEGRATION HUB UI

Route: `/integrations`.

Group cards by:

- Email & Communication.
- Social & Publishing.
- Search & Intelligence.
- CRM & Productivity.
- AI Providers.
- MCP Servers.
- Custom APIs & Webhooks.

Each integration card shows:

- Provider name and icon.
- Short description.
- Connection status.
- Available capabilities.
- Required permissions.
- Last successful test/sync.
- Setup method (OAuth, API credentials, webhook, REST/OpenAPI or MCP).
- Connect / configure / test / reconnect / disconnect action.

Connection wizard:

1. Choose provider or Generic Connector.
2. Show requirements and capabilities before credentials.
3. Choose OAuth / API credentials / webhook / OpenAPI / MCP according to provider support.
4. Collect configuration using schema-driven fields.
5. Validate server-side.
6. Perform test connection.
7. Show exact granted capabilities.
8. Set webhook subscriptions if required.
9. Save connection.
10. Show a usable “Connected” state only after the test succeeds.

Do not ask for application-wide OAuth client secrets in each user's account form. These are deployment-level configuration. User authorisation is a separate step.

Credential fields are write-only after save. A secret may be replaced but never re-displayed.

---

# 14. DATA MODEL AND MULTI-TENANCY

Use Doctrine entities, migrations, constraints, indexes and repositories. Every business record belongs to a workspace unless it is genuinely global reference data.

Suggested entities (adapt where justified):

### Identity and workspace

- User
- Workspace
- WorkspaceMembership
- Role / permission model
- ApiCredential / McpCredential

### Prospect and research

- Prospect
- ProspectContact
- ProspectSocialIdentity
- ProspectTag
- ProspectNote
- ResearchRun
- ResearchQuery
- EvidenceItem
- OpportunityFinding
- ScoreSnapshot
- SourceRecord

### Integration

- ConnectorDefinition (may be code/config rather than a database entity)
- ChannelConnection
- OAuthState / token metadata
- IntegrationSecret (encrypted)
- WebhookSubscription
- WebhookEvent
- OutboundWebhookEndpoint
- ConnectorOperationPermission
- IntegrationHealthCheck

### Communication and campaigns

- Conversation
- ConversationParticipant
- Message
- MessageAttachment
- MessageDeliveryEvent
- MessageTemplate
- Campaign
- CampaignStep
- CampaignEnrollment
- OutboundAction
- ConsentRecord
- SuppressionEntry

### Approval and workflow

- ApprovalRequest
- ApprovalDecision
- WorkflowDefinition
- WorkflowExecution
- WorkflowStepExecution
- Task
- ActivityEvent
- AuditLog

### AI and MCP

- AgentRun
- AgentToolCall
- ModelUsageRecord
- McpRemoteServer
- McpCapabilityPermission
- McpInvocationLog

Use JSON fields only for bounded provider-specific metadata/configuration, not as a substitute for relational modelling. Add unique constraints for provider IDs and idempotency keys. Add indexes for common workspace/time/status lookup paths.

Use workspace scoping in repositories/services and add tests proving a member cannot access another workspace's records by guessing an ID.

---

# 15. SECURITY, CONSENT AND ABUSE PREVENTION

Security is part of the feature, not an optional later phase.

## 15.1 Authentication

- Secure Symfony Security authentication.
- Password hashing with current Symfony defaults.
- CSRF protection for browser forms.
- Secure, HttpOnly, SameSite cookies.
- Session rotation after login.
- Login throttling.
- Password reset flow using expiring tokens.
- Optional TOTP/2FA if time permits.
- Role-based and workspace-scoped access.

## 15.2 API and webhook security

- Validate all request bodies and query inputs.
- Enforce content type and size limits.
- Use idempotency keys.
- Verify webhook signatures against raw request body.
- Protect against replay attacks where provider supports timestamps/nonces.
- Rate limit by IP, workspace, provider and credential.
- Never trust user-supplied account IDs as authorisation.
- Do not expose debug/profiler endpoints in production.

## 15.3 Prompt injection / agent security

- Treat all web content, emails, uploaded files and remote MCP results as untrusted.
- Tool allowlists are enforced in code, not only in prompts.
- Separate read tools from write tools.
- Write tools run through a policy/approval service.
- Tool parameters are validated with DTOs/schema validators.
- No generic filesystem, shell, SQL or arbitrary-network tools.
- Persist trace of model and tool decisions.

## 15.4 Outreach safety

- Store source, consent/permission basis where applicable, timestamp and opt-out state.
- Maintain workspace and global suppression lists as appropriate.
- Provide unsubscribe/stop handling.
- Stop campaigns when a contact opts out, replies (based on sequence policy), hard-bounces or becomes suppressed.
- Apply sender/provider daily limits and pacing.
- Respect provider template, messaging-window and account eligibility requirements.
- Do not scrape private profiles or bypass platform restrictions.
- Do not mass-DM people through browser automation or unofficial APIs.
- Social messaging only through official, authorised APIs and permitted contexts.
- Default to a human approval before any external communication.
- Provide a global outbound kill switch.

Document that the operator is responsible for complying with applicable laws and provider terms in each target market. Implement controls that support compliance; do not claim the product makes every campaign automatically lawful.

---

# 16. MOBILE PWA REQUIREMENTS

Create a high-quality installable PWA from the same Symfony application.

Required:

- Valid `manifest.webmanifest`.
- App name, short name, icon set and theme colours.
- Service worker.
- Install prompt/help for supported browsers.
- Responsive layout at 390px and common device widths.
- Touch targets at least 44px, preferred 48px.
- Mobile bottom navigation with 3–5 labelled items.
- Single-column layouts.
- Bottom sheets for mobile filters/details where appropriate.
- Safe-area support for notches/home indicators.
- Forms remain usable with the mobile keyboard open.
- Offline app shell and non-sensitive static assets only.
- Clear offline status and retry UI.
- Never cache sensitive API responses, tokens, email bodies or prospect personal data by default.
- Optional Web Push notifications with VAPID only if time and provider/browser support permit; in-app notifications must work without push.

Mobile priority screens:

1. Command Centre summary.
2. Inbox / conversations.
3. Prospect detail.
4. Approvals.
5. Tasks / follow-ups.

The desktop design must not simply be stretched onto mobile. Follow the supplied UX skill exactly.

A PWA is the mobile deliverable for this hackathon. Do not delay the main build by attempting separate native iOS and Android applications. A Capacitor wrapper may be considered after the hackathon.

---

# 17. API AND APPLICATION SERVICES

Use Symfony controllers with a clear application/service layer. Use DTOs and validation. Keep business rules out of Twig templates and controllers.

Provide JSON endpoints where needed for asynchronous updates, charts, integration configuration and mobile/PWA interactions. Use appropriate HTTP status codes and a consistent error format.

Suggested service groups:

- ProspectDiscovery
- WebsiteInspection
- EvidenceManagement
- OpportunityScoring
- AgentOrchestration
- OutreachDrafting
- ApprovalManagement
- ChannelRouting
- CampaignExecution
- ConversationSync
- IntegrationRegistry
- CredentialVault
- WebhookProcessing
- McpAuthorization
- AnalyticsQuery
- ActivityTimeline

All UI, automation and MCP actions must use the same application services. Do not duplicate business logic in separate controller, agent and MCP implementations.

---

# 18. DEMO MODE AND SEED DATA

A juror should be able to clone the repo and see the application without needing every third-party credential.

Provide two explicit modes:

### Live mode

- Uses real configured providers.
- Real AI inference.
- Real business/search API results.
- Real messages only after configured approval and valid credentials.
- Real webhook events.

### Demo mode

- Uses clearly labelled synthetic records.
- Uses deterministic fixtures and recorded sample events.
- Simulates provider responses only within demo mode.
- Shows visible `DEMO DATA` / `SIMULATED` labels.
- Never says a real email, WhatsApp message or social post was sent.
- Never mixes demo events with live analytics.

Seed a small, coherent demo workspace with fictional businesses and source records marked as synthetic. Do not use invented contacts for actual outreach. Provide a command such as `php bin/console app:demo:seed` and a documented demo login generated for local/demo only. Do not ship a production default password.

---

# 19. TESTING AND QUALITY GATES

Build tests alongside features. At minimum:

## Backend unit tests

- Lead scoring and weight normalisation.
- Evidence status/confidence rules.
- Duplicate detection.
- Connector capability calculation.
- Secret encryption/decryption and redaction.
- OAuth state / PKCE handling.
- Approval payload immutability and expiry.
- Suppression and consent checks.
- Campaign stop rules.
- Idempotent webhook processing.
- MCP tool authorization and workspace scope.
- SSRF URL/IP validation.

## Backend functional tests

- Register/login/logout.
- Workspace isolation.
- Research run creation and result persistence.
- Prospect list/detail.
- Integration test connection path using a fake HTTP server.
- Webhook verification and duplicate event handling.
- Draft → approve → fake provider send.
- Inbound event → inbox timeline.
- MCP authenticated read tool.
- MCP write tool denied without approval and allowed with valid approval.

## Frontend/browser tests

- Responsive navigation.
- Dashboard empty/loading/error/success states.
- Prospect search and filters.
- Integration connection wizard.
- Approval review/reject/approve.
- Mobile bottom navigation and touch targets.
- PWA manifest/service worker.

Use PHPUnit and Symfony's test tools. Add Playwright or another browser test tool only if setup/time permits. Add static analysis and coding standards where practical.

## Quality gates before marking a phase complete

- No syntax errors.
- Composer dependencies resolve and lock file committed.
- Database migrations apply from an empty database.
- Tests for the phase pass.
- Frontend assets build.
- No secrets committed.
- No unresolved TODO in a user-facing primary path.
- Documentation updated.

---

# 20. GITHUB, CI AND DEPLOYMENT

Repository must be public for hackathon submission. Use a clear open-source license (MIT unless project owner specifies otherwise). Include an accurate README.

GitHub Actions should run:

- Composer validate.
- PHP lint.
- PHPUnit.
- Static analysis if configured.
- Frontend lint/type checks if any JS/TS is used.
- Asset build.
- Security/dependency audit where practical.

Do not put provider credentials in GitHub Actions logs or repository secrets unless required for deployment. Do not require public CI to have production API keys to pass normal tests; use mocked/fake providers for CI.

Deployment documentation must cover:

1. Create Upsun account/project.
2. Connect GitHub repository.
3. Configure the PHP runtime and document root.
4. Provision PostgreSQL and connect the application.
5. Provision Redis only if used.
6. Configure web and worker instances.
7. Add environment variables and secrets.
8. Configure application secret/encryption key.
9. Configure public base URL and trusted hosts.
10. Run database migrations safely.
11. Warm Symfony cache and build frontend assets.
12. Configure HTTPS/custom domain.
13. Configure OAuth redirect URLs.
14. Configure provider webhook URLs.
15. Verify worker health and scheduler.
16. Run health checks and smoke tests.
17. Configure backups and resource budget alerts.

Provide a rollback plan and a warning not to run destructive migrations against production data without a backup.

---

# 21. ENVIRONMENT CONFIGURATION

Create `.env.example` with names and descriptions only, never real values.

Likely configuration groups (adjust to implementation):

```dotenv
APP_ENV=dev
APP_SECRET=
APP_BASE_URL=http://localhost:8000
DATABASE_URL=postgresql://app:app@127.0.0.1:5432/prospectos?serverVersion=16&charset=utf8
MESSENGER_TRANSPORT_DSN=doctrine://default
REDIS_URL=redis://127.0.0.1:6379

# Encryption / signing
APP_ENCRYPTION_KEY=
WEBHOOK_SIGNING_KEY=

# AI / Hugging Face
HUGGINGFACE_TOKEN=
HUGGINGFACE_PROVIDER=
AI_DEFAULT_MODEL=
AI_RESEARCH_MODEL=
AI_DRAFT_MODEL=

# Search providers (optional)
BRAVE_SEARCH_API_KEY=
TAVILY_API_KEY=
SERPAPI_API_KEY=
PAGESPEED_API_KEY=

# OAuth app-level credentials (optional; configure only for enabled providers)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
META_APP_ID=
META_APP_SECRET=
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=

# Provider API (optional)
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
TELEGRAM_BOT_TOKEN=

# PWA / notifications
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=
```

Prefer storing workspace-specific OAuth tokens and provider credentials in encrypted database storage, not in these deployment-level environment variables. The example above shows optional bootstrap/application credentials only. Avoid keeping unused global provider tokens if the product is designed for user-connected accounts.

Document which variables are required for local demo, live AI, each connector and production.

---

# 22. IMPLEMENTATION PHASES AND PRIORITIES

Time is limited. Build in vertical slices and preserve a working demo at every milestone.

## Phase 0 — inspect and prepare

- Inspect repository and references.
- Create `AGENTS.md`, `PROJECT_CONTEXT.md`, `CODEX_PROGRESS.md`.
- Verify current Symfony/AI/MCP package versions and compatibility.
- Record ADRs for framework version, hosting, UI and integration architecture.
- Set up Git, Composer, Docker Compose, PHP and test tooling.

## Phase 1 — working application foundation

- Symfony app boots.
- Authentication and workspace.
- PostgreSQL schema/migrations.
- Base Twig layout and navigation.
- Design tokens from references.
- Responsive PWA shell.
- Seeded demo workspace.

## Phase 2 — prospect intelligence vertical slice

- Real provider-based search adapter (at least one if credentials provided).
- Website inspection with SSRF protection.
- Evidence persistence.
- Deterministic scoring.
- Prospect list/detail.
- Research run status/progress.

## Phase 3 — Symfony AI and MCP vertical slice

- Hugging Face inference configured.
- Prospect research agent.
- Structured report generation.
- Tool call logging.
- MCP server with authenticated tools.
- Remote MCP client connection and tool discovery.
- End-to-end Claude/compatible MCP client test.
- Approval-gated external action.

## Phase 4 — integration and communication vertical slice

- Integration Hub framework.
- API credential vault.
- Generic REST connector and webhook infrastructure.
- SMTP outbound or one verified email API end-to-end.
- Gmail/Graph connectors where credentials and OAuth setup permit.
- WhatsApp Cloud API connector scaffold and live path if eligible credentials are available.
- Unified inbox for channels with real inbound support.

## Phase 5 — workflow, reporting and polish

- Campaign and task flow.
- Stop rules/suppression.
- Command Centre metrics from actual records.
- PWA polish.
- Mobile verification.
- Audit/security checks.
- Demo video and public repo documentation.

Do not attempt to fully integrate every named social platform before the primary vertical slice works. The universal adapter framework and real MVP connectors are more important than a long list of empty integration cards.

---

# 23. REQUIRED HACKATHON DEMONSTRATION

Create a repeatable demo using synthetic data plus at least one real AI inference provider call. If a real search provider is configured, use it for a real discovery demonstration. If no search credential is available, explicitly label the search data as demo fixtures.

Demonstration sequence:

1. Open ProspectOS Command Centre.
2. Enter a research objective for local businesses.
3. Start the AI research job.
4. Show Symfony AI planning and using registered tools.
5. Show a prospect being discovered or loaded from labelled fixtures.
6. Open the prospect report.
7. Show evidence links and evidence confidence.
8. Show deterministic opportunity score and breakdown.
9. Generate personalised outreach grounded in evidence.
10. Show the pending approval screen.
11. Demonstrate an approval-gated test send or safe provider sandbox send.
12. Show activity event / message status.
13. Connect an external MCP client and call a ProspectOS tool.
14. Show MCP invocation logs and that the approval rule still applies.

Prepare a concise 90-second video using the strongest parts of this flow. Keep the repo quick to clone/run and provide a demo command and synthetic data.

---

# 24. DEFINITION OF DONE

The hackathon MVP is not done until these are true:

- [ ] Application boots locally from documented steps.
- [ ] Public GitHub repository is clean and contains no secrets.
- [ ] Database migrations work from empty DB.
- [ ] Authentication and workspace scoping work.
- [ ] UI follows both supplied reference files.
- [ ] Desktop dashboard is functional and not merely a static mock.
- [ ] Mobile PWA installs and core screens are usable at 390px.
- [ ] Research objective can be submitted and processed asynchronously.
- [ ] At least one search source is truly connected OR synthetic demo mode is conspicuously labelled.
- [ ] Website inspection is SSRF-safe.
- [ ] Evidence items store source URLs/timestamps and appear in reports.
- [ ] Lead score is deterministic and explainable.
- [ ] Symfony AI performs real model inference through a configured provider.
- [ ] Hugging Face Inference Providers credits are used when coupon/token are available.
- [ ] ProspectOS exposes authenticated MCP tools through the official PHP SDK/bundle.
- [ ] ProspectOS can connect to at least one compatible remote MCP server/client configuration and discover tools.
- [ ] MCP calls are logged and workspace-scoped.
- [ ] Write actions cannot bypass the human approval mechanism.
- [ ] At least one outbound communication connector works end-to-end in test/safe mode.
- [ ] Generic API/webhook setup framework is functional and documented.
- [ ] Connector health and capabilities are truthful.
- [ ] Demo data is clearly separated from live data.
- [ ] Tests cover scoring, security, approvals, webhook idempotency and MCP authorization.
- [ ] Upsun deployment config and guide exist.
- [ ] README enables a stranger to clone and run the demo.
- [ ] 90-second video is recorded and submission is prepared.
- [ ] `PROJECT_CONTEXT.md` and `CODEX_PROGRESS.md` are updated with the final state and next actions.

---

# 25. HOW YOU MUST WORK AS CODEX

Follow this operating procedure:

1. **Inspect before editing.** List repository files, read the two design references and any existing project instructions. Identify whether the repository is empty or already contains work.
2. **Verify dependencies.** Check live official documentation and Composer metadata for the selected Symfony, Symfony AI, MCP Bundle, PHP MCP SDK and Hugging Face bridge versions. Save the resolved versions in `docs/ARCHITECTURE.md`.
3. **Create continuity documents immediately.** `AGENTS.md`, `PROJECT_CONTEXT.md`, `CODEX_PROGRESS.md` and architecture docs are not optional.
4. **Create a concise implementation plan.** Commit to vertical slices, not an enormous sequence of untestable scaffolding tasks.
5. **Build the smallest complete vertical slice first.** Research → evidence → score → report → approval-gated draft/action.
6. **Run tests after every phase.** Fix failures before expanding the scope.
7. **Use real application services.** Buttons, forms, filters, modals, charts, approvals and integration tests must work. No inert controls.
8. **Use honest states.** Mark demo data, unconfigured integrations, limited permissions and simulated actions visibly.
9. **Do not overbuild.** If time is short, finish the core working flow and document deferred features instead of creating shallow stubs for everything.
10. **Update context continuously.** After each phase, record exact implemented files, commands, tests, results, known limitations and next action in the project context/log.
11. **No fabricated completion claims.** A provider card is not an integration. An interface is not a feature. A test passing in a mock is not proof of real-provider delivery.
12. **Final response to the owner.** Report what is genuinely working, what requires credentials, what remains incomplete, how to run locally, how to deploy, and the next recommended action.

At the beginning of every future session, read in this order:

1. `AGENTS.md`
2. `PROJECT_CONTEXT.md`
3. `CODEX_PROGRESS.md`
4. Relevant architecture/integration/security docs
5. Relevant source code and tests

At the end of every future session, update those files before stopping.

---

# 26. OFFICIAL REFERENCE LINKS

Use these as starting points and verify the latest version/documentation before implementation:

## Hackathon

- https://symfony.com/hackathon
- https://symfony.com/blog/symfony-ai-1-0-0-beta1-ai-and-mcp-hackathon

## Symfony AI / MCP

- https://symfony.com/doc/current/ai/index.html
- https://symfony.com/doc/current/ai/components/agent.html
- https://symfony.com/doc/current/ai/components/platform.html
- https://symfony.com/doc/current/ai/components/platform/huggingface.html
- https://symfony.com/doc/current/ai/bundles/mcp-bundle.html
- https://github.com/modelcontextprotocol/php-sdk
- https://php.sdk.modelcontextprotocol.io/
- https://symfony.com/doc/current/messenger.html
- https://symfony.com/releases

## Upsun

- https://upsun.com/symfony-paas/
- https://upsun.com/php-paas/
- https://upsun.com/pricing/
- https://docs.upsun.com/create-apps/workers.html
- https://docs.upsun.com/add-services/postgresql.html
- https://docs.upsun.com/add-services/redis.html

## Hugging Face

- https://huggingface.co/docs/inference-providers/index
- https://huggingface.co/docs/inference-providers/pricing
- https://huggingface.co/settings/tokens

## Provider platform APIs

- Google Gmail API: https://developers.google.com/gmail/api
- Microsoft Graph: https://learn.microsoft.com/graph/api/resources/mail-api-overview
- WhatsApp Business Platform: https://developers.facebook.com/docs/whatsapp/cloud-api/
- Meta Graph API: https://developers.facebook.com/docs/graph-api/
- Instagram API: https://developers.facebook.com/docs/instagram-platform/
- LinkedIn API: https://learn.microsoft.com/linkedin/marketing/
- Telegram Bot API: https://core.telegram.org/bots/api

---

# FINAL INSTRUCTION

Build ProspectOS as a coherent product owned by 14 Technologies, not as a generic CRUD dashboard.

The first thing a user should be able to do is discover and understand a real business opportunity. The first thing an external MCP client should be able to do is use a safe, authenticated ProspectOS tool. The first external action should require explicit approval and leave an auditable record.

Use the supplied UX skill as the design law and the supplied dashboard image as the visual reference. Use Symfony AI and the official PHP MCP SDK as real architectural foundations. Use Upsun for the PHP application deployment and GitHub for source control. Deliver an installable PWA for mobile.

Most importantly: **keep `PROJECT_CONTEXT.md` current so the next Codex session can continue exactly where this one stopped.**
