# Agent instructions

Guidance for AI coding agents (and humans) working in this repository.
Read `docs/spec.md` first: it is the source of truth for scope and assumptions.

## Stack
- `backend/`: Laravel 13, PHP 8.3+, Pest for tests, Pint for style. JSON API under `/api/v1`.
- `frontend/`: React 19 + TypeScript + Vite, Vitest + Testing Library, oxlint.
- `docker-compose.yml`: `web` (nginx gateway + built SPA), `api` (php-fpm), `mailpit` (dev SMTP sink).

## Architecture rules (do not break)
1. Controllers are thin: validate via a FormRequest, call ONE service method, return a Resource. No business logic, no `Http::`, no direct model queries.
2. Business rules live in `app/Services`. Services depend on contracts in `app/Contracts`, never on concrete integrations.
3. Integrations live in `app/Infrastructure` and implement a contract. Vendor JSON shapes (Fake Store) are mapped to DTOs inside the provider and never leak out.
4. Two seams only: `ProductProviderInterface` and `EmailProviderInterface`. Do not add repositories, event buses or other speculative layers.
5. DTOs are immutable (`readonly`). Money is integer minor units (cents) + currency. Never use floats for money.
6. Product IDs are strings internally.
7. The frontend NEVER calls the Fake Store API or any third party. It only calls `/api/v1/*` through `frontend/src/api`.
8. Enquiries are persisted before email is attempted; an email failure must never lose the enquiry or show the customer an error.
9. Sales emails are plain text; customer-supplied values that land in headers (subject, reply-to name) are forced to one line. The recipient comes from config only.
10. All errors use the envelope `{ "error": { "code", "message", "details?", "request_id" } }`. Never expose stack traces or upstream error bodies.

## Security rules
- No secrets in the repo. Configuration goes through `.env.example` + `config/*.php`; never call `env()` outside `config/` (it returns null once config is cached; Larastan enforces this).
- The sales inbox address comes from config only, never from request input.
- Frontend tests use MSW with `onUnhandledRequest: 'error'`: any request that is not mocked fails the test, which is also how we prove the browser only talks to `/api`.
- Frontend: no `dangerouslySetInnerHTML`, no inline `style` attributes or inline scripts (the gateway CSP forbids them).
- Treat upstream product data as untrusted.

## Commands
- Backend (from `backend/`): `vendor/bin/pest`, `vendor/bin/pint --test` (use `vendor/bin/pint` to fix), `vendor/bin/phpstan analyse` (level 6).
- Frontend (from `frontend/`): `npm test`, `npm run lint`, `npm run typecheck`, `npm run build`.
- Full stack: `docker compose up --build` then open http://localhost:8080 (Mailpit UI on :8025).

## Working agreements
- Ask before adding a dependency; prefer what is already installed.
- Small, reviewable commits. Every behaviour change ships with a test.
- Keep comments for non-obvious intent only.
- Record any AI mistake you notice in `AI_USAGE.md`.
