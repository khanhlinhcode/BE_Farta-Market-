# Farta Market V10 R6 Gold Remediation Authoring Report

## Freeze lineage

- Revision: 6
- Parent revision: 5
- Schema: `farta-v10.3.0`
- Parent R5 dataset SHA-256: `1c2b671a3ec303fbf2071763665e2471322603b31c3eff7015565a97131603ff`
- Verified R5 authoring report SHA-256: `9dea336885e3381a64234df3574bef90eadf8b3dabd6407b2f3fc2232cac4062`
- Verified R5 freeze manifest SHA-256: `b380bcd96517d9167ee7506efb3cab2573547a16a1a11bf4267ddf66c8b388f9`
- R6 dataset SHA-256: `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0`
- Authoring timestamp (UTC): `2026-09-26T17:26:29Z`

All three supplied R5 hashes matched before R6 authoring. The R5 dataset and manifest parsed successfully, the manifest lineage and artifact hashes were consistent, and the required R5 structural counts matched.

## Authorized gold corrections

Exactly four semantic gold fields changed:

| Location | R5 `account_target` | R6 `account_target` |
| --- | --- | --- |
| `V10-ST-0160` | `null` | `tài khoản tôi` |
| `V10-ST-0179` | `null` | `another account` |
| `V10-ST-0260` | `null` | `toi` |
| `V10-ST-0289/bypass` | `null` | `me` |

- Semantic gold changes: 4 `account_target` fields
- Utterance changes: 0
- Schema changes: 0
- Intent changes: 0
- Terminal changes: 0
- Handler changes: 0
- Business-outcome changes: 0
- Evidence changes: 0
- Authorization/security-decision changes: 0
- `requested_mutation_value` changes: 0
- Quantity changes: 0

Revision metadata was updated mechanically for R6. No application or runtime artifact was changed.

## Structural counts

| Measure | R6 count |
| --- | ---: |
| Primary cases | 292 |
| Multi-turn scenarios | 39 |
| Multi-turn turns | 78 |
| Multi-intent primary cases | 27 |
| Multi-intent annotated branches | 56 |
| Privileged-mutation primary cases | 16 |
| True OOD primary cases | 23 |

## Entity counts

| Measure | R5 | R6 |
| --- | ---: | ---: |
| Non-null `account_target`, primary records | 8 | 11 |
| Non-null `account_target`, branch records | 0 | 1 |
| Non-null `account_target`, total | 8 | 12 |
| Entity-bearing primary cases | 172 | 173 |
| Entity-bearing primary cases, branch-inclusive | 176 | 178 |

The branch-inclusive measure counts a primary case when its parent entity gold or at least one annotated branch contains a non-null entity slot.

## Language distribution

| Language bucket | Count |
| --- | ---: |
| `en` | 68 |
| `mixed` | 48 |
| `vi_conversational` | 58 |
| `vi_formal` | 67 |
| `vi_no_diacritics` | 51 |

## Capability distribution

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

## Validation results

| Check | Result |
| --- | --- |
| R5 dataset/report/manifest hash verification | PASS |
| JSON parse | PASS |
| Schema remains `farta-v10.3.0` | PASS |
| Manifest lineage and R5 artifact consistency | PASS |
| Structural counts and unique IDs | PASS |
| Uniform entity slots and entity value types | PASS |
| Four required account targets corrected | PASS |
| Account-target forward invariant | PASS |
| Account-target reverse invariant | PASS |
| Raw account-target surface representation consistency | PASS |
| Product, quantity, mutation value, order and context slots preserved | PASS |
| Quantity and mutation-value semantics unchanged | PASS |
| Auth/security semantics unchanged | PASS |
| Claim support and `NO_EVIDENCE` consistency | PASS |
| Primary utterances byte-identical R5 to R6 | PASS |
| All 39 scenarios and 78 turns unchanged | PASS |
| Multi-intent case, branch count, order and sibling gold preserved | PASS |
| Semantic R5 to R6 change budget exactly four fields | PASS |
| Candidate executed or scored | NO |
| Historical V0-V9 leakage audit run by author | NO |

The account-target coverage validator reviewed the complete dataset-level target set in both directions. Every non-null target is a raw account/user reference established by its utterance, and every reviewed explicit account/user target is represented in the correct slot. Unchanged entity, authority, security, outcome and grounding data were also protected by an exact normalized R5-to-R6 comparison.

## Known limitations

- This revision is a gold-only remediation. It does not measure candidate performance.
- No chatbot, candidate, staging or production request was executed.
- No V0-V9 fixture or historical leakage corpus was inspected or audited, as required by the author independence boundary.
- Runtime/router implementation and prior audit reasoning were not inspected.
- The revision intentionally does not address any concern outside the four authorized `account_target` corrections.

## Author validation status

`PASS — READY TO FREEZE`
