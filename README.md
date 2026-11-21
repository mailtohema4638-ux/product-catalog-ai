# Product Catalogue

A product catalogue (browse, search, enquire) built as a foundation that can grow: the product data source, email provider and look and feel are all designed to be swapped.

> **Status: Phase 2 of 4 (backend core).** The read API (featured, categories, category page, search, product detail) is built and tested against a faked Fake Store. Enquiries (Phase 3) and the React UI (Phase 3) are next. See [docs/spec.md](docs/spec.md) for the full scope.

## Stack and why
| Layer | Choice | Why |
|---|---|---|
| Backend | PHP / Laravel 13 | Built-in DI container, validation, caching, queues and mail make swappable providers cheap to express. |
| Client | React + TypeScript (Vite) | Component model suits a storefront that will be re-skinned; design tokens in `frontend/src/styles/tokens.css`. |
| Local infra | Docker Compose: nginx gateway, php-fpm, Mailpit | One command; single origin (no CORS); Mailpit shows the sales email with no account. All AWS-compatible (ECS/Fargate, S3 + CloudFront, SES, RDS). |

## Run it
Requires Docker with Compose v2.24+.

```bash
docker compose up --build
```

- App: http://localhost:8080
- Mailpit (sales inbox): http://localhost:8025
- API health: http://localhost:8080/api/v1/health

Optional overrides: copy `.env.example` to `.env` (ports, featured product IDs, sales inbox address). No secrets are needed; an ephemeral `APP_KEY` is generated at container start.

## Run without Docker
```bash
# backend (PHP 8.3+, Composer)
cd backend && composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate && php artisan serve     # http://localhost:8000

# frontend (Node 20.19+ / 22)
cd frontend && npm install && npm run dev                                       # http://localhost:5173, proxies /api to :8000
```
In this mode set `MAIL_HOST=127.0.0.1` or `EMAIL_PROVIDER=mock` in `backend/.env`.

## API (so far)
| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/featured` | The curated top-rated products, in configured order |
| GET | `/api/v1/categories` | Category list |
| GET | `/api/v1/categories/{slug}` | `{category, top_rated[], others[]}` |
| GET | `/api/v1/products?q=&category=` | Search; `q` is required (1-100 chars) |
| GET | `/api/v1/products/{id}` | Product detail |
| GET | `/api/v1/health` | Liveness |

Errors always look like `{"error": {"code", "message", "details"?, "request_id"}}`. Prices are `{amount_minor, currency}` (integer cents).

## Tests and checks
```bash
cd backend  && vendor/bin/pest && vendor/bin/pint --test
cd frontend && npm test && npm run lint && npm run typecheck
```

## Repository layout
| Path | Purpose |
|---|---|
| `backend/` | Laravel API (`/api/v1`) |
| `frontend/` | React SPA + nginx gateway config |
| `docs/` | Spec, and (later) ADRs |
| `AGENTS.md` | Rules for AI agents working in this repo |
| `AI_USAGE.md` | How AI was used, what it got wrong |
