# ProspectOS · Evidence Court

**Put a sales lead on trial before you spend a week on it.**

ProspectOS is a Symfony research workspace for small business-development teams. It finds leads, saves every finding with its source, and scores them with a deterministic, explainable formula. Then comes the part that is "more than another chatbot": **Evidence Court**.

It reads like a chat between two colleagues, in plain language:

1. An **Advocate** agent opens with the 2–3 best reasons to pursue the lead.
2. A **Skeptic** agent replies to each point ("Replying to Advocate A1…"): what is weak, unverified or missing.
3. The Advocate answers the strongest worries back, conceding when they are right and naming what would settle it.
4. All three turns are Symfony AI agents with three read-only tools bound to that single lead: `read_evidence`, `score_breakdown` and `team_precedents`.
5. A **Clerk**, which is plain PHP and not a model, checks every claim. A claim that does not cite a real saved record (`[E12]`), the score (`[SCORE]`) or a past team decision (`[M3]`) is **struck**. So is a claim that cites a record that doesn't exist, or a Skeptic claim that just repeats the Advocate. Hallucinations can't talk their way in.
6. **You rule**: pursue, reject, or ask for more evidence. Your ruling and reason become team memory, and future hearings on similar leads can cite them as precedent.

Over **MCP** (official PHP MCP SDK), Claude Desktop, Claude Code or any MCP client can run the whole court: open a hearing and watch it live (progress notifications), read transcripts and evidence as resources, use ready-made prompts, see the hearing as an interactive card (an MCP App), and ask the human to rule. The ruling is an **elicitation**: the client shows the person a form, and only their answer is recorded. No tool accepts a decision as an argument, so a model can argue but never rule.

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
2. **No key yet?** Open **Evidence Court** and click **Watch recorded hearing** on any demo lead. The three demo leads replay hearings recorded from Groq `openai/gpt-oss-120b`; only the model's words are replayed, while the agent loop, evidence tools and clerk run live. It is labelled "Recorded demo hearing".
3. **Connect an AI model** in **Integrations → AI** (about a minute, see below) and press **Send test prompt**. Every connection must pass a real test before it is used. Now **Open hearing** runs live: messages appear as each side writes them.
4. Record your **ruling**, then open **Memory** to see it saved as team precedent.

Judges: [docs/JUDGES.md](docs/JUDGES.md) is a five-minute tour with where-to-look pointers and honest limits.

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

Then ask: *"List the ProspectOS demo leads and put the one with the highest score on trial."* Or pick the `cross_examine` prompt. What the server offers:

| MCP feature | What it does in ProspectOS |
| --- | --- |
| Tools | `list_demo_leads`, `open_evidence_court`, `get_court_case`, `request_ruling`, `show_court_case` |
| Progress notifications | `open_evidence_court` streams each step live: "Advocate called read_evidence(E6)", "Advocate rested: 1 admitted, 3 struck"… |
| Elicitation | `request_ruling` asks the human for Pursue / Reject / Needs more evidence plus a reason, in a form shown by their client. Declining records nothing. Clients without forms are pointed to the app. |
| Resources | `prospectos://court/docket`, plus templates `prospectos://court/{caseId}` and `prospectos://lead/{leadId}/evidence` |
| Prompts | `cross_examine` (run a hearing, check each admitted claim against its evidence, then ask me to rule) and `brief_me` (embeds a transcript as a resource) |
| MCP App | `show_court_case` renders `ui://evidence-court/hearing`: admitted claims highlighted, struck claims crossed out, and a "Rule on this case" button that opens the same human-only form |

Signed-in workspace members get the same court (scoped to their workspace) plus `get_workspace_summary` and `get_prospect_report` over authenticated Streamable HTTP at `/mcp/workspace`.

How this is verified: [`tests/Functional/McpStdioEndToEndTest.php`](tests/Functional/McpStdioEndToEndTest.php) spawns `bin/console mcp:server` and drives it with the official PHP MCP SDK **client**: it answers and declines the ruling form, reads every resource and prompt, and renders the App. Elicitation and progress were also run by hand against a live Groq hearing over STDIO and over authenticated HTTP, on protocol revisions 2025-06-18, 2025-11-25 and 2026-07-28. Rendering inside Claude's own MCP Apps host has not been tested yet.

You can also run a hearing from the terminal:

```bash
docker compose exec app php bin/console app:court:hear 1
```

## What Symfony AI and the PHP MCP SDK made possible

- **Symfony AI Agent + Toolbox**: counsel are real agents. `CaseFileTools` exposes three `#[AsTool]` methods scoped to one lead in one workspace. The tool loop, argument resolution and result handling come from `symfony/ai-agent`. Every call is logged into the transcript, so you can see what each side actually read.
- **Symfony AI Platform**: one `PlatformInterface` behind Groq, Hugging Face, Gemini, OpenAI, OpenRouter and Ollama. The workspace admin chooses the provider; there is no silent fallback. The zero-key demo is a Platform too: `RecordedHearings` replays recorded model output through the same Agent, so tools and the clerk still run for real.
- **Symfony AI Store**: past team decisions are indexed in an in-memory `Store` and a `Retriever` finds those about similar leads, which counsel can cite as precedents. The default vectorizer is a small key-free lexical one (`LexicalVectorizer`); any embedding vectorizer can replace it.
- **PHP MCP SDK via `symfony/mcp-bundle`**: the court is a full MCP citizen over STDIO and authenticated HTTP. `#[McpTool]`, `#[McpResource]`, `#[McpResourceTemplate]` and `#[McpPrompt]` services plus one `#[AsMcpApp]` class, all reusing the `EvidenceCourt` service the UI uses. `ClientGateway::elicit()` is what makes "only a human rules" enforceable outside our own UI, and `ClientGateway::progress()` turns a 20-second agent hearing into something you can watch.
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
   MCP: tools · live progress · resources · prompts · MCP App card
        request_ruling ─► elicitation form ─► only the human's answer is recorded
```

Stack: Symfony 7.4 (PHP 8.4), Twig + Stimulus + Turbo, PostgreSQL 16 + Doctrine, Symfony Messenger (Doctrine transport) with a separate worker, Symfony AI 1.0 beta (Agent, Platform, Generic and Hugging Face bridges), `symfony/mcp-bundle` with `mcp/sdk` 0.9, and a private SearXNG container for web search.

Key files: [`src/Service/Court/EvidenceCourt.php`](src/Service/Court/EvidenceCourt.php), [`CaseFileTools.php`](src/Service/Court/CaseFileTools.php), [`CitationClerk.php`](src/Service/Court/CitationClerk.php), [`src/Mcp/Demo/DemoEvidenceCourtTool.php`](src/Mcp/Demo/DemoEvidenceCourtTool.php), [`McpCourtroom.php`](src/Service/Court/McpCourtroom.php), [`templates/court/show.html.twig`](templates/court/show.html.twig).

## Also in the box

- **Research**: web search through a private SearXNG container (results are labelled unverified candidates), or a single-page website inspection that is SSRF-protected, robots.txt-aware and size/time bounded.
- **Explainable score**: weighted ICP fit, opportunity, evidence quality, contactability and recency. Unknown dimensions are excluded rather than counted as zero, and coverage is shown.
- **Team memory**: every human decision is stored with its reason. Similar past decisions are offered to AI as clearly labelled preferences, never as facts.
- **Email (optional)**: connect Gmail, Yahoo, iCloud, Zoho or business email in about a minute with an app password. ProspectOS detects the provider, fills in the servers and tests the login. It reads your recent inbox; sending needs human approval, is limited to a test address you control, and is paused by default. See [docs/MAIL.md](docs/MAIL.md).
- **PWA**: installable, responsive down to 390 px, light and dark themes.

## Safety and honesty

- AI is off until an admin connects and tests a provider. No silent fallback.
- Evidence text is treated as untrusted (prompt-injection aware). Agents have read-only tools for one lead only, and a cap of six tool calls.
- Only people can rule, approve or send. Over MCP a ruling only comes from the elicitation form the person answers; no tool takes a decision argument, and a test enforces that.
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
