# Demo video voiceover script

Target length: about 84 seconds (under the hackathon's 90-second limit), about 205 words at roughly 150 words per minute. Bracketed notes describe the shot and are not read aloud.

| Time | Shot | Voiceover |
|---|---|---|
| 0:00 – 0:06 | Intro: mascot and title animate in | Every sales team wastes days chasing leads that were never a fit. ProspectOS puts every lead on trial first. |
| 0:06 – 0:16 | Dashboard and research | It researches businesses, saves every finding with its source, and gives each lead a deterministic, explainable score. |
| 0:16 – 0:24 | Opening Evidence Court | Then you open Evidence Court. Two Symfony AI agents, an Advocate and a Skeptic, take the case. |
| 0:24 – 0:36 | Zoom on the tool calls | They can't just make things up. They call real tools, reading the evidence and checking the score, and every claim must cite a saved record. |
| 0:36 – 0:46 | Struck claims | A clerk written in plain PHP, not a model, checks every citation. Anything uncited, invented or repeated is struck from the record. |
| 0:46 – 0:58 | Ruling and team memory | Only a human can rule. Pursue, reject, or ask for more evidence. And every ruling becomes team memory, cited as precedent in future hearings. |
| 0:58 – 1:12 | MCP terminal card and the MCP-opened hearing | With the official PHP MCP SDK, any agent, like Claude or your own, can open a hearing and read the transcript. But MCP can argue. It can never rule. |
| 1:12 – 1:24 | End card | ProspectOS. Built with Symfony AI and the PHP MCP SDK. Clone it, and run it in five minutes. |

Everything shown is the real product running locally: Groq `openai/gpt-oss-120b` through Symfony AI, real tool calls, and a hearing opened over the STDIO MCP server. The demo workspace contains fictional sample businesses only.

## Script v2.1: fuller explanation, including email (about 82 s, ~205 words)

Corrected on 2026-11-17 after a claim-by-claim check against the code and the running app: live research does not produce scores (only the demo fixtures carry them), "any business mailbox" excludes Microsoft accounts, MCP was verified with a generic STDIO client but not with Claude itself, and the clone-and-run time is unverified.

Use this instead of v1 if the video should cover the email system. Every email claim matches verified behaviour: one-click mailbox connect and login test (verified with a real Gmail app password), inbox reading (verified, 10 messages synced), the workspace suppression list, sending paused by default, and per-message human approval for the only messages ProspectOS can send (controlled test emails). It does not claim Gmail drafts, because Google OAuth is not configured.

| Section | Shot | Voiceover |
|---|---|---|
| Hook and hackathon | Intro: mascot, title, hackathon badge | Every sales team loses days chasing leads that were never a fit. ProspectOS, our entry for the Symfony AI and MCP Hackathon, puts every lead on trial first. |
| Research | Dashboard, prospect report with score breakdown | It finds candidate businesses on the web and keeps every finding with its source. Scores come from a transparent formula, never from the AI, and unknowns stay unknown. |
| Evidence Court | Open hearing, counsel reading, tool-call chips | Then the hearing begins. Two Symfony AI agents, an Advocate and a Skeptic, argue the case, calling real tools to read the evidence. |
| The clerk | Admitted claims with citation chips, then struck claims | A clerk written in plain PHP checks every citation. Anything uncited or invented is struck from the record. |
| Human ruling | Pursue click, "Ruling: Pursue", Memory page | Only a human can rule, and every ruling becomes team memory that later hearings can cite as precedent. |
| Email outreach | Email hub ("Your mailbox"), mailbox form auto-detecting a typed example address (do not submit), connection status, suppression list, test-approval page | When a lead earns a yes, email stays just as careful. Connect Gmail or most business mailboxes in about a minute. ProspectOS detects your provider and tests the login. It reads your recent inbox, checks every recipient against your suppression list, and never sends anything without a person approving it. |
| MCP | Real STDIO MCP call, the how_to_rule reply, typographic beat | With the official PHP MCP SDK, any MCP client can open a hearing and read the transcript. But MCP can argue. It can never rule. |
| End card | Mascot wave, git clone line, hackathon badge | ProspectOS. Built with Symfony AI and the PHP MCP SDK. Clone it, and run it yourself with Docker. |

Privacy for the email shots: the demo workspace is connected to the owner's real Gmail. Never show the inbox list, message contents or the connected address. Show the provider detection with a typed example such as `you@gmail.com` (not submitted), the suppression list, the paused-sending state and the approval screen. Blur or crop anything that reveals the real mailbox.
