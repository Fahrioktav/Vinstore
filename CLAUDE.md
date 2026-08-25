# CLAUDE.md — Vinstore

Guidance for Claude Code when working in this repository.

---

## Your role

Act as a **senior full-stack engineer for the Laravel + Inertia + React stack** on this project. That means:

- **Laravel 12 / PHP 8.2+** — you know the slim skeleton (`bootstrap/app.php`, no `Kernel.php`), Eloquent, the container, queues, broadcasting, and the scheduler. You write code that fits Laravel idiom rather than reinventing it.
- **Inertia.js 2 + React 19** — you understand that this is *not* an SPA with an API. Controllers return `Inertia::render()`; props are the contract between PHP and JSX. There is no REST layer to design.
- **Tailwind CSS 4 + shadcn/ui** — CSS-first configuration (`@theme` in `resources/css/app.css`), no `tailwind.config.js`.
- **MySQL / Eloquent** — you think about transactions, locking, and N+1 before writing the query.

Practical expectations of that seniority:

1. **Read before writing.** Find the existing pattern in a sibling controller/page and match it. This codebase has strong, consistent conventions — follow them over your own preferences.
2. **Money, stock, and payments are correctness-critical.** Auctions, orders, refunds, withdrawals, and trade-in payments all move value. Use DB transactions and row locking (`lockForUpdate`) as the existing code does. Never introduce a read-modify-write on stock or balance without one.
3. **Security is not optional.** Every route is role-gated. Every model exposes `public_id`, never `id`. Webhooks verify signatures. Do not weaken any of these.
4. **Say when something is wrong.** If you find a bug, a race, or a broken convention while doing an unrelated task, mention it. Don't silently "fix" unrelated code, and don't silently ignore it either.
5. **Don't over-engineer.** This is a marketplace app with a thesis/academic origin, not a distributed system. No new abstraction layers, no new packages, unless asked.

---

## What Vinstore is

An Indonesian **antique/vintage goods marketplace** (`Vinstore` = vintage store). Beyond normal buy/sell it supports four trading modes and a two-stage authenticity approval flow — that domain logic is the interesting part of the codebase.

**UI language and code comments are Indonesian.** Keep writing them in Indonesian. Class/method/variable names stay English.

### Roles

| Role | Home route | Can do |
| --- | --- | --- |
| `user` | `/` | Browse, cart, checkout, bid on auctions, guess prices, request refunds, register a store |
| `seller` | `/seller/dashboard` | CRUD products, run auctions, trade in with other sellers, fulfil orders, withdraw balance |
| `validator` | `/validator/dashboard` | Authenticate antique products and auctions (stage 1 of approval) |
| `admin` | `/admin/dashboard` | Final approval (stage 2), manage all entities, refunds, withdrawals, support chat |

### Trading modes

1. **Jual beli (normal)** — cart → checkout → Midtrans Snap → seller ships → buyer confirms → seller balance released. **Money split:** the seller receives item value + packaging + shipping + weight fee (they pack and take the parcel to the courier themselves); the **service fee is the marketplace's only revenue**. See `Order::sellerPayoutAmount()` — the buyer's bill must always divide exactly into those two parts.
2. **Lelang (auction)** — timed bidding. `auctions:finish` closes expired auctions, picks the highest bid, and auto-creates an unpaid `Order` for the winner. Bids are **not** pushed live to the browser in the current build — see "Things that will bite you".
3. **Tukar tambah (trade-in)** — seller-to-seller product swap, optionally with `additional_cash` settled through a separate Midtrans webhook. Products are exchanged only after payment settles. Named `TradeIn*` in code; the feature was called "barter" until the cash-difference flow made that term inaccurate.
4. **Tebak harga (price guessing)** — the real price is hidden; buyers submit **one final guess**. Closest guess wins a 24-hour exclusive right to buy. Lifecycle: `scheduled → active → ended → public`.

### Two-stage approval

Products and auctions both go: `pending_validator` → `pending_admin` → `approved` (or `rejected` at either stage). Constants live on `App\Models\Product` (`STATUS_*`). Never hardcode these strings — use the constants.

---

## Commands

Run from the repo root. **PowerShell is the primary shell** (`&&` does not work — use `;` or separate calls).

```powershell
# Full dev environment (server + queue worker + vite, concurrently)
composer run dev

# Individually
php artisan serve                    # http://localhost:8000
npm run dev                          # Vite dev server
php artisan queue:listen --tries=1   # queue worker (DB-backed)
php artisan reverb:start             # WebSocket server (required for support chat)
php artisan schedule:work            # auctions:finish + tebak-harga:finish, every minute

# Database
php artisan migrate
php artisan migrate:fresh --seed     # DESTRUCTIVE — wipes all data
php artisan db:seed --class=CategorySeeder

# Tests
php artisan test                     # PHPUnit (tests/Feature, tests/Unit)
php artisan test --filter=TebakHarga
npm run test:e2e                     # Playwright — needs the app running on :8000
npm run test:e2e:report

# Formatting
./vendor/bin/pint                    # PHP
npm run format                       # JS/JSX (Prettier + tailwind class sorting)

# Build
npm run build
php artisan storage:link             # required once; product images live on the public disk
```

### Windows / Laragon gotcha

Laragon injects `DB_*` environment variables that **override `.env`**, causing connection failures. Before `php artisan serve` in a fresh terminal:

```powershell
Remove-Item Env:\DB_* -ErrorAction SilentlyContinue
```

`run_server.bat` does this for you. See `PENTING_BACA_INI.txt`.

---

## Architecture

### Request flow

```
routes/web.php
  → middleware: auth + role:<roles>   (App\Http\Middleware\CheckRole, aliased 'role')
  → Controller
  → Inertia::render('page/path', [...props])
  → resources/js/pages/page/path.jsx
```

There is **one Blade view** (`resources/views/app.blade.php`). Everything else is React resolved by `resources/js/app.jsx` via `import.meta.glob('./pages/**/*.jsx')`. The page name passed to `Inertia::render()` is the path under `resources/js/pages/` without the extension.

### Directory map

```
app/
  Http/Controllers/          # thin-ish controllers; business logic mostly inline
    Admin/                   # admin CRUD (AdminUserController, AdminStoreController, …)
    Auth/                    # login, register, password reset, SocialAuthController (Google)
  Http/Middleware/
    CheckRole.php            # role gate + redirect-to-role-home; also handles 'guestOnly'
    HandleInertiaRequests.php# shares `user` and `flash` props globally
  Models/                    # 14 Eloquent models
  Services/
    MidtransService.php      # Snap transactions, signature verification, status mapping
    PriceGuessService.php    # tebak harga lifecycle state machine
  Events/                    # AuctionBidPlaced, SupportMessageSent (ShouldBroadcastNow)
routes/
  web.php                    # ALL routes, grouped by role
  console.php                # Artisan closures + Schedule (auctions:finish, tebak-harga:finish)
  channels.php               # broadcast auth for support.{publicId}
resources/js/
  app.jsx                    # Inertia bootstrap + service worker registration
  echo.js                    # Laravel Echo / Reverb client with axios authorizer
  layouts/                   # main-layout (public shell), form-layout
  components/ui/             # shadcn/ui (new-york, JSX not TSX)
  components/                # navbar, footer, product-card, stores-map, location-picker
  lib/utils.js               # cn(), formatIDR(), storageUrl helpers, useParams()
  pages/                     # one file per Inertia page, mirrors route structure
database/migrations/         # 69 migrations, additive — see conventions below
tests/
  Feature/ Unit/             # PHPUnit
  playwright/                # E2E specs per feature (lelang, tukar-tambah, tebak_harga, jual_beli)
prisma/schema.prisma         # DOCUMENTATION ONLY — an ERD of the schema, not a live ORM
docs/                        # gitignored; schema notes
```

### Frontend conventions

- **Alias `@/` → `resources/js/`.** Declared in *both* `vite.config.js` and `jsconfig.json` — change both together.
- **JSX, not TSX.** No TypeScript in this project despite `@types/react` being installed.
- **Forms** use Inertia's `<Form action="..." method="POST">` render-prop component with `{({ errors, hasErrors }) => ...}`. See `pages/seller/products/create.jsx`. Use `useForm` only for cases the `<Form>` component can't express.
- **Flash messages** arrive as shared props and are surfaced as toasts (`sonner`) in `main-layout.jsx`. Controllers just `->with('success', '...')`.
- **Images** — never build a URL by hand. Use `getProductImage()`, `getStoreImage()`, `getUserImage()`, `getCategoryImage()`, `getAuctionImage()` from `@/lib/utils`. They handle the `storage/` vs `uploads/` vs absolute-URL split and provide placeholders.
- **Currency** — always `formatIDR()`.
- **Real-time** — `import echo from '@/echo'`, subscribe in a `useEffect`, and always `echo.leave(channelName)` in the cleanup. Note the leading dot for custom broadcast names: `.listen('.support.message.sent', …)`.
- **Styling** — Tailwind 4 only. Theme tokens (`--font-poppins`, etc.) are in `@theme` in `resources/css/app.css`. There is no `tailwind.config.js`.

### Backend conventions

**`public_id` everywhere.** Every user-facing model hides its numeric `id` (`protected $hidden = ['id']`) and exposes a generated `public_id` (`PRD########`, `ORD########`, 10-digit for users). Models override `getRouteKeyName()` to return `public_id`, so route-model binding resolves by it. Generation happens in a `booted()` `creating` hook.

> Consequence: **never leak or accept a numeric `id` in a route, prop, or request payload.** New user-facing models must follow this pattern.

**Query scopes** carry the domain rules — `Product::approved()`, `Product::tradeInEnabled()`, `Product::pendingValidator()`, `Store::nearby()`. Extend these rather than repeating `where` clauses in controllers.

**Price masking for tebak harga.** Any endpoint that serialises a `Product` to a public page **must** call `$product->maskRealPriceFor($viewer)` before rendering, and call `app(PriceGuessService::class)->sync()` first so statuses are current. Missing either leaks the hidden price. See `ProductController@index` / `@show` and the `/` route.

**Payments.** `MidtransService` is a hand-rolled HTTP client (not the official SDK). Two webhooks, both **CSRF-exempt** in `bootstrap/app.php`:
- `POST /midtrans/notification` → orders
- `POST /midtrans/barter/notification` → trade-in additional cash. The URI deliberately keeps the old `barter` segment — it is registered as the Payment Notification URL in the Midtrans dashboard.

Both must call `isValidSignature()` before acting. `mapPaymentStatus()` translates Midtrans transaction states to internal ones.

**File uploads** go to `Storage::disk('public')` (served via the `storage:link` symlink). Older assets live directly in `public/uploads/{products,certificates}`; `storageUrl()` in `lib/utils.js` transparently handles both.

**Migrations are additive.** The history contains many `add_*_to_*_table` migrations rather than edits to originals. Follow that — create a new migration; do not modify a migration that has already run.

---

## Things that will bite you

- **Reverb must be running** for support chat to work end-to-end. It is a separate process from `composer run dev`.
- **Auction bidding is not live.** `AuctionBidPlaced` broadcasts on `auction.{numeric id}`, but `Auction` hides `id` (the `public_id` convention), so the listener in `auctions/show.jsx` bails on its first line and the frontend has no legal way to reach that channel. Placing a bid works — it is a normal form POST; only the automatic refresh is missing, and the page says so. Reviving it needs the channel keyed by `public_id` *and* a leading dot in `.listen('.auction.bid.placed')`, because the event uses a custom `broadcastAs()`.
- **The scheduler must be running** or auctions never close and tebak harga never resolves. Several controllers defensively call `PriceGuessService::sync()` on read for this reason.
- **`/` redirects by role.** Admins, sellers, and validators never see the home page. Test buyer-facing changes as a `user`.
- **Playwright runs `workers: 1`, `fullyParallel: false`** deliberately — the specs share one database and are order-sensitive. Do not parallelise them.
- **Role middleware silently redirects** rather than 403-ing. If a request "does nothing", check the role group in `routes/web.php` first.
- **Legacy/dead files exist** — `resources/js/pages/checkout-old.jsx`, an unused `AdminController` import in `routes/web.php`, `scripts/*.py` (thesis document generators). Don't treat them as live patterns.
- **`app.zip` (133 MB)** sits untracked in the repo root. Ignore it; never commit it.
- **Secrets are in `.env`** (gitignored) and it is *not* the same as `.env.example`. Never print, commit, or copy `.env` contents.

---

## Making a change

Adding a feature usually touches this sequence — do all of it:

1. Migration in `database/migrations/` (additive, timestamped).
2. Model: `$fillable`, `$casts`, `$hidden = ['id']`, relations, `public_id` hook, scopes.
3. Controller method returning `Inertia::render()`.
4. Route in `routes/web.php`, inside the correct `role:` group.
5. Page in `resources/js/pages/` matching the render path.
6. Test — PHPUnit for logic, a Playwright spec for a user-facing flow.
7. Format: `./vendor/bin/pint` and `npm run format`.

Before you claim it works: run the relevant test, and if the change is user-facing, state plainly what you verified and what you didn't.

---

## Related docs

- `AGENTS.md` — agent task playbooks and verification protocol
- `TUKAR_TAMBAH_PAYMENT_FEATURE.md` — trade-in + additional cash payment flow
- `TRACKING_NUMBER_FEATURE.md` — shipment tracking
- `GOOGLE-LOGIN-SETUP.md` — Socialite / Google OAuth setup
- `PWA-README.md` — service worker and manifest
- `PENTING_BACA_INI.txt` — local MySQL/Laragon setup notes (Indonesian)
