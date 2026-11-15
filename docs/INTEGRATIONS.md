# Integrations

## Current release state (2026-11-14)

### AI

AI is disabled by default (`AI_PROVIDER=disabled`) and does not block app startup. `AiProviderConfiguration` selects exactly one configured provider, exposes provider/model and a data disclosure, and never falls back to another endpoint. The selected provider is stored with every accepted evidence summary. A harmless provider test is available from Integrations and shares the 10-actions-per-user-per-hour limit with summaries.

Supported configuration paths:

- `ollama`: Symfony AI Generic Platform pointed at an owner-controlled local Ollama OpenAI-compatible endpoint. The host machine's `host.docker.internal` endpoint is a local-development path; it is not available to Upsun production by default.
- `gemini`: Symfony AI Generic Platform pointed at Google's documented OpenAI-compatible endpoint (`https://generativelanguage.googleapis.com/v1beta/openai/`). The project tier must be verified by the owner in AI Studio; ProspectOS does not infer the billing tier from a key. Google documents a free tier with limited model access and says prompts may be used to improve its products on the free tier. The paid tier has different data-use terms and can incur charges.
- `huggingface`: optional existing Symfony AI Hugging Face bridge; requires `HF_TOKEN` and `HF_MODEL`.
- `openai-compatible`: HTTPS endpoint with operator-configured model and key; billing and data terms depend on that service.

Symfony AI Bundle, Generic Platform, and Hugging Face Platform remain locked at 1.0.0-BETA1. Generic Platform is configured using its official `base_url` and `api_key` settings. No Hugging Face or Gemini key is configured. Ollama is installed locally and `qwen3.5:4b` (3.3 GB) was already present, so no model was downloaded. A harmless local inference attempt did not return the exact provider-test response and was stopped; local inference is therefore **not verified as working in ProspectOS**. Do not enable it for prospect reports until the exact-response test passes.

Google pricing, tier/data-use, and model access change; check the official [Gemini API pricing](https://ai.google.dev/gemini-api/docs/pricing), [billing](https://ai.google.dev/gemini-api/docs/billing), [models](https://ai.google.dev/gemini-api/docs/models), and [OpenAI compatibility](https://ai.google.dev/gemini-api/docs/openai) pages before configuring it. Generic Platform and Ollama integration details are in the [Symfony AI Bundle docs](https://symfony.com/doc/current/ai/bundles/ai-bundle.html) and [Ollama compatibility docs](https://ollama.com/blog/openai-compatibility).

### MailOS

MailOS version 0.5.4 was reviewed, including its API documentation, email-assistant/MCP notes, JMAP handler, and API-key scope code. It exposes JMAP discovery/session, mailbox-scoped `Email/query`, `Email/get`, and `Thread/get`. The MailOS API-key scope `jmap` also enables JMAP submission, so it does not satisfy ProspectOS's separate read-versus-send permission requirement. Its REST `read` scope is separate from `send`, but its current `GET /api/v1/messages` response contains message content; ProspectOS does not use it for header-only sync.

`MailOSInboxProvider` implements the documented read-only JMAP calls and asks for only IDs, thread IDs, address headers, subject, date, read state, and thread counts. `MailboxMessage` stores those fields only, with a workspace-scoped unique key; `/inbox` and thread detail use workspace filters. The adapter uses the fixed verified MailOS origin and refuses redirects and cross-origin API URLs. It is disconnected by default and has only been tested against mocked JMAP responses. **Do not configure a `jmap` key for ProspectOS until MailOS offers a read-only JMAP scope or a header-only API authenticated by its existing read-only scope.** No mailbox key was present, no private messages were read, and no message was sent.

The current MailOS local API docs establish `POST /api/v1/send` with a separate `send` scope, but ProspectOS does not yet implement drafting, immutable approval records, suppression/consent checks, sending, or response recording. No outbound operation is available.

### Other providers

SearXNG remains the only configured and previously real-tested external discovery service. Google OIDC code is implemented but unconfigured and not live-tested. WhatsApp/social, CRM, transactional password-reset sender, GitHub CI, and Upsun are not connected.

## Framework

Symfony AI Bundle, Symfony MCP Bundle and Symfony's AI MCP Tool bridge are installed and locked. Package compatibility was resolved through Composer. The MCP Bundle exposes server and client capabilities; no external provider credential or remote MCP server has been configured.

## AI provider implementation

`EvidenceSummaryGenerator` receives the explicitly selected Symfony AI `PlatformInterface` and model from `AiProviderConfiguration`. AI is disabled by default. Supported configuration is local Ollama, Google's Gemini OpenAI-compatible endpoint, Hugging Face, or another HTTPS OpenAI-compatible endpoint. There is no automatic fallback. The signed-in user sees which provider/model receives prospect context and bounded saved evidence, plus a data-use disclosure. Web evidence is treated as untrusted prompt input, citations are validated against submitted evidence, and accepted summaries record provider/model/time/source IDs. The Integration Hub has a harmless provider test; summary and test requests share a 10/hour per-user limit.

No provider has passed a real ProspectOS provider test. The existing local Qwen3.5:4b did not return the exact harmless expected response, so local inference is not verified. No AI key is configured. Do not describe any configured path as connected until the application test and report generation both succeed.

## Web search

Docker Compose runs the official SearXNG image `ghcr.io/searxng/searxng:2026.11.11-248b8ffc0`, pinned to the Linux/amd64 digest `sha256:46de7f6d2dcc11f11cc801f2e1234a519e29ea7deb31d8ae5efdd03a46566722`. The container has no published host port; only the app and worker use it through `http://searxng:8080`. `/etc/searxng/settings.yml` enables JSON output for the app. The Integration page can send a real sample query and shows the API result count or a useful provider failure.

`SearXNGDiscoveryProvider` sends the research objective as a form-encoded POST and stores up to five distinct public host results with title, URL, excerpt, source engine, retrieval method and timestamp. `PublicUrlPolicy` rejects result links with credentials, unsafe schemes/ports, local or metadata destinations, or restricted DNS addresses. Search results are labeled `search_result`, category `Web search candidate`, location `Not verified`, and are left unscored until evidence supports scoring. Search-result text and URLs are untrusted. This is web-result discovery, not authoritative business verification or automatic website crawling. The seeded `DemoProspectDiscovery` mode remains available and clearly synthetic.

SearXNG requires no ProspectOS API key, but it forwards queries to configured upstream engines. Those engines can rate limit, block, log or return incomplete results; the user-facing form discloses that query flow. SearXNG itself is licensed AGPL-3.0-or-later; its official container is used without modifying its source. Review upstream engine terms before production use. The public instance is disabled from Compose, but this local setup is not a supported public service deployment.

No SMTP server, Gmail/Microsoft app, Meta app, WhatsApp Business account or social publishing permission is connected.

An optional website URL can be inspected through Symfony HttpClient. The worker resolves and validates all A/AAAA addresses, pins the request to a public IP, manually revalidates up to three redirects, allows only HTML, and enforces a 1 MB body limit and total timeout. This is a single-page metadata inspection, not search or business verification. Before fetching, it checks robots rules, applies a shared PostgreSQL per-host lock and two-second start interval, and requires operators to review site terms. A successful page fetch was smoke-tested against `https://example.com/`; mock tests cover rejected destinations and responses. Do not expose it publicly until production deployment configuration and security review are complete.

No outreach can be sent from the application.

## MCP

The Symfony MCP Bundle and official PHP SDK dependency are installed. The synthetic `demo-workspace-summary` tool is available only over local STDIO. The Streamable HTTP endpoint `/mcp/workspace` requires the normal authenticated app session and exposes two read-only tools: `get_workspace_summary` and `get_prospect_report`. Both resolve the caller's workspace through `CurrentWorkspaceProvider`; prospect lookup filters by workspace before returning at most 50 timestamped evidence items and the deterministic score breakdown. An ID from another workspace returns a generic protocol error without disclosing the record. An actual MCP initialize handshake and `tools/call` request were exercised in the authenticated functional test.

The endpoint uses the app's session cookie, MCP SDK DNS-rebinding protection and local Compose defaults. OAuth bearer-token authentication and external client onboarding are not configured; do not expose this endpoint publicly. Nyholm PSR-7 1.8.2 provides the PSR-17 factories required by the HTTP transport. No write tools, remote MCP client or invocation audit log are implemented.
