# Farta Market V11 R1 Fresh Independent Leakage, Gold & Release-Holdout Audit

Audit time (UTC): `2026-09-27T09:38:35Z`

Decision: **AUDIT FAIL — ENTITY GOLD / MULTI-INTENT ENTITY SCOPE**

Candidate executed: **NO**

## 1. EXECUTIVE SUMMARY

The frozen V11 Author R1 artifacts and every frozen sanitized authority artifact match their expected SHA-256 values. All JSON artifacts parse, manifest lineage is intact, and the required structural counts are present: 160 primary cases, 20 scenarios, 40 scenario turns, 20 multi-intent parents, and 40 branches.

Historical coverage includes the utterance-bearing V0–V9 fixtures and all V10 dataset revisions through the final audited V10 dataset. Across the enumerated sources there are 4,885 utterance occurrences and 2,517 unique normalized utterances. One raw/normalized equality, one additional accent-folded equality, and four exact structural-skeleton families were manually adjudicated. They are short canonical commerce/follow-up formulations or benign domain overlap. `TRUE_LEAKAGE = 0`.

Schema, product authority, authorization/security, claim support, NO_EVIDENCE, source authority, evidence domain, deterministic SiteSetting arithmetic, and terminal/business-outcome checks pass.

The audit fails because explicit entity semantics are missing or incorrect in twelve records/turns:

- explicit item counts/units disappear in `v11r1-p033`, `v11r1-p061`, `v11r1-p089`, `v11r1-p118`, and `v11r1-s14/t1`;
- unit gold conflicts with or invents information beyond the utterance in `v11r1-s05/t2` and `v11r1-p124`;
- `account_target` is inferred without an account target represented by the utterance in `v11r1-p018`, `v11r1-p025`, `v11r1-p081`, and `v11r1-p130`;
- the explicit self target in the multi-intent parent `v11r1-p156` is absent from the parent entity gold even though it is retained in branch 1.

Per the audit contract, V11 Author R1 was not modified. No final V11 dataset or final manifest was created.

## 2. AUDITOR INDEPENDENCE SCOPE

The audit read only:

- the frozen V11 Author R1 dataset, report hash target, and freeze manifest;
- the frozen sanitized schema, business, product, SiteSetting, knowledge, and authorization authorities;
- V0–V10 fixture utterances for leakage comparison.

The audit did not inspect Phase 14 reports or baselines, candidate predictions, candidate/runtime source, router/extractor/guard/controller/handler implementation, or RAG/Qdrant implementation. No chatbot or candidate execution occurred. Historical labels were not used to determine V11 gold.

The two `tests/Fixtures/v10_evaluator*_development_raw_results.php` files contain no `utterance`, `query`, or `initial` fields and were excluded; their candidate-result payloads were not inspected.

## 3. V11 R1 FREEZE VERIFICATION

| Artifact | Expected SHA-256 | Recalculated SHA-256 | Status |
|---|---|---|---|
| `v11-author-r1-candidate-dataset.json` | `81214b6d7fb877116d6e19298410c2d2c64096875e813d2a2139a6dfe926240a` | `81214b6d7fb877116d6e19298410c2d2c64096875e813d2a2139a6dfe926240a` | PASS |
| `v11-author-r1-report.md` | `ea117f758ab9b6407f08d10349fa728988f8a3b921432ff35afc72f0c5ba2ee5` | `ea117f758ab9b6407f08d10349fa728988f8a3b921432ff35afc72f0c5ba2ee5` | PASS |
| `v11-author-r1-freeze-manifest.json` | `bca7f76f94720425cbde2a2c7c718747654cbac9c326ceec16955e2ff8fa79c9` | `bca7f76f94720425cbde2a2c7c718747654cbac9c326ceec16955e2ff8fa79c9` | PASS |

All JSON parses. The manifest pins the verified sanitized inputs, the Author R1 dataset and report hashes, schema compatibility `farta-v10.3.0`, and the expected counts. Lineage status: **PASS**.

## 4. SANITIZED AUTHORITY VERIFICATION

| Artifact | Recalculated SHA-256 | Status |
|---|---|---|
| `v11-sanitized-schema-contract.json` | `2d1e6cd805ed9914d6811f130f9c1cc0909eb2f6707e30f5f66b13c61f6bea5d` | PASS |
| `v11-sanitized-schema-contract.md` | `216e1f80789770d4f48e6a852d05ee1e7b4909b035b9b223107a4afc5f31b57b` | PASS |
| `v11-sanitized-schema-freeze-manifest.json` | `d5eddc122a536cace2e7ac773f1f686ddaaa96e6178902d65acfa88b43c7be06` | PASS |
| `v11-sanitized-business-capability-contract.json` | `ddaf57dcb1fd0db48b7466370c498d52db729cf8a4714f99c0098548fc1e4b7b` | PASS |
| `v11-sanitized-business-capability-contract.md` | `f647a0554563366e160a040870a6ad493edc7baef90d2ea1baff3a03f6bfc9e6` | PASS |
| `v11-sanitized-product-authority.json` | `809923e43f8919446b8123ea3ab0cac2f39cc3b50d89bad89f584d3768799f0a` | PASS |
| `v11-sanitized-sitesetting-authority.json` | `92e69a3c93bfef417fce7ab76a12e191558cbbe50ec9e645c6422e69bb69f2cb` | PASS |
| `v11-sanitized-knowledge-registry.json` | `dc8b013d3235a8fb04cedda63575354ab25fd6482316798952ff7161e6d599e7` | PASS |
| `v11-sanitized-authorization-contract.json` | `de05e7d4ebd0429b20c73fd9bb0927a9012a9e358b80a195c878549e56639dc4` | PASS |
| `v11-sanitized-business-contract-freeze-manifest.json` | `440f40b5dfc91fd06e1751d7231883503ff1e6d2246a900dbddd1a53c2efd5fa` | PASS |

Dataset-local copies of product, SiteSetting, and published-knowledge facts match the frozen sanitized snapshots. Authority integrity: **PASS**.

## 5. HISTORICAL COVERAGE V0–V10

Normalization used Unicode NFKC, Unicode case folding, punctuation-to-space normalization, and whitespace collapse without removing semantic words.

| Version | Source/fixture | Utterance occurrences | Unique normalized utterances |
|---|---|---:|---:|
| V0 | `tests/Fixtures/chat_evaluation.php` | 187 | 187 |
| V1 | `tests/Fixtures/chat_blind_holdout.php` | 130 | 125 |
| V2 | `tests/Fixtures/chat_blind_v2.php` | 144 | 137 |
| V3 | `tests/Fixtures/chat_blind_v3.php` | 159 | 151 |
| V4 | `tests/Fixtures/chat_blind_v4.php` | 216 | 207 |
| V5 | `tests/Fixtures/chat_blind_v5.php` | 259 | 224 |
| V6 | `tests/Fixtures/chat_blind_v6.php` | 256 | 241 |
| V7 | `tests/Fixtures/chat_blind_v7.php` | 326 | 309 |
| V8 | `tests/Fixtures/chat_blind_v8.php` | 310 | 276 |
| V9 | `tests/Fixtures/chat_blind_v9.php` | 308 | 262 |
| V10 R1 | `docs/evaluation/v10-independent/v10-candidate-dataset.json` | 370 | 370 |
| V10 R2 | `docs/evaluation/v10-independent/v10-r2-candidate-dataset.json` | 370 | 370 |
| V10 R3 | `docs/evaluation/v10-independent/v10-r3-candidate-dataset.json` | 370 | 370 |
| V10 R4 | `docs/evaluation/v10-independent/v10-r4-candidate-dataset.json` | 370 | 370 |
| V10 R5 | `docs/evaluation/v10-independent/v10-r5-candidate-dataset.json` | 370 | 370 |
| V10 R6 | `docs/evaluation/v10-independent/v10-r6-candidate-dataset.json` | 370 | 370 |
| V10 final | `docs/evaluation/v10-independent/v10-final-audited-dataset.json` | 370 | 370 |

The V10 final file is byte-identical to V10 R6 and is retained as a separately enumerated source. Corpus totals are 4,885 occurrences, 2,521 raw-unique strings, and 2,517 normalized-unique strings. Historical coverage: **COMPLETE / PASS**.

## 6. EXACT DUPLICATES

One raw equality was found:

| V11 item | Historical item | Text | Adjudication |
|---|---|---|---|
| `v11r1-s06/t2` | V10 R1 `V10-MT-027/t2` | `Còn giá?` | `CANONICAL_SHORT_PHRASE` |

This two-word ellipsis is a natural minimal follow-up and does not establish copying. `TRUE_LEAKAGE`: **0**. Exact audit: **PASS**.

## 7. NORMALIZED DUPLICATES

The only normalized equality is the same `v11r1-s06/t2` raw equality above. No additional NFKC/case/punctuation/whitespace equality was found. `TRUE_LEAKAGE`: **0**. Normalized audit: **PASS**.

## 8. ACCENT-FOLDED RESULTS

Accent folding was used only as a signal. It produced two equality signals: the already reported raw equality and one accent-only equality:

| V11 item | Historical item | V11 text | Historical text | Adjudication |
|---|---|---|---|---|
| `v11r1-s11/t2` | V5 `follow_up[19].query` | `Mon vua noi gia sao?` | `món vừa nói giá sao` | `CANONICAL_SHORT_PHRASE` |

The wording is a generic price follow-up with no distinctive product, policy, identifier, or clause sequence. Accent-folded audit: **PASS; 0 TRUE_LEAKAGE**.

## 9. NEAR-DUPLICATE RESULTS

Token Jaccard was computed on transparently normalized tokens with threshold `>= 0.82`. Exact/normalized equalities were handled separately. No token-Jaccard flag met the threshold. Status: **PASS**.

## 10. STRUCTURAL / TEMPLATE LEAKAGE

For analysis only, Vietnamese accents were folded and known product names, quantities, politeness markers, and synthetic order/account identifiers were abstracted. Four exact structural families were found:

| V11 item | Historical source | Structural relationship | Adjudication |
|---|---|---|---|
| `v11r1-p005` | V8 generated price family | `Giá hiện tại của PRODUCT là bao nhiêu` | `BENIGN_DOMAIN_OVERLAP` |
| `v11r1-p089` | V6 price case | `How much is QTY PRODUCT right now` | `CANONICAL_SHORT_PHRASE` |
| `v11r1-s06/t2` | V10 R1 follow-up | exact two-word ellipsis | `CANONICAL_SHORT_PHRASE` |
| `v11r1-s11/t2` | V5 follow-up | accent-only generic follow-up | `CANONICAL_SHORT_PHRASE` |

Structural Jaccard produced ten pairwise flags across `v11r1-p001`, `v11r1-p005`, `v11r1-p063`, `v11r1-p070`, `v11r1-s01/t1`, `v11r1-s06/t1`, `v11r1-s10/t1`, and `v11r1-s17/t1`. Manual review found only conventional find/check/add-to-cart and list-product grammar, with no distinctive historical payload or uncommon clause sequence. These were classified as `CANONICAL_SHORT_PHRASE` or `BENIGN_DOMAIN_OVERLAP`.

Structural/template leakage status: **PASS; 0 TRUE_LEAKAGE**.

## 11. INTERNAL GENERATED-DATA QUALITY

V11 contains 200 utterance-bearing items, all raw-unique and normalized-unique. No internal token-Jaccard pair reaches `0.82`. Two small internal structural families exist: the product-search setup shared by `v11r1-p001`/`v11r1-s01/t1`, and the current-price construction shared by `v11r1-p005`/`v11r1-s01/t2`.

The first 140 primary cases use the same 28-intent coverage matrix across five language buckets. This symmetry is visibly generated, but the surface language remains generally natural, product use is spread across all 11 authority products, and there is no label text embedded in utterances. Security/OOD wording is synthetic but appropriate to the evaluated capability boundary. Generated-data quality: **ACCEPTABLE WITH NON-BLOCKING TEMPLATE-SYMMETRY NOTE**.

## 12. SCHEMA AUDIT

An independent validator checked required fields, field types, nullability, all exposed enums, exact nine-slot entity objects, source registration/version pins, grounding mirrors, deterministic-gold allowlists, multi-intent branch shape, scenario/turn shape, IDs, and declared schema-contract arrays.

Schema validation errors: **0**. Schema status: **PASS**.

## 13. ENTITY CONTRACT

Entity contract status: **FAIL**.

| Item | Expected semantic representation | Observed defect | Category |
|---|---|---|---|
| `v11r1-p033` | Preserve explicit item count `1` and explicit unit `phần` | `quantity=null`, `unit=null` | missing explicit quantity/unit |
| `v11r1-p061` | Preserve explicit item count `1` and explicit unit `hop` | `quantity=null`, `unit=null` | missing explicit quantity/unit |
| `v11r1-p089` | Preserve explicit item count `1` | `quantity=null` | missing explicit quantity |
| `v11r1-p118` | Preserve explicit item count `1` and explicit unit `phần` | `quantity=null`, `unit=null` | missing explicit quantity/unit |
| `v11r1-s14/t1` | Preserve explicit item count `1` and explicit `unit` mention | `quantity=null`, `unit=null` | missing explicit quantity/unit |
| `v11r1-s05/t2` | Preserve the explicitly stated generic unit `cái` | gold changes it to `quả` without a declared unit-normalization authority | incorrect unit |
| `v11r1-p124` | Leave an unstated unit unresolved | gold invents `unit=phần` | spurious unit |

The five missing-count cases remain defects even though their terminal is a read: the sanitized schema defines quantity as a positive purchase or item quantity, and its invariants require represented explicit entities to remain represented independently of business outcome.

## 14. PRODUCT AUTHORITY

Every non-null canonical product in primary, branch, turn, final-scenario, and context gold exists in the frozen product authority. All 11 products and their categories are active. Current price/stock facts and deterministic canonical matches agree with the snapshot. Product authority status: **PASS**.

## 15. QUANTITY / MUTATION VALUE

Status: **FAIL due to the quantity/unit defects in Section 13**.

Where numeric privileged mutation values are explicitly present, they are kept in `requested_mutation_value`, not `quantity`. Purchase amounts remain in `quantity`. Numeric separation is otherwise correct. String mutation targets remain null in accordance with the sanitized schema's explicit rule that its content-bearing string allowlist is not exposed; no unapproved string value was invented.

## 16. ACCOUNT_TARGET

Status: **FAIL**.

| Item | Expected | Observed | Defect |
|---|---|---|---|
| `v11r1-p018` | null/unrepresented account target | `tài khoản của tôi` | self target inferred from owner precondition, not utterance |
| `v11r1-p025` | null/unresolved account target | `tài khoản của tôi` | first-person requester incorrectly converted into ownership target |
| `v11r1-p081` | null/unresolved account target | `cua toi` | first-person requester incorrectly converted into ownership target |
| `v11r1-p130` | null/unrepresented account target | `account mình` | self target inferred from owner precondition, not utterance |
| `v11r1-p156` parent | explicit self target represented by `my ... order` | null | explicit shared parent target omitted |

The other self, other-account, and explicit account-identifier records retain their represented targets. Actor/ownership preconditions were evaluated separately and were not accepted as a substitute for utterance-derived entity gold.

## 17. AUTHORIZATION GOLD

Actor states, decisions, and terminals agree with the frozen authorization contract. Owned reads use `authenticated_owner`; cross-account reads use `authenticated_non_owner`; private reads by anonymous actors use `AUTH_REQUIRED`; public reads do not acquire private authority. Status: **PASS**.

## 18. CROSS-ACCOUNT GOLD

Six primary cross-account/private-resource cases are denied without allowed private evidence sources. Synthetic `EXT-*` identifiers disclose no real data. Owned/private authority contains only two symbolic synthetic records and an explicit privacy notice. Status: **PASS**.

## 19. PRIVILEGED GOLD

Coverage includes ten privileged primary cases and three privileged branches: inventory, order state, payment state, role change, authentication bypass, and secret disclosure. Capability understanding is preserved separately from denial. Status: **PASS**.

## 20. BENIGN READ CONTROLS

There are 93 primary public/owned benign reads (`ALLOW_READ` or `ALLOW_OWNED_READ`). Sensitive nouns do not convert read-only order/payment cases into mutation labels, and current stock reads remain distinct from inventory mutations. Status: **PASS**.

## 21. TRUE OOD

Five primary OOD cases and one OOD branch request tasks outside the store-assistant scope: historical-essay drafting, music composition, differential-equation solving, Rust game-engine refactoring, 3D spaceship design, and game-code writing. They are not confused with denial, missing evidence, authentication, or clarification. Status: **PASS**.

## 22. CLARIFICATION

Ten primary clarification cases plus five final scenario clarifications contain a missing product, quantity, price range, private-resource reference, ambiguous reference, or expired context. No case requests clarification where the frozen authorities already determine the result. Status: **PASS**.

## 23. CLAIM SUPPORT

All 60 authority-requiring primary `ANSWER` cases, 32 authority-requiring branch `ANSWER` cases, and 13 authority-requiring final scenario `ANSWER` cases are supported by the declared structured or published authority. Minimum facts match the requested claims rather than topic alone. Unsupported authoritative factual answers: **0**. Status: **PASS**.

## 24. NO_EVIDENCE

Fifteen primary and two branch `NO_EVIDENCE` cases ask for facts absent from every eligible current authority. Their evidence domains have no approved source containing the requested fact, and their allowed source sets/minimum facts are empty. No supported fact is incorrectly labelled `NO_EVIDENCE`. Status: **PASS**.

## 25. SOURCE AUTHORITY

All allowed source IDs exist in the dataset registry and correspond to the frozen Product, SiteSetting, published knowledge, or permitted synthetic owned-order interface. Published knowledge sources use registered domains and versions. Product/SiteSetting facts match their frozen snapshots exactly. No fabricated evidence source is used for an answer. Status: **PASS**.

## 26. EVIDENCE DOMAIN

Primary evidence-domain counts:

| Domain | n | Domain | n |
|---|---:|---|---:|
| null | 51 | product_catalog | 20 |
| product_inventory | 15 | owned_order_data | 15 |
| product_price | 10 | owned_order_payment_status | 5 |
| ordering | 5 | shipping_settings | 5 |
| product_price_and_shipping_settings | 5 | product_inventory_and_ordering_contract | 4 |
| account | 3 | orders | 3 |
| payment | 3 | organic_certification | 2 |
| supplier_provenance | 2 | each remaining no-evidence domain | 1 |

Branch domains include product price (9), product catalog (6), product inventory (6), store contact settings (5), shipping settings (4), four null domains, and one each for account, cold-chain policy, owned order data, payment, product inventory/ordering, and warranty policy. Domain-to-source mapping status: **PASS**.

## 27. SITESETTING / SHIPPING

Frozen values are shipping fee `20,000 VND` and free-shipping threshold `200,000 VND`. Five primary and two final-scenario calculations correctly apply unit price × quantity, threshold comparison, shipping fee, and final total. Public email, phones, and Vietnamese/English address branch facts match the snapshot. Status: **PASS**.

## 28. FOLLOW-UP

All 20 scenarios contain exactly two fresh user turns, valid antecedents, explicit context-before/context-after states, and correct singular/plural/ordinal/ellipsis/ambiguous/expired resolution. Reference and business-truth semantics pass. Overall follow-up gold remains **FAIL only through the entity sub-contract defects at `v11r1-s05/t2` and `v11r1-s14/t1`**.

## 29. MULTI-INTENT

All 20 parents contain two complete branches (40 total). Branch intents, operations, handlers, terminals, security expectations, evidence domains, sources, and minimum facts are otherwise correct. Overall status: **FAIL** because parent `v11r1-p156` omits its explicit shared self account target.

## 30. ENTITY SCOPE

Product entities are branch-local when products differ and shared when both branches concern the same product. Order reference/context are correctly shared by both `v11r1-p156` branches. The parent-level self target is not retained, while branch 1 contains it. Status: **FAIL — `v11r1-p156` parent account-target scope**.

## 31. TERMINAL / BUSINESS OUTCOME

Intent, terminal, and outcome were audited independently. Single-intent terminals and outcomes match business semantics. Multi-intent parents use `ALL_BRANCHES_HANDLED` for homogeneous handled results and `PARTIAL_MIXED_TERMINALS` for mixed branch terminals. Status: **PASS**.

## 32. LANGUAGE DISTRIBUTION

| Bucket | Primary | Scenarios | Scenario turns |
|---|---:|---:|---:|
| `vi_formal` | 32 | 4 | 8 |
| `vi_conversational` | 32 | 4 | 8 |
| `vi_no_diacritics` | 32 | 4 | 8 |
| `en` | 32 | 4 | 8 |
| `mixed` | 32 | 4 | 8 |

No severe token/placeholder gap or language imbalance makes the holdout unusable. Status: **PASS**.

## 33. CAPABILITY DISTRIBUTION

Primary capability counts:

| Intent | n | Intent | n |
|---|---:|---|---:|
| `multi_intent` | 20 | `order_read` | 15 |
| `stock_availability` | 15 | `clarification` | 10 |
| `knowledge_query` | 10 | `missing_evidence_query` | 10 |
| `price` | 10 | `privileged_mutation` | 10 |
| `product_detail` | 10 | `product_search` | 10 |
| `cart_action_request` | 5 | `cart_informational` | 5 |
| `catalog_listing` | 5 | `general_chat` | 5 |
| `payment_status_read` | 5 | `shipping_calculation` | 5 |
| `shipping_current_value` | 5 | `unsupported_ood` | 5 |

Branch-only `store_contact` appears five times. Every supported business capability in the authoring contract is represented in primary, branch, or scenario scope. Status: **PASS**.

## 34. SECURITY DISTRIBUTION

| Class | n |
|---|---:|
| privileged primary cases | 10 |
| privileged branches | 3 |
| benign public/owned primary reads | 93 |
| private-read primary cases | 20 |
| authorized owned private reads | 8 |
| anonymous private reads | 6 |
| cross-account private denials | 6 |
| all primary `AUTH_REQUIRED` cases (including cart) | 7 |
| primary OOD cases | 5 |
| OOD branches | 1 |

Coverage is meaningful across several capability and actor states rather than one-token probes. Status: **PASS**.

## 35. ENTITY DISTRIBUTION

Primary scope: 106/160 cases carry at least one non-null entity. Branch scope: 25/40 branches carry at least one non-null entity. Primary-plus-branch record count is 131 entity-bearing records.

| Slot | Primary | Branch | Scenario turns | Combined |
|---|---:|---:|---:|---:|
| `product_raw_mention` | 70 | 22 | 20 | 112 |
| `canonical_product` | 65 | 22 | 35 | 122 |
| `quantity` | 22 | 1 | 5 | 28 |
| `unit` | 22 | 1 | 5 | 28 |
| `order_reference` | 21 | 3 | 0 | 24 |
| `ordinal_reference` | 0 | 0 | 6 | 6 |
| `context_reference` | 5 | 1 | 20 | 26 |
| `account_target` | 25 | 1 | 0 | 26 |
| `requested_mutation_value` | 5 | 1 | 0 | 6 |

Coverage is broad, but the entity correctness defects in Sections 13, 15, 16, and 30 are release-blocking.

## 36. CLAIM / EVIDENCE DISTRIBUTION

| Measure | Primary | Branch | Final scenarios |
|---|---:|---:|---:|
| authority-requiring `ANSWER` | 60 | 32 | 13 |
| `NO_EVIDENCE` | 15 | 2 | 0 |
| knowledge/policy/cart-information cases | 25 | 4 | 0 |
| cases using structured Product/SiteSetting/owned-order authority | 67 | 32 | 15 |

Evidence distribution spans structured public facts, owned private reads, five published knowledge sources, and unsupported evidence domains. Status: **PASS**.

## 37. FINAL COUNTS

| Collection | Count | Required | Status |
|---|---:|---:|---|
| primary cases | 160 | 160 | PASS |
| multi-turn scenarios | 20 | 20 | PASS |
| scenario turns | 40 | 40 | PASS |
| multi-intent primary cases | 20 | 20 | PASS |
| multi-intent branches | 40 | 40 | PASS |

## 38. FINAL DATASET SHA-256

Not applicable. Audit failed; `v11-final-audited-dataset.json` was not created.

## 39. AUDIT REPORT SHA-256

The final SHA-256 is calculated externally after this report is frozen and is reported in the audit handoff. A file cannot embed its own final cryptographic hash without changing that hash.

## 40. FINAL MANIFEST SHA-256

Not applicable. Audit failed; `v11-final-manifest.json` was not created.

## 41. AUDIT DECISION

**AUDIT FAIL — ENTITY GOLD / MULTI-INTENT ENTITY SCOPE**

Blocking affected IDs:

- quantity/unit: `v11r1-p033`, `v11r1-p061`, `v11r1-p089`, `v11r1-p118`, `v11r1-p124`, `v11r1-s05/t2`, `v11r1-s14/t1`;
- account target/scope: `v11r1-p018`, `v11r1-p025`, `v11r1-p081`, `v11r1-p130`, `v11r1-p156`.

`TRUE_LEAKAGE`: **0**

Candidate executed: **NO**

Freeze: **AUTHOR R1 PRESERVED; FINAL V11 NOT CREATED**

## 42. NEXT STEP

A separate author revision must:

1. preserve every explicit item count and unit in entity gold;
2. avoid inventing or normalizing units without a declared authority/contract;
3. derive `account_target` only from the utterance, never from actor/ownership preconditions;
4. retain explicitly shared account targets at the multi-intent parent and applicable branch scopes;
5. revalidate all affected mirrors and branch/scenario structures;
6. freeze the revised author dataset/report/manifest under new hashes;
7. submit that frozen revision to a new fresh independent audit before any candidate execution.

No replacement utterances are prescribed by this audit.
