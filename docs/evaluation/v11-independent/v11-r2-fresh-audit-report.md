# V11 R2 Fresh Independent Audit Report

## 1. EXECUTIVE SUMMARY

**AUDIT FAIL — ENTITY_GOLD / UNIT_INVARIANTS / ACCOUNT_TARGET_INVARIANTS / MULTI_INTENT_ENTITY_SCOPE**

V11 Author R2 is hash-intact, has the declared lineage, stays inside the 12-record R1→R2 change budget, contains zero utterance changes, and has no verified V0–V10 leakage. Its structural schema, product/source authority, factual claim support, authorization, security, terminal, and business-outcome gold pass the independent checks.

The release gate nevertheless fails. The complete entity audit found unit annotations that are absent, inferred, or normalized without a frozen contract, plus four `account_target` values derived from requester/authentication language rather than an account target established by the utterance. Because the defects include multi-intent parents/branches and scenario turns, no final V11 dataset or final manifest was created.

Candidate executed: **NO**.

## 2. AUDITOR INDEPENDENCE SCOPE

The audit read only:

- frozen V11 R1 and V11 R2 artifacts needed for integrity and semantic diff;
- frozen sanitized schema and business/authority artifacts;
- V0–V9 utterance-bearing fixtures and V10 dataset revisions, only to extract historical utterances for leakage comparison.

The audit did not read Phase 14 analysis, candidate predictions, candidate scores, chatbot/runtime implementation, router/extractor/guard/controller/handler bodies, or RAG/Qdrant implementation. It did not run the chatbot, candidate, staging, or production requests. It did not modify R1 or R2.

## 3. R2 FREEZE VERIFICATION

All hashes exactly match the requested freeze values.

| Artifact | Recalculated SHA-256 | Status |
|---|---|---|
| `v11-author-r2-candidate-dataset.json` | `b89b78105caad702ebefe1a301f6a1b91a64cf13b5af7659a091e562de9320aa` | PASS |
| `v11-author-r2-report.md` | `dc6283e475ed688d6dce5bef364557eaa39585c0b00dff6960eb7a40ae1f4de7` | PASS |
| `v11-author-r2-freeze-manifest.json` | `a1cbcaf6cee1e7640fbb65e234cd03618a70db63b40bc6830399265a5471caaa` | PASS |
| R1 dataset | `81214b6d7fb877116d6e19298410c2d2c64096875e813d2a2139a6dfe926240a` | PASS |
| R1 report | `ea117f758ab9b6407f08d10349fa728988f8a3b921432ff35afc72f0c5ba2ee5` | PASS |
| R1 manifest | `bca7f76f94720425cbde2a2c7c718747654cbac9c326ceec16955e2ff8fa79c9` | PASS |

Both manifests declare `FROZEN`. R2 identifies `V11-author-r1` as its parent and pins the exact R1 hashes above. JSON parsing passed.

## 4. SANITIZED AUTHORITY VERIFICATION

All sanitized inputs match their expected hashes and declare frozen/compatible state.

| Artifact | Recalculated SHA-256 | Status |
|---|---|---|
| Schema JSON | `2d1e6cd805ed9914d6811f130f9c1cc0909eb2f6707e30f5f66b13c61f6bea5d` | PASS |
| Schema Markdown | `216e1f80789770d4f48e6a852d05ee1e7b4909b035b9b223107a4afc5f31b57b` | PASS |
| Schema manifest | `d5eddc122a536cace2e7ac773f1f686ddaaa96e6178902d65acfa88b43c7be06` | PASS |
| Business JSON | `ddaf57dcb1fd0db48b7466370c498d52db729cf8a4714f99c0098548fc1e4b7b` | PASS |
| Business Markdown | `f647a0554563366e160a040870a6ad493edc7baef90d2ea1baff3a03f6bfc9e6` | PASS |
| Product authority | `809923e43f8919446b8123ea3ab0cac2f39cc3b50d89bad89f584d3768799f0a` | PASS |
| SiteSetting authority | `92e69a3c93bfef417fce7ab76a12e191558cbbe50ec9e645c6422e69bb69f2cb` | PASS |
| Knowledge registry | `dc8b013d3235a8fb04cedda63575354ab25fd6482316798952ff7161e6d599e7` | PASS |
| Authorization contract | `de05e7d4ebd0429b20c73fd9bb0927a9012a9e358b80a195c878549e56639dc4` | PASS |
| Business manifest | `440f40b5dfc91fd06e1751d7231883503ff1e6d2246a900dbddd1a53c2efd5fa` | PASS |

The dataset schema version is `farta-v10.3.0`, compatible with the frozen sanitized schema contract. Dataset-local product, SiteSetting, and published-knowledge registry content matches the corresponding sanitized snapshots and versions.

## 5. R1→R2 SEMANTIC DIFF

The recursive semantic diff found exactly 16 field changes in exactly 12 records/turns. There are no additions, removals, reorderings, schema changes, or non-entity changes.

| Scope | Field | R1 | R2 |
|---|---|---:|---:|
| `v11r1-p018` | `account_target` | `tài khoản của tôi` | `null` |
| `v11r1-p025` | `account_target` | `tài khoản của tôi` | `null` |
| `v11r1-p033` | `quantity`, `unit` | `null`, `null` | `1`, `phần` |
| `v11r1-p061` | `quantity`, `unit` | `null`, `null` | `1`, `hop` |
| `v11r1-p081` | `account_target` | `cua toi` | `null` |
| `v11r1-p089` | `quantity` | `null` | `1` |
| `v11r1-p118` | `quantity`, `unit` | `null`, `null` | `1`, `phần` |
| `v11r1-p124` | `unit` | `phần` | `null` |
| `v11r1-p130` | `account_target` | `account mình` | `null` |
| `v11r1-p156` parent | `account_target` | `null` | `my account` |
| `v11r1-s05/t2` | `unit` | `quả` | `cái` |
| `v11r1-s14/t1` | `quantity`, `unit` | `null`, `null` | `1`, `unit` |

## 6. CHANGE-BUDGET VERIFICATION

**PASS.** The affected scopes are exactly the authorized 12. The diff contains 16 entity-field changes and no mechanically unrelated mirror changes. Intent, handler, capability, terminal, outcome, auth state, security expectation, evidence domain, sources, claim support, canonical products, utterances, and schema are bytewise unchanged.

## 7. ZERO-UTTERANCE-CHANGE CHECK

**PASS.** All 160 primary utterances and all 40 scenario-turn utterances are bytewise identical between R1 and R2. Utterance changes: **0**.

## 8. HISTORICAL COVERAGE V0–V10

The audit extracted utterance-bearing fields and multi-intent utterance keys from every available V0–V9 fixture and from all seven available V10 dataset revisions.

| Version | Raw utterance instances | Version-scoped unique utterances |
|---|---:|---:|
| V0 | 187 | 187 |
| V1 | 130 | 125 |
| V2 | 144 | 137 |
| V3 | 159 | 152 |
| V4 | 216 | 209 |
| V5 | 259 | 224 |
| V6 | 284 | 241 |
| V7 | 396 | 309 |
| V8 | 366 | 311 |
| V9 | 328 | 262 |
| V10, seven revisions combined | 2,590 | 401 union-unique |

Total scanned historical instances: **5,059**. Historical versions covered: **V0–V10**.

## 9. EXACT DUPLICATE AUDIT

One raw exact historical match was found: `v11r1-s06/t2`, `Còn giá?`, also appears in V10. It is a minimal natural commerce follow-up and is classified `CANONICAL_SHORT_PHRASE`, not leakage.

Internal R2 raw duplicates: **0**.

## 10. NORMALIZED DUPLICATE AUDIT

Unicode NFKC + case + whitespace-only historical matches beyond the raw exact match: **0**. Internal normalized duplicates: **0**.

## 11. ACCENT-FOLDED AUDIT

Accent-fold-only full-string matches: **0**. Token normalization surfaced `v11r1-s11/t2` (`Mon vua noi gia sao?`) against the accented V5 phrase `món vừa nói giá sao`; this is classified `CANONICAL_SHORT_PHRASE` because it is a short, ordinary follow-up rather than a distinctive authored sentence.

Internal accent-fold duplicates: **0**.

## 12. NEAR-DUPLICATE AUDIT

Token Jaccard/containment and ordered-LCS screening produced **12 pair signals**. The highest signals involved short price, stock, account-verification, product-search, and add-to-cart phrasings. Examples include `v11r1-s05/t2`, `v11r1-s07/t2`, `v11r1-p019`, `v11r1-p002`, `v11r1-s01/t2`, and `v11r1-p126`.

Each signal was reviewed. They are classified as `CANONICAL_SHORT_PHRASE` or `BENIGN_DOMAIN_OVERLAP`; no distinctive multi-clause wording, unusual error, or non-canonical authored structure was copied. `TRUE_LEAKAGE = 0`; `UNCERTAIN = 0`.

Internal R2 near-duplicate pairs: **1** — `v11r1-p001` versus `v11r1-s01/t1` (same product-search request, differing by `vui lòng`). It is benign but noted as an internal diversity signal.

## 13. STRUCTURAL TEMPLATE AUDIT

Product/number/reference placeholder comparison produced two historical template signals: the common current-price question used by `v11r1-p005`, and the short follow-up used by `v11r1-s11/t2`. Both are canonical commerce skeletons. No true structural leakage was found.

Internal product-substitution template duplicates: **0** under the same placeholder comparison.

## 14. INTERNAL GENERATED-DATA QUALITY

**ACCEPTABLE.** There are no exact, normalized, or accent-fold internal duplicates; only one near pair. Language buckets are evenly distributed. Canonical-product entity occurrences range from 9 to 17 across the 11 frozen products, with no material product over-concentration. Some phrasing is benchmark-like, but no label names, handlers, expected terminals, or gold fields leak into utterances.

## 15. SCHEMA AUDIT

Structural schema compatibility: **PASS**. Semantic entity conformance: **FAIL**.

Every required object/field, enum, nullable shape, branch/scenario shape, grounding mirror, version pin, and entity slot passes the frozen structural contract. The semantic failure is that `unit` and `account_target` values do not always satisfy the contract meaning plus the stricter forward/reverse invariants required by this audit.

## 16. QUANTITY FORWARD AUDIT

**PASS.** Across primary, branch, and scenario-turn scopes, 33 non-null purchase/item quantity annotations are present. All explicit positive purchase quantities that are representable as a numeric quantity are preserved. Missing: **0**. Incorrect: **0**.

Indefinite count questions such as `mấy phần`/`mấy hộp` correctly leave numeric quantity null because the schema accepts only a positive number, while their separately expressed unit still requires review under the unit invariant.

## 17. QUANTITY REVERSE AUDIT

**PASS.** All 33 non-null quantities are justified by their scope's explicit utterance semantics. Spurious: **0**. Mutation targets/deltas are not counted as purchase quantities.

## 18. UNIT FORWARD AUDIT

**FAIL.** Explicit unit semantics disappear in these scopes:

| Scope | Failure |
|---|---|
| `v11r1-p149` parent | The utterance explicitly asks stock in `hop`, but the parent unit is null. |
| `v11r1-p149-b2` | The stock branch explicitly asks `may hop`, but its unit is null. |
| `v11r1-s08/t2` | The turn explicitly asks `mấy phần`, but its unit is null. |

The lack of a numeric quantity does not erase a unit associated with an explicit product/count reference; the schema allows `unit` independently as a nullable non-empty string.

## 19. UNIT REVERSE AUDIT

**FAIL.** Of 31 non-null unit annotations, 10 are not licensed by an explicit surface value or a frozen normalization rule:

| Scope | Gold unit | Failure class |
|---|---|---|
| `v11r1-p092` | `items` | Generic unit inferred from plural product wording. |
| `v11r1-p093` | `items` | No surface unit. |
| `v11r1-p098` | `boxes` | Undeclared cross-language/product-name normalization. |
| `v11r1-p108` | `items` | No `items` surface value or declared mapping. |
| `v11r1-p121` | `quả` | Product-convention inference only. |
| `v11r1-p148` parent | `quả` | Product-convention inference only. |
| `v11r1-p148-b1` | `quả` | Product-convention inference only. |
| `v11r1-s14/t2` | `items` | Elliptical `five` does not license `items`; prior `unit` is not mapped to `items`. |
| `v11r1-s16/t2` | `items` | Surface `item` was plural-normalized without a frozen mapping. |
| `v11r1-s20/t2` | `item` | No surface unit. |

No unit normalization map exists in the sanitized schema or business authorities. The contract permits a raw/schema-compatible string but does not license product-convention inference, translation, generic `items`, or singular/plural rewriting.

## 20. ACCOUNT_TARGET FORWARD AUDIT

**PASS.** Explicit self-account, other-account, and identifier targets are retained. No independently established account target was found missing.

## 21. ACCOUNT_TARGET REVERSE AUDIT

**FAIL.** Four non-null account targets are supported only by requester/actor language, not by an account target established by the utterance:

| Scope | Gold account target | Why unsupported |
|---|---|---|
| `v11r1-p043` | `mình` | `giùm mình` marks the beneficiary/requester, not the account owning the order. |
| `v11r1-p046` | `mình` | `Mình chưa đăng nhập` states requester/auth state; it does not target the order to an account. |
| `v11r1-p072` | `cua toi` | The utterance contains requester `Toi` but no `cua toi` account target. |
| `v11r1-p128` | `account mình` | Logged-out requester state and a generic order-history request do not explicitly establish `account mình`. |

Actor and ownership fixtures cannot supply these entity values. Nineteen other non-null account targets are utterance-supported.

## 22. MULTI-INTENT ENTITY SCOPE

**FAIL.** Product, order, context, mutation, and account scope is otherwise isolated correctly across all 20 parents and 40 branches. Unit scope fails for:

- `v11r1-p148` parent and `v11r1-p148-b1`: an inferred `quả` is carried at the relevant parent/branch scopes;
- `v11r1-p149` parent and `v11r1-p149-b2`: explicit `hop` is absent from the parent union and the stock branch.

No unit contaminates the unrelated siblings (`p148-b2`, `p149-b1`).

## 23. v11r1-p156 REVIEW

**PASS.** The parent phrase `my OWN-BRAVO order` establishes `my account` for the parent and read branch. Branch 1 correctly carries the order reference and self-account target. Branch 2 correctly carries `OWN-BRAVO` plus `that order`, but does not receive the branch-local account target. The read remains authorized and the chatbot cancellation remains denied. No sibling contamination was found.

## 24. ENTITY MIRROR CONSISTENCY

Mechanical mirrors: **PASS**. R1→R2 introduced no stale or unrelated mirror delta. The incorrect unit values on `p148` are consistently mirrored, and the missing unit on `p149` is consistently absent; that mechanical consistency does not cure the semantic failures reported above.

## 25. PRODUCT AUTHORITY

**PASS.** Every non-null canonical product exists in the frozen product authority. All 11 products and their categories are active. Current prices, stock values, category membership, and active-only result sets match the frozen snapshot.

## 26. QUANTITY VS MUTATION VALUE

**PASS.** Purchase/item amounts use `quantity`; inventory/payment mutation numbers use `requested_mutation_value`. Six non-null mutation-value annotations, including parent/branch mirrors, do not leak into quantity. Purchase quantities do not become mutation values.

## 27. ORDER / CONTEXT REFERENCES

**PASS.** Symbolic order references, ordinal references, and context references are explicit or context-bound at the correct scope. Ordinals are non-zero and resolve to the correct product order. Expired and ambiguous references do not resolve to a canonical product.

## 28. AUTHORIZATION GOLD

**PASS.** Anonymous private reads require authentication; owned reads use owner-only authority; non-owner reads are denied; public reads remain public. Entity annotations do not grant authority.

## 29. CROSS-ACCOUNT GOLD

**PASS.** Every explicit other-account/private-resource request preserves its target and order reference, uses `authenticated_non_owner`, terminates `DENIED`, and exposes no private evidence or facts. No real private data is embedded.

## 30. PRIVILEGED GOLD

**PASS.** All 13 privileged-mutation scopes are denied, including inventory, order, payment, role, auth-bypass, and secret-disclosure classes. Denial does not erase supported product/order/account/mutation-value understanding.

## 31. BENIGN READ CONTROLS

**PASS.** Stock, order, payment, and account-like nouns are classified as reads when the requested operation is read-only. They are not mislabeled as mutation by keyword.

## 32. TRUE OOD

**PASS.** Six OOD primary/branch scopes are genuinely outside store capability and terminate `UNSUPPORTED`. Denied, no-evidence, auth-required, and clarification cases remain distinct.

## 33. CLARIFICATION

**PASS.** Clarification is used for missing products/quantities/references, ambiguous context, expired context, or unusable price constraints. No frozen authority already resolves these cases.

## 34. CLAIM SUPPORT

**PASS.** All 105 factual/policy `ANSWER` records in primary, branch, and scenario-final scoring scopes have a domain-correct eligible source and non-empty required facts directly supported by that source. Product, SiteSetting, published-knowledge, and owned-order boundaries are respected. Unsupported authoritative answers: **0**.

## 35. NO_EVIDENCE

**PASS.** All 17 `NO_EVIDENCE` primary/branch scopes request facts absent from eligible frozen authority, carry an appropriate evidence domain, and have empty allowed sources and facts. Conversely, every factual `ANSWER` has eligible evidence. False abstentions found: **0**.

## 36. SOURCE AUTHORITY

**PASS.** Every referenced source ID exists in the dataset registry, has the expected authority type/version, and matches the frozen snapshot. Version pins are positive, allowed-source-scoped, and equal registry versions. No fabricated source or version was found.

## 37. EVIDENCE DOMAIN

**PASS.** Information needs map to the sanitized evidence taxonomy. Current product, inventory, price, shipping, contact, published-knowledge, and owned-private domains are separated correctly. Missing-policy domains have no eligible current source.

## 38. SITESETTING / SHIPPING

**PASS.** Shipping fee is 20,000 VND and free shipping begins at a merchandise subtotal of 200,000 VND. All deterministic subtotals, fees, threshold decisions, and final totals recompute correctly. Contact values match the frozen SiteSetting snapshot.

## 39. FOLLOW-UP

**FAIL — ENTITY UNIT GOLD ONLY.** All 20 scenarios and 40 turns have valid fresh setup, antecedents, reference resolution, context lifecycle, canonical products, terminals, evidence, and business truth. Unit defects remain in `v11r1-s08/t2`, `v11r1-s14/t2`, `v11r1-s16/t2`, and `v11r1-s20/t2`. Corrected `v11r1-s05/t2` is valid.

## 40. MULTI-INTENT

**FAIL — ENTITY UNIT GOLD ONLY.** All 20 parents and 40 branches are structurally complete, and their intent, operation, handler, terminal, security, domain, source, and claim-support fields are otherwise correct. Unit defects affect `v11r1-p148`, `v11r1-p148-b1`, `v11r1-p149`, and `v11r1-p149-b2`.

## 41. TERMINAL / BUSINESS OUTCOME

**PASS.** Terminal and business-outcome gold independently matches the frozen semantics. Composite terminal aggregation is coherent: 15 `ALL_BRANCHES_HANDLED` parents and 5 `PARTIAL_MIXED_TERMINALS` parents.

## 42. LANGUAGE DISTRIBUTION

Primary cases are exactly balanced: 32 each of `vi_formal`, `vi_conversational`, `vi_no_diacritics`, `en`, and `mixed`. Scenarios are exactly balanced: 4 each. The R1→R2 distribution is unchanged.

## 43. CAPABILITY DISTRIBUTION

Primary intent distribution is unchanged from R1:

`cart_action_request 5; cart_informational 5; catalog_listing 5; clarification 10; general_chat 5; knowledge_query 10; missing_evidence_query 10; multi_intent 20; order_read 15; payment_status_read 5; price 10; privileged_mutation 10; product_detail 10; product_search 10; shipping_calculation 5; shipping_current_value 5; stock_availability 15; unsupported_ood 5`.

Branch intent distribution:

`cart_action_request 1; catalog_listing 1; knowledge_query 2; missing_evidence_query 2; order_read 1; price 9; privileged_mutation 3; product_detail 5; shipping_current_value 4; stock_availability 6; store_contact 5; unsupported_ood 1`.

Scenario-turn intent distribution:

`product_search 20; clarification 5; price 5; stock_availability 4; cart_action_request 2; product_detail 2; shipping_calculation 2`.

## 44. ENTITY DISTRIBUTION

Counts cover all 240 entity objects: 160 primary parents, 40 branches, and 40 scenario turns.

| Entity | R1 | R2 | Delta |
|---|---:|---:|---:|
| `product_raw_mention` | 112 | 112 | 0 |
| `canonical_product` | 122 | 122 | 0 |
| `quantity` | 28 | 33 | +5 |
| `unit` | 28 | 31 | +3 |
| `order_reference` | 24 | 24 | 0 |
| `ordinal_reference` | 6 | 6 | 0 |
| `context_reference` | 26 | 26 | 0 |
| `account_target` | 26 | 23 | -3 |
| `requested_mutation_value` | 6 | 6 | 0 |

The deltas are fully explained by the authorized R1→R2 changes, but global semantic correctness still fails for the pre-existing annotations identified in Sections 18, 19, and 21.

## 45. STRUCTURAL VALIDATION

**PASS.** Checks covered JSON validity, top-level contract arrays, required fields, exact nine-slot entity shapes, types/nullability, enums, unique IDs, branch rules, two-turn scenario rules, ordered turn numbers, source registration, source-version pins, grounding mirrors, terminal vocabulary, actor states, deterministic-gold keys, and manifest counts. Failures: **0**.

## 46. FINAL COUNTS

| Collection | Count | Expected | Status |
|---|---:|---:|---|
| Primary | 160 | 160 | PASS |
| Scenarios | 20 | 20 | PASS |
| Scenario turns | 40 | 40 | PASS |
| Multi-intent parents | 20 | 20 | PASS |
| Multi-intent branches | 40 | 40 | PASS |

## 47. FINAL DATASET SHA-256

**NOT CREATED — AUDIT FAILED.** The frozen R2 dataset remains unchanged at `b89b78105caad702ebefe1a301f6a1b91a64cf13b5af7659a091e562de9320aa`.

## 48. AUDIT REPORT SHA-256

The report is hashed only after its final byte is written. The post-freeze SHA-256 is intentionally reported in the audit handoff rather than embedded here, because embedding a file's own hash would change that hash.

## 49. FINAL MANIFEST SHA-256

**NOT CREATED — AUDIT FAILED.** No final V11 manifest exists from this audit.

## 50. AUDIT DECISION

**AUDIT FAIL — ENTITY_GOLD / UNIT_INVARIANTS / ACCOUNT_TARGET_INVARIANTS / MULTI_INTENT_ENTITY_SCOPE**

Passing gates: freeze integrity, authority integrity, R1→R2 change budget, zero utterance changes, historical coverage, leakage, quantity, structural schema, product authority, mutation separation, references, authorization/security, claim support, NO_EVIDENCE, source/evidence, SiteSetting/shipping, terminal/outcome, distribution, and generated-data quality.

Failing gates: unit forward, unit reverse/normalization, account-target reverse, multi-intent entity scope, and follow-up entity gold.

`TRUE_LEAKAGE = 0`. Candidate executed: **NO**. Final freeze: **NOT CREATED**.

## 51. NEXT STEP

Create a new author revision from frozen R2; do not edit R2 in place. The sanitized remediation requirements are:

1. Remove unit values not licensed by explicit utterance/context semantics or a newly frozen normalization contract.
2. Preserve each explicit unit at the correct parent, branch, or turn scope even when numeric quantity is unknown.
3. Preserve raw/schema-compatible unit representation unless a frozen mapping explicitly authorizes normalization, translation, or inflection.
4. Remove account targets supported only by requester pronouns, actor/authentication state, or ownership fixtures.
5. Recompute all parent/branch/turn mirrors, entity distributions, hashes, and the change budget in a new manifest.
6. Run a fresh independent audit before any final V11 freeze.

No replacement utterances are proposed. R1 and R2 remain unmodified.
