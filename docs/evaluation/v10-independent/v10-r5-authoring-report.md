# V10 Revision 5 Authoring Report

## Freeze lineage

- Parent revision: R4
- Parent R4 dataset SHA-256: `1f9ee94637ce70fc7e64fb03d94ebf211b85a455e79d80c6c939cd58b995b19b`
- Schema version: `farta-v10.3.0`
- R5 dataset SHA-256: `1c2b671a3ec303fbf2071763665e2471322603b31c3eff7015565a97131603ff`
- Replacement count: `1`
- Gold correction count: `3`

## Approved remediation

- `V10-ST-0259`: kept the utterance unchanged and corrected `entity_gold.account_target` from `null` to the explicitly expressed raw target `tài khoản của tôi`. Intent, handler, auth precondition, security decision, terminal, mutation value, and business outcome were preserved.
- `V10-ST-0030`: kept the utterance and Xoài keo entity identity unchanged; corrected the unsupported product-use claim to the existing `NO_EVIDENCE` contract with no allowed source.
- `V10-ST-0272/detail`: kept the parent utterance and Xoài keo entity identity unchanged; corrected only the detail branch to the existing `NO_EVIDENCE` product-claim contract. The cart sibling was preserved. The mechanically derived parent and grounding terminals changed to `PARTIAL_MIXED_TERMINALS`.
- `V10-ST-0142`: replaced the sole utterance with `minh dang dat do cho bua tiec; rieng nho tim thi de so luong 20 trong gio nhe`. The language bucket, cart path, security expectation, terminal, `product_raw_mention = nho tim`, `canonical_product = Nho tím`, `quantity = 20`, `unit = item`, and `requested_mutation_value = null` were preserved.

## Files changed

- `docs/evaluation/v10-independent/v10-r5-candidate-dataset.json`
- `docs/evaluation/v10-independent/v10-r5-authoring-report.md`
- `docs/evaluation/v10-independent/v10-r5-freeze-manifest.json`

No R4 artifact, application/runtime file, dependency, fixture, or historical evaluation artifact was modified.

## Structural counts

| Measure | Count |
| --- | ---: |
| Primary cases | 292 |
| Multi-turn scenarios | 39 |
| Multi-turn turns | 78 |
| Multi-intent primary cases | 27 |
| Multi-intent branches | 56 |
| Privileged-mutation primary cases | 16 |
| True OOD primary cases | 23 |

## Recomputed language distribution

| Language bucket | Count |
| --- | ---: |
| `en` | 68 |
| `mixed` | 48 |
| `vi_conversational` | 58 |
| `vi_formal` | 67 |
| `vi_no_diacritics` | 51 |

## Recomputed capability distribution

| Capability | Count |
| --- | ---: |
| `product_search` | 24 |
| `product_detail` | 18 |
| `price` | 18 |
| `stock_availability` | 18 |
| `catalog_listing` | 12 |
| `shipping_current_value` | 12 |
| `shipping_calculation` | 16 |
| `cart_informational` | 10 |
| `cart_action_request` | 18 |
| `order_read` | 14 |
| `payment_status_read` | 10 |
| `knowledge_query` | 20 |
| `missing_evidence_query` | 18 |
| `general_chat` | 8 |
| `unsupported_ood` | 23 |
| `clarification` | 10 |
| `privileged_mutation` | 16 |
| `multi_intent` | 27 |

## Recomputed entity and follow-up counts

- Entity-bearing primary cases: `172`
- Branch-inclusive entity-bearing primary cases: `176`
- Non-null primary `account_target` records: `8`
- Non-null primary `requested_mutation_value` records: `14`
- Non-null branch `requested_mutation_value` records: `3`

| Follow-up subtype | Count |
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

## Validation results

- Claim-support validation: **PASS**. Product/Category structured authority and all five published knowledge sources were checked without candidate output. All 193 factual `ANSWER` records retain an allowed authority; numeric product, inventory, category, shipping, and version references validate against the frozen registry. All 23 `NO_EVIDENCE` records have empty allowed-source sets and no published knowledge topic that supplies the requested claim. Product usage/suitability `NO_EVIDENCE` is limited to `V10-ST-0030`, `V10-ST-0036`, and `V10-ST-0272/detail`; no allowed authority supports the requested Xoài keo claims.
- Entity-preservation validation: **PASS**. All 426 entity objects use the uniform slot shape. Canonical products resolve to the frozen product authority. `V10-ST-0030` and `V10-ST-0272/detail` retain raw and canonical Xoài keo identity despite `NO_EVIDENCE`; `V10-ST-0259` retains the explicit self-account target despite `DENIED`; `V10-ST-0142` retains Nho tím and quantity 20. All eight non-null account targets are present in their utterances, and the R5-only explicit-account scan found no additional deterministic omission.
- Schema validation: **PASS**. JSON parsing, required fields, unique IDs, `farta-v10.3.0` entity shape, terminal vocabulary, source/version references, auth/security consistency, quantity-versus-mutation invariants, shipping arithmetic, scenario structure, branch completeness, and parent aggregation passed.
- Internal utterance-quality validation: **PASS**. `V10-ST-0142` has unambiguous Nho tím–quantity-20 binding and does not duplicate another R5 cart-action skeleton after internal product/quantity normalization. No V0–V9 comparison was performed.
- R4→R5 semantic diff: **PASS**. Changed primary case IDs are exactly `V10-ST-0259`, `V10-ST-0030`, `V10-ST-0272`, and `V10-ST-0142`; changes within them are limited to the approved fields and the derived composite terminal. All 39 scenarios and 78 turns are byte-semantically unchanged.

## Known limitations

- Validation is an author-side static authority and schema review; candidate execution, chatbot execution, V10 scoring, and staging requests were intentionally not performed.
- Historical V0–V9 similarity and leakage analysis were intentionally not performed and remain work for a fresh auditor.
- R5 inherits the independence limitation already disclosed in the frozen R4 dataset metadata. During R5 authoring, no R1–R4 audit report, candidate prediction, runtime/router implementation, or V0–V9 fixture was opened or used.
