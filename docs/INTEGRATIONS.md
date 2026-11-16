# Integrations

## AI providers

AI is disabled by default (`AI_PROVIDER=disabled`). Workspace owners/admins can open **Integrations → Configure AI provider**, save separate provider/model connections, test each one independently, then select the active route. Saving or testing another connection does not erase or replace the active provider's credentials. A successful test is required after every settings change before summaries can use that route. There is no silent fallback. Summary and provider-test requests share a limit of 10 actions per signed-in user per hour.

The UI supports Gemini, OpenAI, Groq, OpenRouter, Hugging Face Inference Providers, local Ollama, and disabling AI. Cloud provider base URLs are fixed to known provider endpoints; the UI does not accept arbitrary remote endpoints, preventing saved API keys from being forwarded to an operator-entered host. The optional Ollama endpoint is restricted to local Docker-reachable names and port 11434. The Symfony AI Generic Platform implements OpenAI-compatible chat-completions requests using `/chat/completions` appended to the configured versioned base URL. Gemini publishes an OpenAI-compatible endpoint, Groq documents its OpenAI base URL, and Hugging Face provides an OpenAI-compatible router ([Gemini](https://ai.google.dev/gemini-api/docs/openai), [Groq](https://console.groq.com/docs/openai), [Hugging Face](https://huggingface.co/docs/inference-providers/index)). Compatibility does not guarantee every provider-specific feature.

Provider keys are encrypted at rest in the workspace settings table with authenticated secretbox encryption and a key derived from `APP_SECRET`. They are never re-displayed. Leaving the key field blank preserves the existing key only for that provider. Rotating `APP_SECRET` makes saved keys unreadable; re-enter them afterward. The database contains one row per workspace/provider with provider/model, endpoint only for Ollama, encrypted credential, last editor, active-route flag and successful-test timestamp/latency—not the raw key. Only one saved route is active at a time.

The app sends the prospect name, objective, source URLs, bounded evidence and up to five similar team decisions to the selected provider. The UI displays this disclosure. Prior decisions are preferences only and cannot be cited as facts about the current prospect. `qwen2.5:1.5b` has passed exact-marker inference from Windows, through the app container, and through ProspectOS's Symfony AI provider test in an isolated test workspace. No provider connection is saved in the owner's application workspace, and Gemini has not passed an inference test. Review current model access, billing, retention, and data terms in the selected provider account before use. See [Gemini API key security](https://ai.google.dev/gemini-api/docs/api-key) and [Symfony AI platform setup](https://ai.symfony.com/cookbook/ai-bundle-fast-track).

### Local model setup

Install Ollama on the Windows host, choose the model storage directory on D: **before** downloading a model, run `ollama pull <model-id>` for a model that fits available memory and disk, then select Ollama in provider setup with endpoint `http://host.docker.internal:11434/v1` and the exact model ID. On Windows Ollama reads `OLLAMA_MODELS` from the user environment; restart Ollama after changing it. Model files can be large, so check free D: space first. ProspectOS does not install Ollama or download weights automatically. [Ollama Windows docs](https://github.com/ollama/ollama/blob/main/docs/windows.mdx).

## Email connections — current implementation

See [MAIL.md](MAIL.md) for the complete setup, capability and security reference. Gmail OAuth now supports recent inbox reads and plain-text bodies, finalized-prospect drafts and Gmail reply drafts. It requests `gmail.readonly` plus `gmail.compose`. Standard IMAP/SMTP supports a bounded inbox cache and separately approved labelled tests to a controlled recipient, with encrypted credentials, TLS, DNS pinning, pause controls, suppression checks and one-attempt claims. Gmail has an app-password preset when permitted by the account. One Gmail and one IMAP/SMTP connection may coexist per workspace.

No real mailbox is connected or verified. The owner explicitly skipped MailOS, Resend and Cloudflare. The owner's active Ollama qwen2.5:1.5b connection now passed its real provider test in 12,328 ms. The older email and provider-readiness descriptions below are retained as historical context and superseded by this section and `MAIL.md`.

## Historical Gmail draft-only integration

MailOS and its inbox/JMAP sync have been removed. ProspectOS does not read a connected mailbox. Gmail is the first outreach provider and is intended only to create a provider-side draft from a **finalized** lead. A human enters the recipient, subject and body, confirms they checked the recipient's contact basis, and explicitly requests draft creation. ProspectOS calls Gmail `users.drafts.create`; no send method exists. It stores the provider draft ID, recipient, subject, actor and timestamp. The body remains in Gmail.

The Gmail access token refresh credential is encrypted in the database with a key derived from the app secret. Set `GOOGLE_MAIL_CLIENT_ID`, `GOOGLE_MAIL_CLIENT_SECRET` and `GOOGLE_MAIL_REDIRECT_URI` in the private runtime environment. Enable the Gmail API and register the exact callback shown at `/outreach`. Google OAuth state is session-bound, and callbacks require the authenticated workspace session.

**Scope limitation:** Gmail `gmail.compose` permits managing drafts and sending email. ProspectOS only invokes draft creation, but the Google grant itself is broader than that operation. Google classifies the scope as restricted and may require consent-screen verification for public use. The connection and live draft API operation have not been tested with owner credentials. The app has no send function; users must review and send manually in Gmail. Disconnecting removes the encrypted refresh token from ProspectOS, but the user must revoke Google's authorization in their Google Account if they want to remove the provider grant.

The workspace suppression list blocks ProspectOS draft creation for an address that has been recorded there. It is currently maintained manually; there is no unsubscribe webhook, campaign sender, bulk messaging, in-app approval ledger or delivery/reply sync. A user attestation is not a substitute for legal review or provider policy compliance.

## Web search and website inspection

Docker Compose runs SearXNG on the private app network. It returns at most five distinct-host candidates with source URL, title and excerpt. These are unverified results, not authoritative company records. Search objectives are sent to upstream engines; avoid personal or confidential input. SearXNG is AGPL-3.0-or-later and upstream engines may rate-limit, block or log queries.

An optional public website URL is inspected as a single HTML page. The worker checks robots.txt, applies SSRF protections, pins a validated public IP, rechecks redirects and caps time/response size. This does not verify company identity or fit; site terms remain an operator responsibility.

## MCP

The synthetic `demo-workspace-summary` tool is available over local STDIO. Authenticated HTTP at `/mcp/workspace` exposes workspace summary and prospect report tools, including lead status and human decision history. These tools are workspace-filtered and read-only. OAuth bearer access for external MCP clients and an outbound MCP client integration are not yet implemented. Upsun currently blocks `/mcp` at the edge, and no live Upsun deployment has been tested.

## Other integrations

Google OIDC sign-in/account linking is separate from Gmail outreach, requires its own configured OAuth client and has not been live-tested. Microsoft Graph, SMTP, social networks, CRMs, inbound webhooks and remote MCP servers are not connected.

## Provider setup audit — 2026-11-15

MailOS stays removed. The owner wants direct, easy provider-native email connections. **Gmail OAuth/draft is currently the only email connector.** Resend outbound and Cloudflare Email Routing inbound are not implemented. Cloudflare Email Routing can forward mail to a verified address or a Worker; it is not an IMAP mailbox connection. A Worker-based inbound path needs authenticated, replay-protected, size-limited delivery into Symfony and explicit workspace mapping before any message body is persisted. Refer to Cloudflare [routing rules](https://developers.cloudflare.com/email-service/configuration/email-routing-addresses/) and the [email Worker handler](https://developers.cloudflare.com/email-service/api/route-emails/email-handler/).

AI provider settings now permit multiple provider records per workspace and a separately selected active route. Local workspace state remains AI-off with zero saved encrypted provider keys and no Gmail connection. A strong random `APP_SECRET` is now passed into the app and worker through local Compose; never commit or print it. Gmail-specific OAuth client credentials, Resend key/from address, and Cloudflare account/token are missing.
