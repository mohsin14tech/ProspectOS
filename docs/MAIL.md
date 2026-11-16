# Gmail and standard mailbox setup

## One-click mailbox setup — 2026-11-16

- `/outreach` now leads with **Your mailbox** (recommended, app password). Gmail API OAuth is folded under "Advanced (for developers)" unless it is configured or connected.
- `/outreach/mailbox` asks for three things: email address, provider (auto-detected from the domain: Gmail, Yahoo incl. country domains, iCloud, Zoho; Outlook/Hotmail/Live shows a "Microsoft requires OAuth, not supported" notice) and app password. Each provider shows a short step guide with official links. Server settings, login username and test recipient sit under a collapsed "Server settings and test address" section. A blank username or test recipient defaults to the mailbox address.
- For `imap.gmail.com` / `imap.googlemail.com`, whitespace (including non-breaking spaces) is stripped from the password, because Google displays app passwords as four groups of four letters. This is a plausible cause of the owner's earlier `AUTHENTICATIONFAILED` result; it is unverified until the owner re-enters the password.
- **Connect and test** saves and immediately runs the real IMAP + SMTP login (counted by the mailbox rate limiter). Failures are mapped to safe hints: Google-specific app-password advice, generic "provider rejected the username or password", or "could not reach the mail server". Raw server output is never shown. Tests: `tests/Service/MailboxSetupHelpersTest.php`, plus two new cases in `tests/Functional/MailWorkflowTest.php`.
- Unchanged: sending stays paused by default (`OUTBOUND_EMAIL_PAUSED=1` and the workspace pause), and every test send needs separate approval to the controlled test recipient.


Implemented on 2026-11-15. MailOS, Resend and Cloudflare are outside this connector slice at the owner's request. The owner saved a Gmail IMAP/SMTP connection; the real Gmail IMAP login test returned `AUTHENTICATIONFAILED` / `Invalid credentials`. The connection remains unverified and paused. SMTP, inbox read and delivery are not yet live-verified. Automated checks use mocked Gmail HTTP responses and scripted SMTP responses; they do not prove delivery.

## Supported operations

| Connection | Reads | Drafts | Sends |
|---|---|---|---|
| Gmail OAuth | Latest 10 INBOX messages; fetch plain text on demand | Finalized-prospect drafts and replies in Gmail threads | Review/send manually in Gmail; no Gmail send API call |
| Standard IMAP + SMTP | Latest 10 inbox messages within 30 days; fetch plain text on demand | No remote IMAP drafts | One approved, labelled test at a time to the configured test recipient |
| Gmail app password | Same IMAP path using the Gmail server preset | Use normal Gmail to draft replies | Same SMTP test path, when the account permits app passwords |

One Gmail OAuth connection and one IMAP/SMTP connection can coexist per workspace. Connection management is owner/admin-only. Mail is shared with authenticated workspace members, not private to the user connecting it.

## Gmail OAuth

Enable the Gmail API in a Google Cloud project and configure a web OAuth client. Put `GOOGLE_MAIL_CLIENT_ID`, `GOOGLE_MAIL_CLIENT_SECRET`, and `GOOGLE_MAIL_REDIRECT_URI` in the ignored local environment or hosting secret manager. The supported callback URLs are `http://127.0.0.1:8000/outreach/gmail/callback`, `http://localhost:8000/outreach/gmail/callback`, and `https://prospectos.14technologies.com/outreach/gmail/callback`.

Open **Email → Email connections** and connect Gmail. This is separate from Google sign-in. Consent requests `gmail.readonly` and `gmail.compose`; both are restricted scopes, and Google may require verification and a security assessment before public use. `gmail.compose` also grants sending authority, although ProspectOS does not call Gmail's send endpoints. Partial grants fail closed. Existing draft-only connections must reconnect to enable inbox access. OAuth state is single-use, bound to the session/workspace and expires after 10 minutes.

Refresh tokens are encrypted with a purpose-separated key derived from `APP_SECRET`. They are never displayed again. Disconnect removes the token and local cached mail; revoke the app separately in Google Account settings to remove Google's grant. Rotating `APP_SECRET` requires reconnecting.

## IMAP and SMTP

Open **Email → Email connections → Set up IMAP and SMTP**. Enter the mailbox address, login username, official server hostnames, mailbox/app password and a test recipient you control. One username/password is used for both protocols. Choose:

- IMAP: TLS on port 993.
- SMTP: implicit TLS on port 465 or mandatory STARTTLS on port 587.
- Gmail preset: `imap.gmail.com`, `smtp.gmail.com`, port 465. Use a Google app password when permitted; regular Google passwords are not supported. OAuth is preferable when available. App passwords require 2-Step Verification and may be unavailable under account or administrator policies.

Credentials are encrypted. Password fields never redisplay saved values. A blank password retains the existing credential only when both server names and the login username remain unchanged. Saving settings clears the connection test, pauses sending, invalidates old approvals and clears cached messages.

**Test IMAP and SMTP** authenticates and examines the inbox, then authenticates with SMTP without submitting a message. Hosts must resolve exclusively to public IPs. Connections pin a validated IP while TLS verifies the original hostname; private/loopback/metadata hosts and plaintext transports are rejected. SMTP must advertise supported authentication. A login test is not a delivery test.

IMAP reads have a 1 MiB response budget, 25-second operation deadline and 5-second socket timeout. SMTP has a 5-second socket timeout, not an overall session deadline; a separate overall SMTP time/byte budget remains hardening work for public service operation.

## Inbox and replies

Use **Sync Gmail** or **Sync IMAP inbox**. No provider read/unread flags, labels or folders are changed. Headers are cached; bodies are fetched only by a CSRF-protected explicit action. Rendered mail is escaped plain text, capped at 20,000 characters. Gmail bodies are requested only for messages with estimated size at most 256 KiB. HTML, remote images and attachments are not displayed. Emails are untrusted data and are not sent to AI.

The cache retains at most 100 messages per connection and displays at most 100 across the workspace. This is a bounded cache, not a complete mirror: moved/deleted provider messages may remain locally. Admins can clear it without deleting provider mail. Disconnecting a mailbox removes its cache. Cached headers/bodies and test message bodies are database content, not separately encrypted fields; protect the database and backups. Email responses use `private, no-store`, no-referrer, nosniff and frame denial. The PWA does not cache mailbox pages.

Gmail messages offer **Prepare a Gmail reply draft**. The user enters and verifies the recipient; sender text is never treated as proof of identity. A contact-basis attestation and workspace suppression check are mandatory. Thread ID and safe Message-ID reply headers are supplied. Keep the original subject for Gmail threading. The resulting draft ID, recipient, subject, actor and time are recorded; reply body stays in Gmail. Check Gmail Drafts before retrying an uncertain creation result. IMAP replies are handled in the user's normal mail app in this release.

## Approved SMTP test

1. Save and pass the connection login test.
2. The server operator sets `OUTBOUND_EMAIL_PAUSED=0` in the private runtime environment. It defaults to `1` even if absent. Restart long-running processes after environment changes.
3. An admin enables workspace test sending and prepares a message for their controlled test address, attesting that provider rules were reviewed.
4. Review the immutable sender, recipient, subject and body, then approve. Approval expires after 15 minutes and is bound to the message hash, connection ID and connection revision.
5. Use **Send approved test** once. The sender rechecks admin membership, connection test/revision, both pause controls, suppression and a five-attempt-per-workspace hourly limit under a database lock. It commits an `attempting` claim before contacting SMTP.

`accepted` means SMTP returned acceptance; it does not prove arrival. Any transport error becomes `uncertain`; a process crash can leave `attempting`. Neither state is automatically retried. A stable Message-ID is an audit identifier, not an SMTP idempotency guarantee. An already claimed attempt can finish after a pause. General prospect sending, campaigns, unsubscribe ingestion, delivery webhooks and bounce processing are not implemented.

Mailbox tests, sync, body fetch and Gmail draft operations share a 30-action-per-user hourly limit. Production scale-out needs shared limiter/session storage.

## Verification and references

`tests/Functional/MailWorkflowTest.php`, `tests/Service/GmailOutreachProviderTest.php`, `tests/Service/MailTransportSafetyTest.php` and `tests/Entity/EmailActionTest.php` cover workspace/role boundaries, CSRF, cache deduplication, escaped content, provider errors, reply threading, partial OAuth consent, TLS requirements, DNS restrictions, credential reuse, immutable approvals, expiry, suppression after approval, hourly limits, disconnect/reconnect, uncertain outcomes and repeated send denial. Full suite: 82 tests / 369 assertions, one opt-in Ollama test skipped. Owner-workspace Ollama was tested separately through the browser and passed.

Official references checked on 2026-11-15: [Gmail IMAP/SMTP](https://developers.google.com/workspace/gmail/imap/imap-smtp), [Gmail scopes](https://developers.google.com/workspace/gmail/api/auth/scopes), [Google app passwords](https://support.google.com/accounts/answer/185833), [Gmail drafts](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.drafts/create), [Symfony Mailer](https://symfony.com/doc/current/mailer.html), [ImapEngine](https://github.com/DirectoryTree/ImapEngine). Locked SMTP/IMAP package APIs were inspected in the installed source.
