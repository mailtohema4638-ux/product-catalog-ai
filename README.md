# Product Catalogue

A simple product catalogue application where users can:

- Browse featured products
- Browse products by category
- Search products
- View product details
- Submit product enquiries

When an enquiry is submitted, the sales team receives an email notification and the customer receives a confirmation message.

---

# Project Overview

This solution was built as a proof of concept with future growth in mind.

The challenge specifically mentioned that:

- Product data sources may change
- Email providers may change
- The application's look and feel may change

To support this, product and email integrations are separated behind provider interfaces, allowing them to be replaced without major application changes.

---

# Technology Stack

| Layer | Technology |
|---------|------------|
| Backend | Laravel 13 (PHP) |
| Frontend | React + TypeScript |
| Database | SQLite |
| Email | Laravel Mail / Mailpit |
| Development Environment | Docker Compose |

---

# Features

✅ Home page with featured products

✅ Category pages with top-rated and additional products

✅ Product search

✅ Product detail pages

✅ Customer enquiry form

✅ Sales email notifications

✅ Responsive user interface

---

# Business Decisions

Several decisions were made where requirements were open to interpretation.

Examples include:

- Featured products are manually selected by the entrepreneur.
- Search is executed only when the user explicitly submits a search.
- The selected category remains highlighted when viewing product details.
- Customer enquiries are saved before emails are sent.

Additional details can be found in `DECISIONS.md`.

---

# Running the Application

## Using Docker

```bash {preview="false"}
docker compose up --build
```

Application:

```text
http://localhost:8080
```

Mailpit:

```text
http://localhost:8025
```

---

## Running Without Docker

### Backend

```bash {preview="false"}
cd backend

composer install

cp .env.example .env

php artisan key:generate

touch database/database.sqlite

php artisan migrate

php artisan serve
```

Backend URL:

```text
http://localhost:8000
```

### Frontend

```bash {preview="false"}
cd frontend

npm install

npm run dev
```

Frontend URL:

```text
http://localhost:5173
```

---

# Testing

Backend:

```bash {preview="false"}
cd backend

vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Frontend:

```bash {preview="false"}
cd frontend

npm test
npm run lint
npm run typecheck
```

---

# Configuration

The application supports multiple catalogue providers.

Default:

```text
JsonCatalogueProvider
```

Alternative:

```text
FakeStoreProvider
```

Switch provider using:

```env {preview="false"}
CATALOGUE_PROVIDER=local
```

or

```env {preview="false"}
CATALOGUE_PROVIDER=fakestore
```

Product data is currently stored in:

```text
backend/resources/catalogue/products.json
```

---

# Architecture Summary

The application separates business logic from external dependencies.

```text
React UI
    ↓
Laravel API
    ↓
ProductProvider
    ↓
Catalogue Source
```

Current provider:

```text
JsonCatalogueProvider
```

Future providers could include:

- Fake Store API
- SQL Database
- Shopify
- ERP systems
- Other external APIs

Email delivery follows a similar approach through an EmailProvider abstraction.

More details are available in `ARCHITECTURE.md`.

---

# Documentation

| File | Purpose |
|--------|---------|
| ARCHITECTURE.md | Technical architecture and design |
| DECISIONS.md | Assumptions and trade-offs |
| AI_USAGE.md | How AI was used during development |

---

# Known Limitations

- SQLite is used for simplicity and local development.
- Product administration UI is not included.
- Customer confirmation emails are not implemented.
- Docker configuration was prepared but not executed locally due environment limitations.

---

# Future Improvements

Given additional time, I would implement:

- Customer confirmation emails
- Search suggestions and autocomplete
- Featured product management screen
- Database-backed catalogue management
- Redis caching
- End-to-end browser testing
- AWS deployment automation

---

# Time Spent

Approximate effort:

| Activity | Time |
|-----------|-------|
| Analysis and planning | 15 min |
| Development | 90 min |
| Testing and validation | 45 min |
| Documentation | 30 min |
| Total | ~3 hours |
```