# Product Catalogue

A product catalogue (browse, search, enquire) built as a foundation that can grow: the product data source, email provider and look and feel are all designed to be swapped.

> **Status: Phase 3 of 4 (feature complete).** Browse, category pages, search, product details and enquiries all work end to end (React, Laravel, real SMTP, live Fake Store). Remaining: hardening, cold-start verification of the Docker stack, and the architecture/decision docs. See [docs/spec.md](docs/spec.md) for the full scope.

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
| POST | `/api/v1/enquiries` | Submit an enquiry (201 `{reference}`); emails the sales inbox after responding |
| GET | `/api/v1/health` | Liveness |

Errors always look like `{"error": {"code", "message", "details"?, "request_id"}}`. Prices are `{amount_minor, currency}` (integer cents).

## Seeing the sales email
`docker compose up --build`, submit an enquiry from any product page, then open Mailpit at http://localhost:8025. If email delivery ever fails the customer still sees "Thank you", the enquiry is kept with status `failed`, and you can retry with:

```bash
docker compose exec api php artisan enquiries:resend-failed
```

Set `EMAIL_PROVIDER=mock` to run with no mail server at all.

## Tests and checks
```bash
cd backend  && vendor/bin/pest && vendor/bin/pint --test
cd frontend && npm test && npm run lint && npm run typecheck
```

## Repository layout
| Path | Purpose |
|---|---|
| `backend/` | Laravel API (`/api/v1`) |
| `frontend/` | React SPA (`src/api` client, `src/features` by area, `src/pages`) + nginx gateway config |
| `docs/` | Spec, and (later) ADRs |
| `AGENTS.md` | Rules for AI agents working in this repo |
| `AI_USAGE.md` | How AI was used, what it got wrong |
