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

**V3 M3 shipped:** governance — `CooperativeMeeting` (scheduled date, a `quorum_required` count, status)
with `MeetingAttendance` per member and `CooperativeMeeting::quorumMet()` reading straight off the
attendance count, and `CooperativeVote` (optionally tied to a meeting, or raised on its own) with
`VoteBallot` — one ballot per member per vote, enforced by a database unique constraint rather than
application code alone, and `CooperativeVote::tally()` grouping by choice. Nothing here touches the
accounting ledger: membership rights are not cash, unlike loans (V3 M2), so there was no posting
question to resolve this time. Deliberately out of this milestone: weighted voting (one member, one
vote here — share-weighted or patronage-weighted voting is a different rule a cooperative's bylaws would
have to specify), and quorum enforcement blocking a vote from opening (a vote can currently open and
close regardless of whether its associated meeting hit quorum — the flag is informational, not a gate,
until a real workflow asks for one). Shipped API-first, with a Livewire screen added in a follow-up
pass — `Meetings` and `Votes` tabs alongside `Members`/`Loans` on the existing Cooperative page, using
the same modal-form pattern (`RecordContribution`) established for lifecycle actions elsewhere in this
module.

**V4 — field operations and the rest of the module inventory.** Phased by how much new domain shape each
piece actually needs, not by the order §3 lists them in:

- **Phase 1 shipped: field operations.** `SoilTestRecord` (pH, N-P-K, organic matter, recommendations)
  and `IrrigationLog` (method, duration, volume), both keyed to `Field` the same way `AnimalHealthRecord`
  is keyed to `Animal` — a dated record with values and notes, no lifecycle of its own. Both reuse the
  `farms` module and permission group (`record-soil-test`/`record-irrigation` actions on `FieldPolicy`)
  rather than a new module toggle, since a soil test or irrigation event only makes sense once Farms is
  already on. Greenhouse Management was left out — a business needing environmental logs beyond what a
  soil test or irrigation record already covers gets that as its own milestone when the demand is real,
  not spec'd speculatively now.
- **Phase 2a shipped: Machinery & Equipment.** `AssetMaintenanceRecord` (service/repair/inspection, cost,
  optional next-due date) keyed to `FixedAsset`, the same dated-record shape as `AnimalHealthRecord` and
  `SoilTestRecord` — a tractor is a fixed asset with a maintenance log, not a new concept. Reuses the
  `assets` module and permission group (a new `record-maintenance` action). Note on precedent: `FixedAsset`
  itself predates the API-first discipline and has no REST surface (Livewire only) — the new maintenance
  records got one anyway (`/api/v1/fixed-assets/{fixedAsset}/maintenance-records`), consistent with "every
  module ships API-first" applying to what's newly built, not requiring a retrofit of what it extends.
  **Phase 2b shipped: Utility Management.** `UtilityAccount` (electricity/water/internet/gas/other,
  provider, account number, optional `Farm` link) with `UtilityReading` (meter reading, consumption,
  cost) as its own new module — unlike Phase 2a, nothing existing already modeled a metered account or
  a consumption log, so this got its own `utilities` module and permission group rather than reusing
  another's. `consumption` is entered directly per reading rather than derived from consecutive meter
  readings, since a meter can be replaced or reset and a derived running total would then be wrong; the
  Expenses screen already records what a utility bill cost, this module records what was actually used.
  Full API + Livewire UI shipped together this time (no repeat of Phase 2a's assets legacy-precedent
  situation, since `utilities` is new end to end).
- **Phase 3a shipped: Fleet & Logistics.** `FleetTrip` (driver, purpose, start/end dates, start/end
  odometer, fuel cost) keyed to `FixedAsset`, the same shape as `AssetMaintenanceRecord` — a vehicle is a
  fixed asset with a trip log, same reasoning that decided Phase 2a. Distance is computed from the two
  odometer readings rather than stored, so it can never drift from what was actually entered. Reuses the
  `assets` module and permission group (new `record-trip` action).

  **Phase 3b shipped: Supply Chain & Traceability.** No new schema at all — `stock_movements.batch_number`
  has carried a batch's identity since Agri M1, and every movement against it (a delivery or harvest in,
  a sale or void out) was always there to read back; this phase is a read-only surface over data the
  platform already writes, not a new capability. `ItemController::traceBatch()` returns a batch's full
  history in order; the `Trace a batch` screen (`/products/trace`) reads it back on the Products module.
  Reuses the `products` permission group — no new one needed for a query.
- **Phase 4: Partner & NGO CRM, Project & Grant Management.** New domain shape (grant milestones,
  disbursement conditions) — closer to Cooperative's shape than to Sales', worth designing alongside
  whichever comes first.

  **Phase 4 shipped.** `Partner` — an NGO, donor, government or cooperative partner — is a dedicated
  model, deliberately not a `Contact`: a partner funds or co-runs work, which is a different relationship
  than a customer or a supplier, the same reasoning that kept `Animal` off `FixedAsset` in V2 M1.
  `PartnerInteraction` is the dated-record pattern again (a contact log, no lifecycle of its own,
  cascade-deleted with its partner). `GrantProject` is the funded work itself, with an optional
  `partner_id` — a business can track a self-funded project without ever switching Partner CRM on, the
  same reasoning `Animal.farm_id` is optional. `GrantTransaction` covers both directions of real grant
  cash: a `receipt` (the partner's money arriving) and an `expenditure` (the project spending it) — both
  post to the accounting ledger the moment they're recorded, resolved the same way loans were in V3 M2:
  a grant is real cash the business did not earn from a sale, so it is not deferred the way harvest and
  contribution value recognition are. `RecordsBusinessEvents::recordGrantReceipt()` debits cash/bank and
  credits a new `grant_income` role (SYSCOHADA 741, *Subventions d'exploitation* — the plan's own account
  for an operating subsidy or grant received, a closer fit than folding it into ordinary sales income);
  `recordGrantExpenditure()` is the mirror, debiting a new `project_expenses` role (658, *Charges
  diverses* — the plan's catch-all for an operating cost with no better-fitting class-6 account, which a
  grant-funded project's spend is). `GrantProject` caches `received_amount`/`spent_amount` running totals
  the same way `Loan` caches `balance`, and `GrantTransactionRecorder` refuses an expenditure larger than
  what the grant has left, the same guard `LoanRepaymentRecorder` applies to an overpayment.

  Permission groups are `Partner Crm` (slug `partner-crm`) and `Grants` — not `Partners`, which already
  names the unrelated secretariat client-book programme; a donor/NGO/government partner here is never
  that meaning of the word. Both new modules (`partner_crm`, `grants`) default on, ungated by plan, same
  as every V4 module so far. Deliberately not built this milestone: grant milestones/disbursement
  conditions (a funder's tranche schedule tied to deliverables) and budget lines within a project (right
  now a `GrantProject` tracks one total against one running spend, not a per-category budget) — both are
  real shape a heavier grants-management tool would need, but nothing shipped so far asked for them, and
  speculative schema is exactly what this roadmap avoids building ahead of demand.
- **Phase 5 shipped: Analytics & Business Intelligence** — a single cross-module dashboard,
  `App\Domain\Analytics`, over data every earlier V1–V4 module already writes. No new schema at all, the
  same shape Phase 3b (Traceability) took: `AnalyticsSummaryService` reads `CropCycle`, `Animal`/
  `AnimalProductionRecord`, `PurchaseOrder`, `AssetMaintenanceRecord`, `Loan`/`MemberContribution`, and
  `GrantProject`/`GrantTransaction` and aggregates each into its own section — crop yield (planned vs
  actual), livestock production volume, procurement spend (received vs open order value), asset
  maintenance cost, loan portfolio (outstanding vs disbursed) plus member contributions, and grant fund
  utilisation (received vs spent). One period-over-period view ships: input cost vs harvest volume,
  month by month, over a fixed six-month lookback — not a date-range picker, because nothing shipped so
  far asked for one and a fixed window is what "operations picture" actually needs day to day.

  Each section checks its own source module independently and renders `enabled: false` rather than
  erroring when that module is off for the company — a business with Crops switched off gets an empty
  crops card, not a broken page, the same "degrade gracefully" contract every module toggle promises.
  This is also why `analytics` itself has no `requires`: its one screen has no model-backed detail page
  to gate (page-level, like Reports), so there is nothing for a disabled source module to make
  unreachable.

  Permissions are a new `Analytics` group with `analytics.view` and `analytics.export` — deliberately not
  folded into `Reports`, and deliberately not per-source-module (`crops.view` etc.): this dashboard reads
  across modules a role might not otherwise have `view` on individually (a Sales Officer has no
  `farms.view`, `livestock.view` or `grants.view`, but does get `Analytics => ['view']`), so gating it
  behind six other permissions would just mean most roles see nothing on the page they were granted
  access to. `export` joined the group in the follow-up milestone below, the same view/export split
  `Reports` already used — most roles that had `Analytics => ['view']` picked up `export` alongside it;
  the Sales Officer role kept `view` only, mirroring its `Reports => ['view']`-without-export choice, since
  reading the operations picture and walking out with it as a file are still different rights. No
  `create`/`update`/`delete` actions exist in the group — the dashboard still writes nothing.

  API-first as usual: `GET /api/v1/analytics/dashboard` behind `abilities:analytics.view`, returning the
  whole aggregation in one call rather than one endpoint per section. Livewire UI is
  `App\Livewire\Analytics\Dashboard`, one screen, reusing the existing `x-ui.panel`/`x-ui.bar-chart`
  components Reports already established rather than introducing a charting dependency.

  **Follow-up milestone: the four scope cuts above, built.** `AnalyticsSummaryService`'s `summary()` and
  every section method now take optional `from`/`to` (`CarbonImmutable`) plus `groupBy` params — omitted,
  every method still returns exactly what it always did (the fixed six-month trend window, all-time
  company totals), so the original default is the same default. Passed, the trend row re-buckets over the
  custom range, and crops/livestock/procurement/assets/cooperative each filter their underlying query to
  it (grant *project* totals stay cumulative to-date — a project's committed/received/spent figures don't
  have a meaningful "in this date range" slice at the project level, only its transactions do). The API
  takes `?from=&to=` as validated query params (`date`, `after_or_equal:from`) on
  `GET /api/v1/analytics/dashboard`; a single bound with no partner is treated as "no range given" rather
  than building a silently open-ended query. The Livewire `Dashboard` exposes the same as `#[Url]`-backed
  date inputs, following the `type="date"` + `wire:model.live` convention Assets/Reports already use, with
  a "reset to default (6mo)" action.

  Breakdown: `crops_group_by=farm` groups the crop section by `Farm` (via `Field belongsTo Farm`), adding
  a `by_farm` array of per-farm cycle counts and planned/actual yield alongside the unchanged company
  totals. `cooperative_group_by=member` does the same for loans and contributions, grouped by
  `CooperativeMember`, added as `by_member`. Both are additive — the plain totals a caller already parses
  are still there — and both default to off, so `summary()` with no groupBy args is unchanged output.

  Drill-down is a "View details" toggle per card (`Dashboard::toggleDrilldown()`), expanding into the
  underlying records for that section scoped to the active date range — `CropCycle`, `AnimalProductionRecord`,
  `PurchaseOrder`, `AssetMaintenanceRecord`, `Loan`, `GrantTransaction`. Built as a minimal inline list in
  `Dashboard` itself rather than linking out to each module's own list view: none of Crops/Cooperative/etc.
  `Index` accepts an arbitrary date range today, and Analytics reads company-wide across fields (member
  name via `Loan->member->contact`, farm via `CropCycle->field->farm`) those screens don't surface either,
  so reusing them would mean extending four unrelated components' filter surfaces for one caller. Capped
  at 200 rows — a drill-down is a "what's behind this number" glance, not a paginated export.

  Export is CSV, one combined file covering every enabled section (plus the `by_farm`/`by_member`
  breakdown rows when a groupBy is active) rather than PDF or one file per section — following the
  `Reports::exportCsv()` `streamDownload()`/`fputcsv` pattern exactly, no new dependency. It respects
  whatever date-range and breakdown filters are currently on screen. **CSV only, no PDF** — a plain
  remaining scope cut stated here rather than a regression: nothing shipped so far has asked for a
  formatted PDF of this data, and CSV is what every other export surface in the platform (`Reports`) already
  standardised on.

  This closes V4 — every phase in §4 has now shipped, including the four cuts Phase 5 originally deferred.

**Five inventory items are already usable today and were deliberately NOT rebuilt as separate modules,**
because doing so would duplicate schema that already exists:

- **Poultry Management** — an `Animal` with `species` set to whatever poultry breed, or an `AnimalBatch`
  for a flock counted rather than individually tagged (the batch tracking V2 M2 built was written with
  poultry as the motivating case).
- **Fisheries & Aquaculture** — an `AnimalBatch` per pond/tank, `species` set accordingly; a fish is
  counted the same way a flock is, not individually tagged.
- **Beekeeping** — an `AnimalBatch` per hive; production (honey) records through the same
  `ProductionRecorder` a dairy animal's milk does.
- **Forestry & Agroforestry** — a `CropCycle` with a long `planned_harvest_date`; nothing about the
  model assumes an annual crop.
- **Nursery & Seed Management** — a `CropCycle` in `planned`/`planted`/`growing` status ahead of
  transplant, or its own short-lived cycle if a business tracks nursery stock as a distinct saleable
  item (seedlings are just another `Item` of `type=product`).

A business using OPES AGRI OS for any of these five today sets the right `species`/crop name and gets
correct behaviour — no code change needed, only choosing sensible values on the existing forms. If a
genuine gap surfaces later (poultry-specific batch mortality causes, apiary inspection checklists,
silvicultural rotation schedules), that gap gets its own milestone then, not speculative schema now.

**2026-08-05 — Hardening: Core Sales/Invoicing audit fixes.** A read-only audit of the Core Sales/Invoicing
module found three confirmed defects, fixed here:

- `DocumentIssuer::issue()` checked the draft status against a possibly-stale in-memory model *before*
  its transaction opened, and never re-read the row under a lock inside it — two concurrent requests
  issuing the same draft could both pass the check and both issue. It now re-fetches with
  `lockForUpdate()` and re-checks the status inside the transaction, the same pattern
  `PaymentRecorder::record()` already used for the identical race.
- `DocumentConverter::void()` had the same shape of bug against `amount_paid`: a payment recorded between
  the check and the save was invisible to it, so a paid invoice could still be voided. Same fix, same
  pattern.
- Sales lines never carried `item_id` through any production path — `StoreDocumentRequest`,
  `CreateDraftDocumentAction::normalisedLines()`, and the `Documents/Create` composer all dropped it, so
  `StockLedger::move()` (which only acts on lines with an `item`) never fired on a real invoice. `item_id`
  is now an optional, company-scoped validated field threaded through the store request, the draft action,
  and the web composer (an item picker per line, filling the description on selection); a document issued
  through the real API/`DocumentIssuer` path with a tracked-item line now writes a `StockMovement` and
  decrements stock. The `document_lines.item_id` column already existed unused — no migration needed.
- Also fixed as a cheap adjacent bug: `DocumentController::update`'s `due_date` rule compared only against
  a same-request `issue_date`, so a PATCH sending `due_date` alone could be rejected (or wrongly accepted)
  against nothing rather than the document's stored `issue_date`. It now falls back to the stored value
  when the request doesn't include one.
- Left alone, on purpose: `StockLedger::move()`/`reverseSale()` still write a negative-stock movement
  without checking availability first. No precedent search turned up an existing business rule either way
  for sales (unlike stock *transfers*, which do refuse a shortage — see `StockLocationsTest`), so adding
  one here would be inventing policy the audit didn't ask for; flagged for whoever scopes that decision.

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

- **Resolved (V4 follow-up):** Harvest and livestock-production value recognition. `HarvestRecorder` and
  `App\Services\Livestock\ProductionRecorder` now post to the accounting ledger through the new
  `RecordsBusinessEvents::recordStockValuation()` whenever a caller supplies a unit cost — debiting `stock`
  (31, the same account a purchase or a sale already moves) and crediting the new `production_stored` role
  (736, *Variation des stocks de biens produits* — the plan's own line for production the business grew
  itself becoming stock, not a sale to a customer). A harvest or production record recorded with no known
  cost stays exactly as before: unposted, because there is nothing to value it at. This did not need V3's
  payables/receivables work first — the entry the plan actually calls for (736, not a revenue account)
  turned out not to depend on it.
- Whether an animal's `acquisition_cost` should ever flow into the accounting ledger (e.g. as a capital
  purchase) the way a `FixedAsset`'s does — still open. A different question from the one above: this is
  about capitalising the animal itself at purchase, not valuing what it later produces.

**V2 M2 shipped:** breeding/genealogy (`sire_id`/`dam_id` self-referencing links on `Animal`, validated
against sex) and poultry-style batch tracking (`AnimalBatch` — a flock recorded as a running count via
`App\Services\Livestock\BatchCountAdjuster`, not as individually tagged animals). `AnimalBatch` is
deliberately its own model rather than `Animal` with a `quantity` column: an individually-tracked animal
and a counted flock have almost no shared lifecycle (one gets tagged, health records and a genealogy;
the other gets a running total adjusted up or down), so forcing them into one table would mean most
columns are null for one side or the other.

**Resolved (V4 follow-up):** a batch's mortality/loss now posts to the accounting ledger, and every
adjustment — not just the running total — leaves its own audit-trail row. `AnimalBatch` gained a nullable
`unit_cost` (what one head in the batch is carried at); `BatchCountAdjuster` writes an
`AnimalBatchAdjustment` row for every call (positive or negative — hatching and purchases get a trail
too, not only losses) recording the change, the resulting count, and an optional reason, addressing "no
per-adjustment audit trail for batch counts, just the running total" directly — `current_count` on
`AnimalBatch` stays the fast-read figure, `GET /api/v1/animal-batches/{id}/adjustments` is where a
business reconstructs *when* and *why* it moved. A negative adjustment with a known `unit_cost` also posts
through `RecordsBusinessEvents::recordBatchLoss()`: the loss debits the new `livestock_loss` role (818)
and credits `stock` (31) — a counted flock is stock the business holds, so a fall in the count is a fall in
that same account, the harvest/production valuation above credits into. A batch with no `unit_cost` set
adjusts exactly as before, unposted. Still open: species-specific vaccination schedules or reminders —
untouched by this pass.

Cooperative governance (V3 M3) also carried two deliberately-out items, both **resolved in this V4
follow-up**:

- **Weighted voting.** `CooperativeMember.vote_weight` (default 1) is a bylaws-set number a business edits
  per member; `CooperativeVote.weighted` decides per-resolution whether `CooperativeVote::tally()` reads
  member weight or a plain headcount. A cooperative can run ordinary one-member-one-vote for most business
  and switch a specific capital resolution to weighted without every vote being forced the same way — the
  flag lives on the vote, not on the cooperative.
- **Quorum as a hard gate.** `CooperativeVoteController::store()` now refuses to open a vote tied to a
  meeting whose `status` is `held` and whose `quorum_required` the attendance count did not reach — the
  gate V3 M3 explicitly left informational. A meeting still only `scheduled` (attendance isn't in yet) is
  not held to quorum, and a vote raised with no meeting at all — `CooperativeMeeting::quorumMet()` has
  nothing to check in that case — is unaffected, exactly as before.
