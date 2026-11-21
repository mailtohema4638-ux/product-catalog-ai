# Decisions

Assumptions, cuts, trade-offs, and what I would do next. The brief leaves gaps on purpose; this is how I filled them and why. For structure and flows see [ARCHITECTURE.md](ARCHITECTURE.md); for exact behaviour see [docs/spec.md](docs/spec.md).

## Assumptions
Where the acceptance criteria were unclear I picked the most reasonable reading, recorded it, and moved on.

| ID | Unclear point | Assumption | Cost of being wrong |
|---|---|---|---|
| A1 | "I have chosen 5 products to be top rated" | A curated, ordered list of product IDs in config (`FEATURED_PRODUCT_IDS`), not computed from ratings. Default `12,11,3,18,5`: the five highest-rated Fake Store products when I built it. IDs missing from the source are skipped and logged, never silently replaced. | Low: if it should be computed, it is one method (`CatalogueService::featured()`). |
| A2 | "Top rated products for that category … and additional products" | Top 3 (configurable) by rating, then review count, then title; the rest follow alphabetically. | Low: one comparator. |
| A3 | "Products that have text matching that search term" | Case-insensitive; every word must match (AND); searches title, description and category name; plain substring, no fuzzy matching. 1–100 characters; a blank term is rejected (422) and the UI prevents it. | Low. |
| A4 | "Complete the subsequent form with my contact details" | Name and email required; phone and message (≤1000 chars) optional. No quantity. | Low: documented recipe for adding a field. |
| A5 | "The sales inbox will receive an email" | Only the sales inbox is emailed. The customer sees an on-screen thank-you with a reference. No confirmation email to the customer. | Medium if confirmation is wanted: needs a second message and a decision on content. Listed as next step. |
| A6 | Scope | No auth, accounts, cart, checkout, or admin UI. | None for this MVP. |
| A7 | Currency | USD as given. Prices are stored and moved as integer cents; formatted only in the UI. | Low. |
| A8 | Product IDs | Strings internally, because another source may not use integers. | None. |
| A9 | Product images | Passed through from the source (`https` only) and allowed by the CSP. The mapper is the single place to rewrite them (for example to a CDN). | Privacy/availability depend on a third-party image host. |
| A10 | Where enquiries live | SQLite (portable to PostgreSQL/RDS via `DB_*`). | Not suitable for multiple API instances; see limitations. |
| A11 | Category size | About 20 products in total, so no pagination. | Revisit when a real source is larger. |
| A12 | Accessibility / language | English only. Accessibility basics (semantic HTML, labels, focus management, ARIA on errors), not a full audit. | A real audit is a next step. |

## What I cut or simplified, and why
Time-box guidance was 2–3 hours; the brief rewards judgement on the critical path over breadth.

| Cut | Why | What it would take |
|---|---|---|
| Customer confirmation email | Not in the acceptance criteria; needs content decisions (A5). | A second `EmailMessage` from the composer; same provider. |
| Real queue (SQS/Redis + worker) | Adds a container and ops for no PoC benefit. The notification is already a job and is dispatched after the response, so the switch is configuration plus retries/backoff. | Set `QUEUE_CONNECTION`, call `dispatch()` instead of `dispatchAfterResponse()`, add `tries`/`backoff`. |
| Admin UI for the featured list | The brief says the entrepreneur "has chosen" them; config is enough for a PoC. | A table plus a form behind the same service method. |
| Pagination | A11. | Add `page`/`per_page` to the search endpoint and provider. |
| Infrastructure as code | "If you include infrastructure" is optional; I documented the AWS mapping instead of half-building it. | Terraform/CDK for ECS, RDS, ElastiCache, SES, CloudFront. |
| Playwright end-to-end tests | The frontend suite drives real routing, data layer and components with the network mocked; setup cost would not fit the time-box. | The first test to add next. |
| OpenAPI document | The API is small and described in the spec. | Generate typed client from it. |
| CAPTCHA | Needs a third-party account, which the brief forbids. I used a honeypot, strict validation and a throttle instead. | Cloudflare Turnstile or similar, if abuse appears. |

## Key design decisions
Each one states the alternative I rejected.

1. **Two seams, not more.** `ProductProviderInterface` and `EmailProviderInterface` are exactly the two things the brief says will change. I rejected repositories, event buses and CQRS: each would add indirection with no second implementation to justify it. *Trade-off:* a future change in an unexpected place (say, auth) will need a new seam then.

2. **Search lives in the service, not the provider.** The Fake Store API has no search endpoint, so putting it in `CatalogueService` makes any source searchable. *Trade-off:* in-memory matching over the whole catalogue does not scale past a few thousand products; the path forward is an optional "searchable" capability that delegates to a search engine.

3. **Featured list is configuration, owned by the app, not by the data source.** It survives a change of source because it is a business decision, not a property of the data. *Trade-off:* IDs are source-specific, so swapping sources means updating the list (the log warns about missing IDs).

4. **Caching as a decorator with stale-if-error.** Caching stays out of both the service and the provider, is the only place outage behaviour lives, and is independently testable. The Fake Store API is a free public service and was observed to time out; serving stale data keeps the demo alive. Cached values are plain arrays because `cache.serializable_classes` is off (verified on the file driver). *Trade-off:* data can be up to 24 h stale during an outage; a user would rather see that than an error page.

5. **Save first, email after the response.** An email outage must never lose a lead or show the customer an error, and the customer should not wait on SMTP. The enquiry (with a snapshot of product title and price) is saved, then a job runs after the response. Failure is recorded on the enquiry and can be retried. *Trade-off:* with `dispatchAfterResponse` there is no automatic retry or backoff; recovery is the `enquiries:resend-failed` command (which also rescues enquiries whose job never ran).

6. **Money as integer cents.** Floats cause rounding errors (the classic `19.99 * 100`); the mapper converts once and the UI formats at the edge.

7. **One origin behind a gateway.** nginx serves the SPA and forwards `/api` to PHP-FPM: one command to run, no CORS, and a CSP that can be strict. The SPA and API remain independently deployable (S3/CloudFront and ECS). *Trade-off:* a mobile client on another origin would need CORS enabled (`CORS_ALLOWED_ORIGINS`).

8. **Plain-text sales email.** Nothing a customer types is ever interpreted as markup, and header-bound values are forced onto one line. *Trade-off:* less polished than HTML mail, which is fine for an internal notification.

9. **Native `<dialog>` for the enquiry form.** Built-in focus trapping, Escape handling and focus restoration, no extra dependency. *Trade-off:* jsdom lacks it, so tests emulate `showModal`/`close`.

10. **Typed, normalised errors end to end.** The backend returns one envelope with a request id; the client turns every failure (including a gateway HTML error page and network loss) into one `ApiError` with a safe user message. The UI never shows server text.

11. **Pest, contract tests and mutation checks.** Contract tests run every implementation through one suite, so a new source or email provider has an objective definition of "done". Architecture tests turn the layer rules into build failures. I verified the tests by breaking behaviour on purpose.

12. **Validation twice, on purpose.** The zod schema gives instant feedback; the server is the authority and strips control characters. Server field errors are mapped back onto the form.

## How the design supports growth beyond the PoC
- **New data source:** one class plus one `match` case, proved by the contract suite. Services, caching, controllers and the frontend do not change.
- **New email provider:** one class plus one case, or just configuration for SES via Laravel's mailer.
- **New look and feel:** components hold no business logic; styling is tokens plus one stylesheet; the API shape is presentation-independent, so a native mobile app can reuse `/api/v1`.
- **Scale:** stateless API (horizontal scaling), cache and database swap by configuration, a queue swap is a one-line change in the job dispatch. See the AWS mapping in [ARCHITECTURE.md](ARCHITECTURE.md).

## Known limitations and risks
Stated plainly so they are not a surprise:
- **Docker stack:** I could not run Docker on my build machine. The images, nginx config and compose file were reviewed by reading and are covered by a CI job (`docker-smoke`) that builds them and walks the whole flow including Mailpit. That job is defined but has not run yet; run `docker compose up --build` once to confirm on your machine.
- **Mailpit:** blocked by my network, so I verified real SMTP delivery with a small throwaway receiver instead. Mailpit itself is covered only by the (not yet run) CI smoke test.
- **Fake Store reliability:** it is a free public service. Caching and stale-if-error cover outages after the first successful load; a cold start while it is down shows a friendly error.
- **SQLite on a volume:** fine for one API container, not for several. Use RDS before scaling out.
- **No retention policy for enquiries:** they contain personal data (name, email, phone). A real system needs retention, deletion and consent handling.
- **Resend is a CLI command:** there is no UI or alert for failed enquiries yet.
- **Accessibility** has been designed in and tested with attribute assertions and a manual browser pass, but not audited with assistive technology.
- **Third-party images** are loaded directly from the source's host (a privacy and availability dependency); the mapper is where to proxy or rewrite them.

## What I would do with another day
1. Run and fix anything the first real Docker run turns up; add a published image build.
2. Playwright end-to-end test of the five acceptance flows against the Compose stack.
3. A real queue with retries and backoff, plus an alert for `failed` enquiries; an admin view to retry them.
4. Customer confirmation email.
5. Circuit breaker for the product source (stop calling a source that is clearly down) and a cache-warming command.
6. Admin UI for the featured list; DB-backed.
7. OpenAPI document and generated frontend types.
8. Terraform/CDK for the AWS deployment and a CloudFront cache policy for the SPA and API reads.
9. Accessibility audit, and enquiry retention/deletion policy.
