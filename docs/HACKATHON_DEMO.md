# Hackathon Demo

## Current walkthrough

The running local product supports authenticated workspaces, persisted research objectives, real SearXNG web search, source-linked unverified candidates, bounded robots-aware page inspection, deterministic explainable scoring, saved evidence reports, and an optional citation-validated Symfony AI summary. Search, AI, and synthetic records are labelled according to their actual state. MailOS inbox setup and a bounded header-only sync adapter are implemented, but MailOS is disconnected because the current `jmap` key scope also grants submission; no safer read-only MailOS key is available. No AI provider is currently configured. A local Ollama invocation was attempted with a harmless test prompt and did not produce the exact response required by the provider test, so do not demonstrate AI as working yet.

## Target 90-second flow

1. Sign in to a local owner workspace.
2. Submit a public business research objective to SearXNG.
3. Inspect source-linked candidate evidence and the deterministic score coverage.
4. Review the report and optional AI provider setup/test state.
5. Inspect the MailOS disconnected state and its least-privilege requirement.
6. Call an authenticated workspace-scoped read-only ProspectOS MCP tool.

Do not imply that AI inference, MailOS sync, email drafting, approval, or outbound send works. A future real send demo requires a separately authorized MailOS `send` key, an immutable human approval, policy and suppression checks, and a MailOS sandbox or owner-controlled test recipient. Never send prospect outreach as part of testing.
