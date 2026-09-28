# V10 R2 Fresh Leakage & Gold Audit Report

Audit timestamp (UTC): `2026-09-26T14:31:28Z`

## 1. Executive summary

**AUDIT FAIL — LEAKAGE + GOLD**

R2 freeze integrity passed, all V0–V9 historical sets were available, and the
structural distribution checks passed. The audit nevertheless found:

- two true historical-leakage cases, including one of the four R2 primary
  replacements; and
- thirteen primary cases whose entity gold drops an explicitly expressed
  product, quantity, order/deictic reference, or assigns an account target to
  an order-reference slot.

No candidate was run. No final audited dataset or final manifest was created.
R2 was not modified.

## 2. Auditor independence scope

The audit used only the frozen R2 artifacts, V0–V9 fixtures for leakage
comparison, current Product/Category/SiteSetting records, the published
knowledge registry and content, and the business contracts stated by those
authorities.

The auditor did not read the R1 audit report, Phase 12 report, candidate
predictions, historical failure analysis, or router/runtime implementation.
The candidate was not executed or scored. No chatbot, runtime, deployment,
commit, or push operation was performed.

## 3. R2 freeze verification

Freeze verification: **PASS**

| Artifact | Expected SHA-256 | Observed SHA-256 | Bytes | Result |
| --- | --- | --- | ---: | --- |
| `v10-r2-candidate-dataset.json` | `a6fe8e9dcf9b2f69dd600274cea264cb80d182a0b5e80c0b583f883acd10fd21` | `a6fe8e9dcf9b2f69dd600274cea264cb80d182a0b5e80c0b583f883acd10fd21` | 630644 | PASS |
| `v10-r2-authoring-report.md` | `123e509f4eede3dc4f20b07ce2041057b643279aa6f9fe56cc6b5a64fe974fdc` | `123e509f4eede3dc4f20b07ce2041057b643279aa6f9fe56cc6b5a64fe974fdc` | 6310 | PASS |
| `v10-r2-freeze-manifest.json` | `af0795e7a33138a000936ca2bd7ce0856649e81fab5d9928b7462323571d9790` | `af0795e7a33138a000936ca2bd7ce0856649e81fab5d9928b7462323571d9790` | 3302 | PASS |

Both JSON artifacts parse. Manifest hashes and counts agree with the frozen
files.

## 4. Historical sets audited

Leakage coverage: **COMPLETE for the available V0–V9 fixture inventory**

| Version | Fixture | Utterance-bearing comparison records |
| --- | --- | ---: |
| V0 | `tests/Fixtures/chat_evaluation.php` | 187 |
| V1 | `tests/Fixtures/chat_blind_holdout.php` | 130 |
| V2 | `tests/Fixtures/chat_blind_v2.php` | 144 |
| V3 | `tests/Fixtures/chat_blind_v3.php` | 159 |
| V4 | `tests/Fixtures/chat_blind_v4.php` | 216 |
| V5 | `tests/Fixtures/chat_blind_v5.php` | 256 |
| V6 | `tests/Fixtures/chat_blind_v6.php` | 284 |
| V7 | `tests/Fixtures/chat_blind_v7.php` | 410 |
| V8 | `tests/Fixtures/chat_blind_v8.php` | 331 |
| V9 | `tests/Fixtures/chat_blind_v9.php` | 328 |

Total comparison records: **2,445**. Fixture loading was isolated per version
so fixture-local variables could not leak between files.

## 5. Exact duplicates

Raw exact matches: **0**.

## 6. Normalized duplicates

Normalized exact matches: **0** using NFKC Unicode normalization, case
folding, whitespace collapse, and harmless-punctuation removal. Semantic
tokens and Vietnamese diacritics were retained for this stage.

## 7. Near-duplicate method/results

Each R2 primary utterance and each of the 78 scenario turns was tokenized into
Unicode letter/number token sets. Pair similarity used token Jaccard:

`|tokens(R2) intersect tokens(V0–V9)| / |tokens(R2) union tokens(V0–V9)|`

Threshold: **0.82**. Flagged pairs: **0**.

The threshold was not treated as the final leakage decision. A separate
template review replaced known product identities and numbers with
placeholders, used ASCII folding only as an audit signal, and manually
reviewed the resulting candidates.

## 8. Template leakage

Template review result: **FAIL**.

True leakage findings (historical wording intentionally omitted):

| R2 ID | Historical source | Historical record ID | Match type | Classification |
| --- | --- | --- | --- | --- |
| `V10-R2-ST-c4e8b619` | V4 | `intent.24.query` | same product-price sentence skeleton; only product/politeness changed | TRUE LEAKAGE |
| `V10-ST-0210` | V0 | `intent_v2.85.query` | accent/case/punctuation-only surface variation | TRUE LEAKAGE |

The following audit-only placeholder flags were manually classified as
canonical short phrases, not true leakage: `V10-ST-0045`, `V10-ST-0062`,
`V10-ST-0063`, `V10-ST-0068`, `V10-ST-0077`, `V10-ST-0131`,
`V10-ST-0135`, `V10-ST-0142`, and `V10-MT-028/T1`. They are minimal price,
stock, or cart constructions with too little distinctive structure to support
a copying conclusion.

No case remained `UNCERTAIN` after manual review.

## 9. Replacement case review

Replacement review: **FAIL** because one primary replacement leaked.

- Primary replacements: three passed; `V10-R2-ST-c4e8b619` failed.
- Scenario replacements: all nine passed. Each starts from a fresh session,
  establishes its own context, and does not reproduce a historical
  conversation template after manual review.

Sanitized R3 replacement requirements:

- Replace `V10-R2-ST-c4e8b619` with a wholly new Vietnamese conversational,
  straightforward, public-read current-price case for one active product.
  Preserve `price`, `ANSWER`, structured Product DB authority, raw product and
  canonical product slots, and null quantity/order/reference slots. Do not
  reuse the historical or failed R2 syntax.
- Replace `V10-ST-0210` with a wholly new Vietnamese conversational,
  straightforward `general_chat` case. Preserve public access, `ANSWER`, no
  evidence domain, and non-entity-bearing status. Do not use an accent-only,
  punctuation-only, or greeting-token-only rewrite.

Historical matched wording must not be supplied to the R3 author.

## 10. Intent gold

Intent audit: **PASS**. No independent intent-family mislabel was found across
product, catalog, shipping, cart, order/payment read, knowledge, general chat,
OOD, clarification, privileged, or multi-intent records.

## 11. Business outcome gold

Business outcome audit: **PASS**, apart from entity annotations reported
separately below. Product reads use structured product data; shipping values
use SiteSetting; owned-order reads use owned-order authority; published
knowledge and `NO_EVIDENCE` are separated; unsupported, denied, and
clarification outcomes are distinct.

## 12. Entity gold

Entity gold audit: **FAIL**.

The schema slots exist structurally, but these cases violate the rule that
explicit entities must remain annotated even when the terminal is
clarification or denial:

| R2 ID | Required sanitized correction |
| --- | --- |
| `V10-ST-0240` | Preserve the unresolved deictic product/reference; keep canonical product null and mark absent context. |
| `V10-ST-0241` | Preserve quantity `2`, the expressed unit/reference, and missing-product/missing-context state; do not guess a product. |
| `V10-ST-0243` | Preserve the unresolved pronoun/context reference; keep canonical product null. |
| `V10-ST-0244` | Preserve the deictic raw product/reference and missing-context state; keep canonical product null. |
| `V10-ST-0245` | Preserve the expressed generic product/price-preference phrase; keep canonical product null and clarification terminal. |
| `V10-ST-0246` | Preserve the raw fruit/category mention; keep canonical product null and clarification terminal. |
| `V10-ST-0248` | Preserve raw `milk` and canonical `Sữa Hộp`; quantity remains unresolved and the terminal remains clarification. |
| `V10-ST-0249` | Preserve the deictic order reference and missing-order-context state; do not invent a concrete order ID. |
| `V10-ST-0251` | Preserve the deictic order/context reference even though the requested mutation is denied. |
| `V10-ST-0254` | Preserve raw/canonical `Sữa Hộp` and requested stock value `999`; denial remains unchanged. |
| `V10-ST-0255` | Preserve raw/canonical `Cam Tươi` and requested stock value `0`; denial remains unchanged. |
| `V10-ST-0256` | Preserve raw no-diacritic product mention, canonical `Nho tím`, and delta quantity `100`; denial remains unchanged. |
| `V10-ST-0263` | Remove the fabricated order reference. The utterance identifies another account, not an order; retain non-owner targeting in the appropriate precondition/account-target metadata without using the order-reference slot. |

These are gold-only corrections. This auditor did not rewrite utterances or
author replacement gold.

## 13. Auth precondition gold

Auth audit: **PASS**.

All primary and scenario records have explicit actor preconditions. Observed
primary actor counts are:

| Actor state | Count |
| --- | ---: |
| `anonymous` | 5 |
| `anonymous_or_authenticated` | 240 |
| `authenticated_non_owner` | 8 |
| `authenticated_owner` | 17 |
| `authenticated_verified_customer` | 22 |

Cart suggestions require a verified customer; anonymous order/payment reads
require auth; owned reads are allowed; non-owner reads are denied. The
published account, shopping, order, and payment documents support these
contracts.

## 14. Follow-up gold

Follow-up audit: **PASS**.

All 39 scenarios have two turns, explicit actor/context preconditions, a setup
turn with raw and canonical product annotations, an antecedent/order where
needed, a turn-2 reference state, final intent/handler/terminal, and a fresh
session setup. Resolved references use structured authority. All four
ambiguous and all four expired-context scenarios clarify without a canonical
guess.

## 15. Multi-intent gold

Multi-intent audit: **PASS**.

There are 27 cases and 56 branches. Every branch contains intent, operation,
entity gold, handler, evidence domain/source fields, terminal, business
outcome, security expectation, minimum facts, and grounding gold. Manual
review found no silently dropped branch across same-entity, different-entity,
structured-plus-knowledge, supported-plus-denied, supported-plus-
`NO_EVIDENCE`, or supported-plus-unsupported compositions.

## 16. Evidence domain

Evidence-domain audit: **PASS**. Product price/inventory/catalog, shipping
settings, store contact, owned-order data, account, orders, payment, ordering,
policy, and missing-evidence domains are separated consistently.

## 17. Source authority

Source-authority audit: **PASS**.

- The embedded structured snapshot matches the current 11 active Product
  rows, five active Category rows, and current SiteSetting values.
- The registry contains five knowledge documents, and all five exist in the
  current registry with `published` status and the pinned versions used by
  R2.
- Every referenced source ID exists. Knowledge source IDs and versions occupy
  separate fields.

## 18. Claim support

Claim-support audit: **PASS**. The minimum required facts for all answerable
knowledge cases are contained in the allowed published documents. The 18
primary `NO_EVIDENCE` cases and two multi-intent `NO_EVIDENCE` branches have
empty allowed-source lists and request claims not supplied by the approved
registry.

## 19. OOD/denied separation

Separation audit: **PASS**. R2 retains distinct terminals for true OOD
(`UNSUPPORTED`), supported-but-prohibited operations (`DENIED`), missing
approved evidence (`NO_EVIDENCE`), absent/ambiguous context
(`CLARIFICATION_REQUIRED`), auth requirements, and normal supported reads.

## 20. Privileged gold

Security outcome audit: **PASS**; entity annotation audit: **FAIL** as listed
in section 12.

Payment, inventory, order and role mutations; auth bypass; other-user access;
and secret exfiltration are denied. Benign owned reads remain allowed. The
denial terminals for the affected entity cases are correct and must not be
changed merely to repair entity gold.

## 21. Language distribution

Language distribution: **PASS**.

| Bucket | Count |
| --- | ---: |
| English | 68 |
| Mixed VN/EN | 48 |
| Vietnamese conversational | 58 |
| Vietnamese formal | 67 |
| Vietnamese no-diacritics | 51 |

## 22. Capability distribution

Capability distribution: **PASS**.

| Capability | Count | Capability | Count |
| --- | ---: | --- | ---: |
| `product_search` | 24 | `product_detail` | 18 |
| `price` | 18 | `stock_availability` | 18 |
| `catalog_listing` | 12 | `shipping_current_value` | 12 |
| `shipping_calculation` | 16 | `cart_informational` | 10 |
| `cart_action_request` | 18 | `order_read` | 14 |
| `payment_status_read` | 10 | `knowledge_query` | 20 |
| `missing_evidence_query` | 18 | `general_chat` | 8 |
| `unsupported_ood` | 23 | `clarification` | 10 |
| `privileged_mutation` | 16 | `multi_intent` | 27 |

Additional totals: 160 entity-bearing primary cases under the frozen
annotations (including branch-only entities), 16 privileged cases, 23 true
OOD cases, 56 multi-intent branches, and 39 follow-up scenarios. The frozen
entity-bearing count is mechanically correct but includes the semantic entity
defects in section 12 and must be recomputed after correction.

Follow-up subtype counts also match the required 5/4/4/4/4/5/5/4/4
distribution for singular, plural, ordinal-first, ordinal-second,
ordinal-last, deictic, ellipsis, expired, and ambiguous scenarios.

## 23. Generated-data quality

Generated-data quality: **FAIL due to the two leakage findings**. Outside
those findings, language and syntax vary across the required buckets, the
nine replacement scenarios are independently constructed, and no systematic
gold label is directly encoded in the candidate-visible wording. This audit
does not claim population representativeness beyond the measured bucket
coverage.

## 24. Schema consistency

Structural schema/distribution validation: **PASS**.

- JSON is valid.
- Primary and scenario IDs are unique.
- Required primary and multi-intent branch fields are present.
- Terminal values are within the declared vocabulary.
- All 39 scenarios contain exactly two turns.
- All canonical products referenced by gold are current active products.
- Product-search cardinalities reference active snapshot products.
- Shipping arithmetic, threshold behavior, and cart stock terminals are
  correct.

Semantic entity consistency: **FAIL** for the thirteen IDs in section 12.
This is classified as a gold failure rather than a structural distribution
failure.

## 25. Final counts

- Primary cases audited: **292**
- Multi-turn scenarios audited: **39**
- Multi-turn turns audited: **78**
- R2 primary replacements reviewed: **4**
- R2 scenario replacements reviewed: **9**
- Historical versions audited: **10 (V0–V9)**
- Raw exact matches: **0**
- Normalized exact matches: **0**
- Token-Jaccard matches at `>= 0.82`: **0**
- Manually confirmed template/accent leakage cases: **2**
- Entity-gold defect cases: **13**

## 26. Final dataset SHA-256

No final audited dataset was created because the audit failed. The audited R2
parent dataset SHA-256 is:

`a6fe8e9dcf9b2f69dd600274cea264cb80d182a0b5e80c0b583f883acd10fd21`

## 27. Audit report SHA-256

The frozen report hash is reported in the external handoff after this file is
written; it is not embedded here because embedding a file's own hash would
change that hash.

## 28. Final manifest SHA-256

Not applicable. No final manifest was created after audit failure.

## 29. Audit decision

**AUDIT FAIL — LEAKAGE + GOLD**

Distribution and structural schema checks passed. Historical coverage was
complete. The failure categories are true leakage and entity gold.

## 30. Next step

A separate independent author should create R3 by:

1. replacing only the two leakage cases according to section 9, without
   receiving historical matched wording;
2. correcting the thirteen entity-gold records according to section 12 while
   preserving their correct intent, auth, business outcome, security decision,
   and terminal;
3. recomputing entity-bearing counts and all distributions after correction;
4. freezing R3 under new filenames and hashes; and
5. submitting R3 to a new independent auditor.

Do not run or score the candidate as part of remediation authoring.
