# OPES AGRI OS — Agri Platform Roadmap

What OPES AGRI OS is growing into beyond the invoicing/accounting core, why each decision below was made,
and what's deliberately out. This is a **living document** — grown as modules ship, not written once and
left to drift. It complements `decisions.md` (technical decisions for the platform as a whole) rather than
replacing it.

---

## 1. What this is

OPES AGRI OS started as a small-business invoicing/accounting PWA (`decisions.md` #1–#8). It is now
expanding into an **agricultural operations suite** — farm, land, and crop management first, with
livestock, warehousing/procurement, cooperative, and finance modules to follow — while keeping the existing
Sales/Documents/Accounting core exactly as-is.

**This is a pivot in place, not a rebuild.** Same codebase, same company/tenancy/permission/billing model,
same Laravel 12 + Livewire 3 + MySQL stack. New agri modules are new `App\Domain\<Context>` bounded contexts
added alongside `App\Domain\Sales`, following the same conventions: Eloquent models with `BelongsToCompany`,
Policies extending `CompanyScopedPolicy`, business logic in service classes, Livewire for the UI, Form
Requests + API Resources for the `/api/v1` surface every module gets from day one.

## 2. What's explicitly out of scope

Cut deliberately, not "later":

- **IoT / sensors** — no weather stations, soil sensors, water sensors, RFID, LoRaWAN, MQTT gateways, or
  any hardware ingestion pipeline.
- **AI / ML** — no disease detection, yield prediction, recommendation engines, chat assistants, or
  predictive analytics. "Analytics & BI" below means dashboards and reports over our own data, not models.
- **Drone operations, satellite analytics, precision agriculture.**
- **Smart contracts, blockchain traceability, carbon credit tooling** (the last depends on sensor/satellite
  data we don't have).
- **GIS beyond storage + display.** "Land Management" stores coordinates and field/plot boundaries (MySQL
  spatial types) and shows them on a plain map (e.g., Leaflet over stored geometry). No imagery overlays, no
  satellite-derived area calculation, no drone imagery.
- **Mobile app** — not built now, but every module ships API-first (routes, Form Requests, Resources, tests,
  OpenAPI docs) so a future app has a real surface to consume. This is the only sense in which mobile is
  "provisioned for" — it is an engineering discipline applied to every module, not a deliverable.
- **Microservices, event bus, CQRS, hexagonal architecture** as day-one requirements. Modular monolith, same
  as the existing app. Revisit per-module only if a specific module's shape genuinely needs it (e.g., a
  future traceability module doing high-volume append-only writes) — not adopted speculatively.

## 3. Module inventory

The full long-term shape, trimmed to what survives §2. Not a commitment to build all of it — see §4 for
what's actually next.

**Foundation** (mostly exists)
Identity & Access · Organization & Multi-Tenancy · Farm Management · Land Management (basic GIS)

**Agricultural production**
Crop Management · Livestock Management · Poultry Management · Fisheries & Aquaculture · Beekeeping ·
Forestry & Agroforestry · Greenhouse Management · Nursery & Seed Management · Soil & Fertility Management ·
Irrigation & Water Management (manual/scheduled, not sensor-automated)

**Operations**
Machinery & Equipment · Asset Management (exists) · Utility Management · Warehouse & Inventory (exists, as
Products/Stock) · Procurement & Supplier Management · Fleet & Logistics · Supply Chain & Traceability

**Business & finance**
Sales & Customer Management (exists) · Finance & Accounting (exists) · HR & Payroll (exists) · Cooperative &
Farmer Groups · Microfinance & Credit · Partner & NGO CRM · Project & Grant Management

**Intelligence**
Analytics & Business Intelligence (dashboards/reports, no ML) · Reporting & Document Management

**Shared platform services** — cross-cutting capabilities, not modules with their own UI, mostly already
partially present (audit logs, file storage, notifications, scheduler, queues, offline sync). Extended as
individual modules need them; not built ahead of demand.

## 4. Phasing

**V1 — the planting-to-sale loop.** The thing a real farm can use end-to-end:

- **Farm Management** — register a farm, its fields/plots, seasons.
- **Land Management (basic)** — plot boundaries and coordinates on a plain map; ownership/lease records.
- **Crop Management** — planting plans, growth-stage tracking, harvest recording, yield per plot/season.
- **Warehouse & Inventory** — extend the existing Items/Stock domain to be farm-input-aware (seed, feed,
  fertiliser as stocked items, not just resale goods).
- **Procurement** — supplier records and purchase orders for farm inputs, feeding into the same Stock ledger
  Sales already uses.

Sales, Payments, Accounting stay exactly as they are — a harvest becomes a document through the same
`DocumentIssuer`/`PaymentRecorder` path an invoice does today, not a parallel one.

**V2 — livestock.** Livestock + Poultry (animal registry, health/vaccination, breeding, production
tracking). Separable from V1 — doesn't block or depend on the planting/harvest loop.

**V3 — cooperative & finance.** Cooperative & Farmer Groups, Microfinance & Credit. A different domain
shape entirely (membership, shares, loan ledgers, voting) — genuinely new ground, not an extension of
existing Sales/Accounting patterns.

**Later, unscheduled:** Fisheries, Beekeeping, Forestry, Greenhouse, Nursery & Seed, Soil & Fertility,
Irrigation, Machinery & Equipment, Utility Management, Fleet & Logistics, Supply Chain & Traceability,
Partner & NGO CRM, Project & Grant Management, Analytics & BI beyond what Sales/Reports already provide.

## 5. Documentation approach

Per-module specs are written **just before that module is built**, not all up front — this doc stays short
and honest about what's decided vs. still open, and a module's actual spec lives in its own doc (or PR
description) once real schema/API decisions are being made for it. `decisions.md` continues to record
platform-wide technical decisions (tenancy, ID strategy, PDF generation, etc.); this doc records
scope/roadmap decisions specific to the agri expansion.

## 6. Open questions

None blocking V1 as scoped. Revisit before V2: how animal records interact with the existing Asset
Management domain (an animal is not quite "stock" and not quite a "fixed asset" — needs its own model,
flagged here so V2 doesn't retrofit it onto the wrong one).
