# Codex brief: produce the ProspectOS hackathon demo video

Paste everything below the line into Codex.

---

You are producing the final submission video for **ProspectOS**, an entry in the **Symfony AI & MCP Hackathon 2026** (deadline 22 November 2026, 18:00 CET; deliverables are a public repo plus a video of at most 90 seconds showing the real product). The jury judges four things: a creative idea, that it actually works, what Symfony AI and the PHP MCP SDK unlocked, and craft. The video must make all four obvious to a viewer who has never seen the app.

Read `AGENTS.md`, `PROJECT_CONTEXT.md`, `README.md`, `docs/HACKATHON_DEMO.md` and `docs/VIDEO_SCRIPT.md` first.

## The product in one paragraph

ProspectOS puts every sales lead on trial. Research saves each finding with its source and a deterministic, explainable score. In **Evidence Court**, two Symfony AI agents (Advocate and Skeptic, built on `symfony/ai-agent` with a Toolbox) argue a lead using three read-only tools: `read_evidence`, `score_breakdown`, `team_precedents`. A deterministic PHP `CitationClerk` admits claims that cite a real saved record (`[E12]`, `[SCORE]`, `[M3]`) and strikes uncited, invented or repeated ones. Only a human rules (Pursue / Reject / Needs more evidence); the ruling becomes team memory and precedent. Over MCP (official PHP MCP SDK via `symfony/mcp-bundle`, STDIO and authenticated HTTP) any agent can open a hearing and read the transcript, but there is deliberately no ruling tool.

## Assets already prepared (git-ignored, in `var/video/kit/`)

- `voiceover.mp3`: the final narration (ElevenLabs, 67.87 s, mono 44.1 kHz). **Do not regenerate or alter the wording.** Sync the picture to it.
- `raw-screen-recording.mkv`: an earlier 960x540 **dark-mode** capture. Do not use it in the final cut: the owner wants a **fresh recording in the light theme**. It is only a reference for pacing and shot choice.
- `scenes-light/`: **use these.** Light-theme versions of the motion scenes (paper `#F4F5F8` background, ink mascot, white label cards, a dark terminal window kept as a real terminal). `scenes/` holds the earlier dark versions for reference only.
- `brand/`: light and dark mascot SVGs (`mascot-light.svg`, `mascot-dark.svg`), `app-icon.svg`, and 1920x1080 transparent PNGs: `mascot-light.png`, `mascot-dark.png`, `app-icon.png`, `lockup-light.png` (mascot + wordmark), `lockup-dark.png`, `hackathon-badge-light.png`, `hackathon-badge-dark.png` ("Symfony AI & MCP Hackathon 2026 · Entry by 14 Technologies"). Crop with `cropdetect` or scale as needed. `brand.html` regenerates them through `render.py --alpha`.
- `scenes/`: HTML motion scenes (intro, MCP terminal, outro, labels) with a shared `kit.js`/`kit.css` and the vector mascot. `render.py` renders a scene frame by frame at 1920x1080 through headless Edge over the DevTools protocol (stdlib only; `python render.py scene.html out_dir seconds fps [--alpha]`; each page exposes `window.render(t)`). `assemble.py` is a working draft of the cut. You may reuse, improve or replace all of it.
- `fonts/`: TTF versions of Bricolage Grotesque 600/700, Inter 400/600 and JetBrains Mono for FFmpeg `drawtext` (this FFmpeg build cannot read WOFF/WOFF2). The web app's own fonts are in `public/fonts/` (OFL).

## Which voiceover

The owner may supply a new MP3 recorded from **script v2** in `docs/VIDEO_SCRIPT.md`. That version adds the hackathon mention to the hook and an **email outreach** section. If the supplied MP3 is v2, the timing table below (v1) no longer applies: measure every sentence boundary from the new file (`ffmpeg -i vo.mp3 -af silencedetect=noise=-38dB:d=0.35 -f null -`), build a new beat table, and use the v2 shot list in `docs/VIDEO_SCRIPT.md`. For the email section, follow its privacy rules exactly: the demo workspace is connected to the owner's real Gmail, so never show the inbox list, message contents or the connected address.

## Voiceover timing for v1 (measured with `silencedetect=noise=-38dB:d=0.35`)

| VO time (s) | Line |
|---|---|
| 0.00 – 3.77 | Every sales team wastes days chasing leads that were never a fit. |
| 4.35 – 7.32 | ProspectOS puts every lead on trial first. |
| 7.74 – 14.57 | It researches businesses, saves every finding with its source, and gives each lead a deterministic, explainable score. |
| 15.09 – 16.93 | Then you open Evidence Court. |
| 17.44 – 21.58 | Two Symfony AI agents, an Advocate and a Skeptic, take the case. |
| 22.00 – 23.22 | They can't just make things up. |
| 23.64 – 29.70 | They call real tools, reading the evidence and checking the score, and every claim must cite a saved record. |
| 30.14 – 34.48 | A clerk written in plain PHP, not a model, checks every citation. |
| 34.95 – 38.84 | Anything uncited, invented or repeated is struck from the record. |
| 39.28 – 40.73 | Only a human can rule. |
| 41.20 – 44.08 | Pursue, reject, or ask for more evidence. |
| 44.46 – 48.88 | And every ruling becomes team memory, cited as precedent in future hearings. |
| 49.37 – 56.56 | With the official PHP MCP SDK, any agent, like Claude or your own, can open a hearing and read the transcript. |
| 56.99 – 58.41 | But MCP can argue. |
| 58.83 – 59.90 | It can never rule. |
| 60.47 – 65.33 | ProspectOS. Built with Symfony AI and the PHP MCP SDK. |
| 65.81 – 67.87 | Clone it, and run it in five minutes. |

Verify these boundaries yourself before cutting (re-run silencedetect, and listen to the boundaries). Every visual beat must land on its line: the action on screen happens while the words describing it are spoken, never after.

## Running the real app

- Stack: `docker compose up -d` (already running at `http://127.0.0.1:8000`). Sign in with **Enter the demo workspace** (`demo@prospectos.test` / `prospectos-demo`). It contains fictional sample businesses only.
- AI: the demo workspace's active route is **Groq `openai/gpt-oss-120b`** (tested). A hearing takes about 3–6 s and makes real structured tool calls. The local Ollama route also exists but is weak; don't use it on camera.
- Useful records: prospect 1 Juniper Table (score 72), 2 Northstar Dental, 3 Olive & Grain. Hearing #4 contains real **struck** claims (one cites a non-existent `[M3]`); #9 was **opened over MCP** with Groq; #10 is a Groq hearing ruled "Pursue". Open fresh hearings for clean takes; each UI hearing uses 2 of the 10-per-hour AI quota (`app:court:hear <id>` from the CLI does not).
- MCP, for a real on-screen client call: `docker compose exec -T app php bin/console mcp:server` (STDIO JSON-RPC: `initialize`, `tools/list`, `tools/call` `list_demo_leads` / `open_evidence_court` / `get_court_case`). Showing a real terminal exchange is better than an animation of one. If you use an animation, its content must be copied from a real response.
- Theme: **light mode** (owner's decision). Set it before recording: `localStorage.setItem('prospectos-preferences', JSON.stringify({appearance:'light', accent:'blue', density:'comfortable', sidebar:'expanded'}))`, then reload. Sidebar expanded. Hide scrollbars on camera. In light mode, primary buttons are ink-black with a yellow focus ring, the active nav item is ink with a yellow marker, and admitted claims get a pale-yellow highlighter sweep; frame shots so these read clearly.

## Recording requirements

- Capture the product at **native 1920x1080** (or larger, then downscale), 30 or 60 fps, cursor visible only when it helps the story (consider a smooth, enlarged cursor or click ripples), with no browser chrome, OS taskbar, notifications or other windows. Prefer a scripted, repeatable capture (Playwright or Puppeteer with a headless or kiosk browser and a deterministic sequence of actions with eased waits) over manual screen grabbing, so takes can be redone to match the voiceover timing.
- Plan each take from the timing table: pre-roll, the action, and enough hold to cover the line, plus about 0.5 s of handles for transitions.
- Never record personal data: no real mailbox, inbox contents, email addresses other than the demo account, API keys, provider dashboards or the Integrations key fields. Use only the fictional demo workspace.

## Edit and motion design (professional, explanatory)

Target a calm, premium product-film style (think Linear, Stripe or Vercel launch videos): confident typography, generous negative space, purposeful camera moves.

1. **Structure (≤ 90 s total, aim for about 72 s)**: cold open with the problem → title → research → Evidence Court (agents, tool calls, clerk, struck claims) → human ruling → team memory → MCP → end card. Follow the VO.
2. **Hackathon**: state clearly on screen that this is an entry to the **Symfony AI & MCP Hackathon 2026**: a small badge in the intro (for example "Symfony AI & MCP Hackathon 2026 · Entry by 14 Technologies") and again on the end card. Do not imply any endorsement by Symfony, SensioLabs, Groq, Anthropic or Hugging Face.
3. **Explain, don't just show**: when a concept is named, make it visible.
   - Zoom or punch in on the `Tools called` chips (`read_evidence(E4)`, `score_breakdown()`) as "They call real tools" is spoken.
   - Highlight a citation chip and its admitted claim for "must cite a saved record".
   - Show a struck claim (crossed out, red) and its clerk note for "struck from the record". Optionally add a small animated diagram: Advocate and Skeptic → Clerk (PHP) → Human → Team memory.
   - Show the ruling click and the "Ruling: Pursue" verdict for "Only a human can rule".
   - For MCP, show the real client call and the `how_to_rule` reply ("Only a human can rule… No MCP tool can record a ruling"), then land the line "MCP can argue. It can never rule." as a typographic beat exactly at 56.99 s and 58.83 s.
4. **Typography**: Bricolage Grotesque for display, Inter for supporting text, JetBrains Mono for code. Sentence case, no all-caps labels. Kinetic labels are short (2–6 words) and complement the VO rather than duplicate it. Keep them inside title-safe margins, at least 40 px tall for the main line at 1080p, with high contrast.
5. **Motion**: eased camera moves (ease-out cubic or quint; no linear zooms except slow push-ins), 0.25–0.4 s transitions, match cuts on UI motion, subtle parallax or depth on the title cards, and the mascot animating (blink, eye-follow, wave) in the intro and end card. No gimmicky transitions, glitch effects, stock footage or generic AI imagery.
6. **Brand (light theme)**: paper `#F4F5F8`, surface `#FFFFFF`, line `#E3E6EC`, ink `#15171E`, muted `#6A7080`, highlighter `#FFD54A` (pale `#FFF5CC`), ink mascot with a white face (`brand/mascot-light.*`). Keep code and terminal panels dark (`#15171E` / `#0A0C10`) for contrast. Use the yellow highlighter sweep as the recurring motif for "admitted" and key phrases. Title cards, labels and the end card must use the light palette so they match the light-theme footage.
7. **Audio**: voiceover loudness-normalized to about -16 LUFS integrated (true peak ≤ -1.5 dBTP). Optional: a subtle, licence-clean ambient bed (royalty-free with a licence you can cite, or synthesized), ducked about 18 dB under the voice, and soft UI click or whoosh sounds at key actions. Nothing that competes with the narration.
8. **End card** (from VO 60.47 s): ProspectOS + mascot wave; "Built with Symfony AI and the PHP MCP SDK"; `git clone https://github.com/mohsin14tech/ProspectOS && docker compose up` appearing on "Clone it" (65.81 s); hackathon badge; hold about 2 s after the VO ends, then fade.

## Honesty rules (from AGENTS.md)

Everything shown must be the real product running locally. Do not fake UI states, invent tool calls or show capabilities that are not implemented. Captions must not overclaim: say "Groq · openai/gpt-oss-120b", not "any model works perfectly"; MCP is local STDIO plus session-authenticated HTTP, not a public hosted endpoint. The demo data is fictional; it's fine to say so on screen.

## Deliverables

- `var/video/prospectos-hackathon-demo.mp4`: H.264 High, 1920x1080, 30 fps (or 60), yuv420p, AAC 48 kHz 192 kbps, `+faststart`, **≤ 90.0 s** (verify with `ffprobe`), under 100 MB.
- `var/video/prospectos-hackathon-demo.srt`: English captions matching the VO.
- `var/video/thumbnail.png` (1280x720): mascot, "ProspectOS", "Evidence Court for sales leads", hackathon badge.
- A short production log appended to `CODEX_PROGRESS.md` (commands run, takes, durations, ffprobe output) and a one-line pointer in `PROJECT_CONTEXT.md`. Do not commit the video files (they live under ignored `var/`).

## Quality bar: review before you hand over

Export a contact sheet (one frame every 2 s) and a 1-frame-per-beat sheet, and check:
- every line of VO has the matching visual on screen while it is spoken
- no text is clipped, blurry or below about 24 px at 1080p
- no personal data, keys or other windows appear
- transitions don't cut words
- total duration ≤ 90 s

Fix and re-export until all of these pass, then report the final duration, file size and the beat-by-beat timing table.
