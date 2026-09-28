# V10 R4 Independent Dataset Remediation Authoring Report

Authored at: 2026-09-26T15:33:06Z

## EXECUTIVE SUMMARY

V10 Revision 4 was created from the frozen V10 R3 dataset by the minimum
authorized remediation. R4 replaces exactly six primary cart-action
utterances, adds the two required entity dimensions, migrates applicable
entity gold, preserves the product entity in `V10-ST-0206`, and changes the
unsupported direct-consumption claim in `V10-ST-0036` to the existing
`NO_EVIDENCE` behavior.

R4 contains 292 primary cases, 39 two-turn scenarios, 78 scenario turns, 27
multi-intent cases, and 56 annotated branches. Structural, authority,
distribution, entity, arithmetic, duplicate, and controlled-diff validation
passed. The chatbot/candidate was not run or scored. No historical leakage
audit was performed.

## INDEPENDENCE AND SCOPE

R4 authoring used only the frozen R3 dataset and manifest, the R3 authoring
report hash, the frozen Product/Category/SiteSetting and published-knowledge
registry carried by the dataset, and the supplied R4 remediation/evaluation
contract. No V0-V9 fixture, historical utterance corpus, audit report file,
candidate prediction, router/runtime implementation, or historical failure
analysis was opened or used during this work.

Known independence limitation: the initiating IDE context exposed R3 audit
text before R4 authoring began. That ambient text was not used as gold
authority or to construct replacement wording. The supplied R4 contract,
frozen R3 parent records, and allowed authorities determined all changes.

## R3 FREEZE VERIFICATION

All required R3 hashes matched before R4 authoring, both JSON artifacts parsed,
the manifest lineage resolved to revision 3, and all expected counts matched.

| Artifact | Expected SHA-256 | Actual SHA-256 | Result |
|---|---|---|---|
| `v10-r3-candidate-dataset.json` | `f87149909d3bbd0bbad9ecbc4283863efcd0e8a565f06f2079c65c0bdca04ef5` | `f87149909d3bbd0bbad9ecbc4283863efcd0e8a565f06f2079c65c0bdca04ef5` | PASS |
| `v10-r3-authoring-report.md` | `ee0c5c4b20988f325d951c05e7ddacefb7e8eeecf9c3be7382063b5bbd03e897` | `ee0c5c4b20988f325d951c05e7ddacefb7e8eeecf9c3be7382063b5bbd03e897` | PASS |
| `v10-r3-freeze-manifest.json` | `64197bd9eff70fbc769198d891b2b13d106378040d9dba69377b144178cc8836` | `64197bd9eff70fbc769198d891b2b13d106378040d9dba69377b144178cc8836` | PASS |

Verified R3 counts: 292 primary cases, 39 scenarios, 78 turns, 27
multi-intent cases, and 56 branches.

## SCHEMA REVISION

- Old schema version: `farta-v10.2.0`.
- New schema version: `farta-v10.3.0`.
- Bump type: additive minor revision.
- Migration reason: preserve explicitly expressed account targets and requested
  privileged-mutation values without conflating them with order references or
  purchase quantities.

The uniform `entity_gold` object now adds:

- `account_target`: `string|null`. A string is the literal account/user
  identifier or explicit raw account/user target phrase. It is never an order
  reference.
- `requested_mutation_value`: `number|string|null`. Absolute numeric targets
  use JSON numbers, symbolic states use strings, and signed deltas use signed
  strings such as `"+100"`. It never stores purchase quantity or the mutation
  operation.

All 426 shared-contract entity objects were shape-migrated: 292 top-level
primary objects, 56 multi-intent branch objects, and 78 scenario-turn objects.
New fields default to `null` unless the utterance explicitly supplies the
corresponding entity.

## A. SIX UTTERANCE REPLACEMENTS

Exactly these six parent records received new utterance text. Their language
bucket, intent/capability, difficulty, authentication precondition, entity
semantics, handler, business outcome, terminal, evidence gold, and security
expectation remain unchanged.

| Parent ID | R4 utterance |
|---|---|
| `V10-ST-0129` | “Sữa Hộp là mặt hàng tôi chọn; vui lòng để số lượng 2 ở bước xác nhận giỏ hàng.” |
| `V10-ST-0131` | “chuoi thi toi lay 5 qua, cho toi xac nhan gio sau nhe” |
| `V10-ST-0132` | “Purple grapes are my pick—two of them, pending my cart confirmation.” |
| `V10-ST-0137` | “Could my cart confirmation include three Australian apples?” |
| `V10-ST-0139` | “Cho đơn mua này, tôi chọn Thịt bò nạt với số lượng 1; xin chờ tôi xác nhận giỏ hàng.” |
| `V10-ST-0142` | “gio hang nay con thieu nho tim; de toi chot du 20 san pham nhe” |

Replacement count: **6**. The six normalized structural skeletons are unique;
the maximum pairwise skeleton token-Jaccard value is 0.417. This is an R4-only
diversity check, not a historical leakage audit.

## B. ENTITY SCHEMA ADDITIONS

The schema contract, evaluation rules, every primary `entity_gold`, every
multi-intent branch `entity_gold`, and every scenario-turn `entity_gold` now
use the same nine-slot shape. Validators enforce expressed-entity
preservation, account-target separation, mutation-value presence, purchase
quantity separation, and evidence-backed claim terminals.

## C. MIGRATED PRIVILEGED AND ACCOUNT ENTITY GOLD

`account_target` is non-null in 7 primary records:

- `V10-ST-0155`, `V10-ST-0156`, `V10-ST-0157`, `V10-ST-0158`, and
  `V10-ST-0168` preserve their literal raw other-account target phrase;
- `V10-ST-0263` preserves `linh@example.test` while `order_reference` remains
  `null`;
- `V10-ST-0264` preserves the explicit raw target phrase `khách khác`.

`requested_mutation_value` is non-null in 17 entity objects: 14 top-level
primary objects and 3 multi-intent branch objects.

- Payment target `paid`: `V10-ST-0250` through `V10-ST-0253`,
  `V10-ST-0278`, and branch `V10-ST-0278/mark_paid`.
- Inventory targets/delta: `V10-ST-0254=999`, `V10-ST-0255=0`,
  `V10-ST-0256="+100"`, `V10-ST-0280=500`, and branch
  `V10-ST-0280/mutate_stock=500`.
- Order targets: `V10-ST-0257=delivered`, `V10-ST-0258=cancelled`,
  `V10-ST-0277=cancelled`, and branch
  `V10-ST-0277/cancel_order=cancelled`.
- Role targets: `V10-ST-0259=admin` and `V10-ST-0260=admin`.

For `V10-ST-0254`, `V10-ST-0255`, `V10-ST-0256`, and
`V10-ST-0280/mutate_stock`, `quantity` and `unit` are now `null`; their stock
targets/delta are no longer represented as purchase quantities. All affected
security outcomes remain `DENIED`.

## D. V10-ST-0206 ENTITY CORRECTION

The utterance and `NO_EVIDENCE` outcome are unchanged. Entity gold now
preserves:

- `product_raw_mention = "Thịt bò nạt"`;
- `canonical_product = "Thịt bò nạt"`.

## E. V10-ST-0036 CLAIM AND OUTCOME CORRECTION

The utterance, `product_detail` intent, and Nho tím product identity are
unchanged. Because no allowed authority supports suitability for direct
consumption, the gold now requires:

- handler: `KNOWLEDGE_EVIDENCE_GATE`;
- business outcome: `DO_NOT_ASSERT_UNSOURCED_PRODUCT_CLAIM`;
- terminal: `NO_EVIDENCE`;
- evidence domain: `product_usage_suitability`;
- allowed evidence sources: empty;
- claim expectation: state that no allowed authority supports the requested
  fact and do not infer it from category membership or a generic description.

No knowledge source or Product fact was added or broadened.

## COUNTS AND DISTRIBUTIONS

- Primary cases: **292**.
- Multi-turn scenarios: **39**.
- Multi-turn turns: **78**.
- Multi-intent primary cases: **27**.
- Annotated branches: **56**.
- Privileged-mutation primary cases: **16**.
- True OOD primary cases: **23**.

Language distribution:

| Bucket | Count |
|---|---:|
| `en` | 68 |
| `mixed` | 48 |
| `vi_conversational` | 58 |
| `vi_formal` | 67 |
| `vi_no_diacritics` | 51 |

Capability distribution:

| Capability | Count | Capability | Count |
|---|---:|---|---:|
| `product_search` | 24 | `product_detail` | 18 |
| `price` | 18 | `stock_availability` | 18 |
| `catalog_listing` | 12 | `shipping_current_value` | 12 |
| `shipping_calculation` | 16 | `cart_informational` | 10 |
| `cart_action_request` | 18 | `order_read` | 14 |
| `payment_status_read` | 10 | `knowledge_query` | 20 |
| `missing_evidence_query` | 18 | `general_chat` | 8 |
| `unsupported_ood` | 23 | `clarification` | 10 |
| `privileged_mutation` | 16 | `multi_intent` | 27 |

Follow-up subtype distribution: singular reference 5, plural reference 4,
ordinal first 4, ordinal second 4, ordinal last 4, deictic 5, ellipsis 5,
expired context 4, and ambiguous reference 4.

Entity-bearing counts use non-null values across the new nine-slot contract:

- Primary entity-bearing count: **172** primary cases whose top-level
  `entity_gold` has at least one non-null slot.
- Branch-inclusive entity-bearing count: **176** primary cases whose top-level
  `entity_gold` or at least one annotated branch `entity_gold` has a non-null
  slot.

## VALIDATION

Result: **PASS**.

Validated successfully:

- JSON parsing, schema/dataset version, required fields, unique primary and
  scenario IDs, and terminal vocabulary;
- exact 292/39/78/27/56 structural counts and all recomputed distributions;
- uniform nine-slot shape for all 426 entity objects;
- canonical products against the 11-product active frozen registry;
- account-target preservation and separation from order references;
- mutation-value presence and separation from purchase quantity;
- unchanged `DENIED` outcomes for all privileged primary cases;
- source-ID and source-version resolution plus source/grounding mirrors;
- `NO_EVIDENCE` empty-source behavior and the corrected claim support for
  `V10-ST-0036`;
- shipping threshold, fee, subtotal, and final-total arithmetic;
- two-turn scenario structure and byte-semantic preservation apart from the two
  mechanically added null entity slots;
- multi-intent case/branch structure and required branch fields;
- zero raw exact and zero audit-normalized duplicate groups among all 370
  R4 utterance-bearing records;
- six unique replacement skeletons and pairwise structural diversity;
- controlled semantic diff: all clean records and the frozen authority registry
  remain unchanged outside the authorized remediation paths.

## KNOWN LIMITATIONS

- No V0-V9 historical leakage comparison was performed; a fresh independent
  auditor must perform that check later.
- The chatbot/candidate was not executed, predictions were not inspected, and
  no V10 metric was calculated.
- Runtime behavior and application code were not changed or validated.
- The ambient IDE-context disclosure described above remains an independence
  limitation even though it was not used for R4 authoring decisions.

## R4 DATASET FREEZE

- Dataset: `v10-r4-candidate-dataset.json`.
- Dataset bytes: 666,925.
- Dataset SHA-256:
  `1f9ee94637ce70fc7e64fb03d94ebf211b85a455e79d80c6c939cd58b995b19b`.
- Parent R3 dataset SHA-256:
  `f87149909d3bbd0bbad9ecbc4283863efcd0e8a565f06f2079c65c0bdca04ef5`.

The report and manifest hashes are detached freeze outputs calculated only
after their respective files are finalized.

## AUTHOR DECISION

**V10 R4 AUTHOR VALIDATION PASS — FREEZE R4 ARTIFACTS**

Stop after writing and hashing the R4 freeze manifest. Do not run the candidate
or perform the historical audit.
