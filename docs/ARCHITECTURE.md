# Architecture

## Current implementation

The project is a single Symfony application rendered with Twig. Symfony UX/Turbo and Stimulus are available for progressive interactions; Tailwind CSS is compiled by `symfonycasts/tailwind-bundle`. The supplied UX reference and dashboard visual reference are preserved under `docs/reference/`.

The Command Centre, Research screen, inbox and prospect report require an authenticated user and resolve the user's workspace through `WorkspaceMembership`. Research runs, dashboard data, report evidence, deterministic score snapshots, saved AI evidence summaries and cached MailOS message headers are scoped to that workspace. The report renders source records, the score breakdown and, after an explicit CSRF-protected request, the latest stored AI summary with links to its input evidence. AI provider selection is optional and disabled by default; summary rows record the actual selected provider/model. Demo seed records are clearly marked synthetic, and dashboard counts are calculated from saved records. Outreach drafts remain at zero until a real drafting flow exists.

The Research screen validates a 12–500 character objective and optional URL with CSRF protection, creates a workspace-scoped `ResearchRun` and dispatches `ProcessResearchRun` to the Doctrine-backed Messenger `async` transport. `DemoProspectDiscovery` stores synthetic fixture records. `SearXNGDiscoveryProvider` uses the internal Compose service's JSON API to save up to five public web-result candidates with their source URL and excerpt; candidates are deduplicated by host, marked unverified and left unscored. If a URL is supplied, `WebsiteInspector` instead creates one website record with observed page metadata and evidence. No business identity, location or commercial opportunity is inferred from a search result or page title.

`PublicUrlPolicy` permits only HTTP/HTTPS on standard ports, rejects credentials and local/metadata names, validates every DNS address against PHP's global-address range, and pins requests to a validated address. `WebsiteInspector` fetches `/robots.txt` first with the `ProspectOS-Research` user-agent token, safely follows up to five robots redirects and applies the most-specific matching rules with allow winning ties. `RobotsTxtFetcher` caps the robots file at 512,000 bytes and fails closed for server/network errors, 401/403, 429, oversized/malformed responses and unsafe redirects; ordinary unavailable 4xx such as 404 allow access under RFC 9309. Website redirects remain manual (maximum three), and every destination is resolved and checked again, then its robots rules are checked. One inspection has a 20-second total deadline; page HTML is capped at 1 MB, and scripts/subresources are not executed/fetched. `PostgreSqlHostAccessGate` applies a 2-second per-host inspection interval and a single active inspection per hostname, shared between app/worker through PostgreSQL; stale host state older than 30 days is cleaned. Robots rules do not establish legal permission; operators must review site terms before automated access. Page text is untrusted.

`User` and `WorkspaceMembership` provide first-owner setup and workspace membership. `/setup` is available only while the user table is empty; a PostgreSQL advisory transaction lock prevents concurrent requests from creating multiple initial owners. There is no public registration. Form login uses CSRF, Symfony's login rate limiter, password hashing, and secure session cookie settings. Google OIDC uses the official Google PHP client; it requests `openid email`, validates ID-token signature/audience/issuer/expiry via the client and verified-email/nonce claims in application code, and links unique `sub` values only after password reauthentication. Sign-in requires an existing linked identity and workspace membership. This path is unconfigured until client credentials are provided and has not been live-tested. Dashboard, research and prospect report pages require `ROLE_USER`, then resolve workspace data through `CurrentWorkspaceProvider`. The synthetic demo summary is isolated to STDIO. A separate `/mcp/workspace` HTTP server exposes only two read-only tools; both resolve the authenticated member's workspace, and prospect lookup filters by workspace before returning evidence. The route is protected by Symfony session authentication and the MCP SDK's default DNS-rebinding guard; OAuth bearer auth for external clients is not configured. Nyholm PSR-7 supplies the PSR-17 factories required by the HTTP transport.

Compose applies Doctrine migrations, starts a pinned SearXNG service on the internal Docker network, and runs the idempotent `app:demo:seed` command before starting the local PHP server. The worker consumes the same database-backed queue. AI providers use Symfony AI platform services selected explicitly by `AI_PROVIDER`; local Ollama, Gemini's documented OpenAI-compatible endpoint, Hugging Face and generic HTTPS endpoints are supported by configuration, but no AI provider passed a real ProspectOS provider test in this session. The MailOS JMAP adapter caches up to 25 workspace-scoped message headers without bodies or attachments. MailOS remains disconnected because its current JMAP API key scope also grants submission. Outreach is not connected.

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
- AI is disabled by default. An explicit provider selection is required; the summary sends bounded prospect context and saved evidence to that configured endpoint. The selected provider/model and disclosure are shown. Citations are checked against supplied evidence IDs. No silent provider fallback occurs. No provider has passed a real ProspectOS provider test, and operators must not submit confidential material to an unapproved cloud provider.
- MailOS inbox sync is a disconnected, read-only-intent adapter: it requests bounded message headers and thread state, never bodies or attachments, and scopes records to a workspace. The currently documented MailOS `jmap` key scope also permits submission, so the adapter stays disabled until MailOS supplies a separately scoped read-only authorization. Sending, drafts and approvals are not implemented.
- Provider adapters expose capabilities and health rather than assuming a connection works.
- Website inspection applies SSRF checks, robots rules, PostgreSQL-shared host pacing/concurrency limits and bounded response/time budgets. Terms compliance remains an operator responsibility; robots.txt does not substitute for it.
- MCP tools call application services and remain workspace-scoped; no generic shell, filesystem or unrestricted HTTP tool is allowed.
- External actions require explicit approval by default.
