# Deployment

## Local

The current local stack is defined in `compose.yaml` and includes PHP 8.4, PostgreSQL 16, Redis 7 and a Messenger worker:

```powershell
docker compose up --build -d
docker compose ps
```

Compose applies Doctrine migrations and idempotently seeds the synthetic demo workspace on startup. Open <http://127.0.0.1:8000>. Stop the local services with `docker compose down`; named database and cache volumes are retained.

The app is not deployable to Upsun yet. Owner setup requires a one-time environment token. Google OIDC code is present but needs production client credentials, exact redirect configuration, branding/review and a real authorized sign-in test. Email verification, password reset, production migration hooks, a persistent worker topology, provider credentials, production asset build and production security settings still need implementation and verification. Compose currently seeds synthetic fixtures for local development; production must not use Compose or invoke that seed command.

## Production target

Upsun remains the selected production target. Before deployment, add and verify the Upsun application config, PostgreSQL service relationship, persistent Messenger worker, health check, safe migration/deploy hooks, environment variable list, backups and rollback procedure. Do not publish the MCP endpoint until its authentication is complete. Trial hosting is temporary and resource costs must be monitored.

## Environment variables

Configure production values in Upsun's environment rather than committing them. `APP_OWNER_SETUP_TOKEN` must be random, used only for initial provisioning, and removed after the owner is created. `AI_PROVIDER` defaults to `disabled`; provider configuration and model are explicit. Google OIDC uses `GOOGLE_OIDC_CLIENT_ID`, `GOOGLE_OIDC_CLIENT_SECRET`, and exact allowlisted `GOOGLE_OIDC_REDIRECT_URI`; configure secrets only in provider/dashboard environment, then test a linked account. Do not point a production environment at the owner's desktop Ollama endpoint. MailOS configuration remains empty; the documented JMAP scope combines read and submission. The adapter is disabled pending a separately authorized read-only scope. GitHub Actions CI is defined in `.github/workflows/ci.yml`, but has not run because no public repository remote is configured. No Upsun project, public remote, production domain or production provider connection is verified.
