# Spec: Product Catalogue PoC

Source of truth for scope, assumptions and contracts. Agents and humans build against this file.

## Goal
A product catalogue (browse, search, enquire) built as a **foundation, not a throwaway**: the product data source, email provider and look and feel are all expected to change. The default catalogue is a bundled file of sample office products in four categories (Office Supplies & Stationery, Tech & Electronics, Office Furniture, Print & Photo Services); the Fake Store API remains available as an alternative provider.

## Acceptance criteria (from the brief)
| Area | Behaviour |
|---|---|
| Home | Shows the 5 products the entrepreneur chose as "top rated". |
| Category | Shows top-rated products for the category, then the additional products in it. |
| Search | Entering a term and pressing search shows only products with matching text. |
| Product | Clicking a tile opens the product details page. |
| Enquiry | "Enquire" opens a contact form; on submit the sales inbox receives an email and the customer sees a "Thank you" message. |

## Hard constraints
- The browser never calls the Fake Store API; everything goes through our backend.
- AWS-compatible infrastructure only; runs locally in under 10 minutes (`docker compose up --build`).
- No secrets committed (`.env.example`); no paid services or accounts.

## Assumptions
| ID | Assumption |
|---|---|
| A1 | "Chosen top 5" is a curated list of product IDs in config (`FEATURED_PRODUCT_IDS`, default: five of the highest-rated products in the bundled catalogue, from several categories), not computed. Missing IDs are skipped and logged; no silent backfill. |
| A2 | Category "top rated" = top 3 (configurable) by rating, then review count, then title. "Additional products" = the remainder, by title. |
| A3 | Search is case-insensitive, all terms must match (AND), across title, description and category. The term is trimmed and whitespace-collapsed; a blank or over-100-char term is rejected with 422 (the UI prevents it). Plain substring matching: no regex, no fuzzy matching. |
| A4 | Enquiry fields: name (required, 2-100), email (required, RFC-valid), phone (optional, digits and `+()-.` only), message (optional, max 1000). No quantity. Control characters are stripped server-side; name/phone are forced to one line. |
| A5 | Only the sales inbox is emailed; the customer sees an on-screen thank-you with a reference (customer confirmation email is a next step). The recipient comes from `SALES_INBOX_ADDRESS` only. |
| A6 | No auth, accounts, cart, checkout or admin UI. |
| A7 | Currency is USD. Prices are stored as integer cents. |
| A8 | Product IDs are strings internally (a future source may not use integers). |
| A9 | Product images are our own static assets under `/images/` (served by the frontend, so no third-party host); `https` URLs are also accepted from other sources. Each source's mapper/validator is the single place to rewrite them (e.g. CDN). |
| A10 | Enquiries persist in SQLite (portable to Postgres/RDS). |
| A11 | No pagination on category pages (about 20 products). |
| A12 | English only; accessibility basics (semantic HTML, labelled inputs, focus management), not a full audit. |

## Architecture
```
Browser -> web (nginx: SPA + /api gateway) -> api (Laravel / php-fpm)
Controller -> FormRequest -> Service -> Contract -> Infrastructure -> external system
```
Contracts (`backend/app/Contracts`):
- `ProductProviderInterface`: `all()`, `find($id)`, `byCategory($slug)`, `categories()`; typed exceptions only.
- `EmailProviderInterface`: `send(EmailMessage)`; throws `EmailDeliveryException`.

Implementations (`backend/app/Infrastructure`): `JsonCatalogueProvider` (default; our own catalogue file, strictly validated, no network), `FakeStoreProvider` (+ `FakeStoreMapper`; optional), `CachingProductProvider` (decorator, stale-if-error), `InMemoryProductProvider` (tests), `MockEmailProvider`, `LaravelMailEmailProvider`.

Services: `CatalogueService` (featured, category, search, detail), `EnquiryService` (validate product, persist, dispatch notification).

## API (`/api/v1`)
| Method | Path | Purpose |
|---|---|---|
| GET | `/health` | Liveness |
| GET | `/featured` | The curated products |
| GET | `/categories` | Category list |
| GET | `/categories/{slug}` | `{category, topRated[], others[]}` |
| GET | `/products?q=&category=` | Search (`q` required, 1-100 chars; optional `category` slug) |
| GET | `/products/{id}` | Detail |
| POST | `/enquiries` | Submit enquiry (201) |

Success: `{ "data": ..., "meta"?: ... }`. Error: `{ "error": { "code", "message", "details?", "request_id" } }`.

## Error handling
Validation -> 422 with field details. Unknown product/category -> 404. Upstream timeout/5xx/429/malformed -> stale cache if available, else 503 (`catalogue_unavailable`, or `catalogue_busy` for upstream 429; `Retry-After` always set). Our own throttle (60/min/IP on reads) -> 429. Email failure -> enquiry already saved, customer still sees thank-you, status `failed` for operators.

Upstream budget: 4 s timeout x 2 attempts (1 retry, only on connection errors and 5xx), so a user waits at most about 8 s before a friendly error.

## Fake Store quirks (verified against the live API; only relevant when `CATALOGUE_PROVIDER=fakestore`)
- No search endpoint: search runs in `CatalogueService` over the cached catalogue.
- Unknown product id returns **HTTP 200 with an empty body**, not 404: the provider maps it to `ProductNotFoundException`.
- Categories contain spaces and apostrophes (`men's clothing`) and one is misspelt in the source (`jewelery`): slugs are derived by the mapper and category names are URL-encoded when calling upstream.
- `price` is a float: converted to integer cents in the mapper.

## Enquiry flow
1. `POST /api/v1/enquiries` is validated by `StoreEnquiryRequest` (honeypot field `website`; filled = silent fake success, nothing saved).
2. `EnquiryService` checks the product exists (404 if not, 503 if the source is down: nothing is saved in either case), then **saves the enquiry** with a snapshot of the product title and price.
3. `SendEnquiryNotification` runs **after the response is sent**, composes a plain-text email (`EnquiryEmailComposer`) and hands it to `EmailProviderInterface`.
4. Delivery failure never reaches the customer: the enquiry is marked `failed` with a reason and the cause is logged (no customer data in logs). `php artisan enquiries:resend-failed` re-queues failed enquiries. The job is idempotent.
5. Throttle: 5 enquiries per minute per IP. The response contains only a `reference`, never an echo of the submitted data.

Email providers (`EMAIL_PROVIDER`): `mail` (Laravel mailer; transport via `MAIL_MAILER`, SMTP to Mailpit locally, SES later) and `mock` (records only).

## Caching
`CachingProductProvider` wraps the real provider: fresh TTL 10 min, stale copy 24 h served only when upstream fails (and re-armed for 30 s so a dead upstream is not hammered). Atomic lock to prevent stampedes. Values are cached as **plain arrays**, not objects: `cache.serializable_classes` is disabled, so cached objects come back as `__PHP_Incomplete_Class` (verified). `Cache-Control: public, max-age=60` on successful GET endpoints. Enquiries are never cached.

## Out of scope / cut (see DECISIONS.md for reasoning)
Auth, admin UI, cart/checkout, IaC, Playwright E2E, customer confirmation email, category pagination.

## Phases
1. Foundations (repo, scaffolds, Compose, CI, agent files)
2. Backend core (providers, services, API, error model, tests)
3. Enquiry flow + React app
4. Hardening, frontend tests, docs, PR description
