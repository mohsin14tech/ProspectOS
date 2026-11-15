# Deployment

## Local

The current local stack is defined in `compose.yaml` and includes PHP 8.4, PostgreSQL 16, Redis 7 and a Messenger worker:

```powershell
docker compose up --build -d
docker compose ps
```

Compose applies Doctrine migrations and idempotently seeds the synthetic demo workspace on startup. Open <http://127.0.0.1:8000>. Stop the local services with `docker compose down`; named database and cache volumes are retained.

Production configuration is prepared in `.platform.app.yaml`, `.platform/services.yaml`, `.platform/routes.yaml`, and `.environment`. It targets PHP 8.4 and PostgreSQL 16, builds locked Composer dependencies and Tailwind assets, runs Doctrine migrations and Messenger transport setup, and starts a separate Messenger worker. `.environment` reads `APP_SECRET` from Upsun project entropy, `DATABASE_URL` from the database relationship, and `DEFAULT_URI` from the primary route; no credentials are committed. The `/mcp` path is blocked at the web router until the endpoint has an approved public authentication design.

This is configuration work only. No Upsun project has been created or deployed. The new Upsun account is awaiting provider verification (support request #553971). Compose seeds demo fixtures only in local development; production deploy hooks do not run the demo seeder. The configured hosted search mode is `demo` and AI stays disabled until a real provider is approved and verified. A demo-mode deploy must not be described as live web search or AI.

## Production target

Upsun remains the selected production target. After account verification and project creation, review the generated project configuration and free-trial/resource limits before connecting the repository. The configuration uses the default HTTPS route and disables shared-router caching for authenticated application pages. `/healthz` returns a lightweight JSON response; it does not test database, queue, worker, search or AI health. Take a database backup before any production migration that could affect existing data and verify the environment after each deploy. Upsun Fixed deploy-hook failures do not halt deployment, so CI remains the blocking quality gate and deployment activity logs must be checked. Trial duration, monthly incentive terms and resources can change; review the live Console billing and plan terms before any paid conversion.

## Environment variables

Configure production credentials and values in Upsun's sensitive environment variables rather than committing them. Set a random `APP_OWNER_SETUP_TOKEN` only for initial owner provisioning and remove it after the owner is created. Do not save account passwords in this repository. AI remains disabled; enabling it requires an explicit provider/model choice, data disclosure and current billing/data-term review. Google OIDC requires `GOOGLE_OIDC_CLIENT_ID`, `GOOGLE_OIDC_CLIENT_SECRET`, and an exact `GOOGLE_OIDC_REDIRECT_URI` matching the assigned domain, followed by a real linked-account test. Do not point production at a developer's desktop Ollama endpoint. MailOS remains disconnected because its JMAP credential also permits submission; no sending flow is implemented. GitHub Actions is green on run [43669837591](https://github.com/mohsin14tech/ProspectOS/actions/runs/43669837591). No Upsun project, production domain or live provider connection is verified yet.

## Current Upsun configuration

- `.platform.app.yaml` declares PHP 8.4, the `intl`/`pdo_pgsql` extensions, the PostgreSQL relationship, a writable `var` mount, a Messenger worker, and a deny rule for `/mcp`. The build uses the checked-in Composer lockfile and builds the Tailwind asset. The deploy hook applies migrations, creates the Messenger table, then clears the production cache.
- `.platform/services.yaml` provisions PostgreSQL 16. `.platform/routes.yaml` uses Upsun's default HTTPS hostname, disables router caching. The current setup assumes one web instance; add shared session storage before scaling horizontally.
- `.environment` maps Upsun's relationship/route data into Symfony's `DATABASE_URL` and `DEFAULT_URI`, and derives `APP_SECRET` from the project's random entropy value.
- This YAML passed local parsing and `platform app:config-validate` with the official Upsun Fixed CLI 5.16.0. The CLI check validates configuration schema; a hosted build and live deployment are still pending. Re-run `platform app:config-validate` from the repository root after changing platform configuration. Upsun deploy-hook errors do not stop deployment, so run the green GitHub CI first and inspect Upsun activity logs after each deployment. Keep the service size and trial/billing limits under review.
