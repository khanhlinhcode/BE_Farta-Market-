# V10 R3 Fresh Independent Leakage & Gold Audit

Audited at: 2026-09-26T15:14:17Z

## EXECUTIVE SUMMARY

R3 is freeze-integral but is not eligible to become final V10.

The fresh audit found no raw exact duplicate, no audit-normalized duplicate, and no token-Jaccard pair at the configured 0.82 threshold. The mandatory structural review nevertheless found six primary cases whose wording reduces to previously used V0-V9 cart-command templates after only product, quantity, and politeness substitutions. These are `TRUE_LEAKAGE` under the audit contract.

Gold also fails independently. The entity schema omits the required `account_target` and `requested_mutation_value` dimensions, causing an explicit other-account target to disappear and inventory/state mutation values to be conflated with purchase quantity or omitted. One product-specific no-evidence case drops its expressed product entity. One product-detail case requires an `ANSWER` to a claim not supported by the allowed Product authority.

Internal count/distribution checks pass, but the required audit schema does not. No candidate was run. R3 was not modified. No final dataset or final manifest was created.

## AUDITOR INDEPENDENCE SCOPE

This audit used only:

- the three frozen R3 artifacts;
- V0-V9 fixture data for leakage comparison;
- the current Product, Category, SiteSetting, and published knowledge records;
- the authorization statements in approved knowledge and the R3 evaluation contract;
- the R3 schema contract.

The audit did not read R1/R2 audit reports, sanitized remediation briefs, the Phase 12 report, candidate predictions, historical failure analysis, router/runtime implementation, or author reasoning. The R3 authoring report was hash-verified but was not used as a source of gold. The candidate was not executed or scored.

## R3 FREEZE VERIFICATION

All expected hashes match exactly and both JSON artifacts parse.

| Artifact | Expected SHA-256 | Actual SHA-256 | Result |
|---|---|---|---|
| `v10-r3-candidate-dataset.json` | `f87149909d3bbd0bbad9ecbc4283863efcd0e8a565f06f2079c65c0bdca04ef5` | `f87149909d3bbd0bbad9ecbc4283863efcd0e8a565f06f2079c65c0bdca04ef5` | PASS |
| `v10-r3-authoring-report.md` | `ee0c5c4b20988f325d951c05e7ddacefb7e8eeecf9c3be7382063b5bbd03e897` | `ee0c5c4b20988f325d951c05e7ddacefb7e8eeecf9c3be7382063b5bbd03e897` | PASS |
| `v10-r3-freeze-manifest.json` | `64197bd9eff70fbc769198d891b2b13d106378040d9dba69377b144178cc8836` | `64197bd9eff70fbc769198d891b2b13d106378040d9dba69377b144178cc8836` | PASS |

Manifest byte sizes match the files: 631,708 bytes for the dataset and 5,537 bytes for the authoring report. Counts also match: 292 primary cases, 39 scenarios, and 78 scenario turns.

## HISTORICAL COVERAGE

Coverage is complete for every available V0-V9 fixture. An utterance-bearing record is a fixture query, initial/follow-up utterance, direct missing-evidence utterance, or utterance key in the historical multi-intent maps. Repeated occurrences are retained for coverage accounting; unique raw wording is also shown.

| Version | Fixture | Utterance-bearing occurrences | Unique raw utterances |
|---|---|---:|---:|
| V0 | `tests/Fixtures/chat_evaluation.php` | 187 | 187 |
| V1 | `tests/Fixtures/chat_blind_holdout.php` | 130 | 125 |
| V2 | `tests/Fixtures/chat_blind_v2.php` | 144 | 137 |
| V3 | `tests/Fixtures/chat_blind_v3.php` | 159 | 152 |
| V4 | `tests/Fixtures/chat_blind_v4.php` | 216 | 209 |
| V5 | `tests/Fixtures/chat_blind_v5.php` | 259 | 224 |
| V6 | `tests/Fixtures/chat_blind_v6.php` | 284 | 241 |
| V7 | `tests/Fixtures/chat_blind_v7.php` | 444 | 312 |
| V8 | `tests/Fixtures/chat_blind_v8.php` | 435 | 314 |
| V9 | `tests/Fixtures/chat_blind_v9.php` | 348 | 263 |
| **Total** |  | **2,606** | **2,123 across all versions** |

## EXACT DUPLICATES

The 292 primary utterances and all 78 utterance-bearing scenario turns were compared with all 2,606 historical occurrences.

- Raw exact historical duplicates: **0**.
- Result: PASS for raw exact comparison.

## NORMALIZED DUPLICATES

Audit normalization used Unicode NFKC, Unicode case folding, whitespace collapse, and punctuation-to-boundary normalization. It did not remove semantic tokens or accents.

- Normalized-only historical duplicates: **0**.
- Result: PASS for normalized exact comparison.

## NEAR-DUPLICATE RESULTS

Token sets were built from the audit-normalized strings. Jaccard similarity was computed for every R3/historical pair, excluding normalized-equal pairs.

- Threshold: **token Jaccard >= 0.82**.
- Flagged pairs: **0**.
- Result: PASS for the threshold stage.

The threshold result was not treated as proof of structural independence; the separate template audit below was still performed.

## TEMPLATE LEAKAGE

The structural audit folded accents for comparison, substituted known product phrases with `PRODUCT`, substituted numeric/number-word quantities with `QTY`, and removed a narrow list of politeness markers. Candidate pairs required an exact skeleton or both token overlap and sequence similarity; every flagged pair was manually reviewed against its original wording.

The audit produced 68 pair occurrences covering 13 R3 records. Manual classification was:

- `TRUE_LEAKAGE`: 30 pair occurrences covering **6 primary cases**;
- `CANONICAL_SHORT_PHRASE`: 38 pair occurrences covering 5 primary cases and 2 scenario turns;
- `BENIGN_DOMAIN_OVERLAP`: 0 among the structural-threshold flags;
- `UNCERTAIN`: 0.

True leakage cases:

| R3 case | R3 wording | Representative historical wording | Classification |
|---|---|---|---|
| `V10-ST-0137` | “Put three Australian apples in the cart.” | V4: “please put six Trà Gừng in the cart” | Same `put QTY PRODUCT in the cart` skeleton |
| `V10-ST-0132` | “Add two purple grapes to my cart.” | V2: “add eight Sữa Tươi to my cart please” | Same `add QTY PRODUCT to my cart` skeleton |
| `V10-ST-0142` | “them 20 nho tim vao gio” | V0: “them 100 rau cu tuoi vao gio” | Product/quantity-only substitution |
| `V10-ST-0129` | “Hãy thêm 2 Sữa Hộp vào giỏ giúp tôi.” | V2: “thêm 2 Nước Cam vào giỏ” | Product/politeness-only substitution |
| `V10-ST-0131` | “them 5 qua chuoi vao gio” | V0: “them 100 rau cu tuoi vao gio” | Same cart-command template with unit variation |
| `V10-ST-0139` | “Xin thêm 1 Thịt bò nạt vào giỏ hàng.” | V2: “thêm 2 Nước Cam vào giỏ” | Same cart-command template with politeness and `hàng` variation |

The canonical-short-phrase group contains minimal formulations such as `PRODUCT giá bao nhiêu`, `PRODUCT còn hàng không`, `PRODUCT hết chưa`, and short deictic follow-ups. These were not counted as true leakage because their remaining language is the irreducible request itself.

Conversation-level comparison covered all 39 R3 scenarios against 145 reconstructed historical follow-up scenario units. It found **0 near-identical two-turn conversation structures** under the stated structural criteria. This does not cure the six primary-case leaks.

Result: **FAIL**.

## R3 REPLACEMENT REVIEW

The two replacement cases were identified only from the R3 freeze manifest and reviewed without consulting the R2 audit.

- `V10-R3-ST-1f6748ba`: no exact, normalized, Jaccard-threshold, or matching-template leak. Its nearest historical wording was V1 “giờ rau củ tươi giá sao” (token Jaccard 0.600); the R3 syntax and construction are materially different. Classification: `BENIGN_DOMAIN_OVERLAP`.
- `V10-R3-ST-579dee27`: no exact, normalized, Jaccard-threshold, or matching-template leak. Classification: clean.

Result for the two replacements themselves: PASS.

## INTENT GOLD

The assigned intent/capability labels are coherent with the utterances across product search/detail/price/stock/catalog, shipping, cart, order/payment reads, knowledge, general chat, OOD, clarification, privileged actions, and multi-intent cases.

No intent-label defect was required to establish the failures below. Result: PASS for intent labels, subject to business-outcome and evidence defects.

## BUSINESS OUTCOME GOLD

Most handlers, terminals, and high-level outcomes are consistent with their requested operations. One outcome is not supportable:

- `V10-ST-0036`, “Nho tím dùng trực tiếp được không shop?”, requires `ANSWER` / `RETURN_GROUNDED_PRODUCT_DETAILS`. The current Product authority for `Nho tím` does not state that it is suitable for direct consumption. Category membership and a generic product description do not support the requested fact. The gold should not require an affirmative factual answer from this source.

Result: **FAIL**.

## ENTITY GOLD

The entity audit fails independently:

1. The declared entity schema has only `product_raw_mention`, `canonical_product`, `quantity`, `unit`, `order_reference`, `ordinal_reference`, and `context_reference`. It has no `account_target` or `requested_mutation_value`, although both are required audit dimensions.
2. Corrected case `V10-ST-0263` explicitly targets `linh@example.test`, but all entity slots are null. The target was correctly not fabricated as an order reference, but the expressed account target disappears instead of being represented separately.
3. Corrected inventory-mutation cases `V10-ST-0254`, `V10-ST-0255`, and `V10-ST-0256`, plus multi-intent branch `V10-ST-0280/mutate_stock`, place a requested stock state/delta in `quantity`. This conflates purchase quantity with requested mutation value.
4. Role, payment-state, and order-state mutation requests cannot preserve requested values such as `admin`, `paid`, `delivered`, or `cancelled` in the current schema.
5. `V10-ST-0206` explicitly names `Thịt bò nạt`, but its entity gold is entirely null. A `NO_EVIDENCE` terminal does not erase the expressed product entity.

The remaining deictic corrections preserve raw references, unresolved canonical entities, and explicit quantities as required. Canonical products that are present all resolve to one of the 11 active products.

Result: **FAIL**.

## AUTH GOLD

Anonymous, owner, non-owner, and verified-customer preconditions are explicit where authorization changes the outcome. Order/payment reads are owner-scoped; anonymous reads require authentication; cross-account reads are denied; cart suggestions require an authenticated verified customer.

Result: PASS.

## FOLLOW-UP GOLD

All 39 scenarios contain exactly two user turns, start from a fresh declared precondition, establish their own first-turn context, declare the turn-two context state, and have a valid resolved, ambiguous, or expired reference. Counts by subtype match the manifest.

Result: PASS.

## MULTI-INTENT GOLD

All 27 cases contain their expected branches: 25 have two branches and 2 have three branches, for 56 total. No branch is structurally omitted, and parent mixed/all-handled terminals agree with branch terminals.

Branch `V10-ST-0280/mutate_stock` inherits the requested-mutation-value entity defect described above. Structural completeness passes; entity semantics for the complete branch set fail.

## EVIDENCE DOMAIN

Domains align with the actual information need: Product/Category data for catalog facts, SiteSetting for shipping/contact facts, owned-order authority for private reads, and topic-specific knowledge sources for account/order/payment/ordering claims. Missing-policy cases use distinct domains and empty source sets.

Result: PASS.

## SOURCE AUTHORITY

The live structured authority contains 11 active products in 5 active categories. Product names, prices, inventories, categories, and active states match the frozen registry. SiteSetting matches the frozen fee (20,000 VND), free-shipping threshold (200,000 VND), and contact facts.

All five referenced knowledge documents exist and are published with the expected versions: `account-guide-vi` v2, `order-guide-vi` v2, `payment-guide-vi` v2, `shopping-guide-vi` v2, and `policy-index-vi` v1. All source IDs and declared source versions resolve.

Result: PASS.

## CLAIM SUPPORT

Knowledge-source minimum facts were checked against the published chunk content and are supported. Shipping arithmetic and inventory comparisons agree with the current structured authority.

`V10-ST-0036` fails claim-level support as described under Business Outcome Gold: the allowed Product source does not contain the direct-consumption fact requested by the utterance.

Result: **FAIL**.

## OOD/DENIED SEPARATION

The dataset keeps `UNSUPPORTED`, `DENIED`, `NO_EVIDENCE`, `CLARIFICATION_REQUIRED`, `AUTH_REQUIRED`, and normal supported answers distinct at the intent/terminal level. The primary counts are 23 true OOD, 22 denied, 18 no-evidence, 11 clarification-required, and 5 auth-required.

Result: PASS for separation labels and terminals. Entity preservation still fails on denied/no-evidence cases as reported above.

## PRIVILEGED GOLD

All 16 primary privileged cases select denial for payment mutation, inventory mutation, order mutation, role change, auth bypass, other-user access, or secret disclosure. Benign owner reads remain allowed.

The authorization decision is correct, but privileged entity gold is incomplete because target accounts and requested mutation values have no distinct representation.

Result: **FAIL** for complete privileged gold.

## LANGUAGE DISTRIBUTION

Recomputed primary counts match the manifest:

| Bucket | Count |
|---|---:|
| `en` | 68 |
| `mixed` | 48 |
| `vi_conversational` | 58 |
| `vi_formal` | 67 |
| `vi_no_diacritics` | 51 |

## CAPABILITY DISTRIBUTION

Recomputed primary counts match the manifest:

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

Additional recomputed values:

- entity-bearing primary cases: 167 using only the primary `entity_gold`, or 171 when branch entities are included, matching the manifest's branch-inclusive value;
- privileged primary cases: 16;
- true OOD primary cases: 23;
- multi-intent primary cases: 27;
- annotated branches: 56;
- follow-up subtype counts: singular 5, plural 4, ordinal-first 4, ordinal-second 4, ordinal-last 4, deictic 5, ellipsis 5, expired 4, ambiguous 4.

Distribution counts themselves pass.

## GENERATED-DATA QUALITY

There is no literal intent/handler/terminal label leakage in the 370 evaluated utterances. Utterance length ranges from 2 to 14 word tokens, with median 7.

The six true template leaks show generator-style cart-command reuse across versions. Several other short price/stock formulations are repetitive but were classified as canonical short phrases. The dataset is deliberately stratified and includes 39 OOD/privileged primary cases; these counts are useful for capability testing but do not establish population representativeness.

Result: FAIL because the observed generated template reuse crosses the historical holdout boundary.

## SCHEMA CONSISTENCY

The following pass: JSON structure, declared required primary fields, unique IDs, unique NFKC/case-folded primary utterances, terminal vocabulary, declared entity-object key consistency, source references/versions, grounding mirrors, canonical product validity, quantity/inventory checks, auth-state checks, shipping arithmetic, multi-intent counts/required fields, parent terminals, scenario shape, and manifest distributions.

The required audit schema fails because `account_target` and `requested_mutation_value` are absent. This is not merely an optional extension: the missing fields cause loss or conflation of user-expressed security entities in actual R3 cases.

Result: **FAIL**.

## FINAL COUNTS

- Primary cases audited: **292**.
- Multi-turn scenarios audited: **39**.
- Multi-turn turns audited: **78**.
- R3 utterance-bearing records checked for leakage: **370**.
- Historical utterance-bearing occurrences checked: **2,606** across V0-V9.
- Exact duplicates: **0**.
- Normalized duplicates: **0**.
- Jaccard >= 0.82 flags: **0**.
- True structural leakage cases: **6 primary cases**.
- R3 replacements reviewed: **2**.
- Entity-gold corrections reviewed: **13**.

## FINAL DATASET SHA-256

Not created because the audit failed.

## AUDIT REPORT SHA-256

Detached delivery hash. The exact SHA-256 is reported after this file is frozen; embedding a file's own final hash in that same file would change the hash.

## FINAL MANIFEST SHA-256

Not created because the audit failed.

## AUDIT DECISION

**AUDIT FAIL — LEAKAGE + GOLD + DISTRIBUTION / SCHEMA**

Distribution counts pass; the combined category includes `/ SCHEMA` because the required entity schema fails.

## NEXT STEP

Stop. Do not run the candidate. Do not create or freeze final V10 from R3. Preserve R3 unchanged. A different independent author must create an R4 candidate that removes the six leaked templates, adds distinct account-target and requested-mutation-value representation, preserves the omitted product entity, and corrects the unsupported claim gold before a new fresh audit.
