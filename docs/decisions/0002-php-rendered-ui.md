# ADR 0002: Build a Symfony-rendered responsive UI

- **Status:** Accepted
- **Date:** 2026-11-13

## Context

The product brief requires one Symfony application and a mobile-installable PWA. The supplied UX skill prioritizes progressive disclosure, a clear next action, a mobile-first 390px layout and low visual noise.

## Decision

Use Twig as the page renderer with Symfony UX/Stimulus/Turbo available for interactions, and Tailwind CSS for the tokenized stylesheet. The current shell uses server-rendered Twig and a small CSS design system derived from the reference palette.

## Consequences

- No separate JavaScript SPA is required for the MVP.
- Native HTML forms remain usable without JavaScript.
- PWA caching will be limited to static non-sensitive assets.
