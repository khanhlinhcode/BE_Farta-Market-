# Farta Market Blind Holdout V10 — R3 Remediation Authoring Report

## Freeze summary

- Dataset version: `V10-blind-independent-2026-09-26-r3`
- Dataset schema version: `farta-v10.2.0`
- Revision: **3**
- Parent revision: **2**
- Authoring timestamp (UTC): `2026-09-26T14:46:06Z`
- Parent R2 dataset SHA-256: `a6fe8e9dcf9b2f69dd600274cea264cb80d182a0b5e80c0b583f883acd10fd21`
- R3 dataset SHA-256: `f87149909d3bbd0bbad9ecbc4283863efcd0e8a565f06f2079c65c0bdca04ef5`
- Primary cases: **292**
- Multi-turn scenarios: **39**
- Multi-turn turns: **78**
- Replacement primary cases: **2**
- Entity-gold corrections: **13**
- Multi-intent primary cases/branches: **27 / 56**
- Entity-bearing primary cases before/after: **160 / 171**
- Privileged mutation primary cases: **16**
- True OOD primary cases: **23**
- Candidate executed: **false**
- Internal validation: **PASS**

## Remediation scope

Only the requested R3 remediation was authored.

### Primary replacements

| R2 parent ID | R3 replacement ID | Preserved capability |
| --- | --- | --- |
| `V10-R2-ST-c4e8b619` | `V10-R3-ST-1f6748ba` | Vietnamese conversational public current-price read with structured Product authority |
| `V10-ST-0210` | `V10-R3-ST-579dee27` | Vietnamese conversational `general_chat` with no evidence domain or entity |

The replacement IDs are deterministic SHA-256 prefixes derived from the R3
revision, R2 parent ID, and new utterance. The selected price product,
`Rau Củ Tươi`, is active in the frozen structured Product snapshot and has a
current unit price of 65,000 VND.

### Entity-gold corrections

The utterance and all non-entity gold remained unchanged for:

- `V10-ST-0240`
- `V10-ST-0241`
- `V10-ST-0243`
- `V10-ST-0244`
- `V10-ST-0245`
- `V10-ST-0246`
- `V10-ST-0248`
- `V10-ST-0249`
- `V10-ST-0251`
- `V10-ST-0254`
- `V10-ST-0255`
- `V10-ST-0256`
- `V10-ST-0263`

The corrections preserve raw mentions and unresolved deictic/context
references separately from canonical products, retain explicit quantities and
units, and remove the fabricated concrete order reference from
`V10-ST-0263`. Clarification and denial terminals were not changed.

## Files created

- `docs/evaluation/v10-independent/v10-r3-candidate-dataset.json`
- `docs/evaluation/v10-independent/v10-r3-authoring-report.md`
- `docs/evaluation/v10-independent/v10-r3-freeze-manifest.json`

No R1 or R2 artifact, runtime file, dependency, application configuration, or
test fixture was modified.

## Primary language distribution

| Bucket | Count |
| --- | ---: |
| `en` | 68 |
| `mixed` | 48 |
| `vi_conversational` | 58 |
| `vi_formal` | 67 |
| `vi_no_diacritics` | 51 |

## Primary capability distribution

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

## Follow-up subtype distribution

| Subtype | Count |
| --- | ---: |
| `singular_reference` | 5 |
| `plural_reference` | 4 |
| `ordinal_first` | 4 |
| `ordinal_second` | 4 |
| `ordinal_last` | 4 |
| `deictic_that` | 5 |
| `ellipsis` | 5 |
| `expired_context` | 4 |
| `ambiguous_reference` | 4 |

## Validation

All **42** R3 authoring checks passed:

- R2 parent hash matched the frozen expected SHA-256.
- JSON parsed successfully; primary/scenario/turn counts were 292/39/78.
- Primary and scenario IDs were unique.
- NFKC-normalized primary utterances and scenario conversations were unique
  within R3.
- Required primary fields, entity slots, branch fields, and terminal values
  matched the declared schema.
- Every non-null canonical product resolved to an active product in the frozen
  structured snapshot.
- Quantity/unit, actor state, and evidence-source references were consistent.
- All 39 two-turn scenarios were byte-equivalent to R2 and remained
  self-contained.
- Multi-intent totals remained 27 cases and 56 annotated branches.
- Every clean primary record was byte-equivalent to its R2 counterpart.
- Each of the 13 correction records changed only `entity_gold`.
- Both replacement positions and deterministic R3 IDs matched the declared
  parent mapping.
- Thirteen ID-specific semantic entity assertions passed.
- Both replacement records passed their specified semantic assertions.
- Schema, evaluation rules, and source registry remained unchanged from R2.

No V0–V9 fixture or utterance comparison was performed. No chatbot, candidate,
prediction, blind evaluation, scoring process, or runtime implementation was
executed or inspected during remediation.

## Known limitations

- Product, price, inventory, category, and SiteSetting authority remain frozen
  to the 2026-09-26 snapshot inherited from R2.
- Historical leakage was intentionally not audited by this author; R3 requires
  a fresh independent leakage audit.
- The initiating IDE context exposed the R2 audit report before remediation
  began. No audit file, historical fixture, matched wording, candidate output,
  or runtime implementation was subsequently opened or used. This disclosure
  prevents an unqualified claim of auditor-style independence and should be
  considered when assigning the fresh R3 auditor.
