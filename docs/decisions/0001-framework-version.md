# ADR 0001: Use Symfony 7.4 LTS in the current workspace

- **Status:** Accepted for the initial implementation
- **Date:** 2026-11-13

## Context

The master prompt prefers Symfony 8.1 with PHP 8.4 when package compatibility is clean. PHP 8.4.27 is installed. A Composer upgrade attempt showed that the currently required `symfonycasts/tailwind-bundle` 0.8.0 only allows Symfony 5.4–7.x, while the resolved `doctrine/doctrine-bundle` 2.19.4 allows Symfony 6.4–7.x. The Symfony AI 1.0 beta and MCP Bundle beta accept Symfony 7.4 and 8.x.

## Decision

Use the maintained Symfony 7.4 LTS line, and pin the Symfony AI/MCP beta packages and their resolved dependencies in `composer.lock`. This keeps the required UI/database packages compatible while retaining the AI/MCP architecture.

## Consequences

- Symfony framework components are locked at 7.4.x.
- Upgrade to Symfony 8.1 after Doctrine Bundle and Tailwind Bundle publish compatible releases, then verify all package constraints and run the full quality gate.
- Symfony AI and MCP are beta/experimental packages and may change before final release.
