# ProspectOS video voiceover — hook-first full workflow

Target: about 86 seconds of narration plus a 2-second end-card hold. Read conversationally at roughly 140 words per minute, with short pauses between paragraphs. Do not read the visual notes. Demo businesses and research are fictional sample data.

## Spoken script

Would you trust this sales AI claim? It cites M3, a source missing from the case file. The PHP checker strikes it. That's why every lead goes on trial.

Juniper Table is a fictional sample. ProspectOS saves each finding with its source. Its score out of 100 uses fixed rules; unknown facts stay unknown.

In Evidence Court, two AI helpers argue opposite sides. One makes the case; one finds gaps. They read saved facts and score. Each accepted claim links to its source.

PHP code checks each claim against saved sources. No match? It strikes the claim. AI can't grade its own homework.

A person reviews both sides, chooses Pursue, Reject, or Needs more evidence, and records why. ProspectOS saves that decision for future reviews.

Email comes after the human ruling. This lead still needs more evidence, so no draft is ready. ProspectOS makes clear that it will not send an email for you.

MCP lets another AI app open and read a hearing. It cannot rule; only a person can.

Symfony AI powers the helpers; the official PHP MCP SDK connects outside apps. Clone ProspectOS and try the demo with Docker Compose.

## Visual notes (not spoken)

1. Cold open on the actual struck `[M3]` claim. Animate a cursor to the missing-source note, click/pulse, zoom into the red explanation, and land the hook question.
2. Rewind into the live app. Click from the dashboard to Juniper Table, show the score and its factors, then move to the saved source records. Keep the synthetic-data note visible.
3. Click into the real Evidence Court hearing. Show the two sides, real read-only tool calls and an accepted claim's source tag. Use a purposeful close-up so the source tag is legible.
4. Return to the actual M3 strike and show the checker note that the source is absent from the case file.
5. Show the real pending ruling controls, make the human selection, submit the reason, and match-cut to the saved ruling. Open Team Memory to show it persisted.
6. Open the email preparation/connection area. Show the no-send/login-test/approval language without showing any mailbox address, inbox message, or credentials. Do not send email.
7. Show the MCP request and its `how_to_rule` response. Land “MCP can argue” and “It can never rule” on their spoken beats.
8. Close on the ProspectOS lockup, Symfony AI / PHP MCP SDK attribution, hackathon entry badge, and clone/run command.
