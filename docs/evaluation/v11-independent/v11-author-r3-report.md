# V11 Independent Holdout Author R3 Final Entity-Gold Cleanup Report

Status: **FROZEN**  
Frozen at (UTC): `2026-09-27T10:37:40Z`

## 1. Scope and isolation

V11 Author R3 is the final exhaustive `unit` and `account_target` cleanup created from frozen V11 Author R2. The audit covered exactly 240 entity objects: 160 primary objects, 40 multi-intent branch objects, and 40 scenario-turn objects. Every object received one `unit` validation and one `account_target` validation.

No candidate/chatbot was executed. No candidate predictions, runtime/router/handler/RAG implementation, Phase 14 analysis, or V0-V10 historical development data was read. R2 was not modified. No utterance, schema, authority, quantity, other entity slot, intent, handler, terminal, outcome, evidence, authorization, or security gold was changed.

Parent revision: `V11-author-r2`  
Parent dataset SHA-256: `b89b78105caad702ebefe1a301f6a1b91a64cf13b5af7659a091e562de9320aa`  
R3 dataset SHA-256: `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb`  
Dataset schema: `farta-v10.3.0`  
Sanitized schema contract: `farta-v11-sanitized-schema-contract.1.0`

## 2. Input integrity

All required frozen inputs were hashed before authoring and matched the prompt values.

| Input | Verified SHA-256 | Status |
|---|---|---|
| `v11-author-r2-candidate-dataset.json` | `b89b78105caad702ebefe1a301f6a1b91a64cf13b5af7659a091e562de9320aa` | PASS |
| `v11-author-r2-report.md` | `dc6283e475ed688d6dce5bef364557eaa39585c0b00dff6960eb7a40ae1f4de7` | PASS |
| `v11-author-r2-freeze-manifest.json` | `a1cbcaf6cee1e7640fbb65e234cd03618a70db63b40bc6830399265a5471caaa` | PASS |
| `v11-sanitized-schema-contract.json` | `2d1e6cd805ed9914d6811f130f9c1cc0909eb2f6707e30f5f66b13c61f6bea5d` | PASS |
| `v11-sanitized-business-capability-contract.json` | `ddaf57dcb1fd0db48b7466370c498d52db729cf8a4714f99c0098548fc1e4b7b` | PASS |
| `v11-sanitized-product-authority.json` | `809923e43f8919446b8123ea3ab0cac2f39cc3b50d89bad89f584d3768799f0a` | PASS |
| `v11-sanitized-sitesetting-authority.json` | `92e69a3c93bfef417fce7ab76a12e191558cbbe50ec9e645c6422e69bb69f2cb` | PASS |
| `v11-sanitized-knowledge-registry.json` | `dc8b013d3235a8fb04cedda63575354ab25fd6482316798952ff7161e6d599e7` | PASS |
| `v11-sanitized-authorization-contract.json` | `de05e7d4ebd0429b20c73fd9bb0927a9012a9e358b80a195c878549e56639dc4` | PASS |

Both R2 dataset and R2 manifest parse as JSON, the manifest declares `FROZEN`, and its lineage and artifact hashes match the verified parent.

## 3. Exhaustive audit method

The R2 and R3 datasets were flattened into a stable list of 240 scoped entity objects. A complete expected-value map was established for all explicit/licensed units and account targets. Every flattened object was then checked against those maps, so null records were validated as well as non-null records.

The schema contains no unit-normalization mapping. Consequently every surviving non-null unit has `SURFACE` provenance; no `CONTEXT` or `NORMALIZATION` provenance was accepted. Every surviving account target has an exact request-semantic relation to an account identifier or private-resource possession. Actor fixtures, auth state, requester pronouns, and beneficiary phrases were not treated as entity provenance.

## 4. R2 to R3 semantic diff

The recursive entity diff contains exactly 17 field changes across 17 entity objects: 13 `unit` changes and 4 `account_target` changes.

| Scope | Field | R2 | R3 | Remediation reason |
|---|---|---|---|---|
| `v11r1-p043` | `account_target` | `mình` | `null` | `SPURIOUS_REQUESTER_ACCOUNT_TARGET` |
| `v11r1-p046` | `account_target` | `mình` | `null` | `SPURIOUS_REQUESTER_ACCOUNT_TARGET` |
| `v11r1-p072` | `account_target` | `cua toi` | `null` | `SPURIOUS_REQUESTER_ACCOUNT_TARGET` |
| `v11r1-p092` | `unit` | `items` | `null` | `SPURIOUS_UNIT` |
| `v11r1-p093` | `unit` | `items` | `null` | `SPURIOUS_UNIT` |
| `v11r1-p098` | `unit` | `boxes` | `null` | `UNAUTHORIZED_UNIT_NORMALIZATION` |
| `v11r1-p108` | `unit` | `items` | `product` | `UNAUTHORIZED_UNIT_NORMALIZATION` |
| `v11r1-p121` | `unit` | `quả` | `null` | `SPURIOUS_UNIT` |
| `v11r1-p128` | `account_target` | `account mình` | `null` | `SPURIOUS_REQUESTER_ACCOUNT_TARGET` |
| `v11r1-p148` parent | `unit` | `quả` | `null` | `SPURIOUS_UNIT` |
| `v11r1-p148-b1` | `unit` | `quả` | `null` | `SPURIOUS_UNIT` |
| `v11r1-p149` parent | `unit` | `null` | `hop` | `MISSING_EXPLICIT_UNIT` |
| `v11r1-p149-b2` | `unit` | `null` | `hop` | `MISSING_EXPLICIT_UNIT` |
| `v11r1-s08/t2` | `unit` | `null` | `phần` | `MISSING_EXPLICIT_UNIT` |
| `v11r1-s14/t2` | `unit` | `items` | `null` | `INVALID_CONTEXT_UNIT_INHERITANCE` |
| `v11r1-s16/t2` | `unit` | `items` | `item` | `UNAUTHORIZED_UNIT_NORMALIZATION` |
| `v11r1-s20/t2` | `unit` | `item` | `null` | `SPURIOUS_UNIT` |

Reason totals: `MISSING_EXPLICIT_UNIT=3`; `SPURIOUS_UNIT=6`; `UNAUTHORIZED_UNIT_NORMALIZATION=3`; `INVALID_CONTEXT_UNIT_INHERITANCE=1`; `SPURIOUS_REQUESTER_ACCOUNT_TARGET=4`.

Additional same-class defects discovered beyond the known R2 seed list: **0**. No out-of-scope defect was found.

## 5. Unit provenance

All 26 R3 non-null units have direct surface provenance and pass. No unit relies on product convention, translation, accent restoration, inflection, generic-item invention, or implicit carry-over.

| Scope | Unit | Provenance | Surface/branch evidence | Status |
|---|---|---|---|---|
| `v11r1-p008` | `quả` | SURFACE | `12 quả Chuối` | PASS |
| `v11r1-p009` | `phần` | SURFACE | `26 phần Rau Củ Tươi` | PASS |
| `v11r1-p012` | `hộp` | SURFACE | `3 hộp Sữa Hộp` | PASS |
| `v11r1-p014` | `quả` | SURFACE | `2 quả Dưa hấu` | PASS |
| `v11r1-p033` | `phần` | SURFACE | `một phần` | PASS |
| `v11r1-p036` | `trái` | SURFACE | `18 trái Ổi` | PASS |
| `v11r1-p037` | `hộp` | SURFACE | `41 hộp Sữa Hộp` | PASS |
| `v11r1-p040` | `phần` | SURFACE | `2 phần Hamburger` | PASS |
| `v11r1-p042` | `quả` | SURFACE | `4 quả Chuối` | PASS |
| `v11r1-p052` | `cái` | SURFACE | `3 cái` | PASS |
| `v11r1-p061` | `hop` | SURFACE | `Mot hop Sua Hop` | PASS |
| `v11r1-p064` | `trai` | SURFACE | `20 trai Cam Tuoi` | PASS |
| `v11r1-p065` | `phan` | SURFACE | `21 phan Thit bo nat` | PASS |
| `v11r1-p068` | `qua` | SURFACE | `5 qua Chuoi` | PASS |
| `v11r1-p096` | `units` | SURFACE | `4 units of Ổi` | PASS |
| `v11r1-p108` | `product` | SURFACE | `two of the product` | PASS |
| `v11r1-p118` | `phần` | SURFACE | `một phần Hamburger` | PASS |
| `v11r1-p120` | `hộp` | SURFACE | `9 hộp Sữa Hộp` | PASS |
| `v11r1-p126` | `quả` | SURFACE | `3 quả Ổi` | PASS |
| `v11r1-p149` parent | `hop` | SURFACE | Parent union includes branch clause `mấy hop` | PASS |
| `v11r1-p149-b2` | `hop` | SURFACE | Stock branch clause `trong kho còn mấy hop` | PASS |
| `v11r1-s05/t2` | `cái` | SURFACE | `2 cái đó` | PASS |
| `v11r1-s08/t2` | `phần` | SURFACE | `mấy phần` | PASS |
| `v11r1-s09/t2` | `hop` | SURFACE | `3 hop` | PASS |
| `v11r1-s14/t1` | `unit` | SURFACE | `one unit of Chuối` | PASS |
| `v11r1-s16/t2` | `item` | SURFACE | `two of the first item` | PASS |

Global unit gate:

- Unit validations completed: **240/240**
- Explicit/licensed units: **26**
- Correctly represented: **26**
- Missing: **0**
- Incorrect: **0**
- Spurious: **0**
- Unauthorized normalization: **0**

## 6. Account-target provenance

All 19 R3 non-null account targets have explicit semantic provenance. The multi-intent `p156` target is retained only at the parent and owned-read branch; the cancellation branch remains null.

| Scope | Account target | Provenance | Utterance relation | Status |
|---|---|---|---|---|
| `v11r1-p015` | `tài khoản của tôi` | EXPLICIT_SELF | `thuộc tài khoản của tôi` | PASS |
| `v11r1-p016` | `tài khoản của tôi` | VALID_PRIVATE_RESOURCE_POSSESSION | `danh sách đơn hàng của mình` | PASS |
| `v11r1-p017` | `khách-hang-khac` | EXPLICIT_IDENTIFIER | `tài khoản khách-hang-khac` | PASS |
| `v11r1-p044` | `mình` | VALID_PRIVATE_RESOURCE_POSSESSION | `đơn của mình` | PASS |
| `v11r1-p045` | `minh-anh` | EXPLICIT_IDENTIFIER | `đơn EXT-208 của bạn minh-anh` | PASS |
| `v11r1-p055` | `demo-buyer` | EXPLICIT_IDENTIFIER | `tài khoản demo-buyer` | PASS |
| `v11r1-p071` | `cua toi` | VALID_PRIVATE_RESOURCE_POSSESSION | `don OWN-ALPHA cua toi` | PASS |
| `v11r1-p073` | `user-khac` | EXPLICIT_IDENTIFIER | `thuoc tai khoan user-khac` | PASS |
| `v11r1-p074` | `nguoi-dung-b` | EXPLICIT_IDENTIFIER | `don EXT-901 cua nguoi-dung-b` | PASS |
| `v11r1-p083` | `thu-nghiem` | EXPLICIT_IDENTIFIER | `tai khoan thu-nghiem` | PASS |
| `v11r1-p099` | `my account` | VALID_PRIVATE_RESOURCE_POSSESSION | `my order OWN-BRAVO` | PASS |
| `v11r1-p100` | `my account` | VALID_PRIVATE_RESOURCE_POSSESSION | `my order history` | PASS |
| `v11r1-p101` | `other-shopper` | EXPLICIT_IDENTIFIER | `account other-shopper` | PASS |
| `v11r1-p102` | `my account` | VALID_PRIVATE_RESOURCE_POSSESSION | `my OWN-ALPHA order` | PASS |
| `v11r1-p127` | `account mình` | EXPLICIT_SELF | `account mình` | PASS |
| `v11r1-p129` | `buyer-two` | EXPLICIT_IDENTIFIER | `account buyer-two` | PASS |
| `v11r1-p137` | `account mình` | VALID_PRIVATE_RESOURCE_POSSESSION | `order kia của mình` | PASS |
| `v11r1-p156` parent | `my account` | VALID_PRIVATE_RESOURCE_POSSESSION | `my OWN-BRAVO order` | PASS |
| `v11r1-p156-b1` | `my account` | VALID_PRIVATE_RESOURCE_POSSESSION | Owned-read branch of `my OWN-BRAVO order` | PASS |

Global account-target gate:

- Account-target validations completed: **240/240**
- Explicit/licensed account targets: **19**
- Correctly represented: **19**
- Missing: **0**
- Incorrect: **0**
- Spurious: **0**

## 7. Known R2 defect closure

| Gate | Known scopes | R3 status |
|---|---:|---|
| Unit forward | `p149` parent, `p149-b2`, `s08/t2` | PASS |
| Unit reverse/normalization | `p092`, `p093`, `p098`, `p108`, `p121`, `p148` parent, `p148-b1`, `s14/t2`, `s16/t2`, `s20/t2` | PASS |
| Account-target reverse | `p043`, `p046`, `p072`, `p128` | PASS |
| Multi-intent entity scope | all 20 parents and 40 branches | PASS |
| Follow-up entity scope | all 20 scenarios and 40 turns | PASS |

Known R2 failures closed: **17/17 scopes, PASS**.

## 8. Change-scope and zero-change proof

A whole-artifact comparison was performed after masking only `unit` and `account_target` in each entity object. The masked R2 and R3 documents were exactly equal. Separate per-slot comparisons also passed.

| Check | Changes | Status |
|---|---:|---|
| Primary utterances | 0 | PASS |
| Scenario-turn utterances | 0 | PASS |
| Multi-intent parent utterances | 0 | PASS |
| Schema/version/contract | 0 | PASS |
| `quantity` | 0 | PASS |
| Other seven entity slots | 0 | PASS |
| Intent/handler/capability/operation | 0 | PASS |
| Terminal/business outcome | 0 | PASS |
| Actor/auth/security | 0 | PASS |
| Evidence domain/sources/claim support | 0 | PASS |
| Source registry and authority facts | 0 | PASS |
| Additions/removals/reordering | 0 | PASS |

Recursive R2-to-R3 change scope: **PASS**. Every semantic changed path ends in `unit` or `account_target` and matches the 17-row remediation ledger.

## 9. Entity distribution

Counts include all 240 entity objects.

| Entity slot | R2 | R3 | Delta |
|---|---:|---:|---:|
| `product_raw_mention` | 112 | 112 | 0 |
| `canonical_product` | 122 | 122 | 0 |
| `quantity` | 33 | 33 | 0 |
| `unit` | 31 | 26 | -5 |
| `order_reference` | 24 | 24 | 0 |
| `ordinal_reference` | 6 | 6 | 0 |
| `context_reference` | 26 | 26 | 0 |
| `account_target` | 23 | 19 | -4 |
| `requested_mutation_value` | 6 | 6 | 0 |

The unit delta is `+3` missing explicit units, `-8` spurious units, and two value-only corrections, for net `-5`. The account-target delta is four removals.

## 10. Structural, authority, claim, and security regression

- JSON parse, exact nine-slot entity shapes, field types/nullability, unique IDs, source registration, version pins, and grounding mirrors: **PASS**.
- Structural counts: primary `160`; scenarios `20`; turns `40`; multi-intent parents `20`; branches `40`: **PASS**.
- Product, SiteSetting/shipping, published-knowledge, and owned-order authority content: **unchanged, PASS**.
- Claim support, `NO_EVIDENCE`, evidence domains, allowed sources, and minimum facts: **unchanged, PASS**.
- Authorization, cross-account denial, privileged-action denial, benign reads, true OOD, and clarification behavior: **unchanged, PASS**.
- Multi-intent branch count/order, intent, operation, handler, terminal, source, claim, and security fields: **unchanged, PASS**.
- Follow-up turn order, context lifecycle, resolution, handler, terminal, evidence, and security fields: **unchanged, PASS**.

## 11. Distribution regression

All distribution vectors are exactly equal between R2 and R3.

- Primary languages: `en=32; mixed=32; vi_conversational=32; vi_formal=32; vi_no_diacritics=32`.
- Scenario languages: `en=4; mixed=4; vi_conversational=4; vi_formal=4; vi_no_diacritics=4`.
- Primary intents: `cart_action_request=5; cart_informational=5; catalog_listing=5; clarification=10; general_chat=5; knowledge_query=10; missing_evidence_query=10; multi_intent=20; order_read=15; payment_status_read=5; price=10; privileged_mutation=10; product_detail=10; product_search=10; shipping_calculation=5; shipping_current_value=5; stock_availability=15; unsupported_ood=5`.
- Primary terminals: `ALL_BRANCHES_HANDLED=15; ANSWER=65; ANSWER_OR_NOT_FOUND=8; AUTH_REQUIRED=7; CLARIFICATION_REQUIRED=11; DENIED=16; NO_EVIDENCE=15; NO_RESULTS=5; PARTIAL_MIXED_TERMINALS=5; SUGGESTED_ACTION=3; UNAVAILABLE=5; UNSUPPORTED=5`.
- Branch intents: `cart_action_request=1; catalog_listing=1; knowledge_query=2; missing_evidence_query=2; order_read=1; price=9; privileged_mutation=3; product_detail=5; shipping_current_value=4; stock_availability=6; store_contact=5; unsupported_ood=1`.
- Branch terminals: `ANSWER=32; ANSWER_OR_NOT_FOUND=1; DENIED=3; NO_EVIDENCE=2; SUGGESTED_ACTION=1; UNSUPPORTED=1`.
- Branch security: `ALLOW=36; DENY=3; SUGGEST_ONLY=1`.
- Scenario final intents: `cart_action_request=2; clarification=5; price=5; product_detail=2; shipping_calculation=2; stock_availability=4`.
- Scenario terminals: `ANSWER=13; CLARIFICATION_REQUIRED=5; SUGGESTED_ACTION=2`.
- Scenario security decisions: `ALLOW_READ=13; NOT_APPLICABLE=5; SUGGEST_ONLY=2`.
- Primary security decisions: `ALLOW_OWNED_READ=8; ALLOW_READ=85; ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES=3; DENY=10; DENY_CROSS_ACCOUNT=6; NOT_APPLICABLE=21; PER_BRANCH=17; REQUIRE_AUTH=7; SUGGEST_ONLY=3`.
- Primary security capability vector was recomputed and is unchanged across its 20 capability values.
- True OOD: primary `5`, branches `1`. `NO_EVIDENCE`: primary `15`, branches `2`, scenarios `0`.

## 12. Double-pass and manual review

Pass 1 validated the written R3 artifact after remediation. Pass 2 created a fresh JSON reload from disk and repeated the 240-unit and 240-account-target expected-map checks. Both passes produced identical structural counts, entity counts, and zero invariant failures.

Manual review covered all 17 changed fields and all 45 surviving non-null values (26 unit and 19 account target), which is stronger than a sample-only review. The review independently confirmed surface/target evidence and did not change the automated criteria.

- Pass 1: **PASS**
- Fresh reload pass 2: **PASS**
- Entity distributions identical across passes: **PASS**
- Failure count across both passes: **0**
- Manual review: **PASS**

## 13. Final decision

**V11 R3 FINAL ENTITY CLEANUP FREEZE COMPLETE**

- Unit invariant: **PASS**
- Account-target invariant: **PASS**
- Known R2 defects closed: **PASS**
- Additional same-class defects fixed: **0**
- Change scope: **PASS**
- Claim/authority regression: **PASS**
- Security regression: **PASS**
- Double-pass validation: **PASS**
- Candidate executed: **NO**
- Historical V0-V10 data accessed: **NO**
- Freeze: **FROZEN**

The report SHA-256 is recorded in the freeze manifest after this file's final byte is written.
