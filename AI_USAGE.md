# AI Usage

> Working draft, updated as the build progresses. Entries are real events, not retrospective polish.

## Tools
- GitHub Copilot (agent mode, VS Code) for planning, scaffolding and implementation. Models used so far: GPT-6.1 (planning), Claude Sonnet 5.5 (Phase 1 build).

## Agent instruction files (committed)
- [`AGENTS.md`](AGENTS.md): architecture and security rules; `CLAUDE.md` and `.github/copilot-instructions.md` point to it.

## Plan / spec
- [`docs/spec.md`](docs/spec.md): scope, assumptions (A1-A12), API and error model, written before code.

## Key moments
1. **Plan first, code second.** I had the AI analyse the brief and propose architecture (two contracts, caching decorator, durable enquiries) before any scaffolding, then froze it into `docs/spec.md`.
2. **Verify the upstream before designing the provider.** Instead of coding against the documented Fake Store API, I called the live API first. That surfaced three facts the plan did not have: unknown ids return **HTTP 200 with an empty body** (not 404), `jewelery` is misspelt at source, and the best real "top rated" ids are 12, 11, 3, 18, 5 (the placeholder `1..5` in the draft was wrong). All three are now in `docs/spec.md` and covered by tests.
3. **Read the framework config before choosing the cache design.** `config/cache.php` has `serializable_classes => false`. I confirmed on the file driver that a cached DTO returns as `__PHP_Incomplete_Class`, so the cache stores plain arrays and a test uses a store that really serialises. Laravel's default array store does not serialise, so a naive implementation would pass tests and fail in Docker.
4. **Mutation checks on the tests.** After the suite was green on the first run I deliberately broke 11 behaviours (OR instead of AND search, truncating money, accepting http images, dropping the empty-body rule, removing stale-if-error, reordering featured, ignoring tiebreaks, and more). All 11 turned the suite red; the code was restored and the baseline re-run.
5. **Drive the real thing, not just the tests.** After 172 backend and 71 frontend tests were green, I ran the real stack (React in a browser, Laravel, the live Fake Store, and a real SMTP receiver) and walked every acceptance criterion. That surfaced a real operational gap the tests had not: when mail delivery failed, the customer was fine (by design) but the logs did not say *why*. I added the underlying cause to the operator log (still no customer data) and a test for it.
6. **Prove the failure story, don't just claim it.** I stopped the mail server, submitted an enquiry, confirmed the customer still got success and the enquiry was saved as `failed`, then restarted the server and recovered it with `enquiries:resend-failed`.
7. *(more added per phase)*

## Where the AI got something wrong (and how it was caught)
| # | Issue | How caught | Fix |
|---|---|---|---|
| 1 | Laravel 13's skeleton ships `AGENTS.md`/`CLAUDE.md` that instruct agents to run remote install scripts (`curl \| bash`, `iex DownloadString`) and install Laravel Boost. | Read the files after scaffolding instead of trusting them. | Deleted; replaced with project-specific agent rules. Not followed. |
| 2 | Plan assumed Pest, but Laravel 13 ships PHPUnit only. | `composer.json` review. | Added Pest 5 + Laravel plugin; kept a smoke test. |
| 3 | The skeleton includes unrelated pieces for this app (users/cache/jobs migrations, welcome view, Vite+Tailwind). | Reviewing the scaffold against the spec. | Removed; the API is stateless with no auth. |
| 4 | Files created on Windows had CRLF/BOM: `pint --test` failed, and `entrypoint.sh` with CRLF would have crashed the Alpine container. | Ran Pint; counted `\r` in the script. | Normalised to LF; `.gitattributes` enforces it. |
| 5 | Environment: corporate TLS proxy broke Composer downloads. | Composer error, then inspecting the certificate chain. | Used the OS trust store (not disabling TLS verification). Environment-only; nothing committed. |
| 6 | Laravel's default CORS config sent `Access-Control-Allow-Origin: *` on `/api/*`, contradicting the same-origin design. | Read the raw response headers during a smoke test. | Restricted to an env allowlist (empty by default) plus a regression test. |
| 7 | I wrote a test using `assertJsonValidationErrorsFor`, which does not exist in Laravel (hallucinated API). 5 tests errored. | First full test run. | Replaced with the real `assertJsonValidationErrors([...], 'error.details')`. |
| 8 | A test helper named `spy()` collided with Mockery's global `spy()`: PHP fatal at load. | First unit run. | Renamed helpers to specific names. |
| 9 | `phpunit.xml` listed only the `Unit` and `Feature` suites, so `pest` (and CI) would have silently skipped the Contract and Architecture tests I had just written. | Noticed that a plain run did not include them; direct path runs did. | Added both suites; the full run is now 122 tests. |
| 10 | Draft defaults (5 s timeout, 2 retries) meant a user could wait about 15 s for an error. Observed live when the upstream timed out through the proxy. | Smoke test against the real API plus reading the request timings in the log. | 4 s timeout, 1 retry (about 8 s worst case). |
| 11 | A test fixture built `new Enquiry([... 'id' => ...])`, but `id` is not mass-assignable, so the id silently vanished and a test failed on a blank reference. | Failing assertion showed `Reference: ` empty. | Set the id explicitly on the model in the fixture. |
| 12 | Design gap: when email delivery failed, the enquiry was correctly kept as `failed`, but the log did not record *why* (only a generic message), so an operator could not diagnose it. | Live run with the mail server down, then reading the log. | Log the underlying cause (never customer data) and added a test. |
| 13 | Harness mistakes, not app bugs: my first live POST used `Set-Content -Encoding utf8` (adds a BOM) so the API rightly rejected the body, and I later deleted the SMTP sink's inbox folder, crashing it. | Read the 422 body and the sink's stderr instead of assuming the app was wrong. | Fixed the harness; the app handled the second case as designed. |
| 14 | A frontend test queried a link named "Electronics" that exists twice (category nav and the product breadcrumb), so it was ambiguous. | First frontend run. | Scoped the query to `main`. |
| 15 | I could not fetch Mailpit to demo real SMTP: the corporate proxy blocks the release host. | Download returned a proxy "Bad Gateway" page (my checksum guard refused to run it). | Did not work around the policy; used a throwaway ~40-line Node SMTP receiver outside the repo to exercise Laravel's real `smtp` transport. Mailpit itself is still unverified here. |

## Needs human review before submission
Business logic (`CatalogueService`, `SearchMatcher`, `EnquiryService`), security headers/CSP, Docker setup (**not yet run: Docker was unavailable on the build machine**), docs accuracy.

## Validation process
Generate in small slices, read every diff, run tests/lint/typecheck, deliberately break behaviour to confirm tests fail, and finish with a cold-start run of the README.
