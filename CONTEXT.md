# Domain Glossary

Shared terms across all Inea Scents repositories. Each term is annotated with the repo(s) that use it.

## Core Domain

**Booking**:
A reservation for the perfume bar service to be set up at a specific location on a specific date for a number of pax. Includes the status of the reservation, the price, and payment progress. Exactly one Booking per calendar day (physical bar).
_Avoid_: Reservation, appointment, order
_Repos_: backend, client

**Package**:
A predefined service offering that a customer selects when making a booking. Defines the inclusions, freebies, and price for a specific number of pax.
_Avoid_: Tier, option, bundle, service
_Repos_: backend, client

**Scent**:
A physical perfume choice that a customer can select to include in a Package. Inventory is not currently tracked per scent.
_Avoid_: Perfume, fragrance
_Repos_: client

**Inquiry**:
The primary conversion goal of the landing page. An inquiry is a request for an event consultation or quote, submitted via the lead capture form. One row per submission. Email is NOT unique. Converts 1:1 to a Booking (status becomes `booked`, stays `booked` even if the Booking is later cancelled).
_Avoid_: lead, contact form submission, contact request
_Repos_: landing, backend (shared first-class term)

**Signature Collection**:
A curated subset of premium perfumes displayed statically on the landing page to tease quality. The full catalog is reserved for the mobile app.
_Avoid_: catalog, featured scents
_Repos_: landing

## People & Roles

**Customer**:
The contact (name, email, phone) a specific Booking is made under. Prefilled from a User profile but editable per Booking. A Customer is not necessarily an authenticated account.
_Avoid_: client, guest
_Repos_: backend, client

**User** (App User):
An authenticated customer account in the Mobile App (created via `POST /api/register`, `is_admin` always false). Not a Customer until their contact details are attached to a Booking.
_Avoid_: account, member
_Repos_: backend, client

**Admin**:
The business principal operating the Admin Dashboard. A single seeded account with no self-registration; authenticates only at `/admin/login` and never through the customer API. May create Bookings on a Customer's behalf using offline payment method (`cash`).
_Avoid_: Super admin, owner
_Repos_: backend, client

## Applications

**Admin Dashboard**:
The Laravel-based web application exclusively used by Admins to manage the business. Session auth at `/admin/login`; no admin surfaces in the Mobile App. Hosted on its own domain/subdomain.
_Avoid_: Backend, website
_Repos_: backend

**Mobile App**:
The Flutter-based application used by the Customer to book the perfume bar service. Connects to the Backend API. Supports iOS and Android.
_Avoid_: Client, frontend
_Repos_: client

**Landing Page**:
The public-facing website for SEO, marketing, and lead generation. Generates Inquiries, does not create Bookings. A developer-maintained React/Vite application (not no-code).
_Avoid_: Homepage, main site
_Repos_: landing

**Backend API**:
The Laravel-based API serving the Mobile App and Admin Dashboard, utilizing OpenAPI specification for strictly-typed contract synchronization.
_Avoid_: Server, backend
_Repos_: backend

## Booking Lifecycle

**Status** (of a Booking):
`Pending` (awaiting payment or admin confirmation) → `Confirmed` (paid via webhook or admin-approved) → `Cancelled` (expired, rejected, or customer-cancelled).
_Avoid_: state (ambiguous)
_Repos_: backend, client

**Pax**:
The headcount for a Booking, selected from Package `paxOptions`. Determines capacity/price.
_Avoid_: guests, capacity, attendees
_Repos_: client

**Time Slot**:
An informational window (e.g. `2:00 PM - 5:00 PM`) indicating when the bar should be ready. Does NOT affect Availability.
_Avoid_: slot without qualifier
_Repos_: client

**Availability**:
Day-level status `Available` / `Booked` derived from confirmed Bookings and admin-blocked dates via `GET /api/availability`. Consumed by the booking calendar to disable Booked days.
_Avoid_: openness, free
_Repos_: client

## Payments

**Payment Method**:
The declared way to settle a Booking: `online` (PayMongo link page) or `cash` (admin confirms). Backend enum: `online | cash` (`credit_card` renamed 2026-09-12; `bank_transfer` deleted, rows purged; legacy `gcash | maya` values never emitted).
_Avoid_: payment type, mode
_Repos_: client

**Checkout**:
The post-submit phase for a Booking: online opens the PayMongo link and polls `GET /api/bookings` until `Confirmed`/`Cancelled`; offline shows awaiting-admin state.
_Avoid_: payment flow, pay
_Repos_: client

## Infrastructure

**Environment**:
A deployment target with isolated config and backing services. Two environments: `local` (developer machine + Supabase CLI + Studio 54323 + DB 54322) and `production` (Render `ineascents` + Supabase `ineascents-db` + Vercel Production; Vercel Preview shares the prod backend). No state, keys, or cookies cross envs. 12-Factor III.
_Avoid_: env toggle in code
_Repos_: backend, client

**Mailpit**:
Local SMTP catcher (default `:1025`, web UI) that receives dev mail instead of an inbox. The documented default for previewing backend mail; Gmail SMTP is reserved for real-delivery checks and `production`.
_Avoid_: testing mail content by scraping `storage/logs/laravel.log`
_Repos_: backend

**Provenance marker**:
A `[LOCAL]` subject prefix applied to mail produced by a `local` stack (`APP_ENV=local` only), so test mail is visibly distinct from `production` mail in any inbox.
_Avoid_: changing sender identity per env (Gmail requires the from-address to match the account)
_Repos_: backend
