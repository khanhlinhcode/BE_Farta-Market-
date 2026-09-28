# V11 Independent Holdout Author R1 Report

Status: **FROZEN**  
Frozen at (UTC): `2026-09-27T09:07:52Z`

## Independence declaration

This holdout was authored only from the ten frozen sanitized V11 artifacts named in the authoring instruction. No V0–V10 fixture, historical utterance, raw or scored result, Phase 14 material, runtime implementation, candidate prediction, candidate hash, contaminated model card, or contaminated knowledge-authoring document was consulted. The candidate/chatbot was not executed.

## Sanitized input integrity and compatibility

All ten required SHA-256 values matched exactly before authoring. Both sanitized manifests reported `FROZEN`; schema/business compatibility and schema vocabulary compatibility were `PASS`.

- `v11-sanitized-schema-contract.json`: 2d1e6cd805ed9914d6811f130f9c1cc0909eb2f6707e30f5f66b13c61f6bea5d
- `v11-sanitized-schema-contract.md`: 216e1f80789770d4f48e6a852d05ee1e7b4909b035b9b223107a4afc5f31b57b
- `v11-sanitized-schema-freeze-manifest.json`: d5eddc122a536cace2e7ac773f1f686ddaaa96e6178902d65acfa88b43c7be06
- `v11-sanitized-business-capability-contract.json`: ddaf57dcb1fd0db48b7466370c498d52db729cf8a4714f99c0098548fc1e4b7b
- `v11-sanitized-business-capability-contract.md`: f647a0554563366e160a040870a6ad493edc7baef90d2ea1baff3a03f6bfc9e6
- `v11-sanitized-product-authority.json`: 809923e43f8919446b8123ea3ab0cac2f39cc3b50d89bad89f584d3768799f0a
- `v11-sanitized-sitesetting-authority.json`: 92e69a3c93bfef417fce7ab76a12e191558cbbe50ec9e645c6422e69bb69f2cb
- `v11-sanitized-knowledge-registry.json`: dc8b013d3235a8fb04cedda63575354ab25fd6482316798952ff7161e6d599e7
- `v11-sanitized-authorization-contract.json`: de05e7d4ebd0429b20c73fd9bb0927a9012a9e358b80a195c878549e56639dc4
- `v11-sanitized-business-contract-freeze-manifest.json`: 440f40b5dfc91fd06e1751d7231883503ff1e6d2246a900dbddd1a53c2efd5fa

## Dataset counts

- Primary cases: 160
- Independent scenarios: 20
- Scenario turns: 40
- Multi-intent primary cases: 20
- Multi-intent branches: 40
- Entity-bearing primary cases: 106
- Primary cases with `account_target`: 25
- Primary cases with numeric `requested_mutation_value`: 5
- Privileged primary/branch records: 13
- TRUE_OOD primary/branch records: 6
- NO_EVIDENCE primary cases: 15
- NO_EVIDENCE branches: 2
- Clarification primary cases: 11
- Clarification scenario finals: 5
- Auth-required primary cases: 7

## Language distribution

### Primary cases

- `en`: 32
- `mixed`: 32
- `vi_conversational`: 32
- `vi_formal`: 32
- `vi_no_diacritics`: 32

### Scenarios

- `en`: 4
- `mixed`: 4
- `vi_conversational`: 4
- `vi_formal`: 4
- `vi_no_diacritics`: 4

## Capability distribution (primary intent)

- `cart_action_request`: 5
- `cart_informational`: 5
- `catalog_listing`: 5
- `clarification`: 10
- `general_chat`: 5
- `knowledge_query`: 10
- `missing_evidence_query`: 10
- `multi_intent`: 20
- `order_read`: 15
- `payment_status_read`: 5
- `price`: 10
- `privileged_mutation`: 10
- `product_detail`: 10
- `product_search`: 10
- `shipping_calculation`: 5
- `shipping_current_value`: 5
- `stock_availability`: 15
- `unsupported_ood`: 5

### Multi-intent branch distribution

- `cart_action_request`: 1
- `catalog_listing`: 1
- `knowledge_query`: 2
- `missing_evidence_query`: 2
- `order_read`: 1
- `price`: 9
- `privileged_mutation`: 3
- `product_detail`: 5
- `shipping_current_value`: 4
- `stock_availability`: 6
- `store_contact`: 5
- `unsupported_ood`: 1

## Terminal distribution

### Primary cases

- `ALL_BRANCHES_HANDLED`: 15
- `ANSWER`: 65
- `ANSWER_OR_NOT_FOUND`: 8
- `AUTH_REQUIRED`: 7
- `CLARIFICATION_REQUIRED`: 11
- `DENIED`: 16
- `NO_EVIDENCE`: 15
- `NO_RESULTS`: 5
- `PARTIAL_MIXED_TERMINALS`: 5
- `SUGGESTED_ACTION`: 3
- `UNAVAILABLE`: 5
- `UNSUPPORTED`: 5

### Multi-intent branches

- `ANSWER`: 32
- `ANSWER_OR_NOT_FOUND`: 1
- `DENIED`: 3
- `NO_EVIDENCE`: 2
- `SUGGESTED_ACTION`: 1
- `UNSUPPORTED`: 1

### Scenario finals

- `ANSWER`: 13
- `CLARIFICATION_REQUIRED`: 5
- `SUGGESTED_ACTION`: 2

## Follow-up subtype distribution

- `ambiguous_reference`: 2
- `deictic_that`: 2
- `ellipsis`: 2
- `expired_context`: 3
- `ordinal_first`: 2
- `ordinal_last`: 2
- `ordinal_second`: 2
- `plural_reference`: 2
- `singular_reference`: 3

## Internal quality audit

- Schema validation: **PASS** — required shapes, exact entity slots, enums, IDs, two-turn cardinality, branch rules, source registration, mirrored grounding fields, and source-version pins were checked.
- Gold/authority validation: **PASS** — canonical products, current product values, shipping calculations, source/domain eligibility, claim-support terminals, ownership states, and security outcomes were checked against sanitized authorities.
- Claim-support validation: **PASS** — factual answers carry domain-correct registered sources and minimum facts; unsupported factual claims use `NO_EVIDENCE`.
- Entity consistency: **PASS** — quantity and mutation values remain distinct; explicit product, order, account, context, ordinal, and numeric mutation entities are retained at denied/no-evidence/auth/clarification terminals where applicable.
- Authorization consistency: **PASS** — anonymous, verified, owner, non-owner, read-only, suggestion-only, and prohibited mutation paths were audited.
- Source/version consistency: **PASS** — every pin matches the dataset registry and its frozen authority version.
- Terminal/business-outcome consistency: **PASS**.
- Raw exact duplicate groups: 0
- Normalized duplicate groups: 0
- Accent-folded duplicate groups: 0
- Near-duplicate pairs at 92% similarity: 0
- Maximum template-skeleton repetition: 1
- Template diversity: **PASS**.

## Known limitations

- The clean Product/Category snapshot contains eleven active products and five active categories; product-language diversity is therefore intentionally broader than product-identity diversity.
- Published knowledge is available only for account, ordering, orders, payment, and the high-level policy index. Other declared policy domains correctly terminate as `NO_EVIDENCE`.
- Private order/payment coverage uses two explicitly synthetic symbolic records and never real customer data.
- The sanitized schema does not expose the content-bearing string allowlist for `requested_mutation_value`; only numeric mutation values are authored, while string-like protected operations retain their product/order/account entities and security semantics.
- This authoring pass does not compare V11 with any historical dataset and reports no model metric.

## Freeze

Dataset SHA-256: `81214b6d7fb877116d6e19298410c2d2c64096875e813d2a2139a6dfe926240a`  
Candidate executed: **NO**  
Historical data accessed: **NO**  
Freeze: **FROZEN**
