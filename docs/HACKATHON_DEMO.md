# Hackathon demo: Evidence Court

Deadline: **22 November 2026, 18:00 CET** (22:00 Pakistan time). Deliverables are a public repository a juror can clone and run, and a **90-second video of the real product**. Jurors judge creativity, whether it actually works, what Symfony AI and the PHP MCP SDK unlocked, and craft. Submit at https://symfony.com/hackathon with **Submit your project** (SymfonyConnect sign-in).

## Pitch (one sentence)

ProspectOS puts every sales lead on trial: two Symfony AI agents argue for and against it using only saved evidence, a PHP clerk strikes any claim without a valid citation, and a human rules. That ruling becomes team memory, and outside agents can open hearings over MCP but can never rule.

## Before recording

- Connect a capable model in the **demo workspace** (Integrations → AI). Groq or Hugging Face gives much better arguments and real tool calls. Local `qwen2.5:1.5b` works, but it argues weakly and does not call tools.
- Run one hearing in advance so the model is warm, and so you know a good lead to show.
- Use only the fictional demo workspace. Close other tabs, and keep keys, inboxes and dashboards off screen.
- Record at 1280×800 or larger. FFmpeg is installed locally if you need to trim: `ffmpeg -ss 00:00:03 -i raw.mp4 -t 90 -c copy demo.mp4`.

## 90-second script

| Time | Screen | Say |
|---|---|---|
| 0–8 s | Sign-in page → **Enter the demo workspace** | "ProspectOS helps small teams decide which leads deserve their time." |
| 8–18 s | Dashboard: four-step strip | "It researches leads, saves every finding with its source, and scores them with a deterministic formula. Then it puts them on trial." |
| 18–28 s | A prospect report → **Open Evidence Court** | "Two Symfony AI agents, an Advocate and a Skeptic, can only use read-only tools over this lead's evidence." |
| 28–50 s | Hearing page: scale bar, both columns, **Tools called** chips, a **Struck** claim | "Every claim must cite a saved record. A plain PHP clerk, not a model, strikes anything uncited, made up or repeated. Here it struck a claim that cited a record that doesn't exist." |
| 50–62 s | Pick **Pursue**, type a reason, **Record ruling** → **Memory** | "Only a human rules. The ruling becomes team memory, and future hearings on similar leads can cite it as precedent." |
| 62–82 s | Claude Desktop or Claude Code: "List the ProspectOS demo leads and put the best one on trial" | "Over the official PHP MCP SDK, any agent can open a hearing and read the transcript. There is no ruling tool, by design." |
| 82–90 s | Back in the app, the MCP-opened case shows "Opened by an MCP client" | "Symfony AI for the agents, MCP for the outside world, PHP for the guardrails. Clone it and try it in five minutes." |

## Verified versus not verified (keep the video honest)

- Verified locally (2026-11-16): the full hearing through Symfony AI Agent with local Ollama `qwen2.5:1.5b`, from the UI (async worker), the CLI (`app:court:hear`) and the STDIO MCP server (`open_evidence_court` via raw JSON-RPC). Rulings are saved to team memory. Unit and functional tests cover a scripted `read_evidence` tool call that travels round-trip through the Symfony AI agent loop.
- Not yet verified: a cloud model (Groq or Hugging Face) running a hearing with real tool calls, and a live Claude Desktop/Code session against the STDIO server. Run both before recording.
- Email features are optional and not part of the demo. No mailbox is live-verified.
- No hosted deployment exists. The demo runs locally.
