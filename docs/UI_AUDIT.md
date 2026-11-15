# ProspectOS screen audit

This audit records the current screen job, primary action, decisions, states, responsive behavior, and known gaps. It reflects the shipped early-access scope; it does not certify WCAG conformance.

| Screen | Main job and primary action | Decisions and states | Mobile behavior | Current gaps |
|---|---|---|---|---|
| Sign in | Enter an existing workspace; primary action is **Sign in**. | Email/password; inline field validation, failed login notice, and Google sign-in unavailable state. | Single-column auth card. | Google is not configured. Real sign-in depends on owner credentials. |
| First owner setup | Provision the first owner; primary action is **Create workspace owner**. | One-time setup token and account details; setup is unavailable after the first account exists. | Single-column form. | No public registration; email verification and password reset are unavailable without a transactional sender. |
| Workspace settings / account linking | Review membership and optionally link an existing Google identity; primary action is **Continue to Google** when configured. | Password reauthentication; already-linked, provider-unconfigured, cancellation and conflict states. | Stacked panels and form. | Team invitations and roles are not shipped; Google live flow has not been tested. |
| Command Centre | Review saved research and choose the next research action; primary action is **Start research** in the source panel. | Saved-record counts, empty/recent prospects, activity, synthetic labels and integration state. | Cards collapse to one column; the source panel and action stack above bottom navigation. | No populated dataset may be available in a fresh workspace; no live prospect outreach. Search uses a local SearXNG service and depends on upstream engines. |
| Research | Submit an objective and choose web search, sample data, or a URL inspection; primary action is **Start research**. | Objective, source mode, URL if applicable, loading/queued/completed/failed states and terms reminder. | One-column form with touch-sized source choices and bottom navigation. | Search candidates are unverified; URL inspection is one page and requires operator terms review. |
| Prospect report | Inspect source evidence and deterministic score; primary action is **Generate evidence summary** when AI is configured. | Evidence verification, citations, score coverage/unknowns, AI provider disclosure and unavailable state. | Score and evidence stack in one column. | Real AI is unconfigured; no summary is fabricated. |
| Integrations | Check service readiness and run supported diagnostic operations; primary action is **Test web search** or **Test AI provider** when configured. | Search result/error, AI disabled/configured/test-required, MailOS read-only scope unavailable. | Integration cards stack. | Email and Google remain unconfigured; MailOS sync stays disabled because available scope also permits submission. |
| Inbox | Review synchronized message headers; primary action is **Sync inbox** only when a safe key is configured. | Disconnected explanation, empty mailbox, bounded header list, provider errors. | Single-column rows; touch-sized bottom navigation. | No account is connected; bodies/attachments and sending are not supported. |
| Inbox thread | Inspect saved thread headers and continue in MailOS; primary action is **Open MailOS**. | Header-only thread, unavailable content stays in provider. | Thread headers wrap and stack. | No sync data is currently available. |

## Theme and interaction update

- A persistent theme control is available in authenticated and signed-out shells.
- With no saved choice, the app follows the operating-system light/dark preference before styles render; an explicit choice is stored in browser local storage.
- Dark tokens cover shared surfaces, navigation, forms, reports, integration states, inbox rows, and feedback states. Reduced-motion preferences remain respected.
- Browser review should include the signed-out login screen at desktop and 390px. Authenticated screens require an existing test/owner account; do not use a real password in automation or create/modify owner data solely for screenshots.
- Focus visibility, labels, semantic landmarks and visible status/error roles are present in the templates, but keyboard-only, screen-reader, contrast-ratio and 200% zoom verification still require a complete accessibility pass. Do not claim WCAG 2.2 AA conformance until measured and tested.
