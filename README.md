# Seller Hub

Multi-tenant back-office for online sellers: manage Salla, Zid, Shopify, WooCommerce and Amazon stores from one place, create product listings with AI from a photo, publish to chosen channels, sync orders, and see cost / revenue / profit.

**Stack:** Laravel 11 (PHP 8.3) API · Vue 3 SPA (Composition API, Pinia, Vue Router) · Bootstrap 5 · MySQL 8 / PostgreSQL · Redis (optional until later phases) · [`stancl/tenancy`](https://tenancyforlaravel.com) v3 · Sanctum tokens.

The full build plan is in [`docs/SELLER_HUB_PLAN.md`](docs/SELLER_HUB_PLAN.md).

## Status

| Phase | Scope | State |
|---|---|---|
| 1 | Foundation: scaffold, tenancy (database per store), Sanctum auth, `SetTenant`, store registration + provisioning, SPA shell, isolation tests | **Implemented** |
| 2 | Channels + WooCommerce driver (connect, test connection, disconnect, `ChannelManager`) | **Implemented, tests not yet run** |
| 3 | Products CRUD + publish jobs (`product_channel` status per channel) | **Implemented, tests not yet run** |
| 4 | Orders sync + dashboard KPIs | Planned |
| 5 | AI accounts + AI Studio + credits | Planned |
| 6 | Analytics + cost entries | Planned |
| 7 | Shopify, Salla, Zid, Amazon drivers | Planned |
| 8 | SaaS layer (plans, payments, admin, backups) | Planned |

### Verification status of Phase 1

* Frontend: `npm run build` succeeds.
* PHP: every file passes `php -l`, and every `stancl/tenancy` API used was checked against the package source.
* **The PHP test suite has not been executed yet.** It was written in an environment without access to Composer's package registry, so `composer install` could not run there. Run `php artisan test` locally (setup below) before building on top of Phase 1, and treat any failure as a bug to fix first.

## How multi-tenancy works

* **Database per store.** Each store has its own database `tenant_{uuid}`. Tenant tables have no `tenant_id` column; the database is the boundary.
* **Central database:** `tenants`, `users`, `personal_access_tokens`, `plans`, `credit_wallets`, `credit_transactions`, plus Laravel's `jobs` / `failed_jobs` / cache tables. Anything money-related or platform-owned stays central.
* **Tenant database:** `settings`, `products` (more in later phases). Migrations live in `database/migrations/tenant/`.
* **Choosing the store:** login happens centrally. The `SetTenant` middleware runs after `auth:sanctum`, takes the store from the authenticated user and initializes tenancy. The tenant id is never read from the request body, headers or URL (covered by a test).
* **Provisioning** (`POST /api/register`): one central transaction creates the store, owner and trial credits; the queued `ProvisionTenant` job then creates the database, runs tenant migrations, seeds defaults and marks the store `active`. It is idempotent, and on failure it drops the half-built database and marks the store `failed`. The SPA polls `GET /api/tenant/status` and the owner can retry with `POST /api/tenant/retry`.
* Central/tenant connection safety: queue, cache and session tables are pinned to the central connection in `config/*.php`, so they never follow the active tenant.

## Setup

Requirements: PHP 8.3 with the usual Laravel extensions, Composer, Node 20+, MySQL 8 or PostgreSQL whose user has **`CREATE DATABASE`**.

```bash
composer install
npm install
cp .env.example .env          # set DB_* (central database must exist)
php artisan key:generate
php artisan migrate --seed    # central schema + the "free" plan
npm run dev                   # Vite (use `npm run build` for production assets)
php artisan serve             # http://localhost:8000
php artisan queue:work        # REQUIRED: provisions each new store's database
php artisan schedule:work     # scheduler (used from phase 4)
```

`php artisan queue:work` must be running, otherwise new stores stay on the "Setting up your store…" screen. For quick local experiments you can set `QUEUE_CONNECTION=sync` so provisioning runs inline.

### Tests

Create an empty database named `seller_hub_test` (or override `DB_*` in the environment) with a user that can `CREATE DATABASE`, then:

```bash
php artisan test
```

Phase 1 acceptance tests (`tests/Feature`): two stores get two databases; a product created in store A is absent in store B; a store A token cannot reach store B data even when the client sends tenant hints; central and store tables are not mixed; tenancy is ended after each request; a queued job runs inside the store that dispatched it; provisioning is idempotent, cleans up on failure and can be retried.

### Deploying a new tenant migration

Add the migration under `database/migrations/tenant/` (never edit one that already ran), then run `php artisan tenants:migrate`. A per-store failure report that does not abort the batch is part of phase 8.

## API (phase 1)

| Method | Path | Notes |
|---|---|---|
| POST | `/api/register` | Creates store + owner, returns Sanctum token |
| POST | `/api/login` | Returns token |
| POST | `/api/logout` | Revokes the current token |
| GET | `/api/me` | User, store, credit balance |
| GET | `/api/tenant/status` | `provisioning` / `active` / `failed` / `suspended` |
| POST | `/api/tenant/retry` | Owner only, only for a `failed` store |
| GET, POST | `/api/products` | Tenant route (409 until the store is active) |
| GET | `/api/channels` | Connected channels (no secrets) + available drivers |
| POST | `/api/channels` | Owner only. WooCommerce: `store_url`, `consumer_key`, `consumer_secret`. Tested before saving |
| POST | `/api/channels/{id}/test` | Re-test; sets `connected` / `needs_reauth` / `error` |
| GET, PUT, DELETE | `/api/products/{id}` | Show / update / delete (deleting does not touch the sales channels) |
| POST | `/api/products/publish` | `{product_ids, channel_ids}` → queues one `PublishProductToChannel` job per pair (queue `publish`); re-publishing updates instead of duplicating |
| DELETE | `/api/channels/{id}` | Owner only; also removes the central `channel_routes` row |

## Notes

* `config/tenancy.php` enables the cache bootstrapper only when `CACHE_STORE=redis`, because it needs cache tags.
* `.env`, secrets, `vendor/`, `node_modules/` and `public/build/` are git-ignored.
