# SANITIZED REMEDIATION BRIEF — V10 R2

## Sanitized replacement-parent mapping

Only opaque V10 R1 parent IDs are supplied. This section contains no utterance,
matched text, historical reference, candidate behavior, or runtime detail.

| replacement_slot_id | parent R1 ID |
| --- | --- |
| `P-R1` | `V10-ST-0043` |
| `P-R2` | `V10-ST-0049` |
| `P-R3` | `V10-ST-0051` |
| `P-R4` | `V10-ST-0239` |
| `M-R1` | `V10-MT-001` |
| `M-R2` | `V10-MT-007` |
| `M-R3` | `V10-MT-018` |
| `M-R4` | `V10-MT-022` |
| `M-R5` | `V10-MT-023` |
| `M-R6` | `V10-MT-027` |
| `M-R7` | `V10-MT-032` |
| `M-R8` | `V10-MT-036` |
| `M-R9` | `V10-MT-038` |

## A. Primary replacements

Exactly four independently authored primary replacements are required. Product
identity and wording must be chosen independently; no replacement should copy
the surface form or sentence structure of the removed slot.

### P-R1

- `replacement_slot_id`: `P-R1`
- `capability_bucket`: product current-price read
- `intent_family`: `price`
- `language_bucket`: Vietnamese formal
- `difficulty_bucket`: `straightforward`
- `entity_requirements`: one explicit raw product mention and one separately
  validated canonical active product; quantity, unit, order, ordinal, and
  context-reference slots are null unless independently required by the new
  construction
- `auth_precondition_requirement`: public read;
  `anonymous_or_authenticated`
- `evidence_requirement`: current structured Product DB price authority
- `terminal_state_requirement`: `ANSWER`

### P-R2

- `replacement_slot_id`: `P-R2`
- `capability_bucket`: product current-price read
- `intent_family`: `price`
- `language_bucket`: Vietnamese conversational
- `difficulty_bucket`: `straightforward`
- `entity_requirements`: one explicit raw product mention and one separately
  validated canonical active product; quantity, unit, order, ordinal, and
  context-reference slots are null unless independently required by the new
  construction
- `auth_precondition_requirement`: public read;
  `anonymous_or_authenticated`
- `evidence_requirement`: current structured Product DB price authority
- `terminal_state_requirement`: `ANSWER`

### P-R3

- `replacement_slot_id`: `P-R3`
- `capability_bucket`: product current-price read
- `intent_family`: `price`
- `language_bucket`: English
- `difficulty_bucket`: `straightforward`
- `entity_requirements`: one explicit raw product mention and one separately
  validated canonical active product; quantity, unit, order, ordinal, and
  context-reference slots are null unless independently required by the new
  construction
- `auth_precondition_requirement`: public read;
  `anonymous_or_authenticated`
- `evidence_requirement`: current structured Product DB price authority
- `terminal_state_requirement`: `ANSWER`

### P-R4

- `replacement_slot_id`: `P-R4`
- `capability_bucket`: true out-of-domain boundary
- `intent_family`: `unsupported_ood`
- `language_bucket`: English
- `difficulty_bucket`: `security_ood`
- `entity_requirements`: no commerce product, quantity, order, ordinal, or
  context entity
- `auth_precondition_requirement`: no authenticated state required;
  `anonymous_or_authenticated`
- `evidence_requirement`: none; do not force the request into commerce or
  knowledge evidence
- `terminal_state_requirement`: `UNSUPPORTED`

## B. Multi-turn replacements

Exactly nine independent two-turn scenarios are required. Each scenario starts
with a fresh session and must contain all context setup needed for its second
turn. Products must be independently selected from current active structured
data.

| replacement_slot_id | scenario_capability | language_bucket | number_of_turns | context_type | reference_type | entity_scope | expected final intent family | expected handler/business path family | auth precondition requirement | expected terminal-state family | evidence-domain requirement |
| --- | --- | --- | ---: | --- | --- | --- | --- | --- | --- | --- | --- |
| `M-R1` | contextual product price | Vietnamese formal | 2 | product context | singular | one active product resolving to one canonical product | `price` | structured product-price read | public read; `anonymous_or_authenticated` | `ANSWER` | current structured product price |
| `M-R2` | contextual product prices | Vietnamese conversational | 2 | multi-product result list | plural | at least two active products, all resolved without silent dropping | `price` | structured multi-product price read | public read; `anonymous_or_authenticated` | `ANSWER` | current structured product prices |
| `M-R3` | contextual product price | Vietnamese formal | 2 | ordered product list | ordinal-last | ordered active-product list resolving exactly one final item | `price` | structured product-price read after ordinal resolution | public read; `anonymous_or_authenticated` | `ANSWER` | current structured product price |
| `M-R4` | contextual product price | Vietnamese conversational | 2 | product context | deictic | one active product resolving to one canonical product | `price` | structured product-price read after context resolution | public read; `anonymous_or_authenticated` | `ANSWER` | current structured product price |
| `M-R5` | contextual stock availability | Vietnamese no-diacritics | 2 | product context | deictic | one active product resolving to one canonical product | `stock_availability` | structured product-inventory read after context resolution | public read; `anonymous_or_authenticated` | `ANSWER` | current structured product inventory |
| `M-R6` | contextual product price | Vietnamese conversational | 2 | product context | ellipsis | one active product retained as the unique focus | `price` | structured product-price read after elliptical resolution | public read; `anonymous_or_authenticated` | `ANSWER` | current structured product price |
| `M-R7` | safe stale-reference handling | Vietnamese formal | 2 | product context | expired-context | prior product context exists but is expired; no canonical product may be guessed at turn 2 | `clarification` | clarification path before any product fact lookup | public read; `anonymous_or_authenticated`; expired TTL | `CLARIFICATION_REQUIRED` | none until the user re-establishes a valid product reference |
| `M-R8` | safe ambiguous-reference handling | Vietnamese conversational | 2 | multi-product result list | ambiguous | at least two plausible active products and no unique turn-2 referent | `clarification` | clarification path before selecting a product | public read; `anonymous_or_authenticated` | `CLARIFICATION_REQUIRED` | none until ambiguity is resolved |
| `M-R9` | safe ambiguous-reference handling | English | 2 | multi-product result list | ambiguous | at least two plausible active products and no unique turn-2 referent | `clarification` | clarification path before selecting a product | public read; `anonymous_or_authenticated` | `CLARIFICATION_REQUIRED` | none until ambiguity is resolved |

## C. Gold correction rules

### Product search

- Apply to every R2 product-search record, not only replacement slots.
- Resolve expected matches exclusively from the frozen current active Product
  DB/category snapshot available to the author.
- Validate filters, canonical products, and result/no-result terminals against
  that snapshot before freeze.
- Do not derive expected outcome from chatbot output or historical labels.
- `P-R1`--`P-R3` are price slots, so their structured gold must use the selected
  product's current price rather than product-search outcome rules.

### Source authority

- Apply to every knowledge/evidence-bearing primary case, branch, and scenario.
- The author must independently resolve exact allowed source IDs from the
  approved/published registry. If version pinning is needed, store source ID
  and version in their proper separate schema fields.
- `ANSWER` is valid only when an approved source in the required evidence
  domain supports the minimum facts. Otherwise use `NO_EVIDENCE` with an empty
  allowed-source list.
- Structured product, catalog, shipping, contact, and owned-order facts must
  continue to use their corresponding structured authority, not static
  knowledge documents.
- `P-R1`--`P-R3` and `M-R1`--`M-R6` require structured Product DB authority.
  `P-R4` and `M-R7`--`M-R9` require no knowledge source.

### Auth preconditions

- Public product/catalog/price/stock reads: `anonymous_or_authenticated`.
- Cart suggestion: `verified_customer`; otherwise the gold terminal must be
  auth-required and must not contain a suggested mutation.
- Owned order/payment read: `authenticated_owner`.
- Other-account order/payment read: `authenticated_non_owner` with `DENIED`.
- Every multi-intent branch and follow-up scenario must specify one
  deterministic auth-state class whenever terminal behavior depends on auth.
- All replacement slots in A and B are public-read/OOD slots and must not add a
  new auth-sensitive capability.

### Entity

- Keep separate slots for raw product mention, canonical product, quantity,
  unit, order reference, ordinal reference, and context reference.
- Preserve every entity explicitly present even when the terminal is
  clarification, denial, unavailable, or unsupported. Do not erase entities
  merely because an operation is prohibited.
- Do not infer quantity 1 unless the business contract explicitly supplies a
  default for that operation.
- Do not place an account/customer target in an order-reference slot.
- Every follow-up turn 1 must record its raw product mention(s) separately from
  canonical identity. Turn 2 must record the appropriate resolved,
  ambiguous, or expired reference state.
- The author may select different valid active products for all product-bearing
  replacement slots.

### Follow-up

- Turn 1 must establish its own product or ordered-list context in a fresh
  session and must carry the semantic intent actually expressed by that turn.
- Singular, plural, ordinal, deictic, and elliptical references must resolve
  only when context has one valid interpretation.
- Ambiguous and expired references must terminate
  `CLARIFICATION_REQUIRED`; they must not guess a canonical entity or consult
  product evidence as if resolution had succeeded.
- Resolved price and stock references terminate `ANSWER` using fresh structured
  product authority.
- `M-R1`--`M-R6` must resolve. `M-R7`--`M-R9` must clarify.

## D. Distribution constraints

R2 must preserve the following primary distribution after one-for-one
replacement and gold correction:

### Language counts

| language bucket | count |
| --- | ---: |
| English | 68 |
| Mixed VN/EN | 48 |
| Vietnamese conversational | 58 |
| Vietnamese formal | 67 |
| Vietnamese no-diacritics | 51 |

### Intent counts

| intent/capability | count |
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

Additional required totals: 292 primary cases, 27 multi-intent cases, 56
annotated multi-intent branches, 160 entity-bearing primary cases when
branch-only entities are included, 39 follow-up scenarios, and 78 scenario
turns.

### Follow-up subtype counts

| subtype | count |
| --- | ---: |
| singular reference | 5 |
| plural reference | 4 |
| ordinal first | 4 |
| ordinal second | 4 |
| ordinal last | 4 |
| deictic | 5 |
| ellipsis | 5 |
| expired context | 4 |
| ambiguous reference | 4 |

Exact one-for-one preservation requirements:

- Primary replacements preserve intent family, language bucket, difficulty
  bucket, entity-bearing status, auth-state class, evidence-domain class, and
  terminal-state family.
- Multi-turn replacements preserve language bucket, two-turn structure,
  follow-up subtype, entity-scope class, final intent/path family, auth-state
  class, evidence-domain class, and terminal-state family.
- Preserve 23 primary TRUE_OOD cases and 16 primary privileged cases.

Dimensions that need not be preserved exactly: product identity, quantity when
not required by the slot, topic within the TRUE_OOD boundary, politeness,
lexical choices, syntax, or sentence length. New wording and construction must
be independent. Use opaque randomized IDs and shuffle record order; the
candidate must receive only candidate-visible request fields, never IDs,
metadata, or gold.

## E. Allowed authority sources for the author

The author may independently read only:

- the current active Product DB and Category structured snapshot;
- current SiteSetting structured business values;
- the approved/published knowledge registry and approved document content;
- the business authorization contract for guest, verified customer, owner, and
  non-owner behavior;
- the V10 schema contract and this sanitized categorical brief;
- symbolic owned-order harness requirements without private order rows.

The author must independently resolve exact current source IDs from the
approved registry. Static knowledge must not override structured current
values.

## F. Forbidden information

The author must not receive or inspect:

- any V0--V9 utterance, turn, sequence, wording, template, fixture, or
  paraphrase derived from one;
- exact, normalized, near-duplicate, or semantic-template matched text;
- candidate predictions, scores, traces, failure outputs, or runtime behavior;
- runtime implementation, router rules, prompts, regexes, source-code snippets,
  or Phase 12 fix/failure details;
- the removed V10 utterances or instructions describing how to reword them.

The author must create genuinely new constructions from the categorical slots,
not edit or paraphrase removed cases.

## G. Sanitization self-check

- Can an author reconstruct a historical utterance from this brief? **No.**
  Only categorical coverage metadata is present.
- Can an author learn how the candidate failed? **No.** No prediction, score,
  response, or implementation detail is present.
- Can an author infer router implementation details? **No.** Only business
  intent, authority, auth, entity, and terminal contracts are stated.
- Can an author produce fair replacements while preserving coverage? **Yes.**
  The brief supplies exact counts and categorical slot requirements without
  wording or templates.
- Historical/matched text scan: **PASS**.

## H. Brief SHA-256

The full-file SHA-256 is reported with delivery after this file is frozen. It
is not self-embedded because adding the digest would change the file bytes.
