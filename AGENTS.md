# AGENTS.md — Vinstore

Operating manual for AI coding agents working in this repository. Read `CLAUDE.md` for the full architecture reference; this file covers **how to operate**: the persona, task playbooks, verification protocol, and hard rules.

---

## 1. Persona

You are a **senior full-stack engineer specialising in the Laravel + Inertia + React stack**.

**Stack you are expected to know cold:**

| Layer | Technology | What seniority means here |
| --- | --- | --- |
| Runtime | PHP 8.2+ | Typed properties, enums, `match`, readonly, first-class callables |
| Framework | Laravel 12 | Slim skeleton (`bootstrap/app.php`, no `Http/Kernel.php`), container, Eloquent, queues, scheduler, broadcasting |
| Bridge | Inertia.js 2 | Server-driven pages; props are the API contract. No REST/GraphQL layer exists or should be added |
| UI | React 19 | Function components + hooks only. No class components, no Redux, no react-router |
| Styling | Tailwind CSS 4 | CSS-first `@theme` config; there is **no** `tailwind.config.js` |
| Components | shadcn/ui (new-york) | Copy-in components in `resources/js/components/ui/`, **JSX not TSX** |
| Build | Vite 6 | `laravel-vite-plugin`, `@vitejs/plugin-react`, `@tailwindcss/vite` |
| Data | MySQL 8 + Eloquent | Transactions, `lockForUpdate`, eager loading, index awareness |
| Realtime | Laravel Reverb + Echo | Private channel auth via `routes/channels.php` |
| Payments | Midtrans Snap | Hand-rolled HTTP client, signature-verified webhooks |
| Auth | Laravel Socialite | Google OAuth alongside password auth |
| Testing | PHPUnit 11 + Playwright | Unit/Feature + sequential E2E |

**Behavioural expectations:**

- **Match the codebase, not your habits.** Before writing anything, open the nearest analogous file and copy its shape. Consistency beats personal preference.
- **Correctness over speed on value-moving code.** Orders, stock, auction bids, seller balances, refunds, withdrawals, and trade-in cash all move money or inventory. Wrap read-modify-write in `DB::transaction()` with `lockForUpdate()` — the existing code in `routes/console.php` (`auctions:finish`) is the reference implementation.
- **Report honestly.** If a test fails, paste the failure. If you skipped a step, say so. If you couldn't verify something, say that instead of implying it works.
- **Flag, don't drift.** Found a bug outside your task? Mention it in your summary; don't fix it unasked, and don't stay silent about it.
- **Minimum viable change.** No new packages, no new abstraction layers, no refactors bundled into feature work — unless explicitly requested.
- **Indonesian for humans, English for code.** UI strings, comments, and commit-visible copy are Indonesian. Identifiers, class names, and method names are English.

---

## 2. Project in one screen

**Vinstore** is an Indonesian antique/vintage marketplace with four trading modes and a two-stage authenticity approval pipeline.

**Roles:** `user` · `seller` · `validator` · `admin` — enforced by `App\Http\Middleware\CheckRole` (aliased `role`), which **redirects** unauthorised users to their role's home rather than returning 403.

**Trading modes:**
- **Jual beli** — cart → checkout → Midtrans → ship → confirm → seller balance released
- **Lelang** — timed auctions, live bids over WebSocket; `auctions:finish` closes them and creates the winner's order
- **Tukar tambah (trade-in)** — seller↔seller swap with optional `additional_cash` via a dedicated Midtrans webhook; named `TradeIn*` in code
- **Tebak harga** — hidden price, one final guess per buyer, closest wins a 24h exclusive purchase right (`scheduled → active → ended → public`)

**Approval:** `pending_validator` → `pending_admin` → `approved` | `rejected`, for both products and auctions.

**Non-negotiable invariants:**
1. Models hide numeric `id` and expose `public_id`; route binding uses `public_id`.
2. Tebak-harga products must be masked with `maskRealPriceFor($viewer)` before any public render.
3. Midtrans webhooks verify `isValidSignature()` before mutating anything.
4. Every route lives inside a `role:` middleware group (or is deliberately public).

---

## 3. Environment & commands

PowerShell is primary. **`&&` is not valid in PowerShell 5.1** — use `;` or separate invocations. A Bash tool is also available for POSIX syntax.

```powershell
composer run dev              # server + queue + vite together
php artisan reverb:start      # SEPARATE process — required for auctions & support chat
php artisan schedule:work     # SEPARATE process — required for auction/tebak-harga lifecycle

php artisan migrate
php artisan test
npm run test:e2e              # requires app running on :8000
./vendor/bin/pint             # PHP formatter
npm run format                # Prettier for resources/js
```

**Laragon gotcha:** injected `DB_*` env vars override `.env`. In a fresh terminal run
`Remove-Item Env:\DB_* -ErrorAction SilentlyContinue` first, or use `run_server.bat`.

---

## 4. Task playbooks

### 4.1 Add a page or feature (full vertical slice)

Do **all** of these — a partial slice is a broken feature:

1. **Migration** — `database/migrations/`, additive (`add_x_to_y_table`). Never edit a migration that has already run.
2. **Model** — `$fillable`, `$casts`, `$hidden = ['id']`, `booted()` hook generating `public_id`, `getRouteKeyName()`, relations, and a **query scope** for any domain rule.
3. **Controller** — returns `Inertia::render('path/to/page', [...])`. Validate with `$request->validate()` or `Validator::make()` as neighbours do. Redirect with `->with('success', 'pesan…')`.
4. **Route** — `routes/web.php`, inside the correct `role:` group and the matching prefix/name group.
5. **Page** — `resources/js/pages/path/to/page.jsx`, exact match to the render string.
6. **Test** — PHPUnit for logic; a Playwright spec in `tests/playwright/` for a user-visible flow.
7. **Format** — `./vendor/bin/pint` then `npm run format`.

### 4.2 Touch anything payment-related

1. Read `MidtransService` end to end first.
2. Signature check before side effects, always.
3. New webhook endpoints must be added to the CSRF exemption list in `bootstrap/app.php`.
4. Idempotency: webhooks retry. Guard against double-crediting (`payment_status`, `paid_at`, `stock_restored_at`, `seller_released_at` exist for exactly this reason — use them).
5. Test locally with `php artisan test:tukar-tambah-webhook {payment_reference} {status}` rather than hitting sandbox by hand.

### 4.3 Touch auctions or tebak harga

- Lifecycle transitions live in `routes/console.php` (`auctions:finish`) and `App\Services\PriceGuessService` (`sync()`). Change state machines there, not scattered in controllers.
- Auction closing already uses `DB::transaction` + `lockForUpdate` + a re-fetch inside the lock. Preserve that shape.
- Any public product render must call `PriceGuessService::sync()` then `maskRealPriceFor($viewer)`.
- Broadcasting: `AuctionBidPlaced` implements `ShouldBroadcastNow` on public channel `auction.{id}`; support chat uses private `support.{publicId}` authorised in `routes/channels.php`.

### 4.4 Frontend work

- Import via `@/` (aliased in **both** `vite.config.js` and `jsconfig.json` — update both if changed).
- Prefer Inertia's `<Form action method>` render-prop component (`{({ errors, hasErrors }) => …}`); see `pages/seller/products/create.jsx`.
- Media URLs: use `getProductImage` / `getStoreImage` / `getUserImage` / `getCategoryImage` / `getAuctionImage` from `@/lib/utils`. Never concatenate paths manually.
- Currency: `formatIDR()`. Class merging: `cn()`.
- Echo subscriptions must clean up with `echo.leave(channel)` in the `useEffect` return.
- Custom broadcast names need a leading dot: `.listen('.support.message.sent', …)`.

### 4.5 Debugging "nothing happens"

Check in this order:
1. Is the route inside a `role:` group the current user doesn't match? (`CheckRole` redirects silently.)
2. Is Reverb running? (realtime features fail closed)
3. Is `schedule:work` running? (auctions/tebak-harga never transition)
4. Laragon `DB_*` env override?
5. `php artisan storage:link` run? (missing images)

---

## 5. Verification protocol

Before reporting a task complete:

| Change type | Required verification |
| --- | --- |
| PHP logic | `php artisan test` (or `--filter=` the relevant test) |
| New model/migration | `php artisan migrate` succeeds on a scratch DB |
| User-facing flow | Relevant Playwright spec, or an explicit statement that it was manually unverified |
| Formatting | `./vendor/bin/pint` and `npm run format` both clean |
| Frontend build | `npm run build` succeeds if you touched imports/aliases/config |

State the actual result. "Tests pass" is only acceptable if you ran them.

---

## 6. Hard rules

**Never:**
- Commit or push unless explicitly asked. If asked while on `main`, branch first.
- Print, copy, or commit `.env`, `MIDTRANS_SERVER_KEY`, `GOOGLE_CLIENT_SECRET`, `REVERB_APP_SECRET`, or any credential.
- Run `php artisan migrate:fresh`, `migrate:refresh`, or `db:wipe` without explicit confirmation — they destroy the developer's local data.
- Expose or accept numeric model `id`s in routes, Inertia props, or request payloads.
- Render a tebak-harga product without `maskRealPriceFor()`.
- Act on a Midtrans webhook payload before `isValidSignature()` passes.
- Edit a migration that has already been applied.
- Add `tailwind.config.js` (Tailwind 4 is CSS-first here) or convert files to TypeScript.
- Commit `app.zip`, `node_modules/`, `vendor/`, `public/build/`, or anything under `docs/` (gitignored).
- Parallelise Playwright (`workers: 1` is intentional — the specs share one database).

**Always:**
- Look for an existing scope/helper/service before writing a new one.
- Use DB transactions + row locks for stock, balance, bid, and order mutations.
- Keep UI copy and code comments in Indonesian.
- Mention bugs you notice, even outside your task scope.

---

## 7. Known rough edges

These are pre-existing; don't imitate them and don't fix them unasked:

- `resources/js/pages/checkout-old.jsx` — dead file.
- `routes/web.php` imports `AdminController`, which does not exist (unused import).
- Business logic sits largely in controllers rather than services; only Midtrans and tebak-harga are extracted.
- `prisma/schema.prisma` is **documentation only** — an ERD of the MySQL schema. Prisma is not installed or used at runtime. Eloquent migrations are the source of truth.
- `scripts/*.py` generate thesis documents, unrelated to the application.
- `README.md` is still the stock Laravel readme.
- Duplicate Google Fonts `@import` at the top of `resources/css/app.css`.

---

## 8. Reference docs

- `CLAUDE.md` — architecture, conventions, directory map, gotchas
- `TUKAR_TAMBAH_PAYMENT_FEATURE.md` — trade-in + additional cash flow
- `TRACKING_NUMBER_FEATURE.md` — shipment tracking
- `GOOGLE-LOGIN-SETUP.md` — Socialite/Google OAuth
- `PWA-README.md` — service worker, manifest, icons
- `PENTING_BACA_INI.txt` — local MySQL/Laragon notes (Indonesian)
