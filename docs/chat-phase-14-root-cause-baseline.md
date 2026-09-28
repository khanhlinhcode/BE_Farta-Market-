# Phase 14 root-cause baseline

## Status and evidence boundary

This document is the mandatory pre-change baseline for Phase 14. It was
completed before any Phase 14 runtime edit.

Final V10 is development evidence. It is not a blind release result for any
candidate produced after this baseline. The frozen artifacts were verified
locally:

- candidate runtime hash:
  `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`;
- frozen raw capture: 372 records, SHA-256
  `bcf07fa6a9c0e0554237fffad7e7806cef5271c0837a92fd197b61ca59ffe6d3`;
- Offline Scorer V4:
  `c7be5acf4f928f50a2a71861371822dde809da9c063f4390dfafd5412e727d87`;
- scored results SHA-256:
  `f3a72fda1507f8b3a27e9054d3063bf0ab2482eeaffaea9237653f9caf92eec1`.

No Final V10 gold was edited. No candidate request, retry, or runtime patch was
made while producing this baseline.

## Final V10 baseline

| Measure | Result |
| --- | ---: |
| Business outcome | 37.33% (109/292) |
| Handler accuracy | 36.30% (106/292) |
| Intent accuracy | 35.27% (103/292) |
| Intent macro precision | 36.10% |
| Intent macro recall | 36.07% |
| Intent macro-F1 | 34.06% |
| Supported-query recall | 99.63% (268/269) |
| OOD precision / recall | 91.67% / 47.83% |
| Privileged precision / recall | 55.00% / 68.75% |
| Required evidence domain | 27.84% (71/255) |
| Evidence eligibility | 63.22% (220/348) |
| Missing-evidence safety | 21.74% (5/23) |
| Wrong-topic/authority acceptance | 63 |
| Unsupported-policy hallucination signal | 18 |
| Unsafe execution | 0 |
| Wrong-entity unsafe action | 0 |

Only 23 of 292 primary cases have no scored first failure. The remaining 269
primary cases receive exactly one primary root cause below.

Supported-query recall must not be interpreted as semantic quality. The router
almost never emits OOD for supported traffic, but it frequently chooses the
wrong supported route. This explains how supported recall can be 99.63% while
intent, handler, and outcome remain near 35--37%.

## Root-cause reconstruction method

The V4 score report used a fixed scorer order that checked security-decision
agreement before intent. Because the evaluator derives security decision from
the selected route, this mechanically labelled many ordinary intent misses as
`CAPABILITY`. Phase 14 instead applies causal precedence:

1. runtime/authorization failure;
2. multi-intent decomposition when the request was not split;
3. resource/operation capability failure when a privileged boundary is
   involved;
4. intent/domain decision;
5. entity extraction, then DB canonicalization;
6. handler selection;
7. evidence domain, eligibility, and claim support;
8. composition.

An intent miss is therefore not also counted as a handler, evidence-domain, or
authority failure. A canonical-product miss is classified as
`ENTITY_EXTRACTION` when an earlier raw entity slot is missing/incorrect and as
`ENTITY_CANONICALIZATION` only when canonical resolution is the first failed
entity slot. `ELIGIBILITY` from the V4 report is named
`EVIDENCE_ELIGIBILITY` here to match the Phase 14 taxonomy.

The frozen trace does not expose an independent normalized-string or concept
frame. Consequently, no case is speculatively assigned to `NORMALIZATION` or
`LANGUAGE_CONCEPT`; the first observable boundary is used. The cross-language
distribution nevertheless proves that the intent failures are not confined to
one locale.

## First-failure distribution

Percentages use the 269 failed primary cases as denominator.

| Failure layer | Cases | Failed cases | Language distribution | Largest affected capabilities | Representative IDs |
| --- | ---: | ---: | --- | --- | --- |
| INTENT | 163 | 60.59% | en 40; mixed 28; vi conversational 31; vi formal 35; vi no-diacritics 29 | missing evidence 18; price 18; stock 18; shipping calculation 16 | `V10-ST-0172`, `V10-ST-0204`, `V10-ST-0034`, `V10-ST-0025` |
| ENTITY_EXTRACTION | 47 | 17.47% | en 9; mixed 7; vi conversational 12; vi formal 10; vi no-diacritics 9 | cart action 10; privileged mutation 10; product search 10; multi-intent 7 | `V10-ST-0259`, `V10-ST-0002`, `V10-ST-0134`, `V10-ST-0140` |
| ENTITY_CANONICALIZATION | 18 | 6.69% | en 2; mixed 5; vi conversational 1; vi formal 5; vi no-diacritics 5 | catalog 9; general chat 3; knowledge 3; multi-intent 3 | `V10-ST-0089`, `V10-ST-0081`, `V10-ST-0211`, `V10-ST-0080` |
| MULTI_INTENT_DECOMPOSITION | 16 | 5.95% | en 2; mixed 3; vi conversational 6; vi formal 3; vi no-diacritics 2 | multi-intent 16 | `V10-ST-0289`, `V10-ST-0280`, `V10-ST-0285`, `V10-ST-0277` |
| EVIDENCE_ELIGIBILITY | 11 | 4.09% | en 2; mixed 2; vi conversational 2; vi formal 3; vi no-diacritics 2 | shipping current value 11 | `V10-ST-0099`, `V10-ST-0101`, `V10-ST-0093`, `V10-ST-0098` |
| CAPABILITY | 10 | 3.72% | en 3; vi conversational 2; vi formal 4; vi no-diacritics 1 | privileged mutation 5; knowledge 2; order read 2; payment read 1 | `V10-ST-0265`, `V10-ST-0155`, `V10-ST-0177`, `V10-ST-0178` |
| CLAIM_SUPPORT | 3 | 1.12% | en 2; vi no-diacritics 1 | knowledge 2; multi-intent 1 | `V10-ST-0174`, `V10-ST-0269`, `V10-ST-0173` |
| EVIDENCE_DOMAIN | 1 | 0.37% | vi conversational 1 | knowledge 1 | `V10-ST-0182` |

Security impact is concentrated in `CAPABILITY`, the cross-account entity
binding case, and multi-intent requests that combine a safe branch with a
denied branch. Business impact is broadest in `INTENT`: it selects the wrong
handler/domain or abstains, after which downstream output cannot satisfy the
requested task.

No primary case has `HANDLER_SELECTION`, `RETRIEVAL`, `COMPOSITION`, or
`AUTHORIZATION` as its first observable failure after causal reclassification.
This does not mean those layers are generally flawless; it means an earlier
failure explains every affected primary trace. The dedicated follow-up and
multi-branch metrics still expose context and composition failures separately.

## Confusion-driven analysis

Largest non-diagonal intent confusions:

| Gold | Prediction | Count | Interpretation |
| --- | --- | ---: | --- |
| price | product_detail | 14 | Runtime taxonomy collapses a price operation into generic product detail. |
| product_detail | clarification | 11 | Product-detail phrasing/entity mention is not recognized. |
| stock_availability | product_detail | 11 | Stock is treated as generic detail instead of an inventory operation. |
| missing_evidence_query | clarification | 10 | Unknown policy domains abstain instead of reaching the evidence gate. |
| product_search | clarification | 10 | Search/recommendation concepts fail across all language buckets. |
| missing_evidence_query | knowledge_query | 7 | Generic policy domain is widened and may accept the policy index. |
| shipping_calculation | shipping_current_value | 7 | Quantity/product-dependent calculation is reduced to a settings lookup. |
| cart_informational | cart_query | 6 | Guidance about cart behavior is confused with reading the current cart. |
| multi_intent | shipping_current_value | 6 | One shipping branch suppresses its sibling branch. |
| payment_status_read | order_read | 6 | Payment state and general order state are not distinct route operations. |
| shipping_calculation | clarification | 6 | Calculator inputs are not jointly recognized. |
| unsupported_ood | clarification | 6 | True OOD is treated as ambiguity, lowering OOD recall. |
| unsupported_ood | product_detail | 5 | Product nouns inside an OOD request overpower the OOD boundary. |

The failures are taxonomy and semantic-boundary failures, not evidence that an
unbounded LLM router is required. Price, stock, payment status, cart guidance,
shipping calculation, and missing-evidence policy are stable business
operations with deterministic handlers and authorities.

## Intent taxonomy audit

| Intent | Positive definition | Negative boundary / nearest confusion | Required entities | Handler and authority |
| --- | --- | --- | --- | --- |
| product_search | Discover or filter matching active products. | Not one product's facts; not list-all catalog. | Search constraints and optional product/category mention. | `PRODUCT_SEARCH`; Product DB. |
| product_detail | Read stored description/category facts for one product. | Not price, stock, inferred suitability, or policy. | One resolvable product mention/reference. | `PRODUCT_DETAIL`; Product DB. |
| price | Read current unit price for one product. | Not stock/detail or shipping total. | One product. | `PRODUCT_PRICE`; Product DB. |
| stock_availability | Read inventory/availability, optionally against a requested quantity. | Not add-to-cart or inventory mutation. | Product; optional quantity/unit. | `PRODUCT_STOCK`; Product DB. |
| catalog_listing | Browse all active products or one category. | Not semantic search for a need or one product detail. | Optional category. | `CATALOG_LIST`; Product/category DB. |
| cart_informational | Ask how cart suggestions, confirmation, verification, or checkout checks work. | Not reading the current cart and not requesting an add. | No cart payload required. | `KNOWLEDGE_GROUNDED_ANSWER`; ordering guide. |
| cart_action_request | Request a bounded add-to-cart suggestion. | Not direct mutation; not hypothetical guidance. | Product, purchase quantity, unit where expressed. | `CART_SUGGESTED_ACTION`; Product DB plus deterministic auth/revalidation. |
| cart_query | Read current supplied cart state. | Not cart policy/guidance or mutation. | Verified cart references supplied by client. | `CART_READ`; verified customer plus Product DB. |
| order_read | Read owned order status/history. | Not generic order guidance and not payment-only status. | Order reference/recent selector; account target. | `AUTHORIZED_ORDER_READ`; owner-scoped order DB. |
| payment_status_read | Read payment state of an owned order. | Not payment-method guidance and not payment mutation. | Order reference/recent selector; account target. | `AUTHORIZED_PAYMENT_STATUS_READ`; owner-scoped order DB. |
| shipping_current_value | Read current fee or free-shipping threshold. | Not shipping policy/SLA and not a product subtotal calculation. | Optional basket subtotal. | `SHIPPING_SETTINGS`; `SiteSetting`. |
| shipping_calculation | Calculate subtotal/shipping/total for a product quantity. | Not simply reading current settings. | Product, purchase quantity/unit. | Deterministic price/shipping calculator; Product DB + `SiteSetting`. |
| knowledge_query | Ask a supported, approved guidance/policy claim. | Not private current state, structured current values, or unsupported policy. | Knowledge claim/domain. | Grounded answer; domain allow-list. |
| missing_evidence_query | Ask a factual/policy claim for which no approved authority exists. | Not ambiguity; generic index is not proof of a specific policy. | Explicit requested claim/domain. | Evidence gate; terminal `NO_EVIDENCE`. |
| multi_intent | Two or more independently actionable semantic branches. | Modifiers and same-domain comparisons are not separate branches. | Branch-local entities; shared entity only when compatible. | Multi-intent orchestrator; immutable authority per branch. |
| privileged_mutation | Request mutation/admin/bypass/secret/cross-account capability not granted to chat. | Benign read/guidance sharing the same resource noun must not be denied. | Resource, operation, account target, requested mutation value/reference. | Deterministic security denial. |
| unsupported_ood | Clear request outside store commerce/support scope. | Not denial, no-evidence, auth-required, or ambiguity. | None. | OOD boundary. |
| clarification | In-scope request missing a required disambiguating entity/constraint. | Not true OOD and not an unsupported policy claim. | Missing/ambiguous reference represented explicitly. | Clarification. |
| general_chat | Greeting, thanks, farewell, or assistant-scope question. | Not arbitrary open-domain conversation. | None. | Deterministic general conversation. |

These distinctions are justified by different operations, handlers, evidence,
or authorization contracts. They are not introduced solely to mirror labels.

## Wrong-topic and authority analysis

The 63 signal records are all cases (primary plus branches) with at least one
accepted source ID outside the case allow-list. They form two different
failure families:

1. **Authority identity mismatch (12):** eleven otherwise correctly routed
   shipping-current cases cite the internal alias `site-settings` in addition
   to the canonical frozen authority `DB_SITE_SETTINGS_2026-09-26`; one contact
   branch has the same issue. This is not wrong topical content, but it violates
   the source registry contract and must still fail closed.
2. **Semantic route/domain contamination (51):** a wrong route accepts Product
   DB, a payment/account guide, policy index, or shipping settings for another
   task. Major clusters are shipping calculation reduced to shipping settings
   (7), multi-intent reduced to shipping settings (6), OOD accepted as product
   detail (5), clarification accepted as product detail/cart action (7), and
   payment-status read routed to payment guidance (3).

Three missing-evidence cases accepted `policy-index-vi` for cold-chain,
privacy, or bulk-order claims. A policy index can prove only that a category is
listed; it cannot prove a specific policy value or promise.

The existing retriever already filters by routed topic/source and performs a
final eligibility check. The dominant P0 problem occurs earlier: the route
selects the wrong domain, after which the retriever faithfully enforces the
wrong contract. The fix must therefore combine semantic routing repair with a
canonical allowed-source registry and a final deterministic claim-support gate.

## Unsupported-policy hallucination analysis

The 18 signals are all gold `NO_EVIDENCE` branches whose observed terminal was
not `NO_EVIDENCE`:

- 10 became `CLARIFICATION_REQUIRED`, including organic certification,
  packaging return, supplier provenance, delivery SLA, express delivery,
  food-safety certification, loyalty, subscription, traceability, and warranty;
- 3 returned the generic policy index as an answer for bulk-order, cold-chain,
  or privacy policy;
- 3 returned Product DB facts for usage suitability or price-match policy;
- 2 multi-intent branches disappeared and were recorded as missing predictions.

This is primarily a domain/terminal contract failure. The evidence gate needs a
bounded requested-claim domain even when its allowed-source set is empty.
`CLARIFICATION_REQUIRED` is not interchangeable with `NO_EVIDENCE` once the
claim is clear.

## Cross-account analysis

`V10-ST-0156` asks for another person's order using an explicit account target
and order reference. The router selected order read but failed to retain both
`account_target` and `order_reference`. The handler therefore fell back to the
authenticated actor's most recent owned order and returned it.

The response did not expose the third party's data, so the frozen signal is
correctly adjudicated `FALSE_POSITIVE_SIGNAL`. The request binding is still
unsafe behavior: an explicit cross-account request must terminate `DENIED`
before any private-resource response. Primary root cause is
`ENTITY_EXTRACTION`; deterministic authorization must then compare requested
account target, actor identity, resource owner, and operation.

## Entity, context, and multi-intent analysis

The frozen route frame exposes only `topic`, `order_id`, `product_name`, and
`quantity`. The evaluator therefore observes zero recall for unit, account
target, context reference, and requested mutation value. Quantity recall is
21.43%; order-reference recall is 20.59%; product raw-mention recall is 4.73%.
The entity frame must explicitly separate purchase quantity from mutation
value and preserve raw mention before DB canonical resolution.

Follow-up scenario success is 2/39. Reference detection (30/39) is much higher
than canonical resolution (9/39), showing that recognition of deictic language
is not enough. Context must resolve a reference against bounded session state,
then revalidate the current product in the DB; expired/ambiguous context must
clarify.

Multi-intent completeness is 11/27 and whole-request completion is 7/27.
Sixteen primary cases fail first at decomposition. Branch frames must own their
intent, operation/resource, entities, handler, terminal, and evidence domain.
A denied/no-evidence/clarification branch must not erase its safe sibling.

## Architecture before Phase 14 runtime changes

The current runtime already has useful safety foundations:

```text
normalize -> concept extractor -> capability guard -> intent router
          -> ChatRouteFrame -> context resolver -> controller handler
          -> structured authority or evidence-domain retriever
          -> eligibility -> response
```

Observed gaps:

- `ChatRouteFrame` is immutable, but its intent and entity vocabulary is too
  coarse for price, stock, payment status, cart guidance, account targets,
  mutation values, units, and context references.
- capability detection internally reasons about resource and operation but
  exports only a denial reason; route telemetry cannot audit the pair.
- the controller still re-parses raw text for order IDs, product matches,
  quantities, and unsupported catalog facts after routing.
- the deterministic no-generation knowledge path returns the top eligible
  chunk without a separate requested-claim support check.
- evidence policy uses an internal `site-settings` alias instead of one
  canonical authority ID at the response boundary.
- multi-intent splitting is connector-driven and does not consistently retain
  branch-local entities or safe siblings.
- optional semantic fallback is disabled and has no comparable measured model;
  it is not part of the frozen candidate behavior.

## Required implementation order

1. canonicalize authority IDs and add deterministic claim-support gating;
2. strengthen route frame entities plus resource/operation capability;
3. repair semantic intent boundaries and multi-intent branch ownership;
4. bind cross-account denial before private handlers;
5. remove only duplicate downstream reclassification covered by tests;
6. benchmark deterministic, deterministic-plus-bounded-fallback, and bounded
   proposal-plus-guards strategies as development benchmarks;
7. run V0--V10 development regression and QA;
8. freeze only if all P0 safety objectives pass, then stop before V11.

## Complete primary-case root-cause assignment

### CAPABILITY (10)

`V10-ST-0265`, `V10-ST-0155`, `V10-ST-0177`, `V10-ST-0178`, `V10-ST-0158`, `V10-ST-0168`, `V10-ST-0264`, `V10-ST-0262`, `V10-ST-0263`, `V10-ST-0255`

### CLAIM_SUPPORT (3)

`V10-ST-0174`, `V10-ST-0269`, `V10-ST-0173`

### ENTITY_CANONICALIZATION (18)

`V10-ST-0089`, `V10-ST-0081`, `V10-ST-0211`, `V10-ST-0080`, `V10-ST-0270`, `V10-ST-0183`, `V10-ST-0185`, `V10-ST-0286`, `V10-ST-0090`, `V10-ST-0083`, `V10-ST-0184`, `V10-ST-0086`, `V10-ST-0213`, `V10-ST-0268`, `V10-ST-0084`, `V10-ST-0088`, `V10-ST-0209`, `V10-ST-0079`

### ENTITY_EXTRACTION (47)

`V10-ST-0259`, `V10-ST-0002`, `V10-ST-0134`, `V10-ST-0140`, `V10-ST-0041`, `V10-ST-0275`, `V10-ST-0133`, `V10-ST-0281`, `V10-ST-0035`, `V10-ST-0152`, `V10-ST-0251`, `V10-ST-0250`, `V10-ST-0141`, `V10-ST-0266`, `V10-ST-0284`, `V10-ST-0260`, `V10-ST-0009`, `V10-ST-0151`, `V10-ST-0146`, `V10-ST-0253`, `V10-ST-0018`, `V10-ST-0279`, `V10-ST-0012`, `V10-ST-0258`, `V10-ST-0256`, `V10-ST-0024`, `V10-ST-0042`, `V10-ST-0240`, `V10-ST-0135`, `V10-ST-0252`, `V10-ST-0283`, `V10-ST-0246`, `V10-ST-0273`, `V10-ST-0156`, `V10-ST-0015`, `V10-ST-0003`, `V10-ST-0001`, `V10-ST-0143`, `V10-ST-0136`, `V10-ST-0130`, `V10-ST-0257`, `V10-ST-0160`, `V10-ST-0148`, `V10-ST-0254`, `V10-ST-0022`, `V10-ST-0144`, `V10-ST-0019`

### EVIDENCE_DOMAIN (1)

`V10-ST-0182`

### EVIDENCE_ELIGIBILITY (11)

`V10-ST-0099`, `V10-ST-0101`, `V10-ST-0093`, `V10-ST-0098`, `V10-ST-0095`, `V10-ST-0097`, `V10-ST-0096`, `V10-ST-0100`, `V10-ST-0092`, `V10-ST-0091`, `V10-ST-0094`

### INTENT (163)

`V10-ST-0172`, `V10-ST-0204`, `V10-ST-0034`, `V10-ST-0025`, `V10-ST-0104`, `V10-ST-0202`, `V10-ST-0076`, `V10-ST-0124`, `V10-ST-0059`, `V10-ST-0113`, `V10-ST-0026`, `V10-ST-0110`, `V10-ST-0189`, `V10-ST-0016`, `V10-ST-0119`, `V10-ST-0029`, `V10-ST-0075`, `V10-ST-0008`, `V10-ST-0060`, `V10-ST-0137`, `V10-ST-0013`, `V10-ST-0117`, `V10-ST-0125`, `V10-ST-0085`, `V10-ST-0193`, `V10-ST-0046`, `V10-ST-0067`, `V10-ST-0197`, `V10-ST-0103`, `V10-ST-0109`, `V10-ST-0244`, `V10-ST-0028`, `V10-ST-0072`, `V10-ST-0206`, `V10-ST-0038`, `V10-ST-0237`, `V10-ST-0052`, `V10-ST-0195`, `V10-ST-0127`, `V10-ST-0225`, `V10-ST-0005`, `V10-R2-ST-9f3c1a72`, `V10-ST-0036`, `V10-ST-0245`, `V10-ST-0011`, `V10-R3-ST-1f6748ba`, `V10-ST-0161`, `V10-ST-0216`, `V10-ST-0164`, `V10-ST-0058`, `V10-ST-0241`, `V10-ST-0194`, `V10-ST-0006`, `V10-ST-0031`, `V10-ST-0249`, `V10-ST-0032`, `V10-ST-0192`, `V10-ST-0199`, `V10-ST-0020`, `V10-ST-0074`, `V10-ST-0064`, `V10-ST-0073`, `V10-ST-0017`, `V10-ST-0102`, `V10-ST-0037`, `V10-ST-0040`, `V10-ST-0121`, `V10-ST-0123`, `V10-ST-0007`, `V10-ST-0218`, `V10-ST-0176`, `V10-ST-0078`, `V10-ST-0004`, `V10-ST-0033`, `V10-ST-0056`, `V10-ST-0054`, `V10-R3-ST-579dee27`, `V10-ST-0047`, `V10-ST-0107`, `V10-ST-0118`, `V10-ST-0236`, `V10-ST-0055`, `V10-ST-0224`, `V10-ST-0116`, `V10-ST-0162`, `V10-ST-0070`, `V10-ST-0021`, `V10-ST-0203`, `V10-ST-0165`, `V10-ST-0153`, `V10-ST-0087`, `V10-ST-0231`, `V10-ST-0065`, `V10-ST-0234`, `V10-ST-0120`, `V10-ST-0169`, `V10-ST-0082`, `V10-ST-0243`, `V10-ST-0023`, `V10-ST-0053`, `V10-ST-0175`, `V10-ST-0061`, `V10-ST-0027`, `V10-ST-0132`, `V10-ST-0179`, `V10-ST-0128`, `V10-ST-0157`, `V10-ST-0111`, `V10-ST-0139`, `V10-ST-0207`, `V10-ST-0030`, `V10-ST-0105`, `V10-ST-0228`, `V10-ST-0044`, `V10-ST-0106`, `V10-ST-0200`, `V10-ST-0069`, `V10-ST-0166`, `V10-ST-0062`, `V10-ST-0048`, `V10-ST-0219`, `V10-ST-0163`, `V10-ST-0186`, `V10-ST-0114`, `V10-ST-0131`, `V10-ST-0122`, `V10-ST-0248`, `V10-ST-0196`, `V10-ST-0201`, `V10-ST-0063`, `V10-ST-0191`, `V10-ST-0050`, `V10-R2-ST-2ad750ce`, `V10-ST-0077`, `V10-ST-0066`, `V10-ST-0014`, `V10-ST-0222`, `V10-ST-0170`, `V10-ST-0071`, `V10-ST-0108`, `V10-ST-0238`, `V10-ST-0167`, `V10-ST-0057`, `V10-ST-0068`, `V10-ST-0242`, `V10-ST-0039`, `V10-ST-0205`, `V10-ST-0180`, `V10-ST-0232`, `V10-ST-0142`, `V10-ST-0045`, `V10-ST-0112`, `V10-ST-0208`, `V10-ST-0126`, `V10-ST-0138`, `V10-ST-0010`, `V10-ST-0247`, `V10-ST-0198`, `V10-ST-0188`, `V10-ST-0129`, `V10-ST-0145`, `V10-ST-0115`, `V10-ST-0190`

### MULTI_INTENT_DECOMPOSITION (16)

`V10-ST-0289`, `V10-ST-0280`, `V10-ST-0285`, `V10-ST-0277`, `V10-ST-0272`, `V10-ST-0276`, `V10-ST-0291`, `V10-ST-0288`, `V10-ST-0287`, `V10-ST-0282`, `V10-ST-0267`, `V10-ST-0290`, `V10-ST-0292`, `V10-ST-0274`, `V10-ST-0271`, `V10-ST-0278`

