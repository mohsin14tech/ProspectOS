# App UI / UX Skill — Build Apps That Feel Effortless

> Use this file as the single source of truth for designing and building any web app or mobile app.
> It distills the design philosophy visible in @appbrainy Before / After redesigns plus proven patterns from other top UI / UX pages: @ui.martin, @mds, @uxbrainy, @teamui_in, @siren.uix, @uiadrian, @pranitart, @orbixapps, @noahelhadedy, @yogesh_creatives.
> Apply it to every screen, component, flow, and prompt in this project. When in doubt, choose the simpler, more scannable, more forgiving option.

---

## 1. How To Use This Skill

1. **Before you design anything:** Write down the ONE job of the screen in one sentence. Example: "Book a visit for Thu at 10:30." If you cannot write it in one sentence, the screen is trying to do too much.
2. **Before you build anything:** List the ONE primary action. Every other element must support it or be moved to a secondary place.
3. **During design:** Run every screen through Section 3 (The AppBrainy Transformation) and Section 14 (QA Checklist).
4. **During build:** Use the exact tokens in Section 4, 5, 6. Do not invent new spacing, radius, type sizes, or component variants.
5. **For AI implementation:** Paste the prompts in Section 15 verbatim, then add your product, screens, and data. Do not let the model add extra features, extra buttons, or extra visual noise.

**Core rule:** If removing an element does not make the task harder, remove it.

---

## 2. Core Philosophy — The AppBrainy Method

### 2.1 From Dense To Decisive

@appbrainy posts follow the same 8-slide anatomy, and that anatomy IS the philosophy:

- Slide 1: Full Before vs After side-by-side
- Slides 2–5: One component isolated at a time — header, calendar, slots, actions — Before on top, After below
- Slide 6: Full Before alone
- Slide 7: Full After alone
- Slide 8: One clear next step

Apply the same thinking to your app: **Show everything at once = confusion. Show one decision at a time = confidence.**

### 2.2 The 7 Principles

1. **One Screen, One Job, One Primary Action.** A booking screen books. A dashboard answers "What do I have left?" A driving screen answers "What is my next turn?" Never let a screen do three jobs.
2. **Progressive Disclosure.** Show what is needed NOW. Hide the rest behind one clear affordance: "See Month", "See Details", a summary row with a chevron, an expandable section. The user should never scroll through 35 options to find 7 relevant ones.
3. **Group, Then Space.** Related items belong together under one small section label. Unrelated groups get MORE space between them than related items get inside a group. Grouping beats dividers, borders, and boxes.
4. **Make The Next Step Obvious.** The primary action must state the outcome, not the category. "Book 10:30 AM" beats "Confirm". "Start Ordering Drinks · 12 XP" beats "Continue". Always include the value, time, or object in the button when you know it.
5. **Reduce Reading, Increase Scanning.** Replace sentences with structure: icon + short label + number. Replace a paragraph instruction with a large number + short label + icon. Replace a long form with a summary row.
6. **Preserve Context.** Never make the user lose where they are. Keep the next step visible ("Then" preview), keep the trip / order / progress summary pinned, keep the selected value visible in the sticky footer.
7. **Design For Thumbs, Eyes, And Interruptions.** Large targets, large tap areas, forgiving defaults, clear selected and unavailable states, easy undo. A user glancing for 2 seconds while walking must succeed.

---

## 3. The AppBrainy Transformation — Before → After Rules

Use this as a rewrite checklist for every existing screen.

| # | Before (Avoid) | After (Do This) | Example From @appbrainy |
|---|---|---|---|
| 1 | Show full month calendar with 30+ cells | Show week strip with 7 days + "See Month" secondary link | Doctor booking: full November grid → Mon–Sun week strip, selected day as filled pill |
| 2 | Show all 16 time slots in a dense 4×4 grid | Group slots under Morning / Afternoon, show only available slots, mute unavailable with strikethrough | Booking slots: 16 identical boxes → 2 groups, 8 slots, unavailable visibly struck through |
| 3 | 3 stacked equal-weight buttons: Confirm / Add to Calendar / Cancel | 1 primary action in sticky footer + secondary details collapsed into one summary row | Booking: 3 buttons → Footer: price left + "Book 10:30 AM" right. Details in "Checkup · Insurance added >" row |
| 4 | Long credential paragraph: degrees, hospital, location, reviews, fee all in one block | 2 lines only: Name + Role · Experience. Second line: Rating (count) · Price. Move the rest to details | Doctor card: 4-line paragraph → "Cardiologist · 15 years" + "4.9 (312) · $50 visit" |
| 5 | Radio buttons + tiny labels for a binary choice | Segmented control with 2 large equal options, selected state unmistakable | Visit type: radio dots → Segmented: [In Person | Online Visit] |
| 6 | Paragraph instruction: "In 400 ft turn right onto 16th St, then continue for 0.2 mi..." | Large number + short label + large direction icon. Next step as small attached chip | Navigation: sentence banner → "400 ft / 16th St" + arrow icon + "Then" chip below |
| 7 | 5+ equal buttons in bottom bar: Exit, Steps, Share, Search, Add Stop | Bottom bar: summary left (18 min · 4.2 mi · 9:59 AM) + ONE destructive / exit action right. Move rest to expand / overflow | Driving: 5 buttons → Summary + "End" only |
| 8 | Dashboard shows everything: income, expenses, savings, balance, pie chart, bar chart, full table | Dashboard answers one question first: "$1,284 left of $3,200 · 12 days to go" then 3–4 grouped categories, then recent feed | Budget app "Penny": dense dashboard → Balance-first hero + Rent / Food / Fun / Other + Recent |
| 9 | List of lessons with identical rows and tiny labels | Vertical path / journey with nodes, current lesson as a prominent card with tooltip | Language app: flat list → Path nodes + "Ordering Drinks · Start · 12 XP" card |
| 10 | Header with 5 stats crammed: Level, XP, Hearts, Gems, League | Header with 3 max: Flag / Streak / Gems / Hearts — only what drives the next action. Hide the rest | Language app header: 5 competing stats → 4 clear icons with numbers |
| 11 | Notification banner + list + grid + nav all competing | One upcoming item as a card, rest as timeline. One hierarchy, not four | Calendar app: stacked banners → Timeline + one "Upcoming Meeting" card |
| 12 | Small circular avatar + tiny name + tiny meta in a bordered box | Larger rounded-square image + larger name + 2-line meta, generous padding, no inner border | Doctor card: small circle + border → Large rounded image + clear name |

**Rule of thumb:** If a screen has more than 7 interactive elements visible at once, you have not finished simplifying. Group, collapse, or move to the next step.

---

## 4. Layout + Spacing System — Use These Exact Tokens

### 4.1 Grid

- Mobile design width: 390
- Web content max width: 1120 for dashboards, 720 for reading / forms, 1280 for dense data tables only
- Base unit: 4
- Spacing scale — use ONLY these: 4, 8, 12, 16, 20, 24, 32, 48, 64
- Side padding: Mobile 16 or 20. Web 24. Never 13, 18, 27, or other off-scale values.
- Section spacing: 24 between groups on mobile, 32 on web
- Inside a group: 8 or 12 between related items
- Card internal padding: 16. Large card: 20. Never less than 12.

### 4.2 Spacing Consistency — The @mds Rule

- Pick ONE gap for "between modules" and use it everywhere on that screen. @mds shows 12 vs 24: the 12-gap screen feels disconnected, the consistent 24-gap screen feels like one product.
- Margins, side padding, and gap between related objects must match. If side padding is 16, the gap between cards is 16, not 10 or 22.
- More space BETWEEN groups than WITHIN a group. This alone creates hierarchy without any extra decoration.

### 4.3 Radius

- Small chip / tag: 999 (full pill)
- Button: 12 on mobile, 12 on web. Primary CTA in sticky footer: 12 or 16.
- Card: 16. Large card / sheet: 20 or 24.
- Avatar / image: 12 or 16 for rounded-square. Use rounded-square for people / places, not tiny circles, when the image carries information.
- Input: 12
- Bottom sheet: top radius 20 or 24, bottom 0
- Never mix 4, 6, 9, 11, 15 radiuses on the same screen.

### 4.4 Elevation + Borders — The @ui.martin Rule

- Use ONE method per screen: either a thin border OR a soft shadow, not both, not mixed.
- Preferred: 1px border on cards, no shadow. It stays crisp on all screens and does not create muddy layers.
- Do NOT use decorative shapes, background patterns, blobs, or gradients behind content to "make it interesting". Spacing and hierarchy make it interesting.
- Cards sit on the primary surface. Do NOT nest a card inside a card inside a card. One level only.
- List rows: no card per row. Use a plain list with dividers. Reserve cards for distinct objects: a doctor, an order, a budget, a lesson.

### 4.5 Density Rules

- Maximum 1 hero metric per screen. Maximum 3 secondary metrics.
- Maximum 5 items in a bottom nav. Maximum 3 actions in a top bar.
- Maximum 7 days visible in a date strip by default. Maximum 8 slots visible before grouping or "See More".
- Maximum 5 rows visible in a feed before "See All". Maximum 4 categories in a breakdown before "Other".
- If you need more, use search, filter, or progressive disclosure — not a longer screen.

---

## 5. Typography System

### 5.1 Font Rules — The @ui.martin Rule

- Maximum 2 font families per app: 1 for display / headings, 1 for body / UI. Often 1 family with different weights is better than 2.
- Maximum 3 weights: Regular (400), Medium / Semibold (500–600), Bold (700) for hero numbers only.
- Never use ALL CAPS for buttons or long labels. Use sentence case: "Book 10:30 AM", not "BOOK 10:30 AM". ALL CAPS reduces scanning speed and feels like shouting.
- Never use more than 4 text sizes on one screen.

### 5.2 Type Scale — Use ONLY These

| Role | Size | Weight | Line Height | Use For |
|---|---|---|---|---|
| Hero Number | 32–40 | Bold | 1.1 | Balance left, time left, price, next turn distance |
| Screen Title | 20–24 | Semibold | 1.25 | Screen title, section hero |
| Section Label | 14–16 | Semibold | 1.4 | "Morning", "Afternoon", "Recent", "Budgets" |
| Body / Primary Label | 16 | Medium | 1.5 | Names, card titles, button labels, list titles |
| Secondary / Meta | 14 | Regular | 1.5 | "Cardiologist · 15 years", "4.9 (312) · $50 visit" |
| Tertiary / Caption | 12–13 | Regular | 1.4 | Hints, timestamps, "12 days to go", helper text |
| Tab / Nav Label | 12 | Medium | 1.3 | Bottom nav labels |

### 5.3 Hierarchy Without Noise

- Hierarchy comes from: size + weight + spacing + position. Not from adding more styles.
- The most important number is the largest and boldest. The second most important is smaller. The third is smaller and muted in weight.
- Left-align all body text. Center-align only hero numbers, empty states, and short onboarding headlines.
- Line length: Web body max 68 characters per line. Mobile: let it wrap naturally, never force 2 columns of text on mobile.
- Numbers: Use tabular numbers for prices, times, counts so they do not jump when they change. Format consistently: "$1,284", "10:30 AM", "4.9 (312)".
- Truncate with intent: 1 line for titles, 2 lines max for descriptions, then "See More". Never let a description push the primary action off-screen.

---

## 6. Components — Build These Once, Reuse Everywhere

### 6.1 Buttons

**Primary Button**
- One per screen. Full width on mobile forms, auto-width with min 160 in sticky footer.
- Height: 48 minimum, 56 preferred for main CTA. Padding: 16 horizontal, 14 vertical.
- Label: Verb + Object + Value. "Book 10:30 AM", "Pay $48.20", "Start Lesson · 12 XP", "Add Habit".
- States: Default, Pressed (slight scale 0.98 + opacity change), Disabled (muted + no action), Loading (spinner + "Booking…" + disabled).
- Never have 2 primary buttons side-by-side. If you think you need 2, one is actually secondary.

**Secondary Button**
- Outlined or quiet style. Height 48. Used for "See Month", "See All", "Edit", "View Details".
- Maximum 1 secondary button per group.

**Tertiary / Text Action**
- Text + chevron: "Checkup · Insurance added >". Used for summary rows that open a detail sheet.
- Destructive action: Text only, placed away from primary, with confirmation. "Cancel Booking", "Delete", "End Trip". Never as a large filled button next to the primary.

**Icon Button**
- 44×44 minimum touch area, icon 20–24 inside. Top bar: Back (left), 1 action (right). Never 3 icon buttons in a row.

### 6.2 Segmented Control

- Use for binary or 2–3 mutually exclusive choices: [In Person | Online Visit], [Daily | Weekly | Monthly], [Today | Upcoming].
- Container: full width, pill or rounded 12, padding 4 inside.
- Selected segment: filled, bold label. Unselected: plain label.
- Do NOT use radio dots, checkboxes, or dropdowns for a 2-option choice.

### 6.3 Cards

- One idea per card. Title + 1–2 lines meta + 1 action max inside a card.
- Structure: Image (optional, left or top) + Title + Meta + Optional trailing action (chevron / toggle / price).
- Padding 16. Radius 16. Border 1px OR soft elevation — pick one.
- Do NOT put a button + toggle + dropdown + 3 links inside one card.

**Person Card (Doctor / Coach / Seller)**
- Left: 56×56 or 64×64 rounded-square image
- Right: Name (16 Semibold) / Role · Experience (14 Regular) / Rating (count) · Price (14 Medium)
- No degrees, no hospital address, no long bio on the card. Details live on the detail screen.

**Summary Card (Balance / Trip / Order)**
- Top: Small section label
- Middle: Hero number (32 Bold) + supporting line ("left of $3,200 · 12 days to go")
- Bottom: 3–4 grouped breakdown rows, each with label left, value right

### 6.4 Lists

- Row height: 56 minimum, 64 for 2-line rows, 72 for rows with image.
- Row structure: Leading icon / image (optional) + Title + Subtitle + Trailing value / chevron.
- Dividers: 1px line, inset from leading edge, not full width. No divider after last row.
- Grouped list: Section label (14 Semibold) + 2–6 rows. Space 24 before next section.
- Selection: Entire row is tappable. Selected row gets a check icon + filled state, not just a subtle change.

### 6.5 Inputs + Forms

- Label above input, never inside as placeholder only. Placeholder is a hint, not a label.
- Input height 48 minimum, 56 for important forms. Padding 16 horizontal. Radius 12.
- Helper text below input, 12 Regular: "We will send a confirmation here."
- Error: Icon + short message below input + border change on input. Never error by tone alone — always add icon + text.
- Dropdown: Use only when 5+ options. For 2–4 options, use segmented control or chips.
- Date: Week strip by default, "See Month" to expand to full calendar. Never show a full month when the user only needs this week.
- Time: Group by Morning / Afternoon / Evening. Show only available slots. Unavailable = muted + strikethrough + not tappable.
- Multi-step forms: Show progress as "Step 2 of 3 — Details" + progress bar. Collapse completed steps into summary rows: "Checkup · Insurance added >" — tappable to edit.
- Never ask for the same info twice. Never ask for optional info on the first screen.

### 6.6 Chips + Tags

- Chip height 32–36, padding 12 horizontal, radius 999.
- Use for filters, categories, status. Selected chip = filled + bold. Unselected = outlined / quiet.
- Maximum 6 chips visible, rest behind horizontal scroll with fade.
- Status chip: Icon + short label. "Then", "Upcoming", "Paid", "In Progress". Always icon + text, never icon alone for status.

### 6.7 Bottom Sheets + Dialogs

- Bottom sheet for: filters, details, confirmation, picking a slot / date. Not for full flows.
- Dialog for: destructive confirmation, errors that block progress. One dialog at a time, 2 actions max: [Cancel] [Confirm / Delete].
- Sheet header: Title + Close (X) + optional "Clear" / "Done". Content scrolls, footer pins primary action.

### 6.8 Navigation Components

**Mobile Bottom Nav — @uxbrainy / @teamui_in Patterns**
- 3–5 items max. Each: Icon (24) + Label (12 Medium).
- Active item: Filled icon + bold label + indicator. Inactive: outline icon + regular label.
- Center action (if needed): Larger 56 circular button, raised, for the ONE creation action: "+", "Book", "Add". Only ONE app in your portfolio should use a center FAB. If every screen has a FAB, none is special.
- Patterns to choose from (pick ONE per app, use everywhere):
  - Standard: 4 items + labels, evenly spaced
  - Center FAB: 2 items + FAB + 2 items
  - Floating bar: Bar floats above content with 16 margin, radius 20
  - Segmented: Used for 2–3 top-level modes, not for 5-way nav
- Never hide bottom nav on scroll in a booking / checkout flow. Keep it stable.

**Web Top Nav**
- Left: Logo / Product name. Center: 4–6 links max. Right: Search + 1 primary CTA + Avatar.
- Active link: Bold + underline indicator. No more than 1 CTA in nav.
- Secondary nav (tabs): Below top nav, max 5 tabs, scrollable on small screens.

**In-Screen Tabs**
- Use for: [Overview | Activity | Settings], [Daily | Weekly | Monthly].
- Tab label 14 Medium, active = bold + indicator bar below.
- Do NOT use tabs for 2-option binary choices — use segmented control instead.

---

## 7. Screen Patterns — Copy These Structures

### 7.1 Home / Dashboard

Structure top to bottom:
1. Top bar: Greeting + Avatar OR Logo + Search
2. Hero card: ONE number + ONE supporting line + ONE primary action (if any)
3. Breakdown: 3–4 grouped categories, each with label + value + small progress indicator
4. Recent feed: Section label + "See All" + 3–5 rows
5. Bottom nav (mobile) / Sidebar (web)

Do NOT show: full tables, full charts, all categories, all transactions on home. Home answers one question and routes to detail.

### 7.2 Detail / Booking / Checkout

Structure top to bottom:
1. Top bar: Back + Title centered + 1 secondary action (Heart / Share)
2. Hero object card: Person / Product / Place — image + name + 2-line meta
3. Choice: Segmented control (if binary) OR chips (if multiple)
4. Date: Week strip + "See Month"
5. Time / Options: Grouped under Morning / Afternoon, or grouped by category
6. Summary row: "Checkup · Insurance added >" — tappable to edit
7. Sticky footer: Context left (Price / Time / Total) + Primary action right (Verb + Value)

The sticky footer is NON-NEGOTIABLE for any screen with a primary action. The user must never scroll to find the button.

### 7.3 List / Search / Browse

Structure:
1. Search bar (sticky) + Filter chips below it
2. Results count: "212 roles" or "12 places" — small, honest
3. Cards: Image + Title + Meta + Price / Rating, one per row on mobile, 2–3 column grid on web
4. Empty state if 0 results: Icon + "No matches" + "Clear filters" button

### 7.4 Map / Driving / Live Tracking

Structure:
1. Content full-bleed: Map / Camera / Live view takes 100% of screen
2. Floating top card: Next action — Large number + short label + large icon. Next step as small attached chip below it.
3. Floating side controls: Max 2: Re-center + Sound / Layers. Stacked vertically, 44×44 each.
4. Floating bottom bar: Summary left (Time · Distance · Arrival) + ONE action right ("End", "Cancel", "Arrive")
5. Hide everything else: No bottom nav, no ads, no promos, no 5-button bar while driving / tracking.

### 7.5 Learning / Path / Progress

Structure:
1. Header: 3 stats max (Streak, Points, Hearts) — icon + number each
2. Unit title bar: "Unit 3 · Ordering Drinks" + progress
3. Path: Vertical nodes, current node largest + tooltip card: "Ordering Drinks · Start · 12 XP"
4. Completed nodes: Check icon. Locked nodes: Muted + lock icon.
5. Primary action inside tooltip card, not as a separate floating button.

### 7.6 Settings / Profile

Structure:
1. Profile card: Image + Name + 1-line meta + Edit
2. Grouped lists: Account / Notifications / Appearance / Support — each group with 3–5 rows
3. Each row: Icon + Label + Trailing value / toggle / chevron
4. Destructive group at bottom, separated by larger space: "Log Out", "Delete Account"

---

## 8. Mobile-Specific Rules

- Design mobile-first, even if web is the main product. If it does not work on 390 width, it does not work.
- Thumb zone: Primary action in bottom 1/3, within easy reach. Never put the primary action at the very top.
- Touch targets: Minimum 44×44, preferred 48×48. Spacing between tappable items minimum 8.
- One column. Never 2 columns of cards on mobile. Never side-by-side forms on mobile.
- Bottom sheets, not dialogs, for most mobile actions. Dialogs only for destructive confirmations.
- Sticky footer + bottom nav must not overlap. Footer sits ABOVE bottom nav if both are present, or bottom nav hides on checkout / booking screens.
- Safe areas: Respect notch, home indicator, and keyboard. Inputs must move above keyboard. Primary button must stay visible above keyboard or be hidden until keyboard dismisses — never hidden behind it.
- Gestures: Swipe back, pull to refresh, swipe to delete are enhancements, not the only way. Always provide a visible button alternative.
- Haptics: Gentle tap on selection, success on completion, warning on error. Subtle, not on every tap.

## 9. Web-Specific Rules

- Web is NOT a stretched mobile screen. Use the extra width for: sidebar + content, or content + detail pane, not for wider cards.
- Layout: Sidebar (240–280) + Content (max 1120) + Optional detail pane (360–400) for selected item. Or Top nav + Centered content (max 1120).
- Tables: For dense data, use a real table: Checkbox + Name + Status + Value + Date + Actions (3-dot). Row height 56. Header 14 Semibold. Sortable columns. Sticky header. Pagination or "Load More", not infinite scroll for data.
- Dashboards: 2–3 column grid on desktop, 1 column on mobile. Hero card spans full width, breakdown cards in grid, recent feed full width below.
- Hover: Show secondary actions on hover (Edit / Delete / More) — but never hide the ONLY action behind hover. Touch users have no hover.
- Keyboard: Tab order follows visual order. Enter submits, Esc closes sheets / dialogs. "/" focuses search where appropriate.
- Modals on web: Centered, max 480–560 width, overlay, Esc to close, focus trapped. Bottom sheets are for mobile, not web.

## 10. Motion + Micro-Interactions

- Motion must explain, not decorate. Use it for: state change, navigation, feedback, progress.
- Durations: 150ms for toggles / chips, 200ms for buttons / sheets, 300ms for screen transitions. Never longer than 400ms for UI motion.
- Easing: Ease-out for entering, ease-in for exiting. No bounce, no elastic, no springy overshoot on functional UI.
- Screen transitions: Slide / fade, 200–300ms, direction matches navigation (forward = slide left, back = slide right).
- Selected state: Animate the fill / indicator, not a jump in size.
- Loading: Skeleton for lists / cards (shimmer, same shape as content), spinner for buttons, progress bar for multi-step flows. Never blank screen + spinner for a full page.
- Success: Check icon + short message + haptic / subtle scale. Then move forward automatically. Do not require the user to tap "OK" to continue after success.
- Error: Shake is optional and subtle. Message + icon is required. Never error with motion alone.
- Do NOT add: auto-playing carousels, parallax, floating decorative elements, animated backgrounds, confetti on every action. Reserve delight for real milestones: first booking, streak, goal completed.

## 11. Feedback, States, And Edge Cases — Design Every State

For EVERY component and screen, design these 6 states:

1. **Default:** What it looks like before interaction
2. **Selected / Active:** Filled, bold, check — unmistakable
3. **Unavailable / Disabled:** Muted + strikethrough or lock icon + "Not available" hint. Never make unavailable look tappable.
4. **Loading:** Skeleton / spinner + disabled + label change: "Booking…"
5. **Empty:** Icon + 1-line headline + 1-line explanation + 1 action. Example: "No appointments yet" / "Book your first visit" / [Browse Doctors]
6. **Error / Success:** Icon + short message + 1 action to retry / continue. Example: "Could not load appointments" / [Try Again]

**Form errors:** Inline, under the field, icon + text. Never only at the top. Never only as a toast that disappears.
**Network errors:** Full-screen or banner with retry, not a silent failure.
**Permissions:** Explain WHY before asking. "Allow location to show nearby doctors." If denied, show how to enable + a fallback (search by city).
**Offline:** Show cached content + "You are offline" banner + queue actions. Never a blank screen.

## 12. Content + Copy Rules

- Write like a helpful human, not a system. Short, direct, present tense.
- Buttons: Verb + Object + Value. "Book 10:30 AM", "Save Changes", "Send Invite".
- Headings: Answer the question. "What do you have left?" beats "Dashboard". "Your visit on Thu, Nov 12" beats "Booking Details".
- Empty states: Headline + 1 line + 1 action. No paragraphs.
- Errors: Say what happened + what to do. "We could not save your changes. Check your connection and try again." Not "Error 500".
- Numbers: Always format. "$1,284", "12 days", "4.9 (312)". Never "1284" or "1284.0".
- Dates: "Thu, Nov 12" for humans, "Nov 12, 2026" for records. Never "2026-11-12" in UI.
- Times: "10:30 AM", not "10:30:00". Group by Morning / Afternoon / Evening.
- Do NOT use: Lorem ipsum, "Click here", "Learn more" alone, "Submit" alone, jargon, ALL CAPS, exclamation marks in UI (max 1 per flow, for real delight only).
- Accessibility copy: Every icon button has a label for screen readers. Every image has alt text. Every chart has a text summary.

## 13. Accessibility — Non-Negotiable

- Touch: 44×44 minimum. Spacing 8 minimum between targets.
- Text: Minimum 12 for captions, 14 for secondary, 16 for body. Never 10 or 11 for readable text.
- Contrast in structure: Do NOT rely on a single visual cue alone. Selected = fill + bold + check icon. Error = icon + text + border change. Success = icon + text. Never use a single cue alone.
- Focus: Visible focus ring on web for keyboard users. Focus order = visual order.
- Screen readers: Semantic headings (H1 once per screen, H2 for sections), labels on inputs, alt on images, roles on dialogs / sheets.
- Motion: Respect reduced-motion setting. No auto-play, no flashing, no motion that cannot be paused.
- Forms: Labels always visible. Errors announced. Required fields marked with "*" + "Required" text, not by a single symbol alone.
- Test: Navigate the whole flow with keyboard only. Navigate with screen reader. Zoom to 200% — layout must not break.

## 14. Anti-Patterns — Never Do These

- Never show everything at once to prove the app is powerful. Power = one clear next step.
- Never use 3 equal CTAs. Never use 2 primary buttons side-by-side.
- Never use a full month calendar when a week strip will do.
- Never use a 4×4 grid of identical options without grouping.
- Never use radio dots for a 2-option choice. Use segmented control.
- Never use ALL CAPS buttons, tiny 10px text, or 5 different radiuses.
- Never nest cards, never use decorative shapes behind content, never mix border + shadow + gradient on one card.
- Never hide the primary action below the fold. Never hide the only action behind hover.
- Never use a paragraph where a number + label will do.
- Never use "Confirm" alone when you know the value: "Book 10:30 AM" is always better.
- Never use icon-only navigation for ambiguous icons. Always icon + label for bottom nav.
- Never use a toast as the ONLY feedback for an error. Always inline + persistent.
- Never add a feature to a screen because there is empty space. Empty space is a feature — it creates focus.
- Never copy a "Before" screen and call it a redesign by changing the surface. Redesign = fewer decisions, clearer hierarchy, one primary action.

## 15. Implementation Prompts — Paste These Into Your AI Builder

### 15.1 Master Prompt — Web App

```
Build a web app using the App UI / UX Skill in this file.

PRODUCT: [Name, one-sentence job, who it is for]
SCREENS: [List screens in order, e.g. Dashboard, Detail, Booking, Settings]
DATA: [Real example data for each screen, e.g. Doctor: Dr. Elena Ruiz, Cardiologist, 15 years, 4.9 (312), $50 visit]

DESIGN RULES — FOLLOW EXACTLY:
- One screen, one job, one primary action per screen. Primary action is in a sticky footer or sticky header, never below the fold.
- Layout: Sidebar 260 + Content max 1120 OR Top nav + Centered content max 1120. Use Section 4 spacing tokens ONLY: 4, 8, 12, 16, 20, 24, 32, 48, 64. Side padding 24. Section gap 32. Inside-group gap 8 or 12. Card padding 16. Radius: Button 12, Card 16, Chip 999, Input 12.
- Typography: Max 2 font families, max 4 sizes per screen. Scale from Section 5: Hero 32-40 Bold, Title 20-24 Semibold, Section 14-16 Semibold, Body 16 Medium, Meta 14 Regular, Caption 12-13 Regular. Sentence case everywhere. No ALL CAPS buttons.
- Components: Reuse Section 6 components only. Segmented control for 2-3 choices. Week strip + "See Month" for dates. Grouped Morning / Afternoon for times. Summary row with chevron for collapsed details. One primary button with Verb + Object + Value: "Book 10:30 AM".
- Cards: One idea per card, padding 16, radius 16, 1px border OR soft elevation — pick one, use everywhere. No nested cards. No decorative shapes.
- Lists: Row 56-64, divider inset, section label + 2-6 rows per group.
- States: Design Default, Selected, Unavailable, Loading, Empty, Error, Success for every component. Unavailable = muted + strikethrough + not tappable. Selected = filled + bold + check icon. Error = icon + text + border change. Never a single cue alone.
- Accessibility: 44x44 min targets, visible focus, semantic headings, labels on all inputs, keyboard navigable, 200% zoom must not break.
- Motion: 150ms toggles, 200ms buttons/sheets, 300ms screens. Ease-out in, ease-in out. No bounce, no decorative animation.
- Do NOT add: extra features, extra buttons, extra charts, decorative shapes, carousels, or 2 primary buttons. If a screen has more than 7 interactive elements, group or collapse.
- Output: Complete, working, responsive web app with real data, empty/error/loading states, and the exact screens listed above. No placeholder text.
```

### 15.2 Master Prompt — Mobile App

```
Build a mobile app (390 width, single column) using the App UI / UX Skill in this file.

PRODUCT: [Name, one-sentence job, who it is for]
SCREENS: [List screens in order, e.g. Home, Detail, Booking, Profile]
DATA: [Real example data for each screen]

DESIGN RULES — FOLLOW EXACTLY:
- Mobile-first, 390 width, one column. Side padding 16 or 20. Section gap 24. Inside-group gap 8 or 12. Card padding 16. Radius: Button 12, Card 16, Chip 999, Input 12, Sheet top 20-24.
- One screen, one job, one primary action. Primary action in sticky footer above bottom nav: Context left (Price / Time / Total) + Button right (Verb + Value). Example: "$50 visit | Book 10:30 AM". Never 3 stacked buttons.
- Top bar: Back (left) + Title centered + 1 action (right). Bottom nav: 3-5 items, icon + label, one active. Pick ONE nav pattern from Section 6.8 and use everywhere.
- Typography: Scale from Section 5. Hero 32-40 Bold for ONE number per screen. Title 20-24 Semibold. Body 16 Medium. Meta 14 Regular. Caption 12-13. Sentence case. No ALL CAPS.
- Components: Segmented control for binary choice [In Person | Online Visit]. Week strip + "See Month" for dates. Morning / Afternoon groups for times. Summary row "Checkup · Insurance added >" for collapsed details. Person card: 64x64 rounded-square image + Name + Role · Experience + Rating (count) · Price.
- Map / Live screens: Content full-bleed, floating top card with Large number + short label + large icon + "Then" chip, max 2 floating side controls, floating bottom bar with Summary + ONE action. Hide bottom nav and promos on live screens.
- Touch: 48x48 min for primary, 44x44 min for all targets, 8 min gap. Respect safe area, notch, home indicator, keyboard. Inputs move above keyboard.
- States: Default, Selected, Unavailable, Loading, Empty, Error, Success for every component. Skeleton for lists, spinner + "Booking…" for buttons, check + message for success.
- Motion: 150ms toggles, 200ms buttons/sheets, 300ms screens. Subtle haptic on selection / success.
- Do NOT add: 2-column layouts, 2 primary buttons, full-month calendars by default, 4x4 grids without grouping, decorative shapes, auto-playing carousels.
- Output: Complete, working mobile app with bottom nav, sticky footers, bottom sheets, real data, empty/error/loading states, and the exact screens listed above. No placeholder text.
```

### 15.3 Prompt — Redesign Any Screen (Before → After)

```
Redesign this screen using the AppBrainy Before → After method in Section 3.

CURRENT SCREEN: [Paste screenshot description or attach screenshot / list all elements on screen]
ONE JOB OF THIS SCREEN: [One sentence]

DO:
1. Identify the ONE primary action. Rewrite it as Verb + Object + Value.
2. Group related items under small section labels. Increase space BETWEEN groups to 24, keep space WITHIN groups to 8-12.
3. Replace full lists / calendars with progressive disclosure: week strip + "See Month", grouped Morning / Afternoon, summary row with chevron.
4. Replace 3 stacked buttons with 1 sticky footer: Context left + Primary action right.
5. Replace long paragraphs with: Hero number + short label, or Person card: Image + Name + Role · Experience + Rating (count) · Price.
6. Replace radio dots with segmented control for 2-3 choices.
7. Replace paragraph instructions with Large number + short label + large icon + "Then" chip for next step.
8. Reduce to max 7 interactive elements visible. Move the rest to details / next step.

DO NOT: Add new features, add decorative shapes, add extra buttons, or change the job of the screen.
OUTPUT: Before (list of problems, 5 bullets) + After (new structure top-to-bottom) + New copy for hero, primary button, and summary row.
```

### 15.4 Prompt — Audit Any Screen

```
Audit this screen against the App UI / UX Skill in this file.

SCREEN: [Name + screenshot / description]

CHECK AND SCORE 1-5 EACH:
1. One job, one primary action? Is the primary action Verb + Object + Value and in a sticky footer?
2. Progressive disclosure? Week strip, grouped options, summary row — or everything at once?
3. Spacing: Are tokens 4,8,12,16,20,24,32,48,64 used? Is gap BETWEEN groups larger than gap WITHIN groups?
4. Typography: Max 2 families, max 4 sizes, sentence case, hero number clear?
5. Components: Segmented for binary, chips for filters, cards one-idea-per-card, no nested cards?
6. States: Default, Selected, Unavailable, Loading, Empty, Error, Success all designed?
7. Touch / Accessibility: 44x44 min, icon + label for nav, not a single cue alone for selected/error?
8. Motion: 150/200/300ms, no decorative animation?

OUTPUT: Table with Score + Issue + Fix for each. Then Top 3 fixes in order of impact, with exact new copy and exact new structure.
```

### 15.5 Prompt — Build One Component

```
Build this component using Section 6 of the App UI / UX Skill.

COMPONENT: [e.g. Person Card / Segmented Control / Week Strip / Sticky Footer / Summary Row]
CONTEXT: [Where it is used, what data it shows]
DATA: [Real example data]

RULES: Spacing tokens only, radius from Section 4, type scale from Section 5, states Default / Selected / Unavailable / Loading / Empty / Error / Success, 48x48 min touch, sentence case, no decorative shapes, no nested cards.
OUTPUT: Complete, working component with all states + 2 real examples.
```

---

## 16. Figma → Dev Handoff Checklist

- [ ] Every screen has: One job sentence, One primary action, Sticky footer (if it has a primary action)
- [ ] Every component is a reusable component: Button, Card, Input, Chip, Segmented, List Row, Sheet, Dialog, Nav
- [ ] Tokens are named and consistent: Spacing 4–64, Radius 8–24 / 999, Type 12–40
- [ ] Spacing audit: No off-scale values (13, 18, 27, etc.). Gap BETWEEN groups > gap WITHIN groups.
- [ ] Typography audit: Max 2 families, max 4 sizes per screen, sentence case, tabular numbers for prices / times
- [ ] States audit: Every component has Default, Selected, Unavailable, Loading, Empty, Error, Success
- [ ] Copy audit: Every button is Verb + Object + Value. Every empty state has Headline + 1 line + 1 action. Every error has Icon + Message + Retry.
- [ ] Accessibility audit: Keyboard only flow works, focus visible, screen reader labels present, 200% zoom works, 44×44 targets
- [ ] Mobile audit: 390 width works, one column, thumb zone respected, safe area + keyboard handled, bottom nav + footer do not overlap
- [ ] Web audit: Sidebar + content layout, tables for dense data, hover does not hide the only action, modals centered 480–560
- [ ] Motion audit: 150 / 200 / 300ms, no bounce, no decorative animation, reduced-motion respected
- [ ] Final test: Can a new user complete the ONE job of each screen in under 30 seconds without help? If not, simplify again.

---

## 17. Inspiration Sources To Study Weekly — The @uiadrian Method

Do not copy Dribbble shots. Study REAL apps and REAL flows:

- Study real flows: Pageflows, Refero, Mobbin, Screens Design, Collect UI, Gummble
- Study real patterns: Appshots, Nicey Done Club, Minimal Gallery, Recent Design, HTTPster
- Practice: Screenshot a great real screen → Place in Figma → Lower opacity → Trace components → Rebuild from memory → Compare spacing, type sizes, alignment. Repeat daily. This is how @uiadrian teaches "good" mobile design: trace, then replicate, then analyze why each decision was made.
- For each inspiration, write down: What is the ONE job? What is the ONE primary action? What was hidden with progressive disclosure? What was grouped? Copy the structure, not the surface.

---

## 18. What Good Feels Like

When this skill is applied correctly, the app feels:

- **Calm:** Lots of breathing space, one hierarchy, no competing elements
- **Obvious:** You know what to do without reading instructions
- **Fast:** One tap to the next step, no hunting for the button
- **Forgiving:** Unavailable is clear, errors tell you how to fix, undo is easy
- **Trustworthy:** Numbers are formatted, states are honest, nothing is hidden to trick you

If a screen feels busy, noisy, or anxious, it is not a styling problem. It is a structure problem. Go back to Section 3 and remove, group, or collapse until the ONE job is obvious.

---

*End of Skill — Apply to every web app and mobile app. No exceptions.*
