# V11 R3 Fresh Independent Audit Report

## 1. Executive summary

**AUDIT PASS — FINAL V11 FROZEN**

Frozen V11 Author R3 is hash-intact, has the declared R2 lineage, changes only `unit` and `account_target`, and preserves every primary and scenario-turn utterance byte-for-byte. The fresh audit covered all 240 entity objects, all available V0–V10 utterance-bearing development data, every factual/policy `ANSWER`, every `NO_EVIDENCE` scope, authority/source pins, authorization/security, multi-intent scope, follow-up scope, terminal/outcome gold, and structural schema.

The R2 defects are closed. All 26 non-null units have direct surface provenance. All 19 non-null account targets have valid utterance-semantic provenance. No missing or spurious value remains in either dimension.

`TRUE_LEAKAGE = 0`; `UNCERTAIN = 0`. Candidate executed: **NO**.

## 2. Auditor independence scope

The audit read only:

- frozen V11 R2 and R3 artifacts needed for integrity and semantic diff;
- frozen sanitized schema, business, product, SiteSetting, knowledge, and authorization artifacts;
- V0–V9 utterance-bearing fixtures and the seven V10 dataset revisions, only for leakage comparison.

The audit did not read Phase 14 analysis, candidate predictions, candidate scores, chatbot/runtime implementation, router/extractor/guard/controller/handler bodies, or RAG/Qdrant implementation. It did not run the chatbot, candidate, staging, or production requests. It did not modify R2 or R3.

## 3. R3 freeze verification

All requested hashes match exactly.

| Artifact | Recalculated SHA-256 | Status |
|---|---|---|
| `v11-author-r3-candidate-dataset.json` | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | PASS |
| `v11-author-r3-report.md` | `9129c9c873b0a190fe08c02de9202df2f9f948f48a727b1f32f3d93358aec320` | PASS |
| `v11-author-r3-freeze-manifest.json` | `a0776b882fbdffdb84f676b60b38298f3cbc55070001d79124adbc1e8bc5b295` | PASS |
| R2 dataset | `b89b78105caad702ebefe1a301f6a1b91a64cf13b5af7659a091e562de9320aa` | PASS |
| R2 report | `dc6283e475ed688d6dce5bef364557eaa39585c0b00dff6960eb7a40ae1f4de7` | PASS |
| R2 manifest | `a1cbcaf6cee1e7640fbb65e234cd03618a70db63b40bc6830399265a5471caaa` | PASS |

Both manifests declare `FROZEN`. R3 declares revision `V11-author-r3-final-entity-cleanup`, parent `V11-author-r2`, the expected lineage, and the exact R2 artifact hashes. JSON parsing passed.

## 4. Sanitized authority verification

| Artifact | Recalculated SHA-256 | Status |
|---|---|---|
| Schema contract | `2d1e6cd805ed9914d6811f130f9c1cc0909eb2f6707e30f5f66b13c61f6bea5d` | PASS |
| Business contract | `ddaf57dcb1fd0db48b7466370c498d52db729cf8a4714f99c0098548fc1e4b7b` | PASS |
| Product authority | `809923e43f8919446b8123ea3ab0cac2f39cc3b50d89bad89f584d3768799f0a` | PASS |
| SiteSetting authority | `92e69a3c93bfef417fce7ab76a12e191558cbbe50ec9e645c6422e69bb69f2cb` | PASS |
| Knowledge registry | `dc8b013d3235a8fb04cedda63575354ab25fd6482316798952ff7161e6d599e7` | PASS |
| Authorization contract | `de05e7d4ebd0429b20c73fd9bb0927a9012a9e358b80a195c878549e56639dc4` | PASS |

The R3 schema version is `farta-v10.3.0`, compatible with the frozen sanitized schema. Dataset-local product, SiteSetting, published-knowledge, and synthetic owned-order sources match their frozen authority snapshots, versions, and privacy boundaries.

## 5. R2 to R3 semantic diff

The recursive dataset diff contains exactly 17 field changes in exactly 17 entity objects. There are no additions, removals, reorderings, schema changes, or behavior-gold changes.

| Scope | Field | R2 | R3 |
|---|---|---:|---:|
| `v11r1-p043` | `account_target` | `mình` | `null` |
| `v11r1-p046` | `account_target` | `mình` | `null` |
| `v11r1-p072` | `account_target` | `cua toi` | `null` |
| `v11r1-p092` | `unit` | `items` | `null` |
| `v11r1-p093` | `unit` | `items` | `null` |
| `v11r1-p098` | `unit` | `boxes` | `null` |
| `v11r1-p108` | `unit` | `items` | `product` |
| `v11r1-p121` | `unit` | `quả` | `null` |
| `v11r1-p128` | `account_target` | `account mình` | `null` |
| `v11r1-p148` parent | `unit` | `quả` | `null` |
| `v11r1-p148-b1` | `unit` | `quả` | `null` |
| `v11r1-p149` parent | `unit` | `null` | `hop` |
| `v11r1-p149-b2` | `unit` | `null` | `hop` |
| `v11r1-s08/t2` | `unit` | `null` | `phần` |
| `v11r1-s14/t2` | `unit` | `items` | `null` |
| `v11r1-s16/t2` | `unit` | `items` | `item` |
| `v11r1-s20/t2` | `unit` | `item` | `null` |

All other fields, including utterance, product slots, quantity, references, mutation value, intent, handler, capability, operation, terminal, outcome, auth, security expectation, evidence domain, sources, claim support, and schema, are unchanged. Change-scope gate: **PASS**.

## 6. Zero-utterance-change check

All 160 primary utterances and all 40 scenario-turn utterances are bytewise identical between R2 and R3. This includes all 20 multi-intent parent utterances.

Utterance changes: **0**.

## 7. Historical leakage coverage V0–V10

The audit extracted `query`, `initial`, and `utterance` carriers, utterance-keyed multi-intent/grounding maps, and standalone missing-evidence utterances from every executable V0–V9 fixture. For V10 it scanned the primary and scenario-turn utterances in all seven dataset revisions.

| Version | Raw instances | Version-scoped unique |
|---|---:|---:|
| V0 | 187 | 187 |
| V1 | 130 | 125 |
| V2 | 144 | 137 |
| V3 | 159 | 152 |
| V4 | 216 | 209 |
| V5 | 259 | 224 |
| V6 | 284 | 241 |
| V7 | 410 | 309 |
| V8 | 331 | 276 |
| V9 | 328 | 262 |
| V10, seven revisions combined | 2,590 | 401 union-unique |

Total scanned historical instances: **5,038**. Union-unique historical utterances: **2,521**. Historical versions covered: **V0–V10**.

## 8. Leakage adjudication

Raw exact comparison found one match: `v11r1-s06/t2`, `Còn giá?`, in V10. It is a minimal natural commerce follow-up and is classified `CANONICAL_SHORT_PHRASE`.

NFKC/case/whitespace-only matches beyond the raw match: **0**. Accent-fold-only full-string matches: **0**. Accent-folded token comparison surfaced `v11r1-s11/t2`, `Mon vua noi gia sao?`, against `món vừa nói giá sao`; it is a short ordinary follow-up and is classified `CANONICAL_SHORT_PHRASE`.

Token Jaccard/containment/ordered-LCS screening produced eight pair signals. They concern short product search, price, stock, add-to-cart, and account-verification wording at `v11r1-p002`, `v11r1-p019`, `v11r1-s01/t2`, `v11r1-s05/t2`, `v11r1-s07/t2`, and `v11r1-s11/t2`. Manual review classified each as `CANONICAL_SHORT_PHRASE` or `BENIGN_DOMAIN_OVERLAP`; none copies distinctive authored structure.

Product/number/reference placeholder comparison produced 12 historical pair instances across three R3 scopes: `v11r1-p005`, `v11r1-p089`, and `v11r1-s11/t2`. These are canonical current-price or short follow-up templates.

`TRUE_LEAKAGE = 0`. `UNCERTAIN = 0`. Leakage gate: **PASS**.

## 9. Internal generated-data quality

Internal raw, normalized, and accent-fold duplicates are all zero. Two near pairs were found: `v11r1-p001` versus `v11r1-s01/t1`, and `v11r1-p113` versus `v11r1-s17/t1`. Both are benign same-product search phrasings. Product-substitution template duplicates: **0**.

Primary language buckets are perfectly balanced. Canonical-product entity occurrences range from 9 to 17 across all 11 frozen products. No handler, terminal, gold-field, or security-label leakage was found in utterances. Generated-data quality: **ACCEPTABLE**.

## 10. Full 240-entity audit

All nine entity slots were audited in every scope:

| Collection | Entity objects | Status |
|---|---:|---|
| Primary parents | 160 | PASS |
| Multi-intent branches | 40 | PASS |
| Scenario turns | 40 | PASS |
| Total | 240 | PASS |

`product_raw_mention`, `canonical_product`, quantity, unit, order reference, ordinal reference, context reference, account target, and requested mutation value all pass their type, surface/context, authority, forward, reverse, and scope checks.

## 11. Unit forward/reverse invariants

Explicit schema-relevant unit scopes: **26**. Correct: **26**. Missing: **0**. Incorrect: **0**.

Non-null units: **26**. Surface-provenanced: **26**. Context-inherited: **0**. Frozen-normalization-rule: **0**. Spurious: **0**. Unauthorized normalization: **0**.

The three R2 forward failures are closed: `v11r1-p149`, `v11r1-p149-b2`, and `v11r1-s08/t2`. The ten R2 reverse failures are closed: `v11r1-p092`, `p093`, `p098`, `p108`, `p121`, `p148`, `p148-b1`, `s14/t2`, `s16/t2`, and `s20/t2`.

## 12. Unit provenance table

The following CSV is the machine-readable unit audit table. `normalization_rule_id` is empty because no R3 unit depends on normalization.

```csv
scope,unit_value,provenance_type,surface_or_context_evidence,normalization_rule_id,status
v11r1-p008,quả,SURFACE,"12 quả Chuối",,PASS
v11r1-p009,phần,SURFACE,"26 phần Rau Củ Tươi",,PASS
v11r1-p012,hộp,SURFACE,"3 hộp Sữa Hộp",,PASS
v11r1-p014,quả,SURFACE,"2 quả Dưa hấu",,PASS
v11r1-p033,phần,SURFACE,"một phần",,PASS
v11r1-p036,trái,SURFACE,"18 trái Ổi",,PASS
v11r1-p037,hộp,SURFACE,"41 hộp Sữa Hộp",,PASS
v11r1-p040,phần,SURFACE,"2 phần Hamburger",,PASS
v11r1-p042,quả,SURFACE,"4 quả Chuối",,PASS
v11r1-p052,cái,SURFACE,"3 cái",,PASS
v11r1-p061,hop,SURFACE,"Mot hop Sua Hop",,PASS
v11r1-p064,trai,SURFACE,"20 trai Cam Tuoi",,PASS
v11r1-p065,phan,SURFACE,"21 phan Thit bo nat",,PASS
v11r1-p068,qua,SURFACE,"5 qua Chuoi",,PASS
v11r1-p096,units,SURFACE,"4 units of Ổi",,PASS
v11r1-p108,product,SURFACE,"two of the product",,PASS
v11r1-p118,phần,SURFACE,"một phần Hamburger",,PASS
v11r1-p120,hộp,SURFACE,"9 hộp Sữa Hộp",,PASS
v11r1-p126,quả,SURFACE,"3 quả Ổi",,PASS
v11r1-p149,hop,SURFACE,"may hop",,PASS
v11r1-p149-b2,hop,SURFACE,"may hop",,PASS
v11r1-s05/t2,cái,SURFACE,"2 cái đó",,PASS
v11r1-s08/t2,phần,SURFACE,"mấy phần",,PASS
v11r1-s09/t2,hop,SURFACE,"3 hop",,PASS
v11r1-s14/t1,unit,SURFACE,"one unit of Chuối",,PASS
v11r1-s16/t2,item,SURFACE,"the first item",,PASS
```

## 13. Account-target forward/reverse invariants

Explicit or valid private-possession account-target scopes: **19**. Correct: **19**. Missing: **0**. Incorrect: **0**. Spurious: **0**.

The four R2 reverse failures are closed: `v11r1-p043`, `v11r1-p046`, `v11r1-p072`, and `v11r1-p128` are null in R3 because their former values came only from requester/authentication language.

## 14. Account-target provenance table

```csv
scope,value,utterance_evidence,target_relation,provenance_category,status
v11r1-p015,"tài khoản của tôi","tài khoản của tôi",self account,EXPLICIT_SELF,PASS
v11r1-p016,"tài khoản của tôi","đơn hàng của mình",self-owned order history,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p017,"khách-hang-khac","tài khoản khách-hang-khac",named other account,EXPLICIT_IDENTIFIER,PASS
v11r1-p044,mình,"đơn của mình",self-owned order,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p045,minh-anh,"đơn EXT-208 của bạn minh-anh",named other owner,EXPLICIT_IDENTIFIER,PASS
v11r1-p055,demo-buyer,"tài khoản demo-buyer",named account,EXPLICIT_IDENTIFIER,PASS
v11r1-p071,"cua toi","don OWN-ALPHA cua toi",self-owned order,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p073,user-khac,"tai khoan user-khac",named other account,EXPLICIT_IDENTIFIER,PASS
v11r1-p074,nguoi-dung-b,"don EXT-901 cua nguoi-dung-b",named other owner,EXPLICIT_IDENTIFIER,PASS
v11r1-p083,thu-nghiem,"tai khoan thu-nghiem",named account,EXPLICIT_IDENTIFIER,PASS
v11r1-p099,"my account","my order OWN-BRAVO",self-owned order,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p100,"my account","my order history",self-owned order history,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p101,other-shopper,"account other-shopper",named other account,EXPLICIT_IDENTIFIER,PASS
v11r1-p102,"my account","my OWN-ALPHA order",self-owned order,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p127,"account mình","account mình",self account,EXPLICIT_SELF,PASS
v11r1-p129,buyer-two,"account buyer-two",named other account,EXPLICIT_IDENTIFIER,PASS
v11r1-p137,"account mình","order kia của mình",self-owned order,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p156,"my account","my OWN-BRAVO order",self-owned order,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
v11r1-p156-b1,"my account","my OWN-BRAVO order",self-owned read branch,VALID_PRIVATE_RESOURCE_POSSESSION,PASS
```

Provenance totals: `EXPLICIT_SELF = 2`, `EXPLICIT_IDENTIFIER = 8`, `VALID_PRIVATE_RESOURCE_POSSESSION = 9`, `EXPLICIT_OTHER = 0`.

## 15. Quantity and other entity-slot regression

R2 to R3 quantity changes: **0**. Quantity forward and reverse audits pass for all 33 non-null purchase/item quantities. Indefinite counts such as `mấy phần` and `may hop` correctly retain a unit while leaving numeric quantity null.

R2 to R3 changes are also zero for `product_raw_mention`, `canonical_product`, `order_reference`, `ordinal_reference`, `context_reference`, and `requested_mutation_value`. All raw mentions are surface-supported, all canonical products exist in current product authority, all order/context references are explicit or validly context-bound, all ordinals are non-zero and resolve correctly, and all six mutation values remain separated from purchase quantity.

## 16. Multi-intent entity scope

All 20 parents and 40 branches pass parent-shared, branch-local, and not-applicable classification.

- `v11r1-p148`: the utterance has quantity `2` but no surface unit; parent and cart branch correctly keep unit null, and the OOD sibling receives neither product quantity nor unit.
- `v11r1-p149`: `hop` is present in the parent union and stock branch only; it does not leak into the price branch.
- `v11r1-p156`: `my account` is present in the parent and owned-read branch; the cancellation branch carries the order/context reference without receiving the branch-local account target.

No unit or account target leaks to an unrelated sibling. Multi-intent entity scope: **PASS**.

## 17. Follow-up entity scope

All 20 scenarios and 40 turns pass current-turn entity capture, contextual product resolution, ordinal resolution, ambiguity, expiry, and unit/account inheritance checks.

Corrected scopes `v11r1-s08/t2`, `s14/t2`, `s16/t2`, and `s20/t2` now preserve only surface-supported units. No scenario turn invents an account target from speaker identity, session state, or cart language. Follow-up entity scope: **PASS**.

## 18. Product authority and deterministic calculations

All 122 non-null canonical-product entity objects resolve to one of the 11 active products in active categories in frozen product authority. Prices, stock comparisons, category membership, and active-only result sets match the snapshot.

Shipping fee is 20,000 VND and the free-shipping threshold is a merchandise subtotal of 200,000 VND. Every deterministic subtotal, applicable fee, threshold decision, and final total recomputes correctly. Public contact facts match frozen SiteSetting authority.

## 19. Authorization and security

Authorization gold independently matches the frozen authorization contract:

- anonymous private reads require authentication;
- authenticated owners receive read-only owned-order/payment handling;
- six cross-account private reads are denied without private evidence exposure;
- all 13 privileged-mutation scopes are denied across inventory, order, payment, role, auth-bypass, and secret-disclosure classes;
- verified cart requests remain suggestions only and require external confirmation;
- benign stock, order, payment, account-like, product, and settings reads are not converted into privileged mutations.

Entity understanding never grants permission. Authorization, cross-account, privileged, and benign-read gates: **PASS**.

## 20. OOD and clarification

Six primary/branch scopes are genuine out-of-domain tasks and terminate `UNSUPPORTED`. They remain distinct from denied, no-evidence, authentication-required, and clarification cases.

Clarification is limited to missing products, missing cart quantity, missing/ambiguous private reference, ambiguous context, expired context, or unusable price constraints. No frozen authority already resolves those cases. Both gates: **PASS**.

## 21. Claim support and NO_EVIDENCE

All 105 factual/policy `ANSWER` scopes were audited: 60 non-composite primary cases, 32 branches, and 13 scenario-final scopes. Every requested fact has a domain-correct eligible source, matching version pin, and non-empty minimum facts directly supported by that source. Unsupported authoritative answers: **0**.

All 17 `NO_EVIDENCE` scopes were audited: 15 non-composite primary cases and two branches. Each requests a fact absent from current eligible frozen authority, declares the appropriate evidence domain, and has empty sources and minimum facts. False abstentions: **0**.

Dataset-local source IDs exist, match their authority type/version, and mirror the frozen product, SiteSetting, knowledge, or synthetic owned-order source. Grounding domains, source sets, terminal values, and source-version pins match their outer records. Claim support, NO_EVIDENCE, source authority, and evidence-domain gates: **PASS**.

## 22. Terminal and business outcome

Terminal and business-outcome gold was audited independently from intent. Private reads, security denials, no-evidence results, OOD, clarification, stock insufficiency, cart suggestions, and factual answers use the correct terminal semantics. Composite aggregation is coherent: 15 `ALL_BRANCHES_HANDLED` and five `PARTIAL_MIXED_TERMINALS` parents.

## 23. Structural schema and double-pass validation

JSON validity, required fields, exact nine-slot entity shapes, types/nullability, enum values, unique IDs, two-turn scenario structure, multi-intent branch structure, source registration, version pins, grounding mirrors, schema mirrors, and manifest counts all pass.

The auditor loaded the frozen R3 artifact from disk and ran the complete invariant suite twice. Both fresh reload passes returned zero errors with the same counts: 160 primary, 20 scenarios, 40 turns, 20 multi-intent parents, 40 branches, and 240 entity objects.

## 24. Distribution

Primary languages remain exactly balanced at 32 each for `vi_formal`, `vi_conversational`, `vi_no_diacritics`, `en`, and `mixed`. Scenarios remain exactly balanced at four each.

Primary capability distribution:

`cart_action_request 5; cart_informational 5; catalog_listing 5; clarification 10; general_chat 5; knowledge_query 10; missing_evidence_query 10; multi_intent 20; order_read 15; payment_status_read 5; price 10; privileged_mutation 10; product_detail 10; product_search 10; shipping_calculation 5; shipping_current_value 5; stock_availability 15; unsupported_ood 5`.

Branch capability distribution:

`cart_action_request 1; catalog_listing 1; knowledge_query 2; missing_evidence_query 2; order_read 1; price 9; privileged_mutation 3; product_detail 5; shipping_current_value 4; stock_availability 6; store_contact 5; unsupported_ood 1`.

Scenario-turn capability distribution:

`cart_action_request 2; clarification 5; price 5; product_detail 2; product_search 20; shipping_calculation 2; stock_availability 4`.

Primary terminal distribution:

`ANSWER 65; NO_RESULTS 5; NO_EVIDENCE 15; UNAVAILABLE 5; SUGGESTED_ACTION 3; ANSWER_OR_NOT_FOUND 8; AUTH_REQUIRED 7; DENIED 16; CLARIFICATION_REQUIRED 11; UNSUPPORTED 5; ALL_BRANCHES_HANDLED 15; PARTIAL_MIXED_TERMINALS 5`.

Branch terminal distribution:

`ANSWER 32; ANSWER_OR_NOT_FOUND 1; SUGGESTED_ACTION 1; DENIED 3; NO_EVIDENCE 2; UNSUPPORTED 1`.

Scenario-final terminal distribution:

`ANSWER 13; SUGGESTED_ACTION 2; CLARIFICATION_REQUIRED 5`.

Primary security decisions:

`ALLOW_READ 85; SUGGEST_ONLY 3; ALLOW_OWNED_READ 8; REQUIRE_AUTH 7; DENY_CROSS_ACCOUNT 6; NOT_APPLICABLE 21; DENY 10; PER_BRANCH 17; ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES 3`.

Branch security decisions: `ALLOW 36; DENY 3; SUGGEST_ONLY 1`. Scenario security decisions: `ALLOW_READ 13; NOT_APPLICABLE 5; SUGGEST_ONLY 2`.

The holdout remains structurally suitable. No new distribution threshold was invented.

## 25. R2 to R3 entity-count delta

Counts cover all 240 entity objects.

| Entity | R2 | R3 | Delta |
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

The count deltas are fully explained by the 13 unit changes and four account-target changes in the authorized remediation scope.

## 26. Final counts

| Collection | Count | Expected | Status |
|---|---:|---:|---|
| Primary | 160 | 160 | PASS |
| Scenarios | 20 | 20 | PASS |
| Scenario turns | 40 | 40 | PASS |
| Multi-intent parents | 20 | 20 | PASS |
| Multi-intent branches | 40 | 40 | PASS |
| Entity objects audited | 240 | 240 | PASS |

## 27. Final dataset

The final audited dataset is a byte-for-byte copy of frozen R3. No correction was made during or after the audit.

Path: `docs/evaluation/v11-independent/v11-final-audited-dataset.json`

SHA-256: `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb`

## 28. Audit report hashing

The report is hashed only after its final byte is written. Its SHA-256 is recorded in `v11-final-manifest.json`; embedding it here would change the report hash.

## 29. Final decision

**AUDIT PASS — FINAL V11 FROZEN**

R3 hashes, lineage, change scope, utterance stability, historical leakage, all 240 entity objects, unit and account-target provenance, quantity and other entity regressions, multi-intent/follow-up scope, authority, authorization/security, claim support, NO_EVIDENCE, SiteSetting/shipping, terminal/outcome, schema, generated-data quality, and distribution all pass.

Candidate executed: **NO**. Freeze: **FROZEN**.
