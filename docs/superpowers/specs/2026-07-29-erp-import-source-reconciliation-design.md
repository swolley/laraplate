# ERP external-source reconciliation

**Status:** Draft for written review

**Date:** 2026-07-29

**Destination module:** ERP

**Source adapters:** Legacy Symfony SQL, SPLID export, Tricount supported export

**Related plan:** `../plans/2026-07-22-erp-external-source-importers.md`

## Decision summary

The studio's source systems do not form three independent or strictly consecutive
histories:

- the legacy Symfony application was the studio's consolidated management record;
- SPLID was used in parallel for shared expenses with the studio partner;
- SPLID expenses, allocations, payments, and reimbursements were later copied
  manually into Symfony with precise dates;
- Tricount followed SPLID for shared-expense management, but its exact cutover and
  any overlap still require source evidence.

During the Symfony/SPLID overlap, Symfony is authoritative when both sources
represent the same economic event. SPLID remains authoritative for an event that
is demonstrably absent from Symfony. The import process must reconcile the two
sources before persistent SPLID writes rather than treating source-local
idempotency as protection against cross-source duplicates.

The source-specific reconciliation implementation, fixtures, candidate matching,
and approved correspondence map belong to the proprietary
`laraplate-importers` repository. Laraplate remains generic: ERP exposes
destination services and source-neutral origin registration, with no Symfony,
SPLID, or Tricount hook in the backend.

## Authority rules

| Situation | ERP result |
|---|---|
| A Symfony record has no SPLID counterpart | Import the Symfony record |
| An approved pair represents the same event | Create or retain one ERP aggregate using Symfony values and attach both origins |
| A SPLID record is confirmed absent from Symfony | Import the SPLID record |
| A candidate pair differs but is confirmed as the same event | Use Symfony values; retain the SPLID values in reconciliation evidence |
| A candidate is ambiguous or not approved | Do not suppress or merge it; reject it from persistent SPLID import pending review |
| Two records merely look similar | Keep them distinct unless an explicit correspondence is approved |

Authority is a declared policy, not an accidental consequence of importer
execution order. It applies independently to expenses and reimbursements so that
opposite transaction kinds cannot cancel or match each other accidentally.

No precedence rule is yet defined between Symfony and Tricount or between SPLID
and Tricount. The Tricount audit must establish its real cutover, overlap, and
copying history before extending this policy.

## Source timeline manifest

Before reconciliation, the importer package must define a versioned source
timeline manifest containing:

- a stable source-instance key for each Symfony database and SPLID/Tricount
  group;
- the known or approved date range for each source;
- the functional scope of each source during that range;
- overlap windows;
- timezone, currency, and participant-map references;
- the cutover evidence available for Tricount;
- explicit gaps or uncertain boundaries.

Date ranges narrow the candidate search but never prove identity by themselves.
The manifest must not contain live credentials or personal data unsuitable for
version control.

## Reconciliation artifact

`laraplate-importers` generates a versioned correspondence map from anonymized,
approved fixtures or from an operator-controlled audit run. Its logical entries
contain:

- SPLID source-instance key;
- stable SPLID expense or reimbursement identity, or the approved deterministic
  source fingerprint when no native identity exists;
- Symfony source-instance key;
- stable Symfony movement identity;
- transaction kind;
- decision status: `paired`, `distinct`, or `unresolved`;
- comparison evidence and field-level differences;
- approval provenance.

Candidate generation may classify a unique, fully concordant pair as an exact
candidate, but it does not assign the final decision. `paired` means that both
records represent one approved economic event; `distinct` means that the SPLID
record remains independently importable; `unresolved` blocks that SPLID record
from persistent import. Approximate matching may assist review but may not merge,
discard, or suppress a record automatically.

The correspondence artifact lives with the proprietary importer implementation
or in operator-controlled storage. The AGPL repository documents only the
source-neutral contract and destination behavior.

## Candidate matching

Candidate comparison uses normalized values while preserving the originals for
diagnostics. At minimum it compares:

- transaction kind;
- effective date;
- amount and ISO currency;
- payer contributions;
- participant identities;
- owed allocations;
- reimbursements;
- description and notes where available.

A proposed exact match must have one unique counterpart and full agreement on
the accounting fields. Description normalization alone must never establish
identity. When native source IDs are unavailable, deterministic fingerprints
remain scoped to their source instance; they are not cross-source identities.

## Import flow

1. Audit and freeze versioned, anonymized Symfony and SPLID fixtures.
2. Define the timeline manifest and explicit participant, company, pool,
   account, category, tax, and currency mappings.
3. Read both sources without destination writes and generate reconciliation
   candidates plus field-level differences.
4. Review ambiguous and conflicting candidates and freeze the approved
   correspondence map.
5. Import Symfony through ERP destination services and register its stable
   origins.
6. Import SPLID using the approved map:
   - attach the SPLID origin to the existing ERP aggregate for an approved pair;
   - create a new aggregate for a confirmed unmatched SPLID event;
   - reject any unresolved candidate with actionable evidence.
7. Reconcile source counts, amounts, allocations, reimbursements, and participant
   balances without double-counting paired events.
8. Rerun unchanged inputs and prove that no duplicate aggregate or origin is
   created.

The implementation may perform candidate generation in a dedicated audit
command or as a non-persistent importer-package phase. It must not require a
source-specific command option, table, service, or callback in Laraplate.

## Destination and origin behavior

One ERP aggregate may have multiple `core_record_origins`, for example one
legacy Symfony identity and one SPLID identity. Origin lookup and registration
must remain source-neutral.

Attaching a second approved origin must not:

- recreate or repost the movement;
- alter inventory, journal, settlement, or document state through raw SQL;
- overwrite a posted/accounted aggregate;
- hide a field-level conflict;
- create a Core user implicitly.

If the authoritative Symfony aggregate is already posted or accounted, an
approved SPLID origin may be attached only through a safe source-neutral origin
workflow. Any requested value change follows the ERP correction policy instead
of mutating protected state.

## Failure and review evidence

Every unresolved item must identify:

- both source identities when a candidate exists;
- the transaction kind and source locations;
- matching fields;
- differing fields with original values;
- whether the item was withheld to prevent a possible duplicate;
- the operator action required to approve, reject, or split the candidate.

Rejected and unresolved items are part of reconciliation totals. They must not
disappear from the report merely because no ERP aggregate was written.

## Verification

Focused fixtures and tests must cover:

- an exact, unique Symfony/SPLID expense pair;
- an exact reimbursement pair;
- a SPLID expense absent from Symfony;
- a Symfony-only movement;
- equal date and amount with two possible Symfony candidates;
- differing allocations for an otherwise likely pair;
- differing descriptions with equal accounting fields;
- an expense that must not match a reimbursement;
- an approved pair targeting an already posted Symfony aggregate;
- interrupted execution followed by restart;
- unchanged persistent rerun with zero duplicate aggregates;
- complete control totals before and after cross-source deduplication.

Control totals must distinguish raw source rows, approved pairs, authoritative
ERP events, confirmed unmatched events, rejected candidates, amounts by
currency, paid and owed allocations, reimbursements, and final participant
balances.

## Rejected alternatives

### Treat the sources as non-overlapping time periods

Rejected because Symfony and SPLID were used in parallel and the same detailed
events were copied manually.

### Import both sources independently

Rejected because source-local idempotency cannot detect the same event under two
different source identities.

### Make SPLID authoritative during the overlap

Rejected because the studio treated Symfony as the later consolidated record.

### Automatically merge approximate matches

Rejected because repeated amounts, dates, participants, and descriptions can
identify distinct real expenses. Approximate comparison is review assistance,
not deletion authority.

### Put source-specific reconciliation in ERP

Rejected because it would couple the generic AGPL backend to proprietary source
formats and violate the importer ownership boundary.

## Acceptance criteria

- Symfony is explicitly authoritative for an approved Symfony/SPLID pair.
- SPLID-only events remain importable after reconciliation.
- Persistent SPLID import requires an approved correspondence decision for every
  possible overlap candidate.
- A paired event creates one ERP aggregate with both stable origins.
- Ambiguous and conflicting candidates cannot be silently merged or discarded.
- Reconciliation logic and artifacts remain outside the AGPL backend.
- Protected ERP mutations use ERP services and respect posted-record correction
  rules.
- Raw, paired, unmatched, rejected, and destination control totals reconcile.
- The Tricount precedence policy remains gated on real timeline and export
  evidence.
