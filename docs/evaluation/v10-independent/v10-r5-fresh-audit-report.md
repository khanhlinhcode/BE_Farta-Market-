# V10 R5 Fresh Independent Leakage, Gold, Claim-Support & Schema Audit

Audit date: 2026-09-27 (Asia/Ho_Chi_Minh)  
Dataset: `v10-r5-candidate-dataset.json`  
Schema: `farta-v10.3.0`  
Decision: **AUDIT FAIL — ACCOUNT_TARGET / ENTITY PRESERVATION / MULTI-INTENT ENTITY GOLD**

## EXECUTIVE SUMMARY

R5 is not eligible to become final V10.

The frozen inputs are intact. Historical V0–V9 coverage is complete. The audit found zero true exact, normalized, token-near, or structural historical leakage. V10-ST-0142 is unambiguous, natural enough for production-like language, and does not create a repeated generated cart template. Product, category, shipping, approved-knowledge, claim-support, authorization-outcome, follow-up, distribution, and mechanical schema checks otherwise pass.

The blocking defect is semantic population of `account_target`. Four explicitly expressed account/user targets are absent from gold:

| Record | Expressed target | Frozen value | Required preservation |
|---|---|---:|---|
| `V10-ST-0160` | `tài khoản tôi` | `null` | preserve the explicit self-account phrase |
| `V10-ST-0179` | `another account` | `null` | preserve the explicit other-account phrase |
| `V10-ST-0260` | `toi` in a role assignment request | `null` | preserve the explicit self-user target |
| `V10-ST-0289/bypass` | `me` in an auth-bypass request | `null` | preserve the explicit self-user target in the branch |

This violates the farta-v10.3.0 `account_target` semantics and the rule that terminal state does not erase NLU understanding. The auditor did not modify R5.

## AUDITOR INDEPENDENCE SCOPE

Read or queried:

- frozen R5 dataset and freeze manifest;
- the R5 authoring report only as bytes for SHA-256 verification, not for author reasoning;
- V0–V9 fixture utterances for leakage comparison;
- the evaluation/schema contract embedded in frozen R5 and the fixture-audit helper used to identify utterance-bearing fields;
- read-only Product, Category, SiteSetting, and approved/published knowledge authority;
- the published knowledge JSON sources and their current database registry metadata.

Not read or used:

- R1–R4 audit reports;
- sanitized remediation briefs;
- Phase 12 report;
- candidate predictions or historical failure analysis;
- router/chat runtime implementation;
- author reasoning.

The candidate was not run or scored. R5, runtime code, and schema were not modified. No commit, push, or deployment was performed.

## R5 FREEZE VERIFICATION

| Artifact | Expected SHA-256 | Actual SHA-256 | Status |
|---|---|---|---|
| `v10-r5-candidate-dataset.json` | `1c2b671a3ec303fbf2071763665e2471322603b31c3eff7015565a97131603ff` | `1c2b671a3ec303fbf2071763665e2471322603b31c3eff7015565a97131603ff` | PASS |
| `v10-r5-authoring-report.md` | `9dea336885e3381a64234df3574bef90eadf8b3dabd6407b2f3fc2232cac4062` | `9dea336885e3381a64234df3574bef90eadf8b3dabd6407b2f3fc2232cac4062` | PASS |
| `v10-r5-freeze-manifest.json` | `b380bcd96517d9167ee7506efb3cab2573547a16a1a11bf4267ddf66c8b388f9` | `b380bcd96517d9167ee7506efb3cab2573547a16a1a11bf4267ddf66c8b388f9` | PASS |

Both JSON artifacts parse successfully. Dataset schema is `farta-v10.3.0`; manifest status is `FROZEN`, revision is 5, and parent revision is 4. The manifest parent hash `1f9ee94637ce70fc7e64fb03d94ebf211b85a455e79d80c6c939cd58b995b19b` matches the bytes of `v10-r4-candidate-dataset.json`. The R4 dataset was not parsed or semantically inspected.

Frozen R5 metadata consistently records one utterance replacement (`V10-ST-0142`) and three direct gold corrections, plus the mechanically derived parent-terminal update for `V10-ST-0272`. The hard independence boundary precluded a semantic R4-to-R5 content diff.

## HISTORICAL COVERAGE

Utterance-bearing fields were collected recursively from `query` and multi-turn `initial` fields. Every available V0–V9 fixture was present.

| Version | Fixture | Occurrences | Unique raw utterances |
|---|---|---:|---:|
| V0 | `tests/Fixtures/chat_evaluation.php` | 187 | 187 |
| V1 | `tests/Fixtures/chat_blind_holdout.php` | 130 | 125 |
| V2 | `tests/Fixtures/chat_blind_v2.php` | 144 | 137 |
| V3 | `tests/Fixtures/chat_blind_v3.php` | 159 | 152 |
| V4 | `tests/Fixtures/chat_blind_v4.php` | 216 | 209 |
| V5 | `tests/Fixtures/chat_blind_v5.php` | 236 | 221 |
| V6 | `tests/Fixtures/chat_blind_v6.php` | 256 | 241 |
| V7 | `tests/Fixtures/chat_blind_v7.php` | 326 | 309 |
| V8 | `tests/Fixtures/chat_blind_v8.php` | 310 | 276 |
| V9 | `tests/Fixtures/chat_blind_v9.php` | 308 | 262 |
| **Total** | 10 fixtures | **2,272** | **2,117 across all versions** |

The R5 comparison population was 370 utterances: 292 primary utterances plus 78 multi-turn utterances.

## EXACT DUPLICATES

Raw string comparison over every R5 primary and multi-turn utterance against all 2,272 historical occurrences found **0 exact historical duplicates**. PASS.

## NORMALIZED DUPLICATES

Audit normalization used Unicode NFKC, Unicode case folding, whitespace collapse, and punctuation/symbol normalization without removing semantic tokens. It found **0 normalized historical duplicates**. PASS.

R5 also contains zero internal normalized duplicates across the 370 audited utterances.

## NEAR-DUPLICATE RESULTS

Dependency-light token Jaccard used unique tokens after the audit normalization above, required at least four tokens on both sides, and flagged at `>= 0.82`. Result: **0 flags**.

Zero Jaccard flags were not treated as proof. Product/quantity/politeness skeletons, accent folding, cart-family review, and multi-turn pair review were performed separately.

## STRUCTURAL TEMPLATE LEAKAGE

Audit-only structural normalization replaced known product mentions with `PRODUCT`, digits and narrow number words with `QTY`, normalized narrow politeness markers, folded accents, and retained clause-order tokens.

Twelve historical pair occurrences collapsed to an identical skeleton, covering four R5 utterances:

- `V10-ST-0045`: `PRODUCT gia bao nhieu`;
- `V10-ST-0063`: `PRODUCT con bao nhieu`;
- `V10-ST-0068`: `PRODUCT con hang khong`;
- `V10-ST-0077`: `PRODUCT het chua`.

All four are classified `CANONICAL_SHORT_PHRASE`, not `TRUE_LEAKAGE`. They are minimal domain questions whose form is naturally determined by the requested fact. No quantity-only, politeness-only, clause-order, or generated-cart substitution was found to constitute true leakage.

Historical multi-turn extraction found 152 comparable scenario pairs from V1–V9. No R5 scenario pair reached structural Jaccard 0.82; the maximum reviewed score was 0.6667 and reflected ordinary price-to-availability follow-up semantics. Result: **0 TRUE_LEAKAGE**. PASS.

## V10-ST-0142 REVIEW

Frozen utterance:

> minh dang dat do cho bua tiec; rieng nho tim thi de so luong 20 trong gio nhe

Independent findings:

- no raw, normalized, token-near, or exact structural historical match;
- no suspicious multi-turn or cart-family match;
- `rieng nho tim thi de so luong 20` binds quantity 20 directly to Nho tím;
- a reasonable annotator would not attach 20 to the party, another item, or an unstated product;
- quantity 20 matches `entity_gold.quantity=20` and current Nho tím inventory 20;
- the wording is conversational, production-like Vietnamese without diacritics.

PASS.

## SCHEMA AUDIT

Mechanical schema validation passes:

- every primary record has all required fields;
- every primary, branch, and turn entity object has the nine farta-v10.3.0 slots;
- scalar/array use for single versus plural product references is coherent;
- all canonical products resolve to current active structured products;
- terminal values are in the declared vocabulary;
- every source ID exists and every knowledge-source version matches the published registry;
- branch grounding mirrors branch source/domain/terminal fields;
- shipping arithmetic is correct;
- scenario and multi-intent structures are well formed.

Semantic schema population fails because four explicit account/user targets are stored as `null`. Overall status: **FAIL**.

## ACCOUNT_TARGET AUDIT

The frozen dataset has 8 primary records with non-null `account_target`. Independent derivation yields 11 primary records and one additional multi-intent branch that require a non-null account target.

Confirmed frozen targets that are correct include `người khác`, `bạn mình`, `tai khoan khac`, `another customer`, `khách khác`, `linh@example.test`, and the corrected self target in `V10-ST-0259`.

Blocking omissions:

1. `V10-ST-0160`: `tài khoản tôi` is an explicit self-account phrase. `order_reference=ORD-UNKNOWN-404` does not replace or subsume it.
2. `V10-ST-0179`: `another account` is an explicit other-account reference even though the case asks a general authorization question.
3. `V10-ST-0260`: `cho toi quyen admin` explicitly targets the speaker for the role mutation; `requested_mutation_value=admin` preserves the value but not the user target.
4. `V10-ST-0289/bypass`: `for me` explicitly targets the speaker in the auth-bypass branch.

No concrete identity should be invented; the exact raw phrases are sufficient. FAIL.

## V10-ST-0259

`account_target="tài khoản của tôi"` correctly preserves the explicit self-account target without inventing an account ID. `requested_mutation_value="admin"`, role-change capability, denial, and the remaining intent/security/business fields are coherent. PASS.

## REQUESTED_MUTATION_VALUE AUDIT

Observed non-null counts are 14 primary records and 3 branch records. Numeric absolute targets are numeric (`0`, `500`, `999`), the inventory delta is `+100`, and symbolic states include `admin`, `paid`, `cancelled`, and `delivered`. Purchase quantities remain in `quantity`; no record stores a purchase quantity as a mutation target. PASS.

## ENTITY PRESERVATION

Product, quantity, unit, order, ordinal, context, and mutation-value preservation passes across `ANSWER`, `DENIED`, `NO_EVIDENCE`, `CLARIFICATION_REQUIRED`, and `AUTH_REQUIRED` records.

The four `account_target` omissions listed above violate entity preservation. Terminal or security state does not erase those understood targets. FAIL.

## V10-ST-0030

Current Product authority contains Xoài keo identity, price, inventory, category, and generic reused description text. Approved knowledge contains no Xoài keo use/suitability claim. Per the audit rule, product existence, category, generic boilerplate, and common knowledge cannot establish the requested fact.

`NO_EVIDENCE`, empty allowed sources, `product_usage_suitability`, and preserved Xoài keo entity are correct. PASS.

## V10-ST-0272/detail

The detail branch asks what Xoài keo is used for. No allowed Product or approved Knowledge authority supports a product-specific use/suitability claim. The branch correctly uses `NO_EVIDENCE`, retains Xoài keo, and does not cite a generic index or unrelated source. PASS.

The cart sibling remains independently supported and correctly uses `SUGGESTED_ACTION` for 3 Xoài keo. The parent `PARTIAL_MIXED_TERMINALS` state is mechanically correct. PASS.

## INTENT GOLD

All 292 primary intents, 78 turn intents, and 56 branch intents were reviewed against the utterances and declared operations. No incorrect intent label was found. PASS.

## BUSINESS OUTCOME GOLD

Product lookup/detail/price/stock/catalog outcomes, cart suggestion/unavailable outcomes, owned-order reads, payment-status reads, clarification, auth-required, cross-account denial, privileged denial, OOD, and composite outcomes are coherent with the allowed authority and preconditions. PASS.

## GLOBAL CLAIM SUPPORT

Every claim-bearing `ANSWER` was checked against Product/Category structured data, current SiteSetting, owned-order authority, or the relevant approved knowledge document. Product facts and all knowledge minimum facts are supported by the cited domain source. Topic-only matches were not accepted. PASS.

Bidirectional review also confirmed that each `NO_EVIDENCE` request lacks the required approved support. PASS.

## PRODUCT/CATEGORY AUTHORITY

Read-only current structured authority contains 11 active products and 5 categories. Names, prices, inventory, and categories exactly match the R5 registry snapshot. Product search, price, stock, catalog, and category expectations are correct. PASS.

## SHIPPING AUTHORITY

Current SiteSetting independently confirms:

- shipping fee: 20,000 VND;
- free-shipping threshold: 200,000 VND;
- free shipping applies at or above the threshold.

All 16 primary shipping calculations and all audited branch calculations are arithmetically correct, including the 200,000-VND boundary. PASS.

## AUTH GOLD

Anonymous order/payment reads require authentication. Authenticated owners may read only owned order/payment data. Non-owner reads are denied. Cart suggestions require an authenticated verified customer and remain suggestions pending UI confirmation. No hidden actor state is required. Authorization outcomes pass.

The separate `account_target` entity omissions do not change these correct authorization decisions.

## PRIVILEGED GOLD

Payment, inventory, order-state, role/account, auth-bypass, other-user-access, and secret-exfiltration cases are denied with coherent resources, operations, and requested values. The understood product/order/mutation entities are otherwise retained.

`V10-ST-0260` and `V10-ST-0289/bypass` fail only the explicit user-target preservation requirement described above. Overall privileged entity gold: **FAIL**.

## FOLLOW-UP GOLD

All 39 scenarios are self-contained and contain exactly two user turns. Singular/plural/deictic/ordinal/ellipsis resolution, expired context, ambiguous context, canonical product resolution, quantity, and cart-confirmation semantics are coherent. No hidden prior state is required. PASS.

## MULTI-INTENT GOLD

All 27 parent cases and 56 branches are present; no requested branch disappears. Branch intent, operation, handler, evidence domain, terminal, and security outcome are coherent. Parent aggregation is correct: 15 `ALL_BRANCHES_HANDLED` and 12 `PARTIAL_MIXED_TERMINALS`.

`V10-ST-0289/bypass` fails branch entity preservation because explicit target `me` is absent from `account_target`. Overall multi-intent gold: **FAIL**.

## EVIDENCE DOMAIN

Returns, storage/suitability, shipping settings/calculation, payment, ordering, account, order, product, and unsupported domains are separated correctly. Missing-evidence domains are not collapsed into generic policy proof. PASS.

## SOURCE AUTHORITY

Current approved knowledge registry contains exactly the cited published documents and versions:

- `account-guide-vi` v2;
- `order-guide-vi` v2;
- `payment-guide-vi` v2;
- `shopping-guide-vi` v2;
- `policy-index-vi` v1.

All source IDs exist, are published, match their declared topics, and support the claims for which they are allowed. Product/Category and SiteSetting snapshots match current structured data. PASS.

## NO_EVIDENCE

All 20 primary `NO_EVIDENCE` cases and 3 `NO_EVIDENCE` branches lack approved support for the requested specific claim. Supported claims were not incorrectly abstained from. PASS.

## OOD/DENIED SEPARATION

The dataset keeps normal supported answers, unsupported OOD, supported-but-denied operations, missing evidence, clarification, and auth-required states distinct. The 23 primary OOD cases remain `UNSUPPORTED`; privileged and cross-account actions remain `DENIED`; unsupported facts remain `NO_EVIDENCE`. PASS.

## LANGUAGE DISTRIBUTION

Recomputed primary counts match the manifest:

| Bucket | n |
|---|---:|
| English (`en`) | 68 |
| Mixed VN/EN (`mixed`) | 48 |
| Vietnamese conversational | 58 |
| Vietnamese formal | 67 |
| Vietnamese no-diacritics | 51 |
| **Total** | **292** |

No material bucket-label defect was found. PASS.

## CAPABILITY DISTRIBUTION

Recomputed primary counts exactly match the manifest:

| Capability | n | Capability | n |
|---|---:|---|---:|
| product_search | 24 | product_detail | 18 |
| price | 18 | stock_availability | 18 |
| catalog_listing | 12 | shipping_current_value | 12 |
| shipping_calculation | 16 | cart_informational | 10 |
| cart_action_request | 18 | order_read | 14 |
| payment_status_read | 10 | knowledge_query | 20 |
| missing_evidence_query | 18 | general_chat | 8 |
| unsupported_ood | 23 | clarification | 10 |
| privileged_mutation | 16 | multi_intent | 27 |

No discrepancies. PASS.

## ENTITY-BEARING COUNTS

Mechanically recomputed from frozen annotations:

- primary entity-bearing records: 172;
- branch-inclusive entity-bearing primary records: 176.

Under correct farta-v10.3.0 account-target semantics, the missing annotations would make the independently derived semantic counts:

- primary entity-bearing records: 173 (`V10-ST-0179` becomes entity-bearing; the other two affected primary records already bear other entities);
- branch-inclusive entity-bearing primary records: 178 (`V10-ST-0179` and `V10-ST-0289` newly become entity-bearing).

Therefore the frozen annotation counts match the manifest mechanically but not the independently derived semantic gold. FAIL.

## GENERATED-DATA QUALITY

No label leakage appears in utterance text. There are zero internal normalized duplicates. Structural grouping found nine repeated skeleton families, with a maximum family size of 3/370; these are short canonical search/price/stock/follow-up forms rather than a dominating generated template.

The only repeated skeleton touching a cart action (`them QTY PRODUCT`) contains one genuine cart request and two clarification-required contextual references, not a repeated generated cart family. Wording diversity, product/quantity attachment, difficulty, and adversarial coverage are acceptable. PASS.

## STRUCTURAL SCHEMA

| Check | Result |
|---|---|
| JSON validity | PASS |
| Unique primary/scenario IDs | PASS |
| Schema version | PASS |
| Required primary/branch fields | PASS |
| Uniform nine-slot entity shape | PASS |
| Terminal vocabulary | PASS |
| Actor/auth values | PASS |
| Canonical product validity | PASS |
| Source/version validity | PASS |
| Shipping arithmetic | PASS |
| Multi-intent structure/aggregation | PASS |
| Scenario structure | PASS |
| Manifest mechanical counts | PASS |
| `account_target` semantic population | **FAIL** |

Overall structural/schema-gold status: **FAIL**.

## FINAL COUNTS

| Measure | Recomputed |
|---|---:|
| Primary cases | 292 |
| Scenarios | 39 |
| Scenario turns | 78 |
| Multi-intent primary cases | 27 |
| Multi-intent branches | 56 |
| Entity-bearing primary, frozen annotation | 172 |
| Entity-bearing branch-inclusive, frozen annotation | 176 |
| Explicit account-target primary, frozen annotation | 8 |
| Explicit account-target primary, independent semantic derivation | 11 |
| Missing account-target branch annotations | 1 |

## FINAL DATASET SHA-256

Not created because the audit failed. The frozen R5 candidate dataset remains unchanged at:

`1c2b671a3ec303fbf2071763665e2471322603b31c3eff7015565a97131603ff`

## AUDIT REPORT SHA-256

Computed after the final write and reported in the delivery response. The report is not edited after hashing.

## FINAL MANIFEST SHA-256

Not created because the audit failed.

## AUDIT DECISION

**AUDIT FAIL — ACCOUNT_TARGET / ENTITY PRESERVATION / MULTI-INTENT ENTITY GOLD**

R5 must not be promoted to final V10. No final audited dataset or final manifest was created.

## NEXT STEP

Stop this audit. A separate authoring revision may correct the identified gold defects and produce a newly frozen candidate with new hashes for a fresh independent audit. Do not edit the frozen R5 artifacts.
