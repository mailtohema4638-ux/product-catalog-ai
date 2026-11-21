# AI Usage

How AI was used to plan, build and verify this project, where it was wrong, and how I caught it. Entries are real events from the build, not retrospective polish. I own every line; the sections below say what I reviewed and what I verified.

## Tools and models
- **GitHub Copilot** (agent mode in VS Code) for planning, scaffolding, implementation, test writing, browser-driven verification and documentation.
- Models: **GPT-6.1** for the initial analysis and architecture plan; **Claude Sonnet 5.5** for the build (Phases 1–4).
- A separate **code-review agent** (read-only, fresh context) reviewed the finished branch independently.
- No other AI tools. No code was copied from elsewhere.

## Agent instruction files (committed)
- [`AGENTS.md`](AGENTS.md): architecture and security rules; `CLAUDE.md` and `.github/copilot-instructions.md` point to it.

## Plan / spec
- [`docs/spec.md`](docs/spec.md): scope, assumptions (A1-A12), API and error model, written before code and updated when reality differed from the plan (Fake Store quirks, timeouts).
- The architecture plan was produced first and reviewed before any code: [ARCHITECTURE.md](ARCHITECTURE.md) is its final form.

## Key prompts (paraphrased)
1. **The brief-analysis prompt.** Gave the AI the full challenge PDF and asked it, in a Principal Engineer / AI-native persona, to analyse explicit and implicit requirements and what reviewers score, then design the whole solution (architecture, structure, provider pattern, error handling, caching, testing, security, docs, interview prep, phased plan) *before generating any code*. This produced the two-seam design and the phase plan I then followed.
2. **"Clone it, start Phase 1."** Scaffold, Compose, CI and agent rules first, so every later generation was constrained by `AGENTS.md`.
3. **"Start Phase 2" / "Phase 3".** Each phase was a slice with its own verification gate (tests, mutation checks, live run), committed separately.
4. **"Start Phase 4".** Hardening and docs, starting with an independent review and static analysis rather than more features.
Within phases I steered by correcting: for example, insisting on checking the live API before designing the provider, and on running the real stack instead of trusting green tests.

## Key moments
1. **Plan first, code second.** I had the AI analyse the brief and propose architecture (two contracts, caching decorator, durable enquiries) before any scaffolding, then froze it into `docs/spec.md`.
2. **Verify the upstream before designing the provider.** Instead of coding against the documented Fake Store API, I called the live API first. That surfaced three facts the plan did not have: unknown ids return **HTTP 200 with an empty body** (not 404), `jewelery` is misspelt at source, and the best real "top rated" ids are 12, 11, 3, 18, 5 (the placeholder `1..5` in the draft was wrong). All three are now in `docs/spec.md` and covered by tests.
3. **Read the framework config before choosing the cache design.** `config/cache.php` has `serializable_classes => false`. I confirmed on the file driver that a cached DTO returns as `__PHP_Incomplete_Class`, so the cache stores plain arrays and a test uses a store that really serialises. Laravel's default array store does not serialise, so a naive implementation would pass tests and fail in Docker.
4. **Mutation checks on the tests.** After the suite was green on the first run I deliberately broke 11 behaviours (OR instead of AND search, truncating money, accepting http images, dropping the empty-body rule, removing stale-if-error, reordering featured, ignoring tiebreaks, and more). All 11 turned the suite red; the code was restored and the baseline re-run.
5. **Drive the real thing, not just the tests.** After 172 backend and 71 frontend tests were green, I ran the real stack (React in a browser, Laravel, the live Fake Store, and a real SMTP receiver) and walked every acceptance criterion. That surfaced a real operational gap the tests had not: when mail delivery failed, the customer was fine (by design) but the logs did not say *why*. I added the underlying cause to the operator log (still no customer data) and a test for it.
6. **Prove the failure story, don't just claim it.** I stopped the mail server, submitted an enquiry, confirmed the customer still got success and the enquiry was saved as `failed`, then restarted the server and recovered it with `enquiries:resend-failed`.
7. **An independent reviewer found what I had stopped seeing.** A fresh-context code-review agent read the branch and found real problems that my tests could not: a Docker build that would crash on my own machine (host-built Laravel cache listing dev-only packages), gateway headers that could be spoofed, an enquiry-recovery command that could skip rows and miss stuck enquiries, and a CI step referencing uncommitted files. All were verified against the code and fixed.
8. **Static analysis as a second opinion.** Adding Larastan (level 6) flagged `env()` in `bootstrap/app.php`: it returns null once config is cached, which would have silently disabled trusted-proxy handling (and made per-IP throttling treat every user as one). Fixed at the cause (moved to config + service provider) with tests, not suppressed.
9. **A clean-clone run.** Cloning the committed branch into an empty folder and installing from lockfiles proves nothing depends on files only on my machine.

10. **A late requirement change as a test of the design.** Near the end I changed the product source: show an office-supplies catalogue (four named categories) instead of Fake Store. The change touched the provider layer (a new `JsonCatalogueProvider`), configuration, data and tests, and **no service, controller or UI code**, which is the evidence the seam is real. The new provider joined the same contract suite (so it had to behave exactly like the others), its data got strict validation plus integrity tests (images exist, featured ids exist), and I re-ran the real stack in a browser. Fake Store stays one config switch away.

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
| 15 | I could not fetch Mailpit to demo real SMTP: the corporate proxy blocks the release host. | Download returned a proxy "Bad Gateway" page (my checksum guard refused to run it). | Did not work around the policy; used a throwaway ~40-line Node SMTP receiver outside the repo to exercise Laravel's real `smtp` transport. Mailpit itself is covered only by the CI smoke job, which has not run yet. |
| 16 | Docker build context copied host-built `bootstrap/cache/*.php` into the image; those manifests list dev-only providers that a `--no-dev` image lacks, so the API container could crash on first start. A fresh clone would not hit it; my machine would. | Independent code-review agent. | `.dockerignore` entries, `rm -f bootstrap/cache/*.php` in the image, and `package:discover` first in the entrypoint. |
| 17 | `env('TRUSTED_PROXIES')` in `bootstrap/app.php` returns null when config is cached, silently disabling proxy trust (and per-IP throttling behind the gateway). | Larastan rule `noEnvCallsOutsideOfConfig`. | Moved to `config/http.php` and `AppServiceProvider`; three tests (default distrust, trusted proxy, unconfigured proxy), mutation-checked. |
| 18 | The gateway overwrote only `X-Forwarded-For`; host, proto, port and prefix from a client could still reach Laravel. | Code-review agent. | nginx now sets all `X-Forwarded-*` values; also switched to an internal front-controller rewrite and an unprivileged image. |
| 19 | `enquiries:resend-failed` used offset paging while changing the filtered column (skips rows past 1000) and ignored enquiries stuck in `received` (job died after the response), so "never lose an enquiry" was only half true. | Code-review agent. | `lazyById`, plus recovery of `received` enquiries older than 10 minutes; four new tests. |
| 20 | `config/auth.php` still referenced the `User` model I had deleted in Phase 1. | Larastan. | Pointed at a real framework class. |
| 21 | Adding a Home link to the header: (a) an existing home-page test grabbed "the first list on the page" and assumed it was the product grid, which broke as soon as the nav rendered immediately; (b) my code comment claimed `end` stopped Home being "current" on every route, but React Router already special-cases the root link. | (a) The test failed on the first run; (b) a mutation check removing `end` survived, so I read the library source instead of assuming a test gap. | (a) Scoped the test to the "Top rated products" region; (b) corrected the comment to say what `end` really does. |

## Which parts the AI generated, and what a human must check
| Area | AI role | Human review focus |
|---|---|---|
| Architecture, spec, ADR-style docs | Drafted; edited after verification against reality | Be able to defend every trade-off in [DECISIONS.md](DECISIONS.md) aloud |
| Boilerplate (DTOs, resources, requests, routes, Docker, nginx, CI) | Mostly generated | Config accuracy, security headers, ports and volumes (Docker is verified only by CI, see below) |
| Business logic (`CatalogueService`, `SearchMatcher`, `EnquiryService`, error mapping, caching decorator) | Generated, then reviewed line by line | This is what gets probed in the interview |
| Tests | Generated from a test list I approved | Assertions are meaningful (checked by mutation, not by coverage) |
| React components and styling | Mostly generated | Accessibility, error states, no unsafe HTML |
| Docs | Drafted, then corrected against actual behaviour | Accuracy, and the time-spent table |

## Common AI mistakes I watched for
Floats for money; business logic or `Http::` in controllers; assuming the source has a search endpoint; not URL-encoding `men's clothing`; hallucinated framework APIs (caught one: #7); tautological tests that mock the thing under test; swallowed exceptions or raw upstream errors returned to clients; over-engineering (repositories, CQRS); secrets in example files; `dangerouslySetInnerHTML` or unescaped email HTML; docs that describe features that do not exist; Windows/Docker line-ending problems (caught: #4).

## Validation process
1. Write the spec and agent rules first, then generate against them.
2. Generate in slices; one commit per slice.
3. Read every diff; ask the AI to explain anything I cannot explain.
4. Run Pint, Larastan (level 6), oxlint, `tsc`, both test suites, `composer audit` and `npm audit`.
5. **Mutation-check the tests:** break behaviour on purpose and confirm the suite goes red (11 + 9 backend, 9 frontend, plus the proxy rule; all caught).
6. **Drive the real stack:** React in a browser, Laravel, the live Fake Store, real SMTP; walk every acceptance criterion, then stop the mail server and prove no enquiry is lost and recovery works.
7. Independent code review by a fresh-context agent; verify each finding before acting (all five were real).
8. Clean-clone run from lockfiles.

## What is verified and what is not
- Verified here: backend and frontend suites, static analysis, audits, live API against the real Fake Store, real SMTP delivery, browser walkthrough of all five acceptance flows, clean clone.
- **Not verified on the build machine: Docker.** It was not installed, so the images, nginx config and compose file were reviewed by reading. The `docker-smoke` CI job (defined, **not yet run**) builds them and exercises the whole flow including Mailpit; run `docker compose up --build` to confirm locally.
