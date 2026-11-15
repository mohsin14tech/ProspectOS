# ProspectOS

ProspectOS is an early-access business research workspace by 14 Technologies. It helps a team discover candidate businesses, collect source-backed evidence, explain opportunity scores and prepare for future human-reviewed outreach.

## Current state

The Symfony app has a responsive Command Centre, database-backed Research Workspace, workspace-scoped reports, Integration Hub, inbox UI and owner-only first-run authentication. Docker Compose includes an internal SearXNG metasearch service; research runs can return up to five source-linked web candidates. Candidates are not verified businesses and are not scored without enough evidence. A bounded, SSRF-checked single-page website inspection is available. AI is optional and disabled by default; provider selection supports local Ollama via Symfony AI Generic Platform, Google's Gemini OpenAI-compatible endpoint, Hugging Face, or another HTTPS OpenAI-compatible endpoint. No provider is currently verified as working in ProspectOS. The installed local Qwen model's harmless provider test did not return the exact expected response. Google OIDC login and password-confirmed identity linking are implemented but unconfigured and not live-tested. MailOS inbox code requests headers only but remains disconnected because the documented MailOS `jmap` scope also enables submission; ProspectOS requires a separate read-only scope before enabling sync. Email verification/reset, drafts, approvals and outbound sends are not implemented.

Symfony AI and MCP Bundle are installed and pinned in `composer.lock`. A workspace-scoped HTTP MCP endpoint at `/mcp/workspace` exposes read-only summary and prospect-evidence tools to authenticated workspace members. It uses the app's session login and remains local-only; OAuth bearer-token login for external MCP clients is not configured. A separate synthetic demo tool is available over STDIO.

## Requirements

- Docker Desktop with its Linux engine running
- Docker Compose v2
- Internet access for images, the Tailwind CLI, and upstream web search engines

## Run locally with Docker

```powershell
docker compose up --build -d
```

Open `http://127.0.0.1:8000`. On a fresh database, set a random `APP_OWNER_SETUP_TOKEN` in the ignored `.env`, open `http://127.0.0.1:8000/setup`, and enter that token to create the first owner. The setup route closes after the first account exists; remove the token from the environment after provisioning. Compose applies database migrations and idempotently seeds the synthetic demo workspace on startup. Stop services with `docker compose down`; named data volumes are retained.

The app, PostgreSQL, Redis, Messenger worker and pinned SearXNG container are defined in Compose. SearXNG is not published to a host port; the app and worker reach it through the Compose network. Search terms are sent onward to upstream engines. To enable AI, choose one explicit `AI_PROVIDER` in the ignored `.env`; provider/model and data disclosure are shown before use, and no fallback is performed. Local Ollama uses `AI_BASE_URL=http://host.docker.internal:11434/v1` and the model already installed on the host; that URL is not a production deployment. Gemini requires the Google endpoint, an API key, an explicitly chosen model, and owner verification of project tier and data terms. Never set a paid key without billing authorization. Prospect summaries are limited to 10 actions per signed-in user per hour. Local default database and SearXNG settings are for development only. Do not expose this stack publicly or reuse them in production. There is no self-service password reset, email verification, team invitation, email drafting, approval or outbound messaging yet. Google sign-in needs an OAuth client configured in the ignored `.env`; it has not been authorized or live-tested.

## Run with local PHP

Requires PHP 8.4, Composer and PostgreSQL. Apply migrations and seed fixtures before opening database-backed screens:

```powershell
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:demo:seed
php bin/console tailwind:build
php -S 127.0.0.1:8000 -t public
```

The Tailwind CLI download and Composer packages require network access on first install. Do not put live keys in committed files.

## Project documentation

- [Project context](PROJECT_CONTEXT.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Integrations](docs/INTEGRATIONS.md)
- [Security](docs/SECURITY.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Hackathon demo](docs/HACKATHON_DEMO.md)
- [Build brief](ProspectOS_CODEX_MASTER_BUILD_PROMPT.md)

## License

Released under the MIT License. See [LICENSE](LICENSE).
