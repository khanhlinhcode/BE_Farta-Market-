# V10 R6 Fresh Independent Leakage, Gold, Entity & Schema Audit

Audit time (UTC): 2026-09-26T17:51:28Z  
Decision: **AUDIT PASS — FINAL V10 FREEZE AUTHORIZED**

## Executive summary

R6 passes every required gate. The three frozen R6 inputs match their expected
SHA-256 digests; the dataset parses as `farta-v10.3.0`; and independently
recomputed counts are 292 primary cases, 39 scenarios, 78 scenario turns, 27
multi-intent cases, and 56 annotated branches.

All 370 R6 utterance-bearing records were compared against 2,593
utterance-bearing records extracted from every available V0-V9 fixture. There
are no raw, normalized, or accent-folded exact matches and no manually
confirmed structural leakage. The R5-to-R6 comparison has zero utterance
changes and exactly four semantic gold changes, all to the required
`account_target` annotations.

The independently derived account-target population is 12 records: 11 primary
records and one branch record. Missing, spurious, and incorrect targets are all
zero. Entity preservation, factual support, authorization gold, follow-up
resolution, multi-intent branch isolation, source/version authority,
distribution, generated-data quality, and structural-schema checks all pass.

## Auditor independence scope

The audit used only:

- the frozen R6 dataset and freeze manifest;
- the R6 authoring report as an opaque file for byte count and SHA-256 only
  (its content and author reasoning were not consulted);
- the frozen R5 dataset and R5 freeze lineage solely for the required
  R5-to-R6 comparison;
- V0-V9 fixture utterances solely for leakage comparison;
- Product, Category, SiteSetting, the published knowledge registry/content,
  the business authorization contract encoded by the allowed authority, and
  the dataset schema contract.

No R1-R5 audit report, remediation brief, Phase 12 report, candidate
prediction, historical failure analysis, or runtime/router implementation was
read. The candidate was not run or scored. No R6 data, gold, schema, runtime,
or chatbot behavior was modified. No commit, push, or deployment was
performed.

## R6 freeze verification

| Artifact | Expected SHA-256 | Recomputed SHA-256 | Result |
|---|---|---|---|
| `v10-r6-candidate-dataset.json` | `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0` | `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0` | PASS |
| `v10-r6-authoring-report.md` | `d5f08489d214b332420754296622dedca0ef8dd132540526a15388cbc2b8b6cf` | `d5f08489d214b332420754296622dedca0ef8dd132540526a15388cbc2b8b6cf` | PASS |
| `v10-r6-freeze-manifest.json` | `8e865be370e11f94345b265c80e85a2e58d49f97e4d943c57a52d8c6e23a19b9` | `8e865be370e11f94345b265c80e85a2e58d49f97e4d943c57a52d8c6e23a19b9` | PASS |

The R6 manifest points to R5 dataset SHA-256
`1c2b671a3ec303fbf2071763665e2471322603b31c3eff7015565a97131603ff`;
the referenced R5 file recomputes to that exact digest. JSON parsing, manifest
lineage, schema, and all five headline counts pass.

## Historical coverage

All available historical fixture families were loaded, including generated
fixture expressions through the project autoloader. Extraction included query
values, follow-up initial/query values, missing-evidence string records, and
utterance keys in multi-intent/grounding maps.

| Historical version | Fixture | Utterance-bearing records |
|---|---|---:|
| V0 | `tests/Fixtures/chat_evaluation.php` | 187 |
| V1 | `tests/Fixtures/chat_blind_holdout.php` | 130 |
| V2 | `tests/Fixtures/chat_blind_v2.php` | 144 |
| V3 | `tests/Fixtures/chat_blind_v3.php` | 159 |
| V4 | `tests/Fixtures/chat_blind_v4.php` | 216 |
| V5 | `tests/Fixtures/chat_blind_v5.php` | 259 |
| V6 | `tests/Fixtures/chat_blind_v6.php` | 284 |
| V7 | `tests/Fixtures/chat_blind_v7.php` | 514 |
| V8 | `tests/Fixtures/chat_blind_v8.php` | 352 |
| V9 | `tests/Fixtures/chat_blind_v9.php` | 348 |
| **Total** |  | **2,593** |

R6 coverage was 292 primary utterances plus 78 scenario-turn utterances, for
370 independently checked utterance-bearing records.

## Leakage audit

| Comparison | Result |
|---|---:|
| Raw exact matches | 0 |
| Unicode/lowercase/punctuation/whitespace normalized exact matches | 0 |
| Accent-folded normalized exact matches | 0 |
| Token-Jaccard candidates at or above 0.50 | 141 pairs across 54 R6 records |
| Delexicalized structural exact candidates | 29 pairs across 11 R6 records |
| **TRUE_LEAKAGE** | **0** |

Every Jaccard and structural candidate was manually reviewed. The candidates
are short, ordinary request forms such as asking whether a product is in
stock, asking a price, requesting a catalog, asking a shipping fee, or making
a concise privilege request. The maximum token Jaccard was 0.80. None contains
a distinctive copied sequence or a historical entity/template combination
that establishes leakage. Natural short canonical requests were not treated
as leakage merely for being short.

## Zero-utterance-change check

The R5 and R6 utterance key sets are identical. All 292 primary utterances and
all 78 scenario-turn utterances are byte-identical: **0 changes**.

The schema version, schema contract, source registry, and evaluation rules are
also identical. Recursive comparison found exactly four semantic gold changes:

- `V10-ST-0160/entity_gold/account_target`: `null` -> `"tài khoản tôi"`
- `V10-ST-0179/entity_gold/account_target`: `null` -> `"another account"`
- `V10-ST-0260/entity_gold/account_target`: `null` -> `"toi"`
- `V10-ST-0289/bypass/entity_gold/account_target`: `null` -> `"me"`

All other differences are R6 identity/timestamp/declaration/change-log
metadata. Result: 4 gold corrections, 0 utterance changes, 0 schema changes.

## Schema audit

The schema remains `farta-v10.3.0`. All 426 `entity_gold` objects (292 primary,
56 branch, 78 scenario-turn) contain the same nine slots:
`product_raw_mention`, `canonical_product`, `quantity`, `unit`,
`order_reference`, `ordinal_reference`, `context_reference`, `account_target`,
and `requested_mutation_value`.

Plural product lists occur only where a record semantically represents several
products; scalar product values occur for singular records. This is a
compatible use of the same slots. `account_target` is consistently
`string|null`; `requested_mutation_value` is consistently `number|string|null`.
No incompatible entity object shape was found.

## Account_target coverage

Independent derivation produced the same total as observation:

| Metric | Count |
|---|---:|
| Expected explicit account/user targets | 12 |
| Observed non-null targets | 12 |
| Primary | 11 |
| Branch | 1 |
| Missing targets | 0 |
| Spurious targets | 0 |
| Incorrect targets | 0 |
| Targets misfiled as `order_reference` | 0 |

The population consists of four self targets, seven other-user/account
targets, and one explicit email identifier. Possessive wording that merely
establishes ownership of an order is represented by `order_reference` plus
owner preconditions; it is not double-annotated as an account target. Direct
self targets of role/auth operations are annotated.

Audited non-null records: `V10-ST-0155`, `V10-ST-0156`, `V10-ST-0157`,
`V10-ST-0158`, `V10-ST-0160`, `V10-ST-0168`, `V10-ST-0179`,
`V10-ST-0259`, `V10-ST-0260`, `V10-ST-0263`, `V10-ST-0264`, and
`V10-ST-0289/bypass`.

## Four R6 corrections

| Record | Explicit surface | R6 target | Result |
|---|---|---|---|
| `V10-ST-0160` | `tài khoản tôi` | `tài khoản tôi` | PASS |
| `V10-ST-0179` | `another account` | `another account` | PASS |
| `V10-ST-0260` | `toi` | `toi` | PASS |
| `V10-ST-0289/bypass` | `for me` | `me` | PASS |

The `V10-ST-0289` safe SePay-information sibling remains unchanged and the
target appears only in the prohibited bypass branch.

## Entity preservation

All user-expressed product, account target, quantity, mutation value, order
reference, ordinal, and context reference annotations were checked in primary,
branch, and scenario-turn records, including terminal states `ANSWER`,
`DENIED`, `NO_EVIDENCE`, `CLARIFICATION_REQUIRED`, and `AUTH_REQUIRED`.

Across all 426 entity objects, non-null slot counts are: 242 product raw
mentions, 226 canonical products, 66 quantities, 66 units, 34 order
references, 12 ordinal references, 45 context references, 12 account targets,
and 17 requested mutation values. Denial or evidence failure does not erase
the user's expressed entities. Explicit `ORD-*` references and all non-null
account targets match their utterance surface; no invented identity was found.

## Quantity vs mutation value

All 66 quantity annotations represent purchase, cart, stock-comparison, or
shipping-calculation quantities. All 17 `requested_mutation_value`
annotations represent mutation targets such as `paid`, `cancelled`,
`delivered`, `admin`, `+100`, `0`, `500`, or `999`.

No entity object has both fields non-null. Inventory mutations use
`requested_mutation_value`; purchase/cart quantities use `quantity`. Result:
PASS.

## Claim support

All 201 primary/branch `ANSWER` records and all 64 scenario-turn `ANSWER`
records were reviewed against allowed authority; non-factual general-chat
answers require no external fact. The 23 primary/branch `NO_EVIDENCE` records
were checked against the full published knowledge content and structured
authority.

Product existence alone, category membership, or generic descriptions were
not accepted as support for a requested use/policy claim. No unsupported
factual `ANSWER` and no false `NO_EVIDENCE` were found.

The stable cases pass independently:

- `V10-ST-0030`: Xoài keo use/suitability is absent -> `NO_EVIDENCE`.
- `V10-ST-0272/detail`: the same unsupported claim remains isolated from the
  valid cart branch -> `NO_EVIDENCE`.
- `V10-ST-0142`: Nho tím quantity 20 equals inventory 20 and remains a
  suggestion requiring UI confirmation -> `SUGGESTED_ACTION`.

## Product/category authority

The live structured authority contains 11 active products and five active
categories. Name, active catalog membership, price, inventory, and category
exactly match the R6 source snapshot. All 348 primary/branch grounding records
were mechanically checked for referenced source IDs and product price,
inventory, category, search-result membership, and deterministic availability
facts; mismatches: 0.

## Shipping authority

SiteSetting independently confirms:

- current shipping fee: 20,000 VND;
- free-shipping threshold: 200,000 VND;
- a subtotal at or above 200,000 VND receives free shipping.

All 23 shipping calculations (16 primary, five multi-intent branches, two
follow-up scenarios) were recalculated from current unit price, quantity,
threshold, and fee. Arithmetic mismatches: 0, including the exact-threshold
case.

## Auth gold

The five actor states used by the dataset are internally consistent:
`anonymous`, `anonymous_or_authenticated`, `authenticated_verified_customer`,
`authenticated_owner`, and `authenticated_non_owner`.

All five primary `AUTH_REQUIRED` records require login before owned order or
payment-status access. Owned authenticated reads are allowed; six primary
cross-account reads are denied. The symbolic owner/non-owner/unknown-order
preconditions agree with their order references and terminals.

## Privileged gold

All 16 primary privileged-mutation cases terminate `DENIED`. Prohibited
payment, inventory, order-state, role/account, auth-bypass, other-user-data,
and secret/prompt-disclosure operations are never marked as executable.

All six denied multi-intent branches remain denied while safe siblings retain
their own allowed terminal. Security decisions and entity understanding are
separate: products, order references, account targets, and mutation values
remain annotated even when execution is denied.

## Follow-up gold

All 39 scenarios are self-contained two-turn sessions. The distribution is:
five singular references, four plural references, four first ordinals, four
second ordinals, four last ordinals, five deictic references, five ellipses,
four expired contexts, and four ambiguous references.

Active singular/deictic/ellipsis references resolve to the stored focus;
plural references retain all ordered products; ordinal references resolve to
the correct indexed product. All four expired and all four ambiguous cases
request clarification and do not invent a canonical product. Scenario
intent/terminal, final entity, evidence domain, source, and context state have
zero consistency errors.

## Multi-intent gold

All 27 multi-intent cases and 56 branches were reviewed. There are 25
two-branch cases and two three-branch cases. Every branch has the required
intent, operation, entity object, handler, evidence domain, source list,
terminal, business outcome, security expectation, minimum facts, and
grounding object.

Parent source lists equal the ordered union of branch sources. Parent terminal
states correctly distinguish all-branches-handled from mixed terminal cases.
No branch is dropped. `V10-ST-0289/bypass` preserves `account_target="me"`
without altering its safe sibling.

## Evidence domain

Evidence domains match the requested semantics: product catalog, price,
inventory, shipping settings/calculation, store contact, account, ordering,
orders, payment, owned-order runtime data, or the precise missing-evidence
policy domain. Topic similarity alone was not accepted as factual support.

## Source authority

All source references are registered and all knowledge-source versions match
the approved published registry. Across 292 primary records, 56 branches, and
39 scenario summaries (387 source-bearing schema records), source-ID,
grounding-source, terminal, and version mismatches are zero.

The embedded snapshot exactly matches current authority for products,
categories, SiteSetting, and five published knowledge documents:
`account-guide-vi` v2, `order-guide-vi` v2, `payment-guide-vi` v2,
`shopping-guide-vi` v2, and `policy-index-vi` v1.

## OOD/denied separation

The terminal classes remain distinct:

- 23 primary true-OOD cases -> `UNSUPPORTED` via `OOD_BOUNDARY`;
- 18 primary missing-evidence cases -> `NO_EVIDENCE` via the evidence gate;
- 16 primary privileged mutations -> `DENIED`;
- 10 primary clarification cases plus one ambiguous product-search case ->
  `CLARIFICATION_REQUIRED`;
- five primary unauthenticated protected reads -> `AUTH_REQUIRED`;
- supported normal reads/actions retain their applicable answer/action state.

Equivalent branch rules also pass. No OOD case is mislabeled as missing
evidence or denial, and no supported-but-denied operation is mislabeled OOD.

## Generated-data quality

There are no normalized duplicate utterances and no evaluation-label/source-ID
leakage in utterance text. Delexicalization yields eight repeated template
groups covering 19 of 370 utterances; the largest group has three records.
These are ordinary short forms such as `find PRODUCT`, `tim PRODUCT`, and
`PRODUCT gia bao nhieu`, not mass-generated repetition.

Utterances span 2-20 normalized tokens (mean 7.56); five have two or fewer
tokens and 14 have three or fewer. Short canonical requests remain valid.
Manual review found no unresolved entity-binding ambiguity outside cases whose
gold explicitly requires clarification. Security/OOD adversarial phrasing is
intentional and correctly scoped rather than being a leaked label.

## Language distribution

| Bucket | Count |
|---|---:|
| English | 68 |
| Mixed | 48 |
| Vietnamese conversational | 58 |
| Vietnamese formal | 67 |
| Vietnamese without diacritics | 51 |
| **Total** | **292** |

## Capability distribution

| Capability | Count |
|---|---:|
| product_search | 24 |
| product_detail | 18 |
| price | 18 |
| stock_availability | 18 |
| catalog_listing | 12 |
| shipping_current_value | 12 |
| shipping_calculation | 16 |
| cart_informational | 10 |
| cart_action_request | 18 |
| order_read | 14 |
| payment_status_read | 10 |
| knowledge_query | 20 |
| missing_evidence_query | 18 |
| general_chat | 8 |
| unsupported_ood | 23 |
| clarification | 10 |
| privileged_mutation | 16 |
| multi_intent | 27 |
| **Total** | **292** |

## Entity counts

| Metric | Count |
|---|---:|
| Entity-bearing primary cases | 173 |
| Entity-bearing primary cases, branch-inclusive | 178 |
| Non-null primary account targets | 11 |
| Non-null branch account targets | 1 |
| Non-null account targets total | 12 |
| Non-null primary requested mutation values | 14 |
| Non-null branch requested mutation values | 3 |
| Privileged-mutation primary cases | 16 |
| True-OOD primary cases | 23 |

## Structural schema

JSON parse, required fields, entity keys/types, terminal vocabulary, actor
values, product validity, source/version validity, grounding alignment,
shipping arithmetic, multi-intent structure, scenario structure, and manifest
counts all pass. All 331 top-level case/scenario IDs are unique; branch IDs are
unique within their parents. No structural error was found.

## Final counts

| Item | Count |
|---|---:|
| Primary cases | 292 |
| Multi-turn scenarios | 39 |
| Scenario turns | 78 |
| Multi-intent primary cases | 27 |
| Multi-intent branches | 56 |

## Final dataset SHA-256

The final audited dataset is a byte-identical copy of frozen R6 and therefore
has SHA-256:
`eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0`.

## Audit report SHA-256

The canonical report digest is recorded in `v10-final-manifest.json` after
this report is closed. The report is not self-hashed inside its own byte
content.

## Final manifest SHA-256

The manifest digest is computed only after the manifest records the frozen
dataset and report digests; it is returned with the final audit result.

## Audit decision

**AUDIT PASS.** Freeze integrity, V0-V9 leakage, zero utterance change,
account-target coverage, entity preservation, quantity/mutation separation,
intent/business gold, claim support, authorization/security, follow-up,
multi-intent, evidence/source authority, OOD separation, generated-data
quality, distribution, and schema all pass.

## Next step

Use `v10-final-audited-dataset.json` as the immutable final V10 holdout and
`v10-final-manifest.json` as its freeze record. Stop. Do not edit the final
dataset, report, or manifest after final hashes are computed. Candidate
execution/scoring remains outside this audit.
