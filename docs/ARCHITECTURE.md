# Architecture

## Evidence Court — 2026-11-16

The headline feature. `EvidenceCourt` (`src/Service/Court/`) runs a hearing on one prospect:

- `CourtCase` (table `court_case`, migration `Version20261116150034`) stores workspace, prospect, opener (app or MCP), status (`pending` → `argued` → `ruled`, or `failed`), provider/model, latency and a JSON transcript. The ruling links to a normal `ProspectDecision`, so it appears in team memory and is offered to later hearings as precedent.
- The UI dispatches `HearCourtCase` to the Doctrine `async` transport, and the worker runs it. The hearing page polls while it is pending. The CLI command `app:court:hear` and the MCP tools run synchronously.
- Two Symfony AI `Agent`s (Advocate, then Skeptic) share one `Toolbox` with `CaseFileTools`: three read-only `#[AsTool]` methods (`read_evidence`, `score_breakdown`, `team_precedents`). They are bound to one prospect's evidence (12 or fewer records), its latest score and its relevant decisions. Each call is logged into the transcript, and there is a cap of 6 tool calls per counsel. The Skeptic receives application-computed gaps (unverified evidence, unknown score dimensions) and the Advocate's claims, and is told not to repeat them.
- `CitationClerk` is deterministic PHP. It splits arguments into claims (6 or fewer), reads only bracketed citations, admits claims citing evidence in the case file or `[SCORE]`, marks `[M#]` precedent citations as preference-only, and strikes uncited claims or claims citing unknown IDs. `EvidenceCourt::strikeRepeats` strikes Skeptic claims that are at least 85% similar to an Advocate claim.
- Provider errors become a `failed` case with a safe message. They are never thrown at the user. Local Ollama gets a 150 s idle / 180 s total HTTP budget, and cloud providers keep 30 s / 60 s.
- MCP: `App\Mcp\Workspace\EvidenceCourtTool` (`open_evidence_court`, `get_court_case`) on the session-authenticated HTTP server, and `App\Mcp\Demo\DemoEvidenceCourtTool` (`list_demo_leads`, `open_evidence_court`, `get_court_case`) on the local STDIO server, scoped to the fictional demo workspace. No MCP tool can rule. A functional test asserts that no tool name matches rule/decide/approve/send.
- `DemoLogin` creates `demo@prospectos.test` as owner of the fictional demo workspace when `DEMO_LOGIN_PASSWORD` is set (8 or more characters) and the environment is not `prod`. The demo account does not count as the first real owner, so `/setup` still works.


## Email architecture update — 2026-11-15

This update supersedes older draft-only Gmail/provider-readiness statements below. Owner-workspace Ollama qwen2.5:1.5b is active and passed real Symfony AI inference (12,328 ms). Gmail now supports inbox reads and replies as provider drafts. `MailboxSync`, `GmailGatewayInterface`, `BusinessMailboxInterface` and `ApprovedEmailSender` serve the Twig controllers. Standard IMAP uses DirectoryTree ImapEngine 1.25.9 without a PHP IMAP extension; SMTP uses Symfony Mailer 7.4.22 with authenticated ESMTP and required TLS.

`BusinessMailConnection` stores encrypted credentials and tested/revision/pause state. `MailMessage` stores a bounded connection-owned cache; `MailReplyDraft` records Gmail draft metadata. `EmailAction` binds an immutable test payload to the original connection/revision, approver and expiry. A database-locked attempt is committed before SMTP; errors are uncertain and never automatically retried. General sending and MCP write tools remain absent. See [MAIL.md](MAIL.md) for exact controls and [RELEASE_AUDIT.md](RELEASE_AUDIT.md) for remaining work.

## Current implementation

The project is a single Symfony application rendered with Twig. Symfony UX/Turbo and Stimulus are available for progressive interactions; Tailwind CSS is compiled by `symfonycasts/tailwind-bundle`. The supplied UX reference and dashboard visual reference are preserved under `docs/reference/`.

The Command Centre, Research screen, Team Memory, Outreach setup and prospect report require an authenticated user and resolve the user's workspace through `WorkspaceMembership`. Research runs, prospects, decision history, report evidence, score snapshots, AI summaries, Gmail connections and outreach draft metadata are workspace-scoped. Each prospect has a lifecycle status and optional canonical company domain; human decisions are saved with actor, reason and timestamp. Repeated research uses the normalized domain to find the existing workspace prospect, preserving its review status and history while appending evidence. AI summaries can see a few similar workspace decisions as preference context, clearly separated from source evidence. Demo seed records are clearly marked synthetic.

The Research screen validates a 12–500 character objective and optional URL with CSRF protection, creates a workspace-scoped `ResearchRun` and dispatches `ProcessResearchRun` to the Doctrine-backed Messenger `async` transport. `DemoProspectDiscovery` stores synthetic fixture records. `SearXNGDiscoveryProvider` uses the internal Compose service's JSON API to save up to five public web-result candidates with their source URL and excerpt; candidates are deduplicated by host, marked unverified and left unscored. If a URL is supplied, `WebsiteInspector` instead creates one website record with observed page metadata and evidence. No business identity, location or commercial opportunity is inferred from a search result or page title.

`PublicUrlPolicy` permits only HTTP/HTTPS on standard ports, rejects credentials and local/metadata names, validates every DNS address against PHP's global-address range, and pins requests to a validated address. `WebsiteInspector` fetches `/robots.txt` first with the `ProspectOS-Research` user-agent token, safely follows up to five robots redirects and applies the most-specific matching rules with allow winning ties. `RobotsTxtFetcher` caps the robots file at 512,000 bytes and fails closed for server/network errors, 401/403, 429, oversized/malformed responses and unsafe redirects; ordinary unavailable 4xx such as 404 allow access under RFC 9309. Website redirects remain manual (maximum three), and every destination is resolved and checked again, then its robots rules are checked. One inspection has a 20-second total deadline; page HTML is capped at 1 MB, and scripts/subresources are not executed/fetched. `PostgreSqlHostAccessGate` applies a 2-second per-host inspection interval and a single active inspection per hostname, shared between app/worker through PostgreSQL; stale host state older than 30 days is cleaned. Robots rules do not establish legal permission; operators must review site terms before automated access. Page text is untrusted.

`User` and `WorkspaceMembership` provide first-owner setup and workspace membership. `/setup` is available only while the user table is empty; a PostgreSQL advisory transaction lock prevents concurrent requests from creating multiple initial owners. There is no public registration. Form login uses CSRF, Symfony's login rate limiter, password hashing, and secure session cookie settings. Google OIDC uses the official Google PHP client; it requests `openid email`, validates ID-token signature/audience/issuer/expiry via the client and verified-email/nonce claims in application code, and links unique `sub` values only after password reauthentication. Sign-in requires an existing linked identity and workspace membership. This path is unconfigured until client credentials are provided and has not been live-tested. Dashboard, research and prospect report pages require `ROLE_USER`, then resolve workspace data through `CurrentWorkspaceProvider`. The synthetic demo summary is isolated to STDIO. A separate `/mcp/workspace` HTTP server exposes only two read-only tools; both resolve the authenticated member's workspace, and prospect lookup filters by workspace before returning evidence. The route is protected by Symfony session authentication and the MCP SDK's default DNS-rebinding guard; OAuth bearer auth for external clients is not configured. Nyholm PSR-7 supplies the PSR-17 factories required by the HTTP transport.

Compose applies Doctrine migrations, starts a pinned SearXNG service on the internal Docker network, and runs the idempotent `app:demo:seed` command before starting the local PHP server. The worker consumes the Doctrine-backed queue. Redis was removed from Compose because the current app does not use it. Workspace owners/admins can configure provider connections through `/integrations/ai`. `AiProviderSetting` stores one row per workspace/provider, including model, Ollama local endpoint, encrypted key, editor, active-route flag and test result; the key uses `SecretVault` authenticated encryption derived from `APP_SECRET`. `AiProviderConfiguration` creates a Symfony AI Generic Platform for workspace settings and uses fixed cloud host mappings; the Ollama host is limited to local port 11434. Tests are independent per connection, and the owner/admin explicitly switches the active route without deleting other credentials. Versioned OpenAI-compatible base URLs use the `/chat/completions` suffix to avoid duplicating `/v1`. A real `qwen2.5:1.5b` inference has passed through the Symfony AI provider service in a temporary test workspace; no owner workspace connection is saved yet. Legacy environment configuration can be tested in Integrations and is persisted encrypted for the workspace when it uses a supported provider. Gmail OAuth can create provider-side drafts for finalized prospects. Refresh tokens are encrypted at rest; draft metadata stays in ProspectOS while the body stays in Gmail. There is no send method. The Gmail connection and `drafts.create` API operation are unconfigured and unverified.

`ProspectDecision` is an append-only human decision record. `ProspectMemory` retrieves a bounded set of recent decisions sharing a category or location; it does not alter model weights or automatically rewrite team preferences. The memory view makes lead status and decision reasons inspectable. No embeddings/vector database are required for the current exact-match retrieval.

## Selected versions

| Component | Locked version | Reason |
|---|---|---|
| PHP | Local 8.4.27 | Installed from the official PHP Group Windows package |
| Symfony | 7.4.23 LTS | Tailwind bundle 0.8.0 and Doctrine Bundle 2.19.4 do not yet accept Symfony 8.1; AI and MCP beta package constraints include Symfony 7.4 |
| Symfony AI Bundle | 1.0.0-BETA1 | Current hackathon prerelease line |
| Symfony MCP Bundle | 1.0.0-BETA1 | Symfony integration for the official PHP SDK |
| MCP PHP SDK | 0.9.0 | Locked by MCP Bundle's `mcp/sdk:^0.9` constraint |
| Symfony AI Generic Platform | 1.0.0-BETA1 | Symfony AI bridge for OpenAI-compatible chat endpoints |
| Symfony AI Hugging Face Platform | 1.0.0-BETA1 | Optional bridge; not a required/default provider |
| Symfony AI Generic Platform | 1.0.0-BETA1 | Explicit OpenAI-compatible endpoints including local Ollama and configured cloud APIs |
| Google API Client | 2.20.1 | Official authorization-code and ID-token verification client; no Gmail scopes |
| Symfony AI MCP Tool | 1.0.0-BETA1 | Connects Symfony AI agents to MCP Bundle clients |
| Doctrine ORM | 3.7.6 | Installed, database-backed product schema is next |
| HTTP client | Symfony HttpClient 7.4.23 | Public website inspection with bounded streamed responses |
| Symfony RateLimiter | 7.4.23 | Throttle failed form logins |
| Symfony BrowserKit / CssSelector | 7.4.23 / 7.4.22 | Functional authentication tests |
| Database | PostgreSQL 16 in local Compose | Workspace, ResearchRun, Prospect, EvidenceItem and ScoreSnapshot entities have migrations and persisted demo flow |

The official Symfony release page lists Symfony 8.1 as current stable, requiring PHP 8.4. PHP 8.4.27 is installed locally. A real Composer resolution attempt showed that the required `symfonycasts/tailwind-bundle` 0.8 and `doctrine/doctrine-bundle` 2.19.4 still block Symfony 8.1. Symfony 7.4 LTS was selected because it resolves with the required components. Revisit Symfony 8.1 after those blockers publish compatible releases.

## Target data flow

```text
Twig PWA shell
   -> Research form / authenticated workspace
   -> Messenger ResearchObjective message
   -> provider adapters (search + safe website fetch)
   -> persisted Prospect and Evidence records
   -> deterministic OpportunityScorer
   -> explicit request to configured Symfony AI platform for evidence summary
   -> Approval record bound to immutable action payload
   -> authorised connector + activity event
   -> authenticated MCP tools expose the same scoped services
```

The implemented portion includes persisted synthetic and search-result evidence, one SSRF-protected and robots-aware page inspection flow, workspace-scoped reports with persisted source-cited AI summary support, and authenticated read-only MCP tools. Live provider inference remains unverified until a token is configured and a real call succeeds; approval-gated outbound actions remain a future slice.

## Key boundaries

- AI may summarize evidence and draft; deterministic code owns scores and policy decisions.
- AI is disabled by default. An explicit provider selection and successful test are required; the summary sends bounded prospect context, saved evidence and a few relevant team decision examples to that endpoint. The selected provider/model and disclosure are shown. Citation IDs are checked against supplied evidence. No silent provider fallback occurs. No provider has passed a real ProspectOS provider test.
- Gmail is connected through an explicit OAuth flow. The app encrypts refresh tokens using a key derived from `APP_SECRET`; it stores no message body in ProspectOS. The OAuth `gmail.compose` grant can send mail, but application code only creates drafts and has no send operation. Draft creation requires a finalized lead, a recipient-basis attestation and a check against the workspace suppression list. The suppression list is currently manual. OAuth and a real Gmail draft operation remain unverified.
- Website-only inspection does not create a numeric score; it stores observed metadata and evidence. Search candidates also remain unscored until a scoring basis exists.
- Provider adapters expose capabilities and health rather than assuming a connection works.
- Website inspection applies SSRF checks, robots rules, PostgreSQL-shared host pacing/concurrency limits and bounded response/time budgets. Terms compliance remains an operator responsibility; robots.txt does not substitute for it.
- MCP tools call application services and remain workspace-scoped; no generic shell, filesystem or unrestricted HTTP tool is allowed.
- External actions require explicit approval by default.
