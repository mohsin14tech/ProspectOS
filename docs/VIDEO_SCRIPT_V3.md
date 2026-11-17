# Voiceover script v3.1 — full product walkthrough (about 85 seconds)

Target: about 210 words at a calm pace (~150 words per minute), under 90 seconds.
Every claim is backed by code and tests in this repository (checked 2026-11-17).
v3.0 covered mostly Evidence Court; v3.1 walks through the whole product.

**Final cut (2026-11-17):** at the owner's request nothing demo-related is shown, so the sentence "No API key? The demo replays recorded hearings." was cut from the recorded voiceover at its pauses (75.04–79.03 s). The final film is 81.8 s and uses only real data: a real SearXNG web search, a real inspection of a café website, live Groq hearings (#33 in the app, #36 over MCP with the ruling through the elicitation form).

## Plain text for the voice generator

Small sales teams waste weeks on leads that were never real. ProspectOS fixes that, step by step.

First, research. Describe who you sell to. ProspectOS searches the web privately, or inspects a single website safely, and saves every finding with its source and a quote.

Next, pick your AI. Groq, Gemini, OpenAI, OpenRouter, Hugging Face, or a local model. Nothing runs until you connect and test one.

Then, put the lead on trial. Two AI agents, built with Symfony AI, talk it through in plain language. The Advocate makes the case. The Skeptic replies to every point. They read the evidence with real tools, and a clerk, plain PHP, removes anything said without proof.

You decide. Your reason becomes team memory, and future hearings find similar past decisions on their own.

Now, outreach. Connect Gmail or another mailbox in about a minute. ProspectOS reads your replies, and nothing is sent without your approval and an opt-out check.

Over MCP, an AI assistant can open a hearing, watch it live, and ask you to rule through a form. It can argue. It can never decide.

No API key? The demo replays recorded hearings.

ProspectOS. Put every lead on trial. Built for the Symfony AI and MCP Hackathon.

## Shot list

| # | Narration (start) | Shot |
|---|---|---|
| 1 | Small sales teams waste weeks… | Title, mascot, process chart |
| 2 | First, research… | Research form → candidates labelled "unverified" → website inspection |
| 3 | …saves every finding with its source and a quote | Evidence list with source link and excerpt |
| 4 | Next, pick your AI… | AI integrations page, provider cards, "Test connection" |
| 5 | Then, put the lead on trial… | "Open Evidence Court" → live chat, typing indicator |
| 6 | The Advocate makes the case. The Skeptic replies… | Messages arriving, "Replying to Advocate A1" |
| 7 | …read the evidence with real tools… a clerk… | "Before writing: read evidence #4", a removed message |
| 8 | You decide… | "Your turn" → ruling → Team memory page |
| 9 | Now, outreach… | Mailbox setup (provider auto-detect), inbox (private data blurred), approval + suppression list |
| 10 | Over MCP… | MCP progress stream, elicitation form, hearing card |
| 11 | It can argue. It can never decide. | Typographic beat |
| 12 | No API key?… | Demo login, "Recorded demo hearing" badge |
| 13 | ProspectOS. Put every lead on trial… | End card |

## Claims and proof

- Private web search / single-site inspection, saved with source and quote: SearXNG container, `WebsiteInspector` (SSRF-protected, robots.txt-aware), `EvidenceItem`; `WebSearchResearchTest`, `WebsiteInspectorTest`.
- Provider choice, nothing runs until tested: `AiProviderCatalog`, `AiProviderSettingsTest`. Local model = Ollama.
- Two Symfony AI agents, plain language, replies per point: `EvidenceCourt`, `EvidenceCourtTest`.
- Real tools and a PHP clerk: `CaseFileTools`, `CitationClerk`, `CitationClerkTest`.
- Team memory and similar past decisions: `ProspectDecision`, `ProspectMemory` + `PrecedentRetriever` (Symfony AI Store), `PrecedentRetrievalTest`.
- Mailbox in about a minute, reads replies, approval + opt-out before sending: `BusinessMailController` (provider auto-detect + immediate test), `MailWorkflowTest`, `OutreachSafetyTest`, `docs/MAIL.md`. Supported: Gmail, Yahoo, iCloud, Zoho and business IMAP/SMTP with app passwords (not Microsoft OAuth-only accounts).
- MCP open / live / form, never decides: `McpStdioEndToEndTest`, `WorkspaceMcpTest`.
- Demo replay: recorded hearings (see `docs/JUDGES.md`).
- Do not say: "scores every lead", "any mailbox", "Claude" by name, or an exact setup time for the whole app.
