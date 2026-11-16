# ProspectOS · Evidence Court

**Put a sales lead on trial before you spend a week on it.**

ProspectOS is a Symfony research workspace for small business-development teams. It finds leads, saves every finding with its source, and scores them with a deterministic, explainable formula. Then comes the part that is "more than another chatbot": **Evidence Court**.

1. An **Advocate** agent argues why the team should pursue the lead.
2. A **Skeptic** agent argues what is weak, unverified or missing.
3. Both are Symfony AI agents with three read-only tools bound to that single lead: `read_evidence`, `score_breakdown` and `team_precedents`.
4. A **Clerk**, which is plain PHP and not a model, checks every claim. A claim that does not cite a real saved record (`[E12]`), the score (`[SCORE]`) or a past team decision (`[M3]`) is **struck**. So is a claim that cites a record that doesn't exist, or a Skeptic claim that just repeats the Advocate. Hallucinations can't talk their way in.
5. **You rule**: pursue, reject, or ask for more evidence. Your ruling and reason become team memory, and future hearings on similar leads can cite them as precedent.

Over **MCP** (official PHP MCP SDK), Claude Desktop, Claude Code or any MCP client can list leads, open a hearing and read the transcript. There is deliberately **no ruling tool**: an AI can argue, but only a person in the app can decide.

> Built for the [Symfony AI & MCP Hackathon 2026](https://symfony.com/hackathon) by 14 Technologies.

## Try it in five minutes

You need Docker Desktop (or Docker Engine with Compose v2) and an internet connection for the first build.

```bash
git clone https://github.com/mohsin14tech/ProspectOS.git
cd ProspectOS
cp .env.example .env
docker compose up --build -d
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

The first build downloads PHP packages and takes a few minutes. Then:

1. Open **http://127.0.0.1:8000** and click **Enter the demo workspace**. Sign-in is `demo@prospectos.test` / `prospectos-demo`. It holds three fictional sample businesses.
2. **Connect an AI model** in **Integrations → AI** (it takes about a minute, see below). Press **Send test prompt**. Every connection must pass a real test before it is used.
3. Open **Evidence Court**, pick a lead, click **Open hearing**, and watch the Advocate and the Skeptic argue. Struck claims appear crossed out with the Clerk's reason.
4. Record your **ruling**, then open **Memory** to see it saved as team precedent.

### Connect a model (pick one)

| Option | Cost | What to enter in Integrations → AI |
|---|---|---|
| **Groq** (recommended for the demo: fast, good tool calling) | Free tier | Create a key at [console.groq.com/keys](https://console.groq.com/keys). Choose a model that supports tool use, for example `llama-3.3-70b-versatile` or `openai/gpt-oss-20b` (use one your account lists). |
| **Hugging Face Inference Providers** | Free monthly credit | A token from [huggingface.co/settings/tokens](https://huggingface.co/settings/tokens) with "Make calls to Inference Providers" enabled, and a model such as `openai/gpt-oss-20b`. |
| **Ollama** (fully local) | Free | Install [Ollama](https://ollama.com/download), run `ollama pull qwen2.5:1.5b`, enter that model name. Endpoint `http://host.docker.internal:11434/v1`. Small local models work, but argue weakly and rarely call tools. |

Gemini, OpenAI and OpenRouter are also available. Keys are encrypted at rest with `APP_SECRET`, sent only to the provider's fixed endpoint, and never shown again.

### Use it from Claude Desktop or Claude Code (MCP)

ProspectOS ships an STDIO MCP server for the demo workspace. Use the absolute path to your clone.

Claude Code:

```bash
claude mcp add prospectos -- docker compose -f /absolute/path/to/ProspectOS/compose.yaml exec -T app php bin/console mcp:server
```

Claude Desktop (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "prospectos": {
      "command": "docker",
      "args": ["compose", "-f", "/absolute/path/to/ProspectOS/compose.yaml", "exec", "-T", "app", "php", "bin/console", "mcp:server"]
    }
  }
}
```

Then ask: *"List the ProspectOS demo leads and put the one with the highest score on trial."* The client calls `list_demo_leads` and `open_evidence_court`, and gets back the clerk-reviewed transcript plus a link where a human can rule. Signed-in workspace members also get `open_evidence_court`, `get_court_case`, `get_workspace_summary` and `get_prospect_report` over authenticated Streamable HTTP at `/mcp/workspace`.

You can also run a hearing from the terminal:

```bash
docker compose exec app php bin/console app:court:hear 1
```

## What Symfony AI and the PHP MCP SDK made possible

- **Symfony AI Agent + Toolbox**: counsel are real agents. `CaseFileTools` exposes three `#[AsTool]` methods scoped to one lead in one workspace. The tool loop, argument resolution and result handling come from `symfony/ai-agent`. Every call is logged into the transcript, so you can see what each side actually read.
- **Symfony AI Platform**: one `PlatformInterface` behind Groq, Hugging Face, Gemini, OpenAI, OpenRouter and Ollama. The workspace admin chooses the provider; there is no silent fallback.
- **PHP MCP SDK via `symfony/mcp-bundle`**: `#[McpTool]` services expose the court to outside agents over STDIO and authenticated HTTP. It took two small classes, and they reuse the same `EvidenceCourt` service the UI uses.
- **Deterministic guardrails around the AI**: the score (`OpportunityScorer`) and the citation check (`CitationClerk`) are plain PHP and unit-tested. The model argues; code decides what counts; a human rules.

## Architecture in one screen

```
Research (SearXNG search / bounded website inspection)
   └─► Evidence items (source URL, excerpt, verification state)  ─► OpportunityScorer (deterministic 0–100)
            │
            ▼
   Evidence Court ── HearCourtCase (Messenger, async worker)
     ├─ Advocate agent ─┐  Symfony AI Agent + Toolbox
     ├─ Skeptic agent  ─┘  tools: read_evidence · score_breakdown · team_precedents
     ├─ CitationClerk (PHP): admit / strike every claim
     └─ Human ruling ─► ProspectDecision (team memory) ─► precedent for future hearings
   MCP: list_demo_leads · open_evidence_court · get_court_case   (no ruling tool, by design)
```

Stack: Symfony 7.4 (PHP 8.4), Twig + Stimulus + Turbo, PostgreSQL 16 + Doctrine, Symfony Messenger (Doctrine transport) with a separate worker, Symfony AI 1.0 beta (Agent, Platform, Generic and Hugging Face bridges), `symfony/mcp-bundle` with `mcp/sdk` 0.9, and a private SearXNG container for web search.

Key files: [`src/Service/Court/EvidenceCourt.php`](src/Service/Court/EvidenceCourt.php), [`CaseFileTools.php`](src/Service/Court/CaseFileTools.php), [`CitationClerk.php`](src/Service/Court/CitationClerk.php), [`src/Mcp/Demo/DemoEvidenceCourtTool.php`](src/Mcp/Demo/DemoEvidenceCourtTool.php), [`templates/court/show.html.twig`](templates/court/show.html.twig).

## Also in the box

- **Research**: web search through a private SearXNG container (results are labelled unverified candidates), or a single-page website inspection that is SSRF-protected, robots.txt-aware and size/time bounded.
- **Explainable score**: weighted ICP fit, opportunity, evidence quality, contactability and recency. Unknown dimensions are excluded rather than counted as zero, and coverage is shown.
- **Team memory**: every human decision is stored with its reason. Similar past decisions are offered to AI as clearly labelled preferences, never as facts.
- **Email (optional)**: connect Gmail, Yahoo, iCloud, Zoho or business email in about a minute with an app password. ProspectOS detects the provider, fills in the servers and tests the login. It reads your recent inbox; sending needs human approval, is limited to a test address you control, and is paused by default. See [docs/MAIL.md](docs/MAIL.md).
- **PWA**: installable, responsive down to 390 px, light and dark themes.

## Safety and honesty

- AI is off until an admin connects and tests a provider. No silent fallback.
- Evidence text is treated as untrusted (prompt-injection aware). Agents have read-only tools for one lead only, and a cap of six tool calls.
- Only people can rule, approve or send. MCP tools cannot.
- Demo data is fictional and labelled as such. The demo login exists only when `DEMO_LOGIN_PASSWORD` is set, and never in `APP_ENV=prod`.
- Secrets live in the ignored `.env`. API keys and mailbox passwords are encrypted in the database.

## Development

```bash
docker compose exec app composer test                      # PHPUnit suite
docker compose exec app php bin/console lint:twig templates
docker compose exec app php bin/console doctrine:schema:validate
docker compose logs -f worker                              # watch hearings run
docker compose down                                        # stop (data volumes are kept)
```

For a real (non-demo) workspace, set `APP_OWNER_SETUP_TOKEN` in `.env`, open `/setup`, and create the first owner. Then remove the token. For anything other than local evaluation, generate a real secret, for example `openssl rand -hex 32`, and put it in `APP_SECRET`.

## Documentation

[Architecture](docs/ARCHITECTURE.md) · [Hackathon demo script](docs/HACKATHON_DEMO.md) · [Integrations](docs/INTEGRATIONS.md) · [Mail](docs/MAIL.md) · [Security](docs/SECURITY.md) · [Deployment (Upsun)](docs/DEPLOYMENT.md) · [Release audit](docs/RELEASE_AUDIT.md) · [Project context](PROJECT_CONTEXT.md)

## License

MIT. See [LICENSE](LICENSE).
