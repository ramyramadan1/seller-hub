---
name: seller-hub-builder
description: Build a multi-tenant SaaS back-office dashboard (Laravel API + Vue 3 SPA + Bootstrap 5) that lets sellers manage Salla, Zid, Shopify, WooCommerce and Amazon stores from one place, with AI product descriptions/images from a photo, one-click publishing to chosen channels, order syncing, and cost/revenue/profit analytics. Use this skill whenever the user mentions a unified seller dashboard, multi-channel/multi-store e-commerce management, connecting Salla/Zid/Shopify/WooCommerce/Amazon, multi-tenant store onboarding, AI product listing from photos, or profit analysis across marketplaces, even if they don't say "skill" or "SaaS".
---

# Seller Hub Builder

Build a simple, working multi-tenant seller dashboard. Favor the simplest option at every decision. Build in phases and keep the app runnable after each one. Multi-tenancy is **database-per-store** (see the multi-tenancy section).

## Stack (fixed, do not substitute)

- **Backend:** Laravel 11 (PHP 8.3), MySQL 8 or PostgreSQL, Redis, Laravel Queues + Scheduler, Sanctum (token auth for the SPA)
- **Frontend:** Vue 3 (Composition API, `<script setup>`), Vue Router, Pinia, Axios, Bootstrap 5 (CSS only, no jQuery), Vite
- **Node.js** is only the frontend build toolchain (Vite, npm). There is no Node back-end.
- **SPA:** Laravel serves one Blade shell (`resources/views/app.blade.php`) with a catch-all route; Vue Router handles pages; all data goes through `/api/*`.

If the user asks for "Node back-end" in addition, clarify once: the back-end is Laravel; Node builds the Vue assets.

## Multi-tenancy (database per store)

Each store gets its **own database**. Use the `stancl/tenancy` package (v3, Laravel 11 compatible; check its docs for the current install steps) in **multi-database mode, no domains required**.

```bash
composer require stancl/tenancy
php artisan tenancy:install      # publishes config, migrations, routes/tenant.php
```

**Two kinds of data**

| Central database (`central`) | Tenant database (one per store, e.g. `tenant_{id}`) |
|---|---|
| `tenants`, `users` (with `tenant_id`, `role`), `personal_access_tokens`, `plans`, `subscriptions` | `channels`, `products`, `product_channel`, `orders`, `order_items`, `cost_entries` |
| `channel_routes` (webhook/OAuth lookup, see below) | `ai_accounts` (the store's own keys), `ai_jobs` |
| `platform_ai_accounts`, `ai_price_list`, `credit_wallets`, `credit_transactions` | |

Rule: **anything money-related or platform-owned stays central; anything the store owns stays in its database.** Tenant tables have **no `tenant_id` column** (the database is the boundary). Tenant migrations live in `database/migrations/tenant/`; central ones in `database/migrations/`.

**How the current store is chosen (no subdomains needed)**
- Users and Sanctum tokens live in the **central** database. Login happens centrally.
- A `SetTenant` middleware runs after `auth:sanctum`: it loads `auth()->user()->tenant` and calls `tenancy()->initialize($tenant)`. From then on the default connection points to that store's database. Never accept a tenant id from the request body, headers or URL to pick the database.
- Add subdomains (`InitializeTenancyBySubdomain`) later only if asked; the rest of the design does not change.
- Central-only endpoints (register, login, credits, payment webhooks, admin) run **without** tenancy initialized and use the `central` connection explicitly.

**Provisioning a new store (`POST /api/register`)**
1. In one central transaction create the `Tenant` (name, slug, currency, locale, plan) and the owner `User`.
2. Dispatch stancl's provisioning pipeline as queued jobs: `CreateDatabase`, `MigrateDatabase` (tenant migrations), `SeedDatabase` (default settings). Set `tenants.status = provisioning`, then `active` when done; the SPA polls `GET /api/tenant/status` and shows a "Setting up your store..." screen.
3. Give optional free trial credits (central `credit_transactions`, kind `bonus`).
4. If any step fails, mark the tenant `failed`, drop the half-built database, and let the user retry. Provisioning must be idempotent.
5. The MySQL/PostgreSQL user in `.env` needs the `CREATE DATABASE` privilege. Use a database prefix (`tenant_`) so tenant databases are easy to find and back up.

**Bootstrappers to enable in `config/tenancy.php`**
- `DatabaseTenancyBootstrapper` (switches DB)
- `QueueTenancyBootstrapper` (queued jobs automatically remember their tenant and re-initialize it when run; keep it enabled)
- `FilesystemTenancyBootstrapper` (private uploads and AI images stored per store; verify that one store can never read another's path)
- `CacheTenancyBootstrapper` **only with Redis** (it needs cache tags; the `database` cache driver does not support tags, so disable this bootstrapper if you are not on Redis yet)

**Things that behave differently with database-per-tenant (handle them explicitly)**
- **Cross-tenant jobs:** the scheduler has no tenant. Loop over active tenants and run work inside each: `Tenant::active()->each(fn ($t) => $t->run(fn () => SyncAllChannels::dispatch()))`, or dispatch one job per tenant. Never run a tenant query outside `$tenant->run()` or an initialized request.
- **Webhooks and OAuth callbacks** arrive with no logged-in user and hit the central app. Use the central `channel_routes(id, tenant_id, channel_id, driver, external_store_id, webhook_token)` table: the webhook URL contains the `webhook_token` (e.g. `/webhooks/{driver}/{token}`), the central controller looks up the tenant, initializes tenancy, verifies the platform signature, and dispatches the job. For OAuth, put a signed `state` containing the tenant id in the authorize URL; the callback validates it, then initializes that tenant to save tokens. Create or delete the `channel_routes` row whenever a channel is connected or removed.
- **AI credits** are central: reserve, charge and refund through the `central` connection even when tenancy is initialized. Store only the ledger transaction id in tenant `ai_jobs` (no cross-database foreign keys, ever).
- **Migrations:** after deploying a new tenant migration run `php artisan tenants:migrate` (loop over all tenants, tolerate and report per-tenant failures instead of aborting the batch). Never edit an old tenant migration that has already run; add a new one.
- **Backups, restore and deletion:** per-store backup and a "delete my store" flow (soft delete the tenant, drop the database after a grace period) belong to the SaaS phase.
- **Reporting across stores** (operator dashboard) must be built from central tables or a nightly job that stores summary numbers centrally; do not query every tenant database live.
- **Connection pool:** many tenants means many database names, not many open connections. Keep `queue:work` workers long-lived and let the package switch connections; reset state between jobs.
- Roles: `owner`, `staff` (simple `role` column on central `users`). Channel credentials and AI keys are encrypted (`'encrypted:array'` / `'encrypted'` casts) in the tenant database.

## Core data model

**Central database**
```
tenants(id, name, slug, currency, locale, plan_id, status[provisioning|active|failed|suspended],
        ai_source_default[own|platform], data json)
users(id, tenant_id, name, email, password, role[owner|staff])
channel_routes(id, tenant_id, channel_id, driver, external_store_id, webhook_token unique)
platform_ai_accounts(id, provider, label, purpose[text|image|both], api_key(encrypted),
                     key_last4, model_text, model_image, base_url, status, is_default_text, is_default_image)
ai_price_list(id, type[description|image], provider nullable, credits_per_unit, unit[request|image], active)
credit_wallets(tenant_id PK, balance, updated_at)
credit_transactions(id, tenant_id, kind[topup|reserve|charge|refund|adjust|bonus], amount,
                    balance_after, ai_job_ref nullable, payment_ref nullable unique, note, created_at)
```

**Tenant database (one per store, no `tenant_id` columns)**
```
channels(id, driver[salla|zid|shopify|woocommerce|amazon], name,
         credentials(encrypted json), external_store_id, status, last_synced_at)
products(id, sku, title, description, price, cost_price, stock,
         images json, source_image, ai_status)
product_channel(id, product_id, channel_id, external_id,
                sync_status[pending|synced|failed], last_error, synced_at)
orders(id, channel_id, external_id, status, currency, subtotal, shipping,
       discount, tax, channel_fee, total, customer json, placed_at, raw json)
order_items(id, order_id, product_id nullable, sku, title, qty, unit_price, unit_cost)
cost_entries(id, type[ads|shipping|fee|other], amount, channel_id nullable, date)
ai_accounts(id, provider[anthropic|openai|google|stability|custom], label,
            purpose[text|image|both], api_key(encrypted), key_last4, model_text nullable,
            model_image nullable, base_url nullable, is_default_text bool, is_default_image bool,
            status[active|invalid|disabled], last_tested_at, last_error, monthly_limit nullable)
ai_jobs(id, product_id, ai_account_id nullable, source[own|platform],
        type[description|image], status, input, output, tokens_used,
        credits_reserved, credits_charged, credit_tx_ref nullable, error)
```
Platform AI accounts are **central only** (`platform_ai_accounts`); a tenant's `ai_jobs.source = platform` just means the job resolved to the platform account at run time. Tenants never see platform keys.

Unique index on `(channel_id, external_id)` for `orders` so syncing is idempotent.

## Channel connector pattern (the heart of the app)

One interface, one class per platform. Controllers and jobs never call platform APIs directly.

```php
interface ChannelDriver {
    public function authorizeUrl(Channel $c): ?string;          // OAuth platforms
    public function handleCallback(Channel $c, array $q): void; // store tokens
    public function testConnection(Channel $c): bool;
    public function pushProduct(Channel $c, Product $p): string; // returns external_id
    public function updateProduct(Channel $c, Product $p): void;
    public function fetchOrders(Channel $c, ?Carbon $since): iterable; // normalized
    public function normalizeOrder(array $raw): OrderDTO;
}
```

Register drivers in a `ChannelManager` (`app(ChannelManager::class)->driver($channel)`). Adding a platform = one new class + one config line.

**Platform notes (verify endpoints and scopes against each platform's current developer docs before coding; do not rely on memory):**

| Platform | Auth | Build order |
|---|---|---|
| WooCommerce | REST consumer key/secret (user pastes them + store URL) | 1st (easiest, good test) |
| Shopify | OAuth app install, Admin API | 2nd |
| Salla | OAuth2 app (Salla Partners), webhooks | 3rd |
| Zid | OAuth2 app (Zid Partners), webhooks | 4th |
| Amazon | SP-API with LWA OAuth, seller authorization, heavy approvals | Last, optional phase; ship the others first |

Rules for every driver:
- Wrap HTTP in Laravel `Http::` client with timeouts, retries with backoff, and 429 handling.
- Refresh OAuth tokens automatically before expiry; mark channel `status=needs_reauth` on failure and surface it in the UI.
- Map platform order statuses to a small internal set: `new, paid, shipped, delivered, cancelled, refunded`.
- Keep the raw payload in `orders.raw` for debugging.

## AI accounts (bring your own key)

Every store chooses where its AI comes from. Two sources, both always available:

1. **Own account (free to the platform):** the store adds its own provider keys on the **AI Accounts** page and pays its provider directly. No credits are used.
2. **Platform AI (paid with credits):** the store uses the SaaS operator's default account and pays per use from a prepaid credit balance, at prices the operator sets (include a markup over real provider cost).

Each store sets a preferred source in Settings (`tenants.ai_source_default = own|platform`) and can override it on every generation in AI Studio. A store may use both: e.g. own key for text, platform AI for images.

**Backend**
- CRUD: `GET/POST /api/ai-accounts`, `PUT/DELETE /api/ai-accounts/{id}`, `POST /api/ai-accounts/{id}/test`, `POST /api/ai-accounts/{id}/make-default` (body: `type=text|image`).
- Only `owner` role can create, edit or delete accounts; staff can only use them.
- Store `api_key` with the `encrypted` cast. After saving, never return the key: return `key_last4` only (show `sk-••••1234`). Editing allows replacing the key, never reading it. Exclude it from logs, exception reports and queue payloads (pass the account id to jobs, not the key).
- **Test connection** makes a tiny real call (a 5-token text prompt, or a provider "list models" call for image providers), saves `status`, `last_tested_at`, `last_error`. Run it automatically on create and on key change. Reject saving as `active` if the test fails.
- Provider adapters behind two interfaces, one class per provider, resolved by `AiManager`:

```php
interface TextAiProvider  { public function describeProduct(AiAccount $a, string $imagePath, array $opts): ProductDraftDTO; }
interface ImageAiProvider { public function generateImages(AiAccount $a, string $prompt, ?string $refImage, int $n): array; }
```
- Resolution order for a job: source/account chosen explicitly in AI Studio, else the tenant's default account for that type (if `ai_source_default=own`), else the platform account (if `ai_source_default=platform`), else fail with a clear "Add an AI account or buy credits" error. Never silently switch from own to platform: if the own account fails, ask the user whether to retry with platform AI (it costs credits).
- Handle provider errors explicitly: invalid key (mark account `invalid`, notify owner), rate limit (retry with backoff), quota exceeded (mark failed, show message). Optional `monthly_limit` counts jobs or tokens per account.
- Anthropic can be used for text and vision only; image generation needs an image-capable provider, so `purpose` must be validated against what the provider supports.

**Frontend: AI Accounts page** (Settings > AI Accounts, also reachable from a "Add AI account" link in AI Studio when none exists)
- Table/cards: provider logo, label, purpose badge (Text / Image), masked key, status badge (Active / Invalid / Disabled), default badges, last tested time.
- "Add account" modal: provider select, label, API key (password input with show/hide while typing only), optional model names and base URL (advanced section collapsed), purpose. On submit show "Testing connection..." then success or the provider error.
- Row actions: Test, Set as default text / default image, Edit (replace key), Disable, Delete (confirm; block if it is the only default and AI jobs are running).
- A **Platform AI** card at the top: "Use our AI, no key needed", current credit balance, price per description/image, **Buy credits** button, link to usage history. Radio control for the store default: "My own account" or "Platform AI (uses credits)". Disable the "My own account" option with a hint if no active own account exists.
- Empty state explaining what a key is and linking to the provider's key page.
- In AI Studio, a source selector per generation: "My account: Anthropic - Main (free)" or "Platform AI (about 4 credits)". Show the estimate and remaining balance before the user presses Generate, and a Buy credits link when the balance is too low.

## Platform AI credits (pay-per-use)

Keep billing simple: a prepaid **credit wallet** per store, a price list, and an append-only ledger.

- **Price list:** `ai_price_list` rows such as description = 1 credit/request, image = 3 credits/image. The operator edits it in the admin panel. 1 credit maps to a fixed currency amount for top-ups.
- **Flow for a platform-source job (use a DB transaction + row lock on the wallet):**
  1. Estimate cost from the price list and show it in the UI before the user confirms.
  2. **Reserve** credits (`kind=reserve`, balance decreases). If balance is insufficient, return `402` with a "Buy credits" action; do not start the job.
  3. Run the job with the platform account.
  4. On success **charge** the final amount (reserve becomes charge; any difference is refunded). On failure or timeout **refund** the reserve automatically.
  5. Every change writes a `credit_transactions` row with `balance_after`. Never edit the balance without a ledger row.
- **Top-up:** `POST /api/credits/checkout` creates a payment session with the gateway (Stripe or a local gateway), and a verified webhook adds a `topup` transaction. Make the webhook idempotent using `payment_ref`.
- **Safeguards:** per-tenant daily cap on platform AI usage, low-balance alert (email + banner at 20%), optional free trial credits (`bonus`) on registration, and global kill switch if the platform provider key fails or hits its quota.
- **Reporting:** a Usage tab shows credits spent per day, per type and per product, and an export. The operator's admin panel shows provider cost vs credits sold (margin).
- Jobs using an own account record `source=own`, charge nothing, and still log usage for the store's own statistics.

## AI product studio (photo to listing)

Flow: upload photo, AI drafts a listing, user edits, user picks channels, publish.

1. `POST /api/products/ai-draft` with the photo (store on a private disk, validate image type and size). Create an `ai_jobs` row and dispatch a queued job. Return the job id.
2. **Description job:** resolve the tenant's text AI account (see AI accounts), then send the photo to its vision-capable model (e.g. Anthropic Claude) with a prompt asking for strict JSON: `title`, `short_description`, `long_description` (HTML-safe), `bullet_points`, `suggested_keywords`, in the tenant's locale(s), e.g. Arabic and English. Parse and validate the JSON; retry once on malformed output.
3. **Image job:** resolve the tenant's image AI account and call it through `ImageAiProvider`. Claude does not generate images, so this must be an image-capable provider. Produce 2-4 variants (clean white background, lifestyle). Save to storage and attach to the draft.
4. UI polls `GET /api/ai-jobs/{id}` (or uses a 2-second interval) and shows progress. Never block the HTTP request on AI calls.
5. User reviews and edits everything, picks one or more channels, and presses **Publish**. This dispatches one `PublishProductToChannel` job per channel and records results in `product_channel`.
6. Per-tenant AI usage limits (daily count tied to plan) and optional per-account monthly limits. Keys live only encrypted in `ai_accounts`; the front-end never receives them.
7. Warn users in the UI that generated images must faithfully represent the real product.

## Orders sync

- **Primary:** webhooks per channel where supported (verify the signature, then dispatch a job; respond fast with 200).
- **Fallback:** scheduler runs `SyncOrders` every 15 minutes per active channel using `last_synced_at` as the cursor, plus a manual "Sync now" button.
- Upsert orders by `(channel_id, external_id)` inside the tenant database. Match line items to products by SKU; store `unit_cost` on the order item at sync time (snapshot from `products.cost_price`) so history stays correct if costs change later.
- The scheduler loops over active tenants (`$tenant->run(...)`) and dispatches one job per channel; jobs carry their tenant automatically via the queue bootstrapper. Use separate queues (`sync`, `ai`, `publish`) so a noisy store or slow AI call does not block others (Horizon if Redis is available, otherwise plain queue workers).

## Analytics (keep it to SQL aggregates)

Definitions (show these in the UI help text):
- **Revenue** = sum of `orders.total` for non-cancelled, non-refunded orders (net of discounts, optionally excluding tax and shipping collected).
- **COGS** = sum of `qty * unit_cost`.
- **Channel fees** = `orders.channel_fee` (set from platform data if available, otherwise a configurable % per channel).
- **Other costs** = `cost_entries` in the period (ads, shipping paid, etc.).
- **Gross profit** = Revenue - COGS. **Net profit** = Gross profit - channel fees - other costs. **Margin %** = Net profit / Revenue.

Endpoints (all accept `from`, `to`, optional `channel_id`):
- `GET /api/analytics/summary` KPIs
- `GET /api/analytics/timeseries?group=day|week|month`
- `GET /api/analytics/products` ranked by revenue, units, margin; flag products with missing `cost_price`
- `GET /api/analytics/channels` comparison

Use a currency per tenant; if channels use different currencies, store the order currency and convert with a simple daily rate table (add only when needed).

## Frontend pages (SPA)

Keep to a left sidebar + top bar Bootstrap layout. Pages:
1. Login / Register (creates store)
2. Dashboard (KPI cards, revenue vs profit chart, recent orders)
3. Channels (connect/disconnect cards per platform, status badge, "Test connection")
4. Products (table, search, bulk "publish to...")
5. **AI Studio** (account selector, upload photo, progress, editable result, image picker, channel checkboxes, Publish, per-channel result status)
6. Orders (filters by channel/status/date, detail drawer)
7. Analytics (date range, channel filter, product profitability table, CSV export)
8. **AI Accounts & Credits** (own provider keys, platform AI card, balance, top-up, usage history)
9. Settings (store info, team, costs, language)

Conventions: one Axios instance with token interceptor and 401 redirect; one Pinia store per domain (`auth`, `channels`, `products`, `orders`); lazy-load route components; RTL support via `dir` switch for Arabic (use Bootstrap's RTL build); toasts for errors; loading skeletons. Use the frontend-design skill for visual polish only after functionality works, but stay within Bootstrap components.

## Build phases (stop and verify after each)

1. **Foundation:** Laravel + Vue + Vite scaffold, install `stancl/tenancy`, Sanctum auth (central), `SetTenant` middleware, store registration with database provisioning, layout shell. *Test first:* register two stores; confirm two separate databases exist, a product created in store A is absent in store B, a store A token can never reach store B data, and a queued job runs in the correct store.
2. **Channels + WooCommerce driver:** connect, test connection, `ChannelManager`.
3. **Products + publish:** manual product CRUD, publish job, `product_channel` statuses.
4. **Orders sync + dashboard KPIs** for WooCommerce.
5. **AI Accounts + AI Studio:** AI Accounts page and provider adapters first (test connection works), then description generation, then images. Build the platform-AI source with a manual credit grant (admin adds credits) so the reserve/charge/refund flow can be tested before payments exist.
6. **Analytics pages** and cost entries.
7. **Add drivers one at a time:** Shopify, Salla, Zid; Amazon last.
8. **SaaS layer:** plans, limits, credit top-up via payment gateway, price list and platform AI accounts in the admin panel, central operator dashboard, per-store backup, store suspension and deletion, and a safe `tenants:migrate` deploy routine.

## Quality checklist

- Isolation tests pass (separate databases, tokens, files, cache keys, queued jobs); no tenant query runs outside an initialized tenant; no cross-database foreign keys.
- `tenants:migrate` works for all stores and reports per-store failures; provisioning is idempotent and cleans up on failure.
- Secrets (channel credentials and AI API keys) encrypted; never in logs, API responses, or queue payloads; only last 4 characters shown.
- Credit wallet changes are transactional, ledgered and idempotent (webhooks, retries); tests cover insufficient balance, failed job refund and double webhook delivery.
- Platform AI keys are never visible to tenants and live only in the central database.
- Only owners can manage AI accounts; tests cover key masking and that one store cannot read another store's `ai_accounts`.
- All outbound calls (platforms, AI) run in queued jobs with retries, timeouts, and failure states shown in the UI.
- Webhook signatures verified; endpoints rate-limited.
- Idempotent syncs; no duplicate orders after re-runs.
- Seeders with demo tenant and fake orders so analytics can be demoed without live stores.
- README with setup: `composer install`, `npm install`, `.env` keys (platform app credentials, AI keys), `php artisan migrate --seed`, `npm run dev`, `php artisan queue:work`, `php artisan schedule:work`.

## Working style

- Ask at most one clarifying question; otherwise state assumptions and proceed.
- Generate code phase by phase, with file paths, migrations, models, controllers, jobs, Vue pages, and tests.
- Prefer boring, readable code over clever abstractions. Do not add packages unless they remove real work (acceptable: `laravel/sanctum`, `laravel/horizon`, an official platform SDK if one is maintained).
