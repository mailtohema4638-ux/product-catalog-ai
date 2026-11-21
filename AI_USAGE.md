# AI Usage

## Overview

AI was used throughout planning, implementation, testing, and documentation.

I used GitHub Copilot (Agent Mode), Claude Sonnet 5.5, and GPT-6.1 to accelerate development while retaining responsibility for all implementation decisions.

All generated code, tests, and documentation were reviewed, adjusted, and verified before being committed.

---

## Tools Used

### GitHub Copilot (Agent Mode)

Used for:

- Project scaffolding
- Laravel implementation
- React component generation
- Test generation
- Documentation support

### Claude Sonnet 5.5

Used for:

- Architecture design
- Provider pattern design
- Documentation drafting
- Testing strategy
- Design reviews

### GPT-6.1

Used for:

- Requirement analysis
- Architecture validation
- Reviewing implementation decisions
- Identifying simplification opportunities

---

## Agent Instructions

The repository contains agent instruction files used during development:

- AGENTS.md
- CLAUDE.md
- .github/copilot-instructions.md

These files define project rules, architectural constraints, validation requirements, and security expectations used by AI during development.

---

## Planning Approach

Before generating code, AI was used to analyse the challenge requirements and identify:

- Functional requirements
- Architectural requirements
- Areas where requirements were ambiguous
- Testing expectations
- Documentation expectations

The project architecture was agreed before implementation began.

This planning phase resulted in:

- Project structure
- Provider-based architecture
- Testing strategy
- Documentation structure
- Phased implementation plan

The resulting design is documented in `ARCHITECTURE.md` and `docs/spec.md`.

---

## How AI Was Used

### Architecture

AI helped design:

- ProductProvider abstraction
- EmailProvider abstraction
- Service layer structure
- Error handling strategy
- Testing strategy

### Backend Development

AI assisted with:

- Laravel setup
- Controllers
- Services
- Form Requests
- API Resources
- Validation
- Email handling
- Tests

### Frontend Development

AI assisted with:

- React structure
- Routing
- API integration
- Form handling
- Search implementation
- Component development
- Frontend testing

### Documentation

AI helped draft:

- README.md
- ARCHITECTURE.md
- DECISIONS.md
- AI_USAGE.md

Documentation was reviewed and updated to match the final implementation.

---

## Key Decisions Influenced by AI

Several architectural decisions came from discussions with AI and were then reviewed before implementation:

### Provider-Based Design

Product access is abstracted behind `ProductProviderInterface`.

This allows the application to switch between:

- JsonCatalogueProvider
- FakeStoreProvider
- Future providers (database, external APIs, etc.)

without changing application logic.

### Email Abstraction

Email delivery is abstracted behind `EmailProviderInterface`.

This allows future migration between providers without affecting enquiry processing.

### Durability of Enquiries

Enquiries are saved before email delivery is attempted.

This ensures customer enquiries are never lost because of email failures.

### Separation of Business Logic

Business logic resides in services rather than controllers, making it easier to test and maintain.

---

## Examples of AI Corrections

AI suggestions were not accepted blindly.

Examples of corrections made during development include:

### Product Categories

The original Fake Store categories did not align with the entrepreneur catalogue scenario.

They were replaced with:

- Office Furniture
- Office Supplies & Stationery
- Print & Photo Services
- Tech & Electronics

to provide a more realistic business catalogue.

### Search Behaviour

Several search approaches were considered.

The final implementation performs search only when the user explicitly submits a search request, matching the acceptance criteria.

### Navigation Improvements

Additional usability improvements were added during review:

- Dedicated Home navigation
- Active category highlighting when viewing product details
- Consistent navigation context between pages

### Data Source Flexibility

The project initially used Fake Store data directly.

A provider abstraction was introduced so data sources can change without impacting the rest of the application.

---

## Human Review Process

Every generated change was reviewed before being committed.

Particular attention was given to:

- Business rules
- Validation logic
- Search behaviour
- Error handling
- Email processing
- User experience
- Documentation accuracy

Any code or recommendation that could not be clearly justified was revised or removed.

---

## What AI Got Wrong

During development several AI-generated assumptions required correction:

- Incorrect assumptions about third-party API behaviour.
- Use of framework APIs that did not exist.
- Overly permissive default configurations.
- Tests with ambiguous selectors.
- Documentation that described proposed behaviour rather than implemented behaviour.

These issues were identified through testing, manual review, static analysis