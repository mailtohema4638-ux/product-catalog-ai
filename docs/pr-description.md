# Product catalogue PoC: browse, search and enquire, built to grow

## What this is
A product catalogue where customers browse, search and submit an enquiry, and the sales team receives it by email. It meets the five acceptance criteria (home, category, search, product page, enquiry) and is deliberately built as a **foundation**: the product data source, email provider and look and feel are each behind a seam so they can change without a rewrite.

The default catalogue is a bundled file of sample office products in four categories (Office Supplies & Stationery, Tech & Electronics, Office Furniture, Print & Photo Services). The Fake Store API is still available as an alternative provider (`CATALOGUE_PROVIDER=fakestore`), which is the swap the brief anticipates.

- **Backend:** Laravel 13 (PHP) JSON API under `/api/v1`
- **Client:** React + TypeScript (Vite)
- **Run:** `docker compose up --build`, then http://localhost:8080 (sales inbox in Mailpit at http://localhost:8025)

## How to review (suggested order, about 15 minutes)
1. [README.md](README.md): stack choice, run instructions, configuration.
2. [DECISIONS.md](DECISIONS.md): assumptions, what I cut and why, trade-offs, limitations, next steps.
3. [ARCHITECTURE.md](ARCHITECTURE.md): layers, flows, error model, how to extend.
4. The seams: `backend/app/Contracts/*`, then `backend/app/Providers/{Catalogue,Enquiry}ServiceProvider.php` (the only place implementations are chosen).
5. The behaviour that matters: `backend/app/Services/CatalogueService.php`, `EnquiryService.php`, `backend/app/Infrastructure/Products/CachingProductProvider.php`, `backend/app/Jobs/SendEnquiryNotification.php`.
6. [AI_USAGE.md](AI_USAGE.md): how AI was directed and where it was wrong.

## Design in one paragraph
Controllers are thin; business rules live in services that depend on two contracts (`ProductProviderInterface`, `EmailProviderInterface`). Each source's specifics are contained in its own provider: the default `JsonCatalogueProvider` (strictly validated, offline) and the optional `FakeStoreProvider` (including its quirks: no search endpoint, HTTP 200 with an empty body for unknown ids, float prices). A caching decorator adds stale-if-error for remote sources so a flaky free API cannot take the demo down. The enquiry is **saved before any email is attempted** and the email is sent after the response, so a mail outage never loses a lead or shows the customer an error. Layer rules are enforced by architecture tests.

## Assumptions and cuts (full list in DECISIONS.md)
- "Top rated" on the home page is a **curated list** (config); on category pages it is **computed** (rating, then review count).
- Search: case-insensitive, all words must match, over title, description and category.
- Only the sales inbox is emailed; the customer sees a thank-you with a reference.
- **Cut:** customer confirmation email, a real queue, admin UI, pagination, IaC, Playwright E2E, CAPTCHA. Each has a stated reason and a path to add it.

## Evidence it works
| Check | Result |
|---|---|
| Backend tests (Pest) | 221 passing: unit, feature, contract (4 product + 2 email implementations), architecture |
| Frontend tests (Vitest + MSW) | 88 passing; any unmocked network request fails the test |
| Static analysis | Pint, Larastan level 6, oxlint, `tsc`: clean |
| Dependency audits | `composer audit`, `npm audit`: no advisories |
| Mutation checks | About 30 deliberate bugs (OR instead of AND search, truncated money, dropped stale-if-error, loosened throttle, trust-all proxies, client calling a third-party origin, …): every one turned the suite red |
| Live run | Real React app in a browser + Laravel + live Fake Store + real SMTP: all five acceptance flows walked; with the mail server stopped the customer still got success, the enquiry was saved as `failed`, and `enquiries:resend-failed` recovered it |
| Independent review | A fresh-context review agent found 5 real issues (e.g. Docker context shipping host-built Laravel cache); all fixed |
| Clean clone | Fresh checkout, install from lockfiles only: all checks pass |
| CI | Workflow defines backend, frontend and a `docker-smoke` job that builds the images and exercises the whole stack including the email arriving in Mailpit. **It has not run yet** (branch not pushed when this was written) |

## Known limitations (honest list)
- **Docker was not available on my build machine.** The Dockerfiles, nginx config and compose file were reviewed by reading, and are exercised by the `docker-smoke` CI job. Please run `docker compose up --build` to confirm locally.
- Mailpit was blocked by my network; real SMTP delivery was verified with a throwaway receiver instead.
- The catalogue is sample data and the images are category illustrations, not product photos. (The optional Fake Store provider depends on a free public API, cached with stale-if-error.)
- SQLite suits a single API container; enquiries have no retention policy yet.
- Accessibility is designed in and tested by attributes, not audited with assistive technology.

## AI usage
Built with GitHub Copilot (GPT-6.1 for planning, Claude Sonnet 5.5 for the build) under committed instructions ([AGENTS.md](AGENTS.md)) and a written spec ([docs/spec.md](docs/spec.md)). I reviewed the output, verified behaviour against the real systems rather than trusting green tests, and documented 20 places the AI was wrong and how each was caught in [AI_USAGE.md](AI_USAGE.md).

## Time spent
About 3 hours, at the top of the 2–3 hour guideline (planning ~15 min, building ~90, testing and verification ~45, review and docs ~30). Breakdown in the README.

## Checklist
- [x] Acceptance criteria 1–5 met and verified end to end
- [x] Browser never calls the Fake Store API (enforced by tests)
- [x] No secrets committed; `.env.example` only; no paid services or accounts
- [ ] `docker compose up --build` confirmed on a machine with Docker (the CI `docker-smoke` job covers this once the branch is pushed; it had not run when this was written)
- [x] README, DECISIONS, ARCHITECTURE, AI_USAGE, agent instruction files committed
