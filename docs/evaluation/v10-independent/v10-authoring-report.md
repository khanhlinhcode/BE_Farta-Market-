# Farta Market Blind Holdout V10 — Independent Authoring Report

## Freeze candidate summary

- Dataset version: `V10-blind-independent-2026-09-26-r1`
- Schema version: `farta-v10.1.1`
- Authoring timestamp (UTC): `2026-09-26T13:10:18Z`
- Primary single-turn cases: **292**
- Explicit multi-turn/follow-up scenarios: **39**
- Total multi-turn turns: **78**
- Multi-intent primary cases: **27** (56 annotated branches)
- OOD primary cases: **23**
- Privileged/security mutation primary cases: **16**
- Multi-intent denied branches: **6**
- Knowledge/grounding primary cases: **38** (20 approved-evidence; 18 NO_EVIDENCE)
- Multi-intent NO_EVIDENCE branches: **2**
- Multi-intent unsupported/OOD branches: **3**
- Entity-bearing primary cases: **160**
- Follow-up scenarios: **39**

No candidate was executed and no candidate performance is reported.

## Intent distribution — primary cases

| Gold intent | Count |
|---|---:|
| cart_action_request | 18 |
| cart_informational | 10 |
| catalog_listing | 12 |
| clarification | 10 |
| general_chat | 8 |
| knowledge_query | 20 |
| missing_evidence_query | 18 |
| multi_intent | 27 |
| order_read | 14 |
| payment_status_read | 10 |
| price | 18 |
| privileged_mutation | 16 |
| product_detail | 18 |
| product_search | 24 |
| shipping_calculation | 16 |
| shipping_current_value | 12 |
| stock_availability | 18 |
| unsupported_ood | 23 |

## Language distribution — primary cases

| Language bucket | Count |
|---|---:|
| en | 68 |
| mixed | 48 |
| vi_conversational | 58 |
| vi_formal | 67 |
| vi_no_diacritics | 51 |

The English bucket is intentionally material, not token coverage. Vietnamese spans formal, conversational and no-diacritics input; mixed-language cases reflect ordinary Vietnamese commerce chat with English nouns and commands.

## Difficulty distribution — primary cases

| Difficulty | Count |
|---|---:|
| edge_case | 34 |
| moderately_ambiguous | 47 |
| normal_paraphrase | 37 |
| security_ood | 27 |
| straightforward | 147 |

## Terminal-state distribution — primary cases

| Terminal | Count |
|---|---:|
| ALL_BRANCHES_HANDLED | 16 |
| ANSWER | 163 |
| ANSWER_OR_NOT_FOUND | 1 |
| AUTH_REQUIRED | 5 |
| CLARIFICATION_REQUIRED | 10 |
| DENIED | 22 |
| NO_EVIDENCE | 18 |
| NO_RESULTS | 5 |
| PARTIAL_MIXED_TERMINALS | 11 |
| SUGGESTED_ACTION | 16 |
| UNAVAILABLE | 2 |
| UNSUPPORTED | 23 |

## Follow-up distribution

| Scenario type | Count |
|---|---:|
| ambiguous_reference | 4 |
| deictic_that | 5 |
| ellipsis | 5 |
| expired_context | 4 |
| ordinal_first | 4 |
| ordinal_last | 4 |
| ordinal_second | 4 |
| plural_reference | 4 |
| singular_reference | 5 |

| Scenario language | Count |
|---|---:|
| en | 9 |
| mixed | 6 |
| vi_conversational | 8 |
| vi_formal | 8 |
| vi_no_diacritics | 8 |

All scenarios establish their own context. Every turn records expected context, handler and terminal state. Expired and ambiguous contexts explicitly require clarification. No inactive/deleted-entity scenario was authored because the approved product snapshot contained only active entities and no authoritative inactive test environment was available.

## Methodology

1. The behavioral and source-authority contracts in the V10 authoring request were converted into a schema before case writing.
2. Business facts were captured read-only from current structured business tables: products, categories and site settings. Only published entries in the knowledge registry and their stored content were accepted as policy authority.
3. Product, price, stock and deterministic shipping cases were golded against the frozen structured snapshot. Order cases use symbolic owner/non-owner references so no private customer record is embedded in the holdout.
4. Knowledge cases were separated by topic and explicit source ID. Topics absent from the published registry were assigned `NO_EVIDENCE`; no policy was invented.
5. Utterances were authored across five language buckets, with varied word order, politeness, brevity, light slang, ellipsis and ordinary code-switching.
6. Multi-intent items were decomposed before any execution. Every branch has intent, operation, entity slots, handler, evidence authority, terminal state and explicit minimum facts.
7. Security coverage combines benign owner-scoped reads with natural-looking prohibited mutations, bypasses, cross-account access and secret disclosure.
8. OOD coverage includes ordinary unrelated requests and lexical collisions such as “rau”, “giá”, “order” and “stock” whose overall meaning is not commerce support.
9. The artifacts were validated structurally and statistically without running a chatbot, runtime, unit test or evaluation harness. Gold is frozen before candidate execution.

A pre-delivery validation hardening pass made branch-level minimum facts and grounding objects explicit for all multi-intent branches. It changed no utterance, intent, entity, outcome, terminal state, source authority, count or distribution, and no candidate had been executed. This change is recorded in the dataset authoring log.

## Allowed sources used

| Source ID | Authority | Scope used |
|---|---|---|
| `DB_PRODUCTS_2026-09-26` | Structured Product DB snapshot | 11 active product names, current prices, inventory, categories and stored descriptions |
| `DB_CATEGORIES_2026-09-26` | Structured Category DB snapshot | 5 active category names |
| `DB_SITE_SETTINGS_2026-09-26` | Structured SiteSetting snapshot | shipping fee 20,000 VND; free-shipping threshold 200,000 VND; current store contact fields |
| `OWNED_ORDER_RUNTIME_AUTHORITY` | Authorized runtime business scope | authenticated actor’s own order/payment read only; no private rows frozen |
| `account-guide-vi@v2` | Published approved knowledge | registration, email verification, resend and password reset |
| `order-guide-vi@v2` | Published approved knowledge | owner-scoped reads, order statuses, self-cancel conditions, chatbot read-only boundary |
| `payment-guide-vi@v2` | Published approved knowledge | COD, SePay, exact transfer matching, server confirmation, expiry handling |
| `shopping-guide-vi@v2` | Published approved knowledge | search, cart suggestion/confirmation, checkout checks and coupon validation |
| `policy-index-vi@v1` | Published approved knowledge | verified-topic index only |

The source IDs and fact snapshots are embedded in the dataset for auditability. Public-web discovery did not produce an attributable Farta Market storefront, so unrelated search results were excluded and no public URL was treated as authority.

## Known limitations

- Product, inventory, price and shipping values are a freeze-time snapshot and may diverge from later live data; evaluators should use the embedded snapshot or explicitly remap to a controlled environment without changing gold semantics.
- Symbolic order references require the evaluation harness to provide owner, non-owner, anonymous and nonexistent states. This avoids including real customer data.
- Approved knowledge documents are Vietnamese. English and mixed-language cases test grounded cross-language rendering, but may not add facts beyond the Vietnamese authority.
- There was no approved authority for returns, refunds, warranties, delivery SLA, cold-chain guarantees, certifications, supplier provenance, loyalty, privacy, bulk-order or subscription policies; these deliberately terminate at `NO_EVIDENCE`.
- All products in the authoritative snapshot were active. Inactive/deleted-entity follow-ups were omitted instead of fabricating unsupported entities.
- Some stored fruit descriptions contain broad health-oriented marketing language. V10 avoids treating those descriptions as medical advice or as authority for disease-treatment claims.
- No real owned-order content was inspected. Order correctness is therefore evaluated as authorization, routing and terminal behavior against symbolic harness states, not as a frozen customer record.

## Independence attestation

During authoring, no Phase 12 material, router/runtime/chatbot implementation, git history/diff, V0–V9 fixture/evaluation, candidate prediction/result/log, development seed, internal failure-pattern prompt, secret file or private customer order was read. The chatbot/candidate and its tests were not run. This report contains authoring statistics only.
