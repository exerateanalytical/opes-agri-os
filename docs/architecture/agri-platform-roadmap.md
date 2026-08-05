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

**V1 shipped.** All five pieces landed as five independently-mergeable milestones in `App\Domain\Agri`:
item/stock batch tracking (`batch_number`/`expires_on` on `stock_movements`), Farms + Fields + Seasons,
Crop Management (`HarvestRecorder`/`CropCyclePlanner`), and Procurement (`PurchaseOrderIssuer`/
`PurchaseOrderReceiver`, reusing `DeliveryReceiver` rather than a second path into the ledger). Each
shipped with its own API surface, Livewire UI, module toggle, permission group and tests; the
cross-cutting ability-enforcement sweep (`AbilityEnforcementTest`) covers all of it structurally and at
runtime.

**V2 — livestock.** Livestock + Poultry (animal registry, health/vaccination, breeding, production
tracking). Separable from V1 — doesn't block or depend on the planting/harvest loop.

**V2 M1 shipped:** animal registry (species/breed/tag/DOB/status, an optional link to `Farm` — a
livestock-only business never has to switch Farms on), health & vaccination records, and production
tracking. `Animal` is a standalone model, not a `FixedAsset` subtype — it breeds, gets sick and dies,
none of which fits depreciation-schedule semantics (this was the roadmap's one open question for V2,
now resolved). Production records write to the Stock ledger through the same `StockLedger::receive()`
path a crop harvest uses (`App\Services\Livestock\ProductionRecorder`), when a product (milk, eggs,
wool) is named — recording without one is allowed for animals that don't produce stocked output.
Breeding/genealogy is not yet built — see below.

**V3 — cooperative & finance.** Cooperative & Farmer Groups, Microfinance & Credit. A different domain
shape entirely (membership, shares, loan ledgers, voting) — genuinely new ground, not an extension of
existing Sales/Accounting patterns.

**V3 M1 shipped:** member registry and share-capital/savings contributions. `CooperativeMember` reuses
`Contact` (a new `type=member`, following the same reuse Procurement did for suppliers) for the person
record rather than a parallel name/phone table, and wraps it with membership-specific fields
(membership number, joined-on, status) plus a cached `balance` recomputed from `MemberContribution` rows
the same way `Contact::recomputeBalance()` works for document balances. Deliberately **not** built yet
in this milestone: loans/credit (a real liability ledger, amortisation, arrears — its own domain shape,
not a contribution in reverse) and voting/governance (quorum rules, AGM records) — both need their own
design pass rather than being bolted onto the membership registry. A contribution does not post to the
accounting ledger, the same open question flagged for harvest/livestock-production value above — a
cooperative's contributions and any future loan disbursements are real cash movements, though, so this
one may need resolving *before* V3's next milestone rather than deferred to V3 the way the harvest
question was to V1.

**V3 M2 shipped:** Microfinance & Credit — loans against a `CooperativeMember`, flat interest (principal
× rate, applied once, no amortisation schedule), with `LoanDisburser` and `LoanRepaymentRecorder`
posting to the accounting ledger from day one. This resolves the accounting-posting question flagged
above the way that flag anticipated: unlike a harvest or a member's own contribution, a loan is real
cash the business does not get back automatically, so `RecordsBusinessEvents::recordLoanDisbursement()`/
`recordLoanRepayment()` debit/credit a new `member_loans` (274) role the moment cash actually moves — a
disbursement debits 274 and credits cash/bank, and each repayment splits proportionally between 274
(principal) and `interest_income` (771), capped at what the loan has left to recognise. Contributions
and harvests remain deliberately unposted; loans are the one V1–V3 event that involves credit rather
than the business's own inventory or a member's own money, which is the distinction that decided it.
Voting/governance (quorum rules, AGM records) remains unbuilt, and is not implied by anything shipped
here — it is a different domain shape again (membership rights, not cash), flagged for its own
milestone whenever cooperative governance becomes the priority over credit.

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

None blocked V1 or V2 M1 as shipped. Resolved: how animal records interact with the existing Asset
Management domain — `Animal` is its own model (§4, V2 M1), not a `FixedAsset` subtype.

Two valuation questions carried into V3, decided together rather than separately since they're the same
underlying question (how does something arrive on the books at zero cash cost):

- Harvest and livestock-production value recognition: a `CropCycle` harvest, a received `PurchaseOrder`,
  and an `Animal`'s production record all write stock movements today with no accounting posting — none
  of them owes anything to anyone, which the existing `RecordsBusinessEvents`/Ledger pattern has no event
  for yet. Debiting stock at cost and crediting a production account is deferred past V1/V2 by design (see
  the M3/M4/V2-M1 commit messages), to be decided once V3's real payables/receivables ledger work is
  underway.
- Whether an animal's `acquisition_cost` should ever flow into the accounting ledger (e.g. as a capital
  purchase) the way a `FixedAsset`'s does — deferred alongside the point above, since it's the same
  "something has a cost basis with nowhere to post it yet" shape.

**V2 M2 shipped:** breeding/genealogy (`sire_id`/`dam_id` self-referencing links on `Animal`, validated
against sex) and poultry-style batch tracking (`AnimalBatch` — a flock recorded as a running count via
`App\Services\Livestock\BatchCountAdjuster`, not as individually tagged animals). `AnimalBatch` is
deliberately its own model rather than `Animal` with a `quantity` column: an individually-tracked animal
and a counted flock have almost no shared lifecycle (one gets tagged, health records and a genealogy;
the other gets a running total adjusted up or down), so forcing them into one table would mean most
columns are null for one side or the other.

Not yet decided, flagged for a future V2 milestone: no accounting posting for a batch's mortality/loss
(same shape as the harvest-valuation question above); no per-adjustment audit trail for batch counts,
just the running total (promote to a ledger-style table if a business needs to reconstruct *when* losses
happened, not just how many); no species-specific vaccination schedules or reminders.
