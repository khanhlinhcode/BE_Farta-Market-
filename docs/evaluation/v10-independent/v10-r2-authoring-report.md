# Farta Market Blind Holdout V10 — Independent Authoring Report R2

## Freeze summary

- Dataset version: `V10-blind-independent-2026-09-26-r2`
- Schema version: `farta-v10.2.0`
- Revision: **2**
- Authoring timestamp (UTC): `2026-09-26T14:14:14Z`
- Original/final primary cases: **292 / 292**
- Original/final multi-turn scenarios: **39 / 39**
- Multi-turn turns: **78**
- Replacement primary cases: **4**
- Replacement scenarios: **9**
- Multi-intent primary cases/branches: **27 / 56**
- Knowledge primary cases: **38** (20 approved-evidence; 18 `NO_EVIDENCE`)
- Entity-bearing primary cases, including branch-only entities: **160**
- Privileged mutation primary cases: **16**
- Security-relevant primary cases: **28**
- Follow-up scenarios: **39**

## Gold defect classes corrected

1. Product-search outcomes now include exact active-snapshot matches and cardinalities. The under-30,000 VND fruit case resolves to Chuối and Ổi; an undefined “around 50k” range fails closed to clarification.
2. Published knowledge references now use the registry's exact `source_id`, with document `version` stored separately and pinned.
3. Public, verified-customer, owner and non-owner auth preconditions are explicit, including every multi-intent branch whose terminal depends on auth.
4. Raw product mention, canonical product, quantity, unit, order reference, ordinal and context reference remain separate. Every follow-up setup turn now records raw mention(s) independently.
5. Follow-up setup intent reflects the actual setup request. Resolved references use structured product authority; ambiguous and expired references clarify without guessing.

## Primary language distribution

| Bucket | Count |
| --- | ---: |
| `en` | 68 |
| `mixed` | 48 |
| `vi_conversational` | 58 |
| `vi_formal` | 67 |
| `vi_no_diacritics` | 51 |

## Primary intent/capability distribution

| Bucket | Count |
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

## Primary terminal-state distribution

| Bucket | Count |
| --- | ---: |
| `ALL_BRANCHES_HANDLED` | 16 |
| `ANSWER` | 163 |
| `ANSWER_OR_NOT_FOUND` | 1 |
| `AUTH_REQUIRED` | 5 |
| `CLARIFICATION_REQUIRED` | 11 |
| `DENIED` | 22 |
| `NO_EVIDENCE` | 18 |
| `NO_RESULTS` | 4 |
| `PARTIAL_MIXED_TERMINALS` | 11 |
| `SUGGESTED_ACTION` | 16 |
| `UNAVAILABLE` | 2 |
| `UNSUPPORTED` | 23 |

## Primary difficulty distribution

| Bucket | Count |
| --- | ---: |
| `edge_case` | 34 |
| `moderately_ambiguous` | 47 |
| `normal_paraphrase` | 37 |
| `security_ood` | 27 |
| `straightforward` | 147 |

## Follow-up subtype distribution

| Bucket | Count |
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

All 39 scenarios are two-turn, fresh-session scenarios. The nine mapped parent scenarios were replaced independently. Resolved R2 references return current structured price or inventory facts; ambiguous and expired references terminate `CLARIFICATION_REQUIRED` with no canonical guess.

## Authority and methodology

- Current structured authority was read directly from active Product/Category rows and the current SiteSetting row: 11 active products, 5 active categories, shipping fee 20,000 VND and free-shipping threshold 200,000 VND.
- The approved registry contained five published documents. Their base source IDs and separate versions were independently verified against stored published content.
- Static knowledge was not used to override current product, catalog, shipping, contact or owned-order values.
- R1 artifacts remained unchanged. Only the sanitized V2 brief supplied replacement-parent IDs; removed utterances were not inspected.
- No candidate, chatbot, Blind V10 score or historical leakage audit was run.

## Validation

All **31** schema and internal-consistency checks passed before freeze:

- `json_serializable`: **PASS**
- `primary_count_292`: **PASS**
- `scenario_count_39`: **PASS**
- `scenario_turn_count_78`: **PASS**
- `unique_primary_ids`: **PASS**
- `unique_scenario_ids`: **PASS**
- `unique_primary_utterances_nfkc`: **PASS**
- `unique_scenario_conversations_nfkc`: **PASS**
- `required_case_fields`: **PASS**
- `entity_schema`: **PASS**
- `terminal_values`: **PASS**
- `language_distribution`: **PASS**
- `intent_distribution`: **PASS**
- `scenario_type_distribution`: **PASS**
- `multi_intent_counts`: **PASS**
- `entity_bearing_count_160`: **PASS**
- `true_ood_count_23`: **PASS**
- `privileged_count_16`: **PASS**
- `replacement_primary_exact`: **PASS**
- `replacement_scenario_exact`: **PASS**
- `evidence_source_references_valid`: **PASS**
- `knowledge_versions_separate_and_pinned`: **PASS**
- `published_knowledge_only`: **PASS**
- `knowledge_gold_consistent`: **PASS**
- `product_search_snapshot_gold`: **PASS**
- `canonical_products_active`: **PASS**
- `structured_price_inventory_facts`: **PASS**
- `auth_preconditions_explicit`: **PASS**
- `followups_self_contained_and_safe`: **PASS**
- `shipping_arithmetic`: **PASS**
- `candidate_executed_false`: **PASS**

## Known limitations

- Product, price, inventory and SiteSetting values are frozen to the 2026-09-26 authority snapshot.
- “Around 50k” has no approved numeric tolerance, so the corresponding search gold requests clarification rather than inventing a range.
- Symbolic order references require owner/non-owner/anonymous harness states; no private order row is embedded.
- Approved knowledge documents are Vietnamese. Cross-language evaluation may translate supported facts but may not add new claims.
- No approved source exists for the `NO_EVIDENCE` policy domains represented in this dataset.
- Product descriptions containing broad health-oriented marketing text are not treated as medical authority.
