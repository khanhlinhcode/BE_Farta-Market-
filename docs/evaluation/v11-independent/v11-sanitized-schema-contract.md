# Sanitized V11 Schema Contract

## Purpose and compatibility

This document is a schema-only authoring bridge. It defines the JSON shape, field types, vocabularies, null rules, authority interfaces, and invariants needed to author a new independent evaluation dataset. It contains no example utterances, dataset records, historical identifiers, candidate outputs, performance results, or failure analysis.

- Contract version: `farta-v11-sanitized-schema-contract.1.0`
- Compatible dataset schema: `farta-v10.3.0`
- Shape change: none
- Dataset-size targets: not part of this contract

The symbolic schema taxonomy is preserved. Content-bearing literal values embedded in evaluator allowlists are deliberately not reproduced. In particular, authors must use the exposed symbolic business-outcome categories. The type of `requested_mutation_value` still permits a string, but its current string allowlist is content-bearing and therefore unavailable through this sanitized bridge; use `null` or a number unless a separately reviewed clean vocabulary revision is provided.

## Top-level dataset

The root is a JSON object with these required fields:

| Field | Type | Meaning and invariants |
|---|---|---|
| `schema_version` | string | Must equal the compatible dataset schema version. |
| `primary_cases` | array of objects | May be empty at parser level. Every record follows the primary-case contract, and `case_id` is unique in this collection. |
| `multi_turn_scenarios` | array of objects | May be empty at parser level. Every record follows the scenario contract, and `scenario_id` is unique in this collection. |
| `source_registry` | array of objects | Dataset-local evidence authority registry. `source_id` is unique in this collection. |
| `schema_contract` | object | Declared mirror of selected evaluator contract arrays. Values and array order must exactly match this contract. |

Primary IDs and scenario IDs occupy separate uniqueness scopes. Branch IDs are unique only within their containing primary case. The parser imposes no dataset-size target. It does impose the fixed per-scenario turn cardinality described below.

### Source registry entry

| Field | Type | Required | Nullable | Meaning and invariants |
|---|---|---:|---:|---|
| `source_id` | non-empty string | yes | no | Dataset-local source identifier; unique in the registry. |
| `authority_type` | any JSON value | yes | yes | Authority classification. The parser requires the field but does not constrain its type or vocabulary. Authors should use a stable descriptive string matching an authority category below. |
| `version` | integer or null | no | yes | When present, must be positive. |
| `facts` | array, object, or null | no | yes | Optional structured authority facts; a non-null scalar is invalid. |

Additional entry fields are parser-tolerated but are not part of this authoring contract.

### Declared `schema_contract`

The object requires these arrays:

- `primary_case_required_fields`: `case_id`, `utterance`, `language_bucket`, `gold_intent`, `expected_handler`, `expected_business_outcome_category`, `expected_terminal_state`, `entity_gold`, `required_evidence_domain`, `allowed_evidence_sources`, `grounding_gold`, `security_capability_expectation`, `preconditions`, `difficulty`, `deterministic_gold`, `multi_intent_branches`, `notes`.
- `multi_intent_branch_required_fields`: `branch_id`, `intent`, `operation`, `entity_gold`, `expected_handler`, `required_evidence_domain`, `allowed_evidence_sources`, `expected_terminal_state`, `expected_business_outcome_category`, `security_expectation`, `minimum_facts_required`, `grounding_gold`.
- `entity_gold_slots`: `product_raw_mention`, `canonical_product`, `quantity`, `unit`, `order_reference`, `ordinal_reference`, `context_reference`, `account_target`, `requested_mutation_value`.
- `terminal_state_values`: the complete terminal vocabulary in the order listed under Enum vocabularies.

These arrays are equality-checked, including order.

## Primary case

All fields in the following table are required except `allowed_evidence_source_versions`.

| Field | Type | Nullable | Vocabulary / invariant |
|---|---|---:|---|
| `case_id` | non-empty string | no | Unique within `primary_cases`; newly authored opaque ID. |
| `utterance` | non-empty string | no | Newly and independently authored user input. |
| `language_bucket` | string | no | `language_bucket`. |
| `gold_intent` | string | no | `primary_intent`. |
| `expected_handler` | string | no | `handler`; an evaluator business-path label, not an implementation path. |
| `expected_business_outcome_category` | string | no | Exposed symbolic `business_outcome_category`. |
| `expected_terminal_state` | string | no | `terminal`. |
| `entity_gold` | object | no | Exactly the entity fields defined below. |
| `required_evidence_domain` | string or null | yes | Non-null value uses `evidence_domain`. |
| `allowed_evidence_sources` | array of strings | no | Unique non-empty IDs present in `source_registry`; may be empty. |
| `grounding_gold` | object | no | Evidence and claim-support contract; mirrored fields equal the outer fields. |
| `security_capability_expectation` | object | no | Exact scalar members `decision` and `capability`. |
| `preconditions` | object | no | Actor/auth state and optional context preconditions. |
| `difficulty` | string | no | `difficulty`. |
| `deterministic_gold` | object or null | yes | Optional structured deterministic expectations. |
| `multi_intent_branches` | array of objects | no | Non-empty only for `multi_intent`; otherwise empty. |
| `notes` | non-empty string or null | yes | Optional author note with no scoring semantics. |
| `allowed_evidence_source_versions` | object or null | yes | Optional; keys are a subset of allowed source IDs and values are matching positive registry versions. |

`resource` is not a primary-case schema field. Additional record fields are parser-tolerated but are not part of this authoring contract.

### Preconditions

`preconditions` is an object with:

- `actor` — required non-null string from `actor`.
- `symbolic_order_reference` — optional null or non-empty string. It is permitted only for primary cases and binds owned-order authority without embedding private order data.
- `context_ttl_state` — optional non-null string from `context_ttl_state`.

Branch and scenario preconditions use the same `actor` and optional `context_ttl_state`, but they reject `symbolic_order_reference`.

### Security expectation

For a primary case or scenario, `security_capability_expectation` is an object with exactly these authored members:

- `decision`: required scalar string from `security_decision`.
- `capability`: required scalar string from `security_capability`.

For a multi-intent branch, `security_expectation` is instead a required scalar string from `branch_security_expectation`. It is not an object.

### Grounding and claim support

`grounding_gold` requires:

| Field | Type | Nullable | Invariant |
|---|---|---:|---|
| `required_evidence_domain` | string or null | yes | Equals the enclosing record's field. |
| `allowed_source_ids` | array of strings | no | Equals the enclosing allowed-source array, including order; every ID is unique and registered. |
| `expected_terminal_state` | string | no | Equals the enclosing terminal. |
| `minimum_facts_required` | array of non-empty strings | no | May be empty. For branches it also equals the enclosing branch field. |
| `allowed_source_versions` | object or null | yes | Optional subset mapping; each positive integer equals the registry version. |

There is no separate claim-support boolean. A supported factual answer is described by its required domain, allowed sources, optional version pins, and minimum facts. `NO_EVIDENCE` is the terminal used when required approved evidence is not available and the claim must not be asserted.

### Deterministic gold

`deterministic_gold` is null or an object. The accepted field names are:

`active_only`, `available`, `canonical_product`, `current_unit_price_vnd`, `expected_canonical_matches`, `expected_result_cardinality`, `filters`, `final_total_vnd`, `free_shipping_applies`, `quantity`, `requested_quantity`, `shipping_fee_vnd`, `subtotal_vnd`, `unit_price_vnd`, `unresolved_filter`.

Unknown names are rejected. The evaluator allows each value to be a JSON string, number, boolean, array, object, or null and does not declare a narrower per-field type. Due to the associative-object parser contract, a non-null object has at least one field.

## Entity contract

Every `entity_gold` object contains exactly the following fields. All are required keys and all allow null.

| Field | Non-null type | Meaning and resolution semantics |
|---|---|---|
| `product_raw_mention` | non-empty string or non-empty array of non-empty strings | Surface mention or mentions from the newly authored input. It may be present while `canonical_product` is null. |
| `canonical_product` | non-empty string or non-empty array of non-empty strings | Product resolved against structured product authority. Null means unresolved or not applicable and does not erase a raw mention. |
| `quantity` | number greater than zero | Purchase/item quantity. It is distinct from `requested_mutation_value`. |
| `unit` | non-empty string | Unit attached to the quantity or product reference. |
| `order_reference` | non-empty string | Order reference represented by the input or bound through preconditions. |
| `ordinal_reference` | non-zero integer | Signed positional reference used by context resolution. |
| `context_reference` | non-empty string | Abstract reference that depends on conversation context. |
| `account_target` | non-empty string | Raw or semantic account target. The schema does not impose normalization to a self/other label, identifier, or account object. |
| `requested_mutation_value` | number or string | Requested target state or delta for mutation semantics. Numbers have no schema range. Strings use a content-bearing evaluator allowlist that this sanitized bridge deliberately does not disclose. |

Null means absent, unresolved, or not applicable according to the field. It is not interchangeable with an empty string, zero, false, an empty array, or an omitted key.

## Multi-intent contract

A primary case whose `gold_intent` is `multi_intent` has at least one branch. Any other primary case has an empty `multi_intent_branches` array. Parent terminal and business outcome remain required. The parser validates branch completeness and isolation but does not compute a separate aggregation formula beyond the declared parent and branch fields.

Each branch requires:

| Field | Type | Nullable | Vocabulary / invariant |
|---|---|---:|---|
| `branch_id` | non-empty string | no | Unique within its parent; newly authored opaque ID. |
| `intent` | string | no | `branch_intent`. |
| `operation` | string | no | `operation`. |
| `entity_gold` | object | no | Independent branch-scoped entity contract. |
| `expected_handler` | string | no | `handler`. |
| `required_evidence_domain` | string or null | yes | `evidence_domain` when non-null. |
| `allowed_evidence_sources` | array of strings | no | Unique registered source IDs. |
| `expected_terminal_state` | string | no | `terminal`. |
| `expected_business_outcome_category` | string | no | Exposed symbolic `business_outcome_category`. |
| `security_expectation` | string | no | Scalar `branch_security_expectation`. |
| `minimum_facts_required` | array of non-empty strings | no | May be empty. |
| `grounding_gold` | object | no | Mirrors branch domain, sources, terminal, and minimum facts. |

Optional branch fields are `preconditions` and `allowed_evidence_source_versions`. Source-version rules are the same as for a primary case. `symbolic_order_reference` is not accepted in branch preconditions. Additional branch fields are parser-tolerated but are not part of the authored contract.

## Multi-turn scenario contract

A scenario requires:

| Field | Type | Nullable | Vocabulary / invariant |
|---|---|---:|---|
| `scenario_id` | non-empty string | no | Unique within the scenario collection; newly authored opaque ID. |
| `language_bucket` | string | no | `language_bucket`. |
| `scenario_type` | string | no | `scenario_type`. |
| `preconditions` | object | no | Requires `actor`; may include `context_ttl_state`; may not include `symbolic_order_reference`. |
| `turns` | array of objects | no | Exactly two ordered turns. |
| `final_expected_intent` | string | no | `turn_intent`. |
| `final_canonical_entity` | string, array of strings, or null | yes | Non-null string is non-empty; non-null array is non-empty and contains non-empty strings. |
| `expected_handler` | string | no | `handler`. |
| `expected_terminal_state` | string | no | `terminal`. |
| `required_evidence_domain` | string or null | yes | `evidence_domain` when non-null. |
| `allowed_evidence_sources` | array of strings | no | Unique registered source IDs. |
| `security_capability_expectation` | object | no | Same decision/capability object as a primary case. |
| `notes` | non-empty string or null | yes | Optional author note. |

Optional scenario fields are `allowed_evidence_source_versions` and `grounding_gold`. Scenario grounding mirrors domain, sources, and terminal; its internal `minimum_facts_required` remains required. Additional scenario fields are parser-tolerated but are not part of the authored contract.

### Turn contract

Each scenario contains exactly two turns. `turn` is an integer equal to the item's one-based position. Each turn requires:

| Field | Type | Nullable | Vocabulary / invariant |
|---|---|---:|---|
| `turn` | integer | no | Sequential one-based position. |
| `role` | string | no | `turn_role`. |
| `utterance` | non-empty string | no | Newly and independently authored turn input. |
| `expected_intent` | string | no | `turn_intent`. |
| `expected_handler` | string | no | `handler`. |
| `expected_terminal_state` | string | no | `terminal`. |
| `entity_gold` | object | no | Exact entity contract. |
| `expected_context_after_turn` | object | no | Context object described below. |
| `context_state_before_turn` | string or null | yes | Optional; non-null value uses `context_state_before_turn`. |

`expected_context_after_turn` requires `status` from `context_status` and `canonical_products_in_order`, an array of non-empty strings that may be empty. Optional `focused_product` is null or a non-empty string.

## Enum vocabularies

The following lists come from evaluator parser/validator declarations and the machine-checkable evaluator contract inventory. No enum is inferred from examples or observed record values. No list contains duplicates.

### Intent

- `primary_intent`: `cart_action_request`, `cart_informational`, `catalog_listing`, `clarification`, `general_chat`, `knowledge_query`, `missing_evidence_query`, `multi_intent`, `order_read`, `payment_status_read`, `price`, `privileged_mutation`, `product_detail`, `product_search`, `shipping_calculation`, `shipping_current_value`, `stock_availability`, `unsupported_ood`.
- `branch_intent`: `cart_action_request`, `catalog_listing`, `knowledge_query`, `missing_evidence_query`, `order_read`, `payment_status_read`, `price`, `privileged_mutation`, `product_detail`, `shipping_calculation`, `shipping_current_value`, `stock_availability`, `store_contact`, `unsupported_ood`.
- `turn_intent`: `cart_action_request`, `clarification`, `price`, `product_detail`, `product_search`, `shipping_calculation`, `stock_availability`.

The schema sources declare the labels but do not provide additional per-label narrative definitions.

### Handler / business path

`AUTHORIZED_ORDER_READ`, `AUTHORIZED_PAYMENT_STATUS_READ`, `CART_SUGGESTED_ACTION`, `CATALOG_LIST`, `CLARIFICATION`, `DETERMINISTIC_PRICE_SHIPPING_CALCULATOR`, `GENERAL_CONVERSATION`, `KNOWLEDGE_EVIDENCE_GATE`, `KNOWLEDGE_GROUNDED_ANSWER`, `MULTI_INTENT_ORCHESTRATOR`, `OOD_BOUNDARY`, `PRODUCT_DETAIL`, `PRODUCT_PRICE`, `PRODUCT_SEARCH`, `PRODUCT_STOCK`, `SECURITY_POLICY_DENIAL`, `SHIPPING_SETTINGS`, `STORE_SETTINGS`.

These are evaluator business-path labels only; they do not document a candidate implementation.

### Terminal

`ANSWER`, `NO_RESULTS`, `SUGGESTED_ACTION`, `UNAVAILABLE`, `AUTH_REQUIRED`, `DENIED`, `ANSWER_OR_NOT_FOUND`, `NO_EVIDENCE`, `UNSUPPORTED`, `CLARIFICATION_REQUIRED`, `ALL_BRANCHES_HANDLED`, `PARTIAL_MIXED_TERMINALS`.

### Language bucket

`vi_formal`, `vi_conversational`, `vi_no_diacritics`, `en`, `mixed`.

### Difficulty

`edge_case`, `moderately_ambiguous`, `normal_paraphrase`, `security_ood`, `straightforward`.

### Actor/auth precondition

`anonymous`, `anonymous_or_authenticated`, `authenticated_non_owner`, `authenticated_owner`, `authenticated_verified_customer`.

### Security decision

`ALLOW_OWNED_READ`, `ALLOW_READ`, `ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES`, `DENY`, `DENY_CROSS_ACCOUNT`, `NOT_APPLICABLE`, `PER_BRANCH`, `REQUIRE_AUTH`, `SUGGEST_ONLY`.

### Security capability

`approved_business_knowledge`, `auth_bypass`, `contextual_product_read`, `deterministic_public_calculation`, `domain_boundary`, `evidence_gated_business_knowledge`, `evidence_gated_product_knowledge`, `inventory_mutation`, `multi_intent_branch_isolation`, `no_direct_cart_mutation`, `order_state_mutation`, `other_user_data`, `payment_state_mutation`, `public_product_catalog`, `public_store_settings`, `read_only_order_access`, `read_only_payment_status`, `role_change`, `safe_disambiguation`, `safe_social_conversation`, `secret_disclosure`.

### Branch security expectation

`ALLOW`, `DENY`, `SUGGEST_ONLY`.

### Operation

`answer_approved_knowledge`, `answer_policy`, `answer_weather`, `bypass_verification`, `calculate_total`, `compare_quantity_to_inventory`, `cross_account_payment_read`, `disclose_hidden_prompt`, `financial_advice`, `list_catalog`, `list_category`, `mutate_inventory`, `mutate_order_state`, `mutate_payment_state`, `read_current_inventory`, `read_current_price`, `read_free_shipping_threshold`, `read_owned_order`, `read_owned_payment_status`, `read_product_detail`, `read_shipping_fee`, `read_store_address`, `suggest_add_to_cart`, `write_code`.

### Evidence domain

`account`, `bulk_order_policy`, `cold_chain_policy`, `delivery_sla`, `express_delivery_policy`, `food_safety_certification`, `fulfilment_compensation_policy`, `loyalty_policy`, `opened_food_return_policy`, `ordering`, `orders`, `organic_certification`, `owned_order_data`, `owned_order_payment_status`, `packaging_return_policy`, `payment`, `policy`, `price_match_policy`, `privacy_policy`, `product_catalog`, `product_inventory`, `product_inventory_and_ordering_contract`, `product_price`, `product_price_and_shipping_settings`, `product_usage_suitability`, `refund_policy`, `returns_policy`, `shipping_settings`, `store_contact_settings`, `subscription_policy`, `supplier_provenance`, `traceability_policy`, `warranty_policy`.

Every evidence-domain field also permits null.

### Business outcome category

`ANSWER_CART_PROCESS_FROM_APPROVED_GUIDE`, `ANSWER_FROM_APPROVED_KNOWLEDGE`, `ASK_TARGETED_CLARIFYING_QUESTION`, `BRIEF_SOCIAL_RESPONSE_OR_CAPABILITY_GUIDANCE`, `CLARIFY_PRICE_RANGE`, `COMPARE_REQUESTED_QUANTITY_TO_STOCK`, `DENY_AUTH_BYPASS`, `DENY_CHATBOT_ORDER_MUTATION`, `DENY_CROSS_ACCOUNT_READ`, `DENY_INVENTORY_MUTATION`, `DENY_PAYMENT_STATE_MUTATION`, `DENY_PROHIBITED_CAPABILITY`, `DENY_SECRET_DISCLOSURE`, `DO_NOT_ASSERT_UNSOURCED_POLICY`, `DO_NOT_ASSERT_UNSOURCED_PRODUCT_CLAIM`, `DO_NOT_FORCE_INTO_COMMERCE_INTENT`, `LIST_OWN_ORDERS`, `NO_MATCH`, `PROCESS_EVERY_BRANCH_WITH_EXPLICIT_TERMINAL_STATE`, `READ_OWN_ORDER`, `READ_OWN_ORDER_OR_NOT_FOUND`, `READ_OWN_PAYMENT_STATUS`, `REJECT_QUANTITY_ABOVE_CURRENT_STOCK`, `REQUIRE_AUTHENTICATION`, `RETURN_ACTIVE_CATALOG`, `RETURN_ACTIVE_CATEGORY_PRODUCTS`, `RETURN_ADD_TO_CART_SUGGESTION_WITH_CONFIRMATION`, `RETURN_CURRENT_AVAILABILITY`, `RETURN_CURRENT_SHIPPING_RULE`, `RETURN_CURRENT_UNIT_PRICE`, `RETURN_DETERMINISTIC_SUBTOTAL_SHIPPING_TOTAL`, `RETURN_GROUNDED_PRODUCT_DETAILS`, `RETURN_MATCHING_ACTIVE_PRODUCTS`.

This is the complete schema-semantic symbolic taxonomy accepted by the compatible evaluator. Content-bearing literal outcome instances are excluded and must not be used for independent authoring.

### Scenario/reference type

`ambiguous_reference`, `deictic_that`, `ellipsis`, `expired_context`, `ordinal_first`, `ordinal_last`, `ordinal_second`, `plural_reference`, `singular_reference`.

These are taxonomy labels, not utterance examples.

### Context and role vocabularies

- `context_ttl_state`: `active`, `expired`.
- `context_state_before_turn`: `ACTIVE`, `EXPIRED`; null is also accepted.
- `context_status`: `ACTIVE`, `AMBIGUOUS`, `EXPIRED`.
- `turn_role`: `user`.

## Structural and semantic invariants

- All root collections and `schema_contract` are required.
- Every `entity_gold` contains exactly the declared field set.
- Evidence sources are registered, unique within each allowed-source array, and mirrored in grounding.
- Source-version pins are positive, source-scoped, and equal registry versions.
- Raw mentions and canonical entities are distinct; a raw mention may exist without canonical resolution.
- Authorization results do not erase entity understanding.
- Purchase quantity and mutation target are distinct semantic dimensions.
- Multi-intent branches independently carry operation, entities, evidence, terminal, business outcome, and security expectation.
- Unsupported factual claims can terminate with `NO_EVIDENCE` when approved support is required but unavailable.
- A required nullable field remains required as a key.
- Null is accepted only where explicitly declared and is never equivalent to empty text, zero, false, an empty list, or omission.

## Business authority interfaces

| Authority category | Authoring interface | Boundary |
|---|---|---|
| Product/Category structured authority | Publish structured product/category facts in `source_registry`; reference registered IDs through evidence and grounding fields. | No prior evaluation examples or candidate behavior. |
| SiteSetting structured authority | Publish versionable store or shipping settings as structured authority facts and pin revisions when required. | No content-bearing historical expected values in this schema bridge. |
| Owned/private order authority | Express actor/ownership through preconditions and use only independently created symbolic references. | No real account, payment, or order identifiers. |
| Published knowledge registry | Register approved knowledge sources, optional versions, and structured facts; declare allowed sources and minimum facts per record. | Only published authority content supports factual-answer expectations. |

## Sanitization declaration

The contract was derived only from evaluator parser/validator declarations and a machine-checkable evaluator contract inventory. It excludes historical fixtures, utterances, IDs, case-specific gold, result captures, scores, candidate runtime behavior, router/handler/controller source, and analysis or remediation reports.

- Historical utterance exposure: 0
- Historical case-ID exposure: 0
- Candidate-output exposure: 0
- Failure-analysis exposure: 0
- Historical counts included: no
- Sample values included: no
- Content-bearing allowlist values included: no
- Freeze status: FROZEN
