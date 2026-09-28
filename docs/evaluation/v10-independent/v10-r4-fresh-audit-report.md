# V10 R4 Fresh Independent Leakage, Gold & Schema Audit

Audit date: 2026-09-26  
Audited artifact: `v10-r4-candidate-dataset.json`  
Dataset schema: `farta-v10.3.0`  
Decision: **AUDIT FAIL — ACCOUNT_TARGET COVERAGE, CLAIM SUPPORT / GOLD, GENERATED-DATA QUALITY**

## 1. EXECUTIVE SUMMARY

R4 freeze integrity passed and historical leakage checks found no
`TRUE_LEAKAGE`. The six R4 replacements are historically clean and do not
reuse one internal generated sentence skeleton.

R4 is nevertheless not a clean final release holdout. Four material defects
were independently found:

| Finding | Severity | Record | Defect |
|---|---:|---|---|
| F-01 | High | `V10-ST-0259` | The explicit mutation target `tài khoản của tôi` is not preserved in `account_target`; observed coverage is 7 but independently expected coverage is 8. |
| F-02 | High | `V10-ST-0030` | The requested claim about what Xoài keo is suitable for is absent from allowed Product authority, but gold requires `ANSWER` from `product_catalog`. |
| F-03 | High | `V10-ST-0272/detail` | The same unsupported Xoài keo usage claim is required as an `ANSWER` branch, making both branch gold and the top-level all-branches outcome wrong. |
| F-04 | High | `V10-ST-0142` | The replacement wording says the cart should be completed to 20 products; it does not unambiguously request 20 units of Nho tím, while gold assigns `quantity=20` to Nho tím and requires a cart suggestion. |

Because all pass requirements are conjunctive, no final audited dataset or
final manifest was created.

## 2. AUDITOR INDEPENDENCE SCOPE

Read scope was limited to:

- the three frozen R4 artifacts;
- V0-V9 fixture files for leakage comparison only;
- current Product, Category and SiteSetting structured records;
- the published knowledge registry and its five approved source documents;
- the frozen evaluation schema/contract and owned-order authorization contract.

The audit did not read R1/R2/R3 audit reports, remediation briefs, Phase 12
reports, candidate predictions, historical failure analyses, chatbot/router
implementation, or prior auditor reasoning. The R3 parent file was hashed for
lineage verification without inspecting its content. No candidate was run or
scored. No R4 record, schema, runtime file, commit, deployment, or remote state
was modified.

## 3. R4 FREEZE VERIFICATION

| Artifact | Expected SHA-256 | Observed SHA-256 | Result |
|---|---|---|---|
| `v10-r4-candidate-dataset.json` | `1f9ee94637ce70fc7e64fb03d94ebf211b85a455e79d80c6c939cd58b995b19b` | `1f9ee94637ce70fc7e64fb03d94ebf211b85a455e79d80c6c939cd58b995b19b` | PASS |
| `v10-r4-authoring-report.md` | `2c75810c110e26e1b39cb48668e9f319b8108527142a8dff63471b1ce456a2fa` | `2c75810c110e26e1b39cb48668e9f319b8108527142a8dff63471b1ce456a2fa` | PASS |
| `v10-r4-freeze-manifest.json` | `23dfbb01f006e8a0def1a0139107ad202e66da07d8361c7326147fe17db7427e` | `23dfbb01f006e8a0def1a0139107ad202e66da07d8361c7326147fe17db7427e` | PASS |

JSON parsing passed. Schema is `farta-v10.3.0`. Manifest revision is 4 with
parent revision 3. The manifest parent hash
`f87149909d3bbd0bbad9ecbc4283863efcd0e8a565f06f2079c65c0bdca04ef5`
matches the on-disk R3 parent hash. Counts independently recompute to 292
primary cases, 39 scenarios, 78 turns, 27 multi-intent cases and 56 branches.

## 4. HISTORICAL COVERAGE

Every fixture present in `tests/Fixtures` for V0-V9 was audited. An
utterance-bearing occurrence is a fixture string stored as `query`, `initial`
or `utterance`, a case key in `multi_types`, `multi_gold` or `grounding`, or a
string item in `missing_evidence`. This includes the differing nested follow-up
layouts in V1-V4.

| Version | Fixture | Utterance-bearing occurrences | Unique raw utterances |
|---|---|---:|---:|
| V0 | `tests/Fixtures/chat_evaluation.php` | 187 | 187 |
| V1 | `tests/Fixtures/chat_blind_holdout.php` | 130 | 125 |
| V2 | `tests/Fixtures/chat_blind_v2.php` | 144 | 137 |
| V3 | `tests/Fixtures/chat_blind_v3.php` | 159 | 152 |
| V4 | `tests/Fixtures/chat_blind_v4.php` | 216 | 209 |
| V5 | `tests/Fixtures/chat_blind_v5.php` | 259 | 224 |
| V6 | `tests/Fixtures/chat_blind_v6.php` | 284 | 241 |
| V7 | `tests/Fixtures/chat_blind_v7.php` | 410 | 309 |
| V8 | `tests/Fixtures/chat_blind_v8.php` | 366 | 311 |
| V9 | `tests/Fixtures/chat_blind_v9.php` | 328 | 262 |
| **Total** | 10 fixtures | **2,483** | **2,120 corpus-wide raw unique; 2,117 corpus-wide normalized unique** |

Historical coverage is complete for the available V0-V9 fixture chain.

## 5. EXACT DUPLICATES

Compared all 292 primary utterances and all 78 utterance-bearing scenario
turns against all 2,483 historical occurrences.

- R4 utterances checked: 370.
- Raw occurrence-pair comparisons: 918,710.
- Raw exact matches: **0**.
- True exact historical duplicates: **0**.

Result: PASS.

## 6. NORMALIZED DUPLICATES

Normalization was Unicode NFKC, Unicode case folding, conversion of Unicode
punctuation to token boundaries, and whitespace collapse. Semantic words were
not deleted. Signed symbols were not treated as semantic words to remove.

- Normalized matches: **0**.
- True normalized historical duplicates: **0**.
- R4 primary normalized utterances are internally unique: **292/292**.

Result: PASS.

## 7. NEAR-DUPLICATE AUDIT

Dependency-light token-set Jaccard was computed after the transparent
normalization above.

- Candidate occurrence pairs: 918,710.
- Initial flag threshold: Jaccard >= 0.82.
- Flagged pairs: **0**.
- Manual classifications required at this layer: **0**.

This zero result was not used as proof of independence; the structural audit
below was run separately.

## 8. TEMPLATE / STRUCTURAL LEAKAGE

The structural pass replaced known current and historical product identities
with `PRODUCT`, numeric and number-word quantities with `QTY`, and a narrow set
of politeness markers with a normalized marker. A second signal accent-folded
Vietnamese text. Exact skeleton equality and skeleton token Jaccard >= 0.82
were reviewed.

- Known product aliases used: 207.
- Structural occurrence flags before collapsing repeated fixture occurrences: 72.
- Manual review items after collapsing by R4 ID, history version and historical text: 66.
- R4 utterances represented by flags: 17.
- Two-turn historical follow-up structures: 152.
- R4-to-history two-turn comparisons: 5,928.
- Two-turn structural flags: 0.

Manual classifications:

| Classification | R4 IDs | Count |
|---|---|---:|
| `CANONICAL_SHORT_PHRASE` | `V10-ST-0045`, `V10-ST-0063`, `V10-ST-0068`, `V10-ST-0077`, `V10-MT-004/T2`, `V10-MT-006/T2`, `V10-MT-008/T2`, `V10-MT-010/T2`, `V10-MT-012/T2`, `V10-MT-014/T2`, `V10-MT-019/T2`, `V10-MT-020/T2`, `V10-MT-028/T1`, `V10-R2-MT-3ce74690/T2` | 14 |
| `BENIGN_DOMAIN_OVERLAP` | `V10-R2-MT-8b260df3/T2`, `V10-R2-MT-1a8f43d2/T2`, `V10-R2-MT-f0395ab1/T2` | 3 |
| `TRUE_LEAKAGE` | none | 0 |
| `UNCERTAIN` | none | 0 |

The canonical flags are short price, stock, category or cart-command forms
whose limited wording follows directly from the information need. The three
benign flags share product-price vocabulary but retain materially different
reference/context structure. Result: PASS, 0 `TRUE_LEAKAGE`.

## 9. SIX R4 REPLACEMENTS

Replacement IDs were taken only from the R4 manifest.

| ID | Historical leakage classification | Best historical skeleton Jaccard |
|---|---|---:|
| `V10-ST-0129` | CLEAN | 0.2174 |
| `V10-ST-0131` | CLEAN | 0.3846 |
| `V10-ST-0132` | CLEAN | 0.3333 |
| `V10-ST-0137` | CLEAN | 0.5000 |
| `V10-ST-0139` | CLEAN | 0.3158 |
| `V10-ST-0142` | CLEAN | 0.2667 |

Across the 15 internal replacement pairs there was no exact skeleton reuse.
Maximum internal skeleton Jaccard was 0.435. There is no product-only,
quantity-only, politeness-only, fixed-clause-order, or label-correlated common
template repeated across all six.

Leakage result: PASS. `V10-ST-0142` separately fails gold/data-quality review
in F-04; `CLEAN` here means only that it is not historical or internal
template leakage.

## 10. SCHEMA VERSION

`farta-v10.3.0` explicitly defines:

- `account_target: string|null`: a literal account/user identifier or explicit
  raw target phrase; it may not contain an order reference.
- `requested_mutation_value: number|string|null`: a new target state, absolute
  value or signed delta; it may not contain purchase quantity or operation.

The version bump from `farta-v10.2.0` to `farta-v10.3.0` is consistent across
the frozen dataset, R4 report and R4 manifest. No incompatible current R4
schema declaration was found. Earlier frozen revisions are historical
artifacts, not current schema declarations.

Syntactic version result: PASS. Semantic completeness fails under F-01.

## 11. ENTITY OBJECT SHAPE

All 426 entity objects were inspected:

- 292 primary objects;
- 56 branch objects;
- 78 scenario-turn objects.

Every object has exactly the nine declared entity slots, including
`account_target` and `requested_mutation_value`; null is used where a slot is
not applicable. No mixed V10.2/V10.3 shape exists. Result: PASS.

## 12. ACCOUNT_TARGET AUDIT

Observed non-null primary values are semantically supported by their
utterances: `người khác`, `bạn mình`, `tai khoan khac`, `another customer`,
`khách khác` (two records), and `linh@example.test`.

Independently derived coverage:

- Expected non-null primary records: **8**.
- Observed non-null primary records: **7**.
- Missing annotations: **1** — `V10-ST-0259` must preserve the explicitly
  targeted `tài khoản của tôi`.
- Spurious annotations: **0**.

General policy mentions such as asking whether another account's order can be
viewed are not counted as an actual target resource. An ownership test against
an order reference is likewise not re-encoded as an account target. F-01 is a
direct mutation target and is not one of those exclusions.

Result: FAIL.

## 13. REQUESTED_MUTATION_VALUE AUDIT

Independently derived coverage matches the data:

- Expected non-null entity objects: **17**.
- Observed: **17** — 14 primary and 3 branch objects.
- Missing: **0**.
- Spurious: **0**.
- Wrong-value cases: **0**.

Values correctly cover `paid`, `cancelled`, `delivered`, `admin`, absolute
stock targets `999`, `0`, `500`, and the signed delta `+100`. Result: PASS.

## 14. QUANTITY VS MUTATION VALUE

No entity object has both a purchase `quantity` and a
`requested_mutation_value`. All cart-action quantities remain in `quantity`.
Stock targets and deltas remain in `requested_mutation_value`; stock mutation
values were not copied into purchase quantity. Result: PASS.

## 15. ENTITY PRESERVATION

Product, order, ordinal, context, quantity and mutation values are preserved
through `DENIED`, `NO_EVIDENCE`, `AUTH_REQUIRED` and
`CLARIFICATION_REQUIRED` terminals where expressed. The exception is F-01:
the explicit account mutation target in `V10-ST-0259` is erased by a null
`account_target`. Result: FAIL.

## 16. PRODUCT ENTITY GOLD

Every non-null canonical product resolves to one of the 11 current active
Product records. Product raw mentions are retained, including unresolved
search terms and ambiguous/expired references. `NO_EVIDENCE` does not
generally erase product identity. Result: PASS.

## 17. V10-ST-0206

The utterance explicitly names `Thịt bò nạt`. Gold preserves both:

- `product_raw_mention = "Thịt bò nạt"`;
- `canonical_product = "Thịt bò nạt"`.

The requested traceability commitment is absent from approved authority, so
`NO_EVIDENCE` remains correct. Result: PASS.

## 18. V10-ST-0036

Current Product authority identifies Nho tím, its category, price, stock and a
generic stored description. It does not support suitability for direct
consumption. R4 correctly uses `NO_EVIDENCE`, does not infer from category,
and preserves raw and canonical product identity. Result: PASS.

## 19. INTENT GOLD

Intent families are otherwise semantically separated and match the expressed
requests. `V10-ST-0142` is not deterministic enough for its assigned cart
action gold: the number 20 describes the desired completed cart count, not
unambiguously a quantity of Nho tím. A clarification outcome is required
before assigning that product quantity. Result: FAIL (F-04).

## 20. BUSINESS OUTCOME GOLD

Structured price, stock, catalog, shipping, authorization and ordinary
knowledge outcomes match their authorities. Business outcome gold fails for:

- `V10-ST-0030`: unsupported usage/suitability claim required as `ANSWER`;
- `V10-ST-0272/detail`: same unsupported claim in a branch;
- `V10-ST-0142`: deterministic cart suggestion from an ambiguous quantity.

Result: FAIL.

## 21. CLAIM SUPPORT

All claim-bearing Product and knowledge cases were checked against actual
allowed authority, not candidate behavior. Two positive-answer requirements
lack support:

- `V10-ST-0030` asks what Xoài keo is suitable for. Its stored Product text
  does not state a use or suitability for Xoài keo.
- `V10-ST-0272/detail` asks the same factual question and repeats the same
  unsupported positive-answer requirement.

The generic sentence `claims must not exceed the stored product description`
does not supply the missing fact and cannot turn topic relevance into claim
support. Result: FAIL (F-02, F-03).

## 22. PRODUCT/CATEGORY AUTHORITY

The current structured authority contains 11 active products and 5 active
categories. Canonical identities, categories, prices, inventory, catalog
membership, active state, deterministic search result sets and no-result cases
match the R4 frozen snapshot. Product/category structured facts pass. The two
unsupported suitability claims fail separately under claim support.

## 23. SHIPPING AUTHORITY

Current SiteSetting directly confirms:

- `shipping_fee_vnd = 20000`;
- `free_shipping_threshold_vnd = 200000`;
- subtotal at or above the threshold has zero shipping fee.

All 16 primary deterministic shipping calculations were recomputed; all unit
prices, quantities, subtotals, threshold decisions, fees and final totals
match. Multi-intent shipping facts also match. Result: PASS.

## 24. AUTH GOLD

The actual contract separates anonymous, authenticated owner,
authenticated non-owner and authenticated verified customer preconditions.
Own reads, authentication requirements, cross-account denial and cart
suggest-only behavior are correct. No hidden actor state is inferred. Result:
PASS for authorization outcomes; entity target completeness fails separately
under F-01.

## 25. PRIVILEGED GOLD

All 16 privileged primary cases and the privileged multi-intent branches were
reviewed. Denial is correctly separated from mutation understanding; order,
product and requested-value entities are retained. `V10-ST-0259` alone drops
its explicit account target, so privileged gold is not semantically complete.
Result: FAIL (F-01).

## 26. FOLLOW-UP GOLD

All 39 scenarios and 78 turns were reviewed. Each scenario is self-contained,
has an explicit fresh-session setup, a valid antecedent and a declared context
state. Singular, plural, ordinal, deictic and ellipsis references resolve to
the declared canonical products when active. Expired and ambiguous references
remain unresolved and request clarification. No hidden prior conversation is
needed. Result: PASS.

## 27. MULTI-INTENT GOLD

All 27 cases and 56 branches have the required branch fields, entity shape,
handler, operation, evidence domain, terminal, security expectation and
grounding object. Safe, denied, no-evidence and OOD siblings are not silently
dropped. The three new mutation-value branch annotations are correct.

`V10-ST-0272/detail` nevertheless requires an unsupported positive claim, and
the parent is therefore incorrectly `ALL_BRANCHES_HANDLED` rather than a mixed
supported/no-evidence result. Result: FAIL (F-03).

## 28. EVIDENCE DOMAIN

Returns, refund, delivery SLA, storage/cold-chain, payment, ordering, account,
owned-order, product price, product inventory and shipping settings remain
distinct. `V10-ST-0030` and `V10-ST-0272/detail` incorrectly use general
`product_catalog` authority for a product-usage-suitability need that the
authority does not answer. Result: FAIL for those two records.

## 29. SOURCE AUTHORITY

All nine registered source IDs exist in the frozen registry. Direct current
registry inspection confirmed that all five knowledge documents are
`published` with the required versions: account 2, order 2, payment 2,
shopping 2 and policy index 1. All 413 source references resolve, and all 62
knowledge version references match. Registry integrity is PASS; fact support
is FAIL only for F-02 and F-03.

## 30. NO_EVIDENCE

R4 has 19 primary `NO_EVIDENCE` terminals and 2 branch `NO_EVIDENCE`
terminals. Their requested facts are absent from the allowed authorities,
including returns, refund, warranty, delivery SLA, cold-chain, certification,
supplier provenance, privacy and traceability topics. `V10-ST-0036` is also
correctly gated.

The reverse-direction audit fails: two `ANSWER` requirements (`V10-ST-0030`
and `V10-ST-0272/detail`) should be evidence-gated. Result: FAIL.

## 31. OOD/DENIED SEPARATION

The data keeps `UNSUPPORTED`, `DENIED`, `NO_EVIDENCE`,
`CLARIFICATION_REQUIRED`, `AUTH_REQUIRED` and normal supported answers as
separate terminals and business outcomes. The 23 primary OOD cases are truly
outside the commerce support domain; security denials are not mislabeled OOD.
Result: PASS.

## 32. LANGUAGE DISTRIBUTION

Recomputed directly from the 292 primary records:

| Bucket | n |
|---|---:|
| English (`en`) | 68 |
| Mixed VN/EN (`mixed`) | 48 |
| Vietnamese conversational (`vi_conversational`) | 58 |
| Vietnamese formal (`vi_formal`) | 67 |
| Vietnamese no-diacritics (`vi_no_diacritics`) | 51 |
| **Total** | **292** |

Counts exactly match the R4 manifest.

## 33. CAPABILITY DISTRIBUTION

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

All counts exactly match the manifest.

## 34. ENTITY-BEARING COUNTS

Counting rule: an entity object is bearing if at least one of its nine slots
is non-null. Primary count considers only the primary object. Branch-inclusive
count counts a primary case once when either its primary object or any of its
branch objects is bearing.

- Primary entity-bearing cases: **172**.
- Branch-inclusive entity-bearing primary cases: **176**.

Counts match the manifest. They do not cure the missing required slot in F-01.

## 35. GENERATED-DATA QUALITY

The 292 normalized primary utterances are unique. The six R4 replacements are
structurally diverse and show no single repeated generated skeleton. The
overall language/capability design is broad enough for a release holdout.

`V10-ST-0142` is a material generator-style defect: its semicolon-separated
second clause changes the number's attachment from Nho tím quantity to a cart
total target, but gold treats it as an unambiguous product quantity. Result:
FAIL (F-04).

## 36. STRUCTURAL SCHEMA

PASS checks:

- valid JSON;
- expected schema version;
- unique top-level IDs;
- unique normalized primary utterances;
- required primary and branch fields;
- uniform 426-object entity shape;
- declared terminal vocabulary;
- recognized actor/security values;
- active canonical products;
- valid source IDs and versions;
- shipping arithmetic;
- 39 two-turn scenarios;
- 27 multi-intent cases and 56 branches;
- manifest structural counts.

Overall schema audit is FAIL because structural validity does not override the
semantic `account_target` omission in F-01.

## 37. FINAL COUNTS

| Measure | Count |
|---|---:|
| Primary cases | 292 |
| Multi-turn scenarios | 39 |
| Scenario turns | 78 |
| Multi-intent primary cases | 27 |
| Multi-intent branches | 56 |
| Entity objects | 426 |
| R4 replacements | 6 |
| Non-null `account_target` primary records | 7 observed / 8 expected |
| Non-null `requested_mutation_value` objects | 17 observed / 17 expected |

## 38. FINAL DATASET SHA-256

Not created. Audit failed; creating `v10-final-audited-dataset.json` is
prohibited by the audit contract.

## 39. AUDIT REPORT SHA-256

Computed only after this report is frozen and reported in the final handoff.
It is intentionally not embedded here because embedding a file's own digest
would change that digest.

## 40. FINAL MANIFEST SHA-256

Not created. Audit failed; creating `v10-final-manifest.json` is prohibited by
the audit contract.

## 41. AUDIT DECISION

**AUDIT FAIL — ACCOUNT_TARGET COVERAGE, CLAIM SUPPORT / GOLD,
GENERATED-DATA QUALITY.**

Freeze integrity and leakage independence pass. The conjunctive final-release
requirements fail on entity-schema semantic completeness, entity
preservation, privileged gold, business outcome gold, claim support,
multi-intent gold, evidence-domain correctness, reverse NO_EVIDENCE coverage
and generated-data quality.

No R4 repair was made. No final V10 artifact was frozen.

## 42. NEXT STEP

A different author should create R5, at minimum addressing F-01 through F-04,
then produce a new immutable candidate/report/manifest hash set for a fresh
independent audit. The auditor must not patch R4 in place or run the candidate.
