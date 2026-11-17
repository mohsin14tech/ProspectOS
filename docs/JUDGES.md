# ProspectOS for judges: what to try, and where to look

ProspectOS puts sales leads on trial. Two Symfony AI agents talk a lead through in plain language (the Advocate opens, the Skeptic replies to every point, the Advocate answers back), a plain-PHP clerk removes anything said without proof, and a person decides. Over MCP, an assistant can run the whole court but can never decide.

## 1. Run it (no API key needed)

```bash
git clone https://github.com/mohsin14tech/ProspectOS.git
cd ProspectOS
cp .env.example .env
docker compose up --build -d
```

Open http://127.0.0.1:8000 and click **Enter the demo workspace** on the sign-in page (fictional data only).

- Go to **Evidence Court** and press **Watch recorded hearing** on any demo lead. With no AI connected, the three demo leads replay hearings recorded from Groq `openai/gpt-oss-120b`. Only the model's words are replayed: the Symfony AI Agent loop, the evidence tools (real database reads), the clerk and the reply threading all run live. The page labels it "Recorded demo hearing".
- For live hearings, connect a free Groq key (about a minute) in **Integrations → AI**. Hearings then run in the background worker and appear message by message.

CI proves the zero-key path on every push: the workflow step *Zero-key demo hearing on a fresh clone* seeds a clean database and runs `app:court:hear 1` with no provider.

## 2. What to look at (5 minutes)

1. **A hearing** (`/court/{id}`): the chat. Each Skeptic reply quotes the point it answers. "Before writing: read evidence #4" shows the agent's real tool calls. Crossed-out messages were removed by the clerk, with the reason in plain words.
2. **Live mode**: open a hearing with an AI connected and watch the typing indicator ("Skeptic is reading evidence #4…") and messages arrive.
3. **Your turn**: rule with a reason. It becomes team memory; later hearings retrieve similar past decisions (Symfony AI Store) as precedents the agents may cite, labelled as preferences, never facts.
4. **MCP** (below): ask your assistant to cross-examine a lead and rule through the form.
5. **Email** (optional, your own mailbox): Integrations → Email. Sending stays approval-gated, limited to a test address, and checks the opt-out list.

## 3. MCP in two minutes

STDIO server (fictional demo workspace only):

```bash
claude mcp add prospectos -- docker compose -f /absolute/path/to/ProspectOS/compose.yaml exec -T app php bin/console mcp:server
```

| Try | What happens |
| --- | --- |
| Prompt `cross_examine` with lead `1` | The assistant opens a hearing, checks admitted claims against `prospectos://lead/1/evidence`, then calls `request_ruling` |
| `open_evidence_court` | Progress notifications stream each step and tool call |
| `request_ruling` | Your client shows a form (Pursue / Reject / Needs more evidence + reason). Only your answer is recorded; decline records nothing |
| `show_court_case` | An MCP App card (`ui://evidence-court/hearing`) in hosts that support MCP Apps |
| Resources | `prospectos://court/docket`, `prospectos://court/{caseId}`, `prospectos://lead/{leadId}/evidence` |

Signed-in users get the same court for their own workspace over authenticated Streamable HTTP at `/mcp/workspace`.

## 4. What Symfony AI and the PHP MCP SDK do here

| Piece | Where |
| --- | --- |
| Agent + Toolbox (`#[AsTool]` read_evidence, score_breakdown, team_precedents), three turns | `src/Service/Court/EvidenceCourt.php`, `CaseFileTools.php` |
| One `PlatformInterface` for Groq, Gemini, OpenAI, OpenRouter, Hugging Face, Ollama; zero-key replay is also a Platform | `src/Service/AiProviderConfiguration.php`, `src/Service/Court/RecordedHearings.php` |
| Store + Retriever for similar past decisions (in-memory store, local key-free vectorizer; any embedding vectorizer can replace it) | `src/Service/Memory/PrecedentRetriever.php`, `LexicalVectorizer.php` |
| MCP tools, resources, resource templates, prompts, progress, elicitation, MCP App | `src/Mcp/**`, `src/Service/Court/McpCourtroom.php`, `CourtDocket.php`, `templates/mcp/*` |
| Deterministic guardrails | `CitationClerk.php`, `OpportunityScorer.php` |

## 5. How it is tested

```bash
docker compose exec app php bin/phpunit
```

- `tests/Functional/McpStdioEndToEndTest.php`: the official PHP MCP SDK **client** spawns `bin/console mcp:server` and accepts/declines the ruling form, reads resources and prompts, renders the App.
- `tests/Functional/WorkspaceMcpTest.php`: no MCP tool accepts a decision/ruling/reason argument; workspace isolation.
- `tests/Functional/EvidenceCourtTest.php`: tool use, threading, rate-limit and malformed-tool-call retries, failure handling, human-only ruling.
- `tests/Functional/RecordedHearingReplayTest.php`: every demo lead replays a full conversation on a fresh install with no key.
- `tests/Functional/PrecedentRetrievalTest.php`: Store retrieval finds similar leads, never another workspace's.

## 6. Honest limits

- Live research (web search, website inspection) saves evidence but does not compute a score; only demo fixtures carry scores.
- The precedent vectorizer is lexical (shared words), not a semantic embedding model.
- The ruling form and the MCP App were verified with the PHP SDK client and a local MCP Apps host harness, not yet inside Claude's own UI.
- Email: Gmail, Yahoo, iCloud, Zoho and business IMAP/SMTP with app passwords. Microsoft accounts that only allow OAuth are not supported.
- A live hearing is three model turns; on Groq's free tier (8,000 tokens per minute) the third turn sometimes waits, and is skipped with a visible note if the limit persists.
