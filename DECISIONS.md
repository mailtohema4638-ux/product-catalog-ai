# Decisions

This document explains the assumptions, simplifications, and design decisions made while implementing the solution.

The challenge intentionally leaves some requirements open to interpretation. Where this occurred, I made reasonable assumptions, documented them, and focused on delivering a maintainable proof of concept.

---

# Assumptions

## A1 - Featured Products

The requirement states:

> "I have chosen 5 products to be top rated."

I interpreted this as a business decision made by the entrepreneur rather than an automatic calculation.

Featured products are therefore managed separately from product ratings and can be changed without modifying application logic.

If this interpretation changes in the future, the selection logic can easily be moved to a rating-based calculation.

---

## A2 - Top Rated Products Within a Category

The requirement asks for top-rated products and additional products within a category but does not specify the number.

I chose to display:

- Top 3 rated products
- Remaining products in a separate section

This provides visibility for the highest-rated products while allowing users to browse the full catalogue.

---

## A3 - Search Behaviour

The acceptance criteria state that a user enters a search term and clicks Search.

I assumed:

- Search is executed only when the Search button is clicked or Enter is pressed.
- Search is case-insensitive.
- Search matches product title, description, and category.
- Search suggestions and live filtering are out of scope.

The search term remains visible until user clicking other menu and after the search is performed so users can refine their query.

---

## A4 - Enquiry Form

The acceptance criteria mention collecting customer contact details but do not define the fields.

I assumed:

Required fields:

- Name
- Email

Optional fields:

- Phone Number
- Message

This provides sufficient information for a sales follow-up while keeping the form simple.

---

## A5 - Sales Notification

The challenge requires the sales inbox to receive an email when an enquiry is submitted.

I assumed:

- Only the sales team receives email notifications.
- The customer receives an on-screen confirmation message and reference number.
- Customer confirmation emails are outside the scope of the proof of concept.

Customer confirmation emails would be one of the first enhancements added to a future version.

---

## A6 - Scope

The following items were considered outside the scope of this proof of concept:

- User accounts
- Authentication
- Shopping cart
- Checkout process
- Product administration screens

The focus was kept on product discovery and customer enquiries.

---

## A7 - Currency

Products are displayed in USD.

Prices are stored internally as integer cents to avoid floating-point rounding issues and formatted only when displayed to the user.

Additional currencies can be introduced through catalogue data and formatting changes.

---

## A8 - Product Identifiers

Product identifiers are treated as strings internally.

This allows future catalogue providers to use different identifier formats without requiring application changes.

---

## A9 - Product Images

The application uses local category illustrations and catalogue images.

This avoids dependency on third-party image providers and ensures a consistent experience during demonstrations.

---

## A10 - Enquiry Storage

Customer enquiries are stored in SQLite.

This keeps setup simple for a proof of concept while allowing future migration to PostgreSQL, MySQL, or other relational databases.

---

## A11 - Category Size

The current catalogue is small enough that users can comfortably browse all products within a category without pagination.

Pagination can be introduced later if catalogue size increases.

---

## A12 - Accessibility and Language

The application is English-only.

Basic accessibility practices have been applied, including:

- Semantic HTML
- Form labels
- Keyboard navigation
- Error messaging

A full accessibility audit has not been completed.

---

# Additional Product and UX Decisions

These decisions were made where the acceptance criteria left room for interpretation.

## Home Navigation

The challenge refers to a home page but does not specify navigation behaviour.

A dedicated Home menu item was added so users can easily return to the featured products page.

---

## Business-Oriented Categories

The original sample Fake Store categories did not align well with the entrepreneur catalogue described in the challenge.

The catalogue was adapted to use:

- Office Furniture
- Office Supplies & Stationery
- Print & Photo Services
- Tech & Electronics

This creates a more realistic business catalogue while keeping the same underlying architecture.

---

## Category Context on Product Details

When a user navigates from a category page to a product page, the category remains highlighted in the navigation.

This helps maintain user context and improves navigation.

---

## Search Term Visibility

After a search is performed, the search term remains visible.

This allows users to understand which query produced the results and makes search refinement easier.

---


## Enquiry Confirmation

Customers receive immediate confirmation after submitting an enquiry.

Enquiries are stored before email delivery is attempted, ensuring that customer requests are not lost if email delivery fails.

---

# Technical Decisions

## Product Provider Abstraction

Product information is accessed through a ProductProvider interface.

Benefits:

- Catalogue source can change without affecting business logic.
- Fake Store API can be replaced by another provider.
