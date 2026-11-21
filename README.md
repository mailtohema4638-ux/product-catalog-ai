# Product Catalogue

A small product catalogue: customers browse, search and enquire about products; the sales team receives each enquiry by email. Built as a **foundation, not a throwaway**: the product data source, the email provider and the look and feel are each designed to be swapped without touching the rest.

| Criterion from the brief | Where to look |
|---|---|
| Home: the 5 chosen top-rated products | `/` |
| Category: top-rated products, then the rest | `/categories/mens-clothing` |
| Search: only matching products | search box in the header |
| Product page | click any product tile |
| Enquiry: sales inbox gets an email, customer sees "Thank you" | **Enquire** on a product page, then Mailpit at http://localhost:8025 |

## Stack and why
| Layer | Choice | Why |
|---|---|---|
| Backend | PHP **Laravel 13** | Dependency injection, validation, caching, queues and mail built in, which makes swappable providers cheap to express and test. |
| Client | **React + TypeScript** (Vite) | Component model suits a storefront that will be re-skinned; TypeScript types the API contract. |
| Local infra | **Docker Compose**: nginx gateway, php-fpm, Mailpit | One command, one origin (no CORS), and a visible sales inbox with no account. Everything is containerised and 12-factor, so it maps to AWS (ECS Fargate, S3 + CloudFront, SES, RDS, ElastiCache). |

The browser never calls the Fake Store API: all product data goes through the backend.

## Run it
Requires Docker with Compose v2.24 or newer.

```bash
docker compose up --build
```

| What | URL |
|---|---|
| The app | http://localhost:8080 |
| Mailpit (the sales inbox) | http://localhost:8025 |
| API health | http://localhost:8080/api/v1/health |

No account or secret is needed. An ephemeral `APP_KEY` is generated when the API container starts. The product source (fakestoreapi.com) is a free public API, so the machine needs outbound internet access.

To change ports, the featured products or the sales address, copy `.env.example` to `.env` and edit it.

### Run without Docker
```bash
# backend (PHP 8.3+, Composer)
cd backend && composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate && php artisan serve     # http://localhost:8000

# frontend (Node 20.19+ or 22)
cd frontend && npm install && npm run dev                                       # http://localhost:5173 (proxies /api to :8000)
```
In this mode set `EMAIL_PROVIDER=mock` in `backend/.env` (or point `MAIL_HOST` at any SMTP server).

## Configuration
All configuration is environment-driven. Defaults live in [`backend/.env.example`](backend/.env.example); nothing secret is required.

| Variable | Default | Purpose |
|---|---|---|
| `CATALOGUE_PROVIDER` | `fakestore` | Product data source (`ProductProviderInterface` implementation) |
| `FEATURED_PRODUCT_IDS` | `12,11,3,18,5` | The curated "top rated" products on the home page, in order |
| `CATEGORY_TOP_RATED_COUNT` | `3` | How many top-rated products lead a category page |
| `EMAIL_PROVIDER` | `mail` | `mail` (Laravel mailer, transport via `MAIL_MAILER`) or `mock` (records only) |
| `SALES_INBOX_ADDRESS` | `sales@catalogue.test` | Where enquiries are emailed (never taken from a request) |
| `CATALOGUE_CACHE_TTL_SECONDS` / `CATALOGUE_STALE_TTL_SECONDS` | `600` / `86400` | Fresh and stale-if-error cache lifetimes |
| `FAKESTORE_TIMEOUT_SECONDS` / `FAKESTORE_RETRIES` | `4` / `1` | Upstream budget (about 8 s worst case) |
| `TRUSTED_PROXIES` | empty (`*` in Compose) | Proxies allowed to set `X-Forwarded-*` |
| `CORS_ALLOWED_ORIGINS` | empty | Cross-origin access is off by default |

## API
| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/featured` | The curated top-rated products, in configured order |
| GET | `/api/v1/categories` | Category list |
| GET | `/api/v1/categories/{slug}` | `{category, top_rated[], others[]}` |
| GET | `/api/v1/products?q=&category=` | Search; `q` is required (1–100 characters) |
| GET | `/api/v1/products/{id}` | Product detail |
| POST | `/api/v1/enquiries` | Submit an enquiry (201 `{reference}`); emails the sales inbox after responding |
| GET | `/api/v1/health` | Liveness |

Errors always look like `{"error": {"code", "message", "details"?, "request_id"}}`. Prices are `{amount_minor, currency}` (integer cents).

## If an email fails
The customer still sees "Thank you" and the enquiry is saved with status `failed`. Retry any failed (or never-sent) enquiries with:

```bash
docker compose exec api php artisan enquiries:resend-failed
```

## Tests and checks
```bash
cd backend  && vendor/bin/pest && vendor/bin/pint --test && vendor/bin/phpstan analyse
cd frontend && npm test && npm run lint && npm run typecheck && npm run build
```
At the time of writing: 179 backend and 71 frontend tests. They cover the acceptance criteria, provider contracts (every product and email implementation runs the same suite), error paths, abuse protection, durability when email fails, and the architectural layer rules. CI (`.github/workflows/ci.yml`) also runs dependency audits and a `docker-smoke` job that builds the images and exercises the whole stack, including the email arriving in Mailpit (defined, but not yet run when this was written).

## Assumptions, cuts and decisions
The acceptance criteria leave gaps on purpose. Every assumption (for example, "top rated" means a curated list on the home page and a computed ranking on category pages), every requirement I simplified or dropped, the trade-offs, and what I would do next are in **[DECISIONS.md](DECISIONS.md)**.

## Documentation
| File | Contents |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Layers, flows, error model, caching, security, testing, how to extend, AWS path |
| [DECISIONS.md](DECISIONS.md) | Assumptions, cuts, trade-offs, limitations, next steps |
| [AI_USAGE.md](AI_USAGE.md) | Tools, prompts, where the AI was wrong and how it was caught, review process |
| [docs/spec.md](docs/spec.md) | The spec the AI built against |
| [AGENTS.md](AGENTS.md) | Rules for AI agents working in this repository |

## Repository layout
| Path | Purpose |
|---|---|
| `backend/` | Laravel API: `app/Contracts`, `app/Services`, `app/Infrastructure`, `app/Http`, `app/DTOs`, `tests/` |
| `frontend/` | React SPA (`src/api` client, `src/features` by area, `src/pages`) and the nginx gateway config |
| `docker-compose.yml` | `web` (nginx + SPA), `api` (php-fpm), `mailpit` |
| `docs/` | Spec |
| `.github/workflows/ci.yml` | Backend, frontend and Docker smoke jobs |

## Time spent
Approximate, AI-assisted, wall-clock. This is over the 2–3 hour guideline.

| Activity | Time |
|---|---|
| Planning (reading the brief, architecture, assumptions, spec) | ~15 min |
| Building (scaffolding, backend, frontend, Docker, CI) | ~75 min |
| Testing and verification (tests, mutation checks, live runs through a browser and real SMTP) | ~45 min |
| Reviewing and documentation (independent code review, fixes, docs) | ~30 min |
| **Total** | **~2 h 45 min** |

## Known limitations
Summarised here, detailed in [DECISIONS.md](DECISIONS.md): Docker was not available on my build machine, so the stack is covered by a CI smoke job (defined, not yet run) rather than verified locally; the product source is a free public API; SQLite suits a single API container only; enquiries have no retention policy yet.
