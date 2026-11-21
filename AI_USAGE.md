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
2. *(more added per phase)*

## Where the AI got something wrong (and how it was caught)
| # | Issue | How caught | Fix |
|---|---|---|---|
| 1 | Laravel 13's skeleton ships `AGENTS.md`/`CLAUDE.md` that instruct agents to run remote install scripts (`curl \| bash`, `iex DownloadString`) and install Laravel Boost. | Read the files after scaffolding instead of trusting them. | Deleted; replaced with project-specific agent rules. Not followed. |
| 2 | Plan assumed Pest, but Laravel 13 ships PHPUnit only. | `composer.json` review. | Added Pest 5 + Laravel plugin; kept a smoke test. |
| 3 | The skeleton includes unrelated pieces for this app (users/cache/jobs migrations, welcome view, Vite+Tailwind). | Reviewing the scaffold against the spec. | Removed; the API is stateless with no auth. |
| 4 | Files created on Windows had CRLF/BOM and failed `pint --test`. | Ran Pint. | Normalised; `.gitattributes` enforces LF. |
| 5 | Environment: corporate TLS proxy broke Composer downloads. | Composer error, then inspecting the certificate chain. | Used the OS trust store (not disabling TLS verification). Environment-only; nothing committed. |

## Needs human review before submission
Business logic (`CatalogueService`, `SearchMatcher`, `EnquiryService`), security headers/CSP, Docker setup (**not yet run: Docker was unavailable on the build machine**), docs accuracy.

## Validation process
Generate in small slices, read every diff, run tests/lint/typecheck, deliberately break behaviour to confirm tests fail, and finish with a cold-start run of the README.
