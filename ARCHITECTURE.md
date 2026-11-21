# Architecture

## Overview

This solution was designed with one primary goal: support future change.

The challenge specifically mentions that:

- Product data sources may change
- Email providers may change
- The application appearance may change

For that reason, the architecture separates business logic from external integrations.

---

## High-Level Architecture

```text
React Frontend
       ↓
Laravel API
       ↓
Product Provider
       ↓
Catalogue Source
```

```text
Customer Enquiry
       ↓
Laravel Service
       ↓
Email Provider
       ↓
Mail System
```

The frontend never communicates directly with external systems.

All product and enquiry operations go through the Laravel backend.

---

## Core Design Principles

### Separation of Concerns

Frontend:

- User interface
- Navigation
- Form handling

Backend:

- Business logic
- Validation
- Product retrieval
- Enquiry processing

Providers:

- External integrations
- Data source implementations
- Email delivery implementations

---

### Swappable Product Sources

The application retrieves product data through a `ProductProvider` interface.

Current implementation:

- JsonCatalogueProvider

Alternative implementation:

- FakeStoreProvider

Possible future implementations:

- Database provider
- Shopify provider
- ERP integration
- External APIs

This allows the product source to change without impacting application logic or user interface components.

---

### Swappable Email Providers

Email delivery is handled through an `EmailProvider` interface.

Current implementation:

- Laravel Mail

Possible future implementations:

- Amazon SES
- SendGrid
- Mailgun
- Microsoft 365

Only configuration and provider implementation changes would be required.

---

## Application Layers

### Controllers

Responsibilities:

- Receive requests
- Validate input
- Call services
- Return responses

Controllers do not contain business logic.

---

### Services

Services contain business rules such as:

- Featured products
- Category product selection
- Search behaviour
- Enquiry processing

This makes application behaviour easier to test and modify.

---

### Providers

Providers handle integration with external systems.

Examples:

- Product catalogue source
- Email provider

Business logic remains independent of these implementations.

---

## Product Flow

```text
User
 ↓
React
 ↓
Laravel Controller
 ↓
Catalogue Service
 ↓
Product Provider
 ↓
Catalogue Source
```

The current catalogue source is a JSON file.

The provider pattern allows new sources to be added easily.

---

## Enquiry Flow

```text
User
 ↓
Submit Enquiry
 ↓
Validation
 ↓
Save Enquiry
 ↓
Return Success Message
 ↓
Send Email Notification
```

A key design decision is that enquiries are stored before email delivery occurs.

This ensures customer requests are not lost if an email service is unavailable.

---

## Error Handling

The application provides a consistent error structure for:

- Validation failures
- Missing products
- Missing categories
- Product source failures
- Email delivery failures

Customers see friendly error messages while technical details remain in application logs.

---

## Security Considerations

Implemented protections include:

- Input validation
- Request throttling
- Safe error responses
- Email recipient protection
- Security