# V11 Observability Gap Resolution Report

Status: **V11 OBSERVABILITY RESOLUTION BLOCKED — SEMANTIC CANDIDATE CHANGE REQUIRED**

Decision path: **PATH C**

Final V11 candidate requests: **0 / 200**  
Operator retries: **0**  
Final V11 execution: **NOT STARTED**

## 1. Scope and holdout protection

This investigation used only candidate source code, V0–V10 development tests,
synthetic messages, the generic V11 contracts, the sanitized authority and
business contracts, and the prior runtime-adapter validation report.

It did not inspect Final V11 utterances, individual Final V11 gold records, or
candidate predictions. It did not execute Final V11. No candidate application
source was changed.

The three frozen Final V11 artifact identities were verified without opening
the dataset contents:

| Artifact | Required SHA-256 | Observed SHA-256 | Result |
|---|---|---|---|
| Final dataset | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | PASS |
| R3 audit report | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | PASS |
| Final manifest | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | PASS |

No final raw capture, scored result, score report, or execution manifest
exists. The existing preflight still records zero candidate requests.

## 2. BUSINESS_OUTCOME_OBSERVABILITY_TRACE

### 2.1 Candidate path

| Stage | ORDER-state mutation | PAYMENT-state mutation | Observation |
|---|---|---|---|
| Concept extraction | Local `orderReference` may be true; `paymentReference` may also be true when payment is mentioned | Local `paymentReference` is true; `orderReference` is commonly also true because payments are tied to an order | These are mention signals, not a resolved mutation target. They are not retained in the route frame. |
| Capability resource | `order_or_payment` | `order_or_payment` | Collapsed in `ChatCapabilityGuard::classify()`. |
| Operation | `mutate` | `mutate` | Identical. |
| Denial reason | `order_or_payment_mutation` | `order_or_payment_mutation` | Identical. |
| Semantic intent | `privileged_mutation` | `privileged_mutation` | Identical after the denial branch. |
| Selected execution path | `Unsupported` controller branch / security-policy denial | `Unsupported` controller branch / security-policy denial | Identical handler selection. |
| Internal result type | `ChatRouteFrame` with `decision_state=denied_action` | `ChatRouteFrame` with `decision_state=denied_action` | No protected-resource subtype exists. |
| External result type | JSON denial with `intent=unsupported`, `resource=order_or_payment`, `operation=mutate`, `decision_state=denied_action`, `code=ACTION_NOT_ALLOWED` | Same JSON denial | The response text also deliberately combines account, order, and payment actions. |
| Required V11 business outcome | `DENY_CHATBOT_ORDER_MUTATION` | `DENY_PAYMENT_STATE_MUTATION` | Two required labels map from one candidate state. |

### 2.2 Synthetic collision proof

Two synthetic, non-V11 messages were routed through the frozen candidate:

- `cancel payment related order 42` — the command target is the order.
- `cancel payment for order 42` — the command target is the payment.

After removing the raw `query` field, both route snapshots were identical,
including:

```text
semantic_intent=privileged_mutation
resource=order_or_payment
operation=mutate
decision_state=denied_action
denial_reason=order_or_payment_mutation
concepts.operation=mutate
concepts.knowledge_topic=null
entities.order_reference=42
entities.requested_mutation_value=cancelled
entities.topic=unknown
```

Reading either request string in an adapter would be the forbidden
request-text heuristic. Exposing the existing `orderReference` and
`paymentReference` booleans would still not resolve this collision because
both signals are true. A new rule would have to decide which mentioned
resource is the mutation target.

### 2.3 Classification

**BUSINESS OUTCOME: B. SEMANTICS_DO_NOT_EXIST**

The candidate understands that the request is a prohibited mutation, but it
does not represent whether the mutation target is order state or payment
state before the business decision. Creating a protected-resource subtype,
target grammar, or precedence rule would add candidate semantics. It is not
an observability-only change.

## 3. RUNTIME_EVIDENCE_PROVENANCE_INVENTORY

| Runtime datum | Classification | Finding |
|---|---|---|
| Route `requiredEvidenceDomain` (`topic`, `claim_type`, allowed sources, authority, structured source) | `EXISTING_STRUCTURED_RUNTIME_DATA` | Immutable domain selected before retrieval. |
| Response `source` and branch `subresponses[].source` | `EXISTING_STRUCTURED_RUNTIME_DATA` | Stable runtime source aliases are available. |
| Retrieval `retrieved_source_ids` | `EXISTING_STRUCTURED_RUNTIME_DATA` | Diagnostic only; retrieval is not acceptance. |
| Retrieval `accepted_evidence_ids` | `EXISTING_STRUCTURED_RUNTIME_DATA` | Source-level accepted provenance. |
| Knowledge citation `source_id`, `title`, `section` | `EXISTING_STRUCTURED_RUNTIME_DATA` | Section identity is already observable and can be a registry-backed knowledge fact key. |
| Knowledge chunk `id` / database chunk ID | `EXISTING_STRUCTURED_RUNTIME_DATA` | Present in the retriever; not currently serialized to the client. |
| Generated `claims[].claim`, `citation_index`, exact `evidence` quote | `EXISTING_STRUCTURED_RUNTIME_DATA` | Created and structurally verified inside `ChatKnowledgeAnswerService`, then intentionally discarded from the public response. |
| Generated claim-to-source link | `DERIVABLE_FROM_EXISTING_RUNTIME_DATA` | `citation_index` joins a generated claim to the accepted chunk and its source/section without gold. |
| Evidence eligibility result | `EXISTING_STRUCTURED_RUNTIME_DATA` | Only eligible, domain-matching chunks enter the accepted set. |
| Claim-support result for generated knowledge | `EXISTING_STRUCTURED_RUNTIME_DATA` | The service verifies all generated claims before returning `answer_status=verified`; it is aggregate rather than a public per-claim contract. |
| Product ID, name, category, price, inventory, inventory status | `EXISTING_STRUCTURED_RUNTIME_DATA` | Present in structured product cards and composition inputs. |
| SiteSetting shipping and public-contact fields | `EXISTING_STRUCTURED_RUNTIME_DATA` | Read as typed fields before deterministic response composition. |
| Owned-order status, payment status, method, total, creation time | `EXISTING_STRUCTURED_RUNTIME_DATA` | Present in the authorized structured order result. |
| Authority type | `DERIVABLE_FROM_EXISTING_RUNTIME_DATA` | Exact static join from accepted source ID to the frozen sanitized registry. |
| Accepted source version | `DERIVABLE_FROM_EXISTING_RUNTIME_DATA` | Exact static join to the pinned registry version; no live lookup is needed. |
| Canonical structured fact key | `DERIVABLE_FROM_EXISTING_RUNTIME_DATA` | Deterministic source + subject + field key for structured authorities, or source + frozen section key for published knowledge. |
| V11 record-specific `claim_id` | `NOT_AVAILABLE` | Its V1 meaning depends on record ID plus exact gold fact and is forbidden in runtime. |
| Gold-required fact identity | `NOT_AVAILABLE` | Correctly belongs only to the offline scorer/fact contract. |

### 3.1 Claim-provenance classification

**CLAIM PROVENANCE: B.
CLAIM_PROVENANCE_CAN_BE_EXPOSED_FROM_EXISTING_STRUCTURED_COMPOSITION_INPUT**

The claim gap is a contract mismatch, not evidence-selection behavior. The
candidate already has typed structured fields, accepted chunks, section
identity, and—when generation is enabled—an exact claim/citation/evidence
association. A gold-independent V2 contract can consume these values without
making the runtime know a V11 claim ID.

This classification does not unblock execution because the independent
business-outcome gate fails.

## 4. Non-frozen Claim Verification Contract V2 design

This section defines the safe correction but does not freeze it as an
execution artifact after Path C was selected.

### 4.1 Candidate evidence tuple

The runtime-side tuple should contain only candidate-observable values:

```json
{
  "source_id": "<pinned authority source>",
  "source_version": 1,
  "authority_type": "<pinned registry authority>",
  "fact_key": "<domain-canonical registry key>",
  "subject_key": "<optional structured subject>",
  "evidence_domain": "<route-selected domain>",
  "support_status": "accepted"
}
```

It must not contain a case ID, gold record ID, exact gold fact, expected
answer, or any identifier derived from those values.

### 4.2 Canonical fact-key families

Exact keys must be generated from the frozen registries, for example:

| Authority | Canonical key form |
|---|---|
| Product/category | `product-category-authority-v11#product:<id>#name`, `#category`, `#current_unit_price_vnd`, `#current_stock`, `#active` |
| SiteSetting | `site-settings-authority-v11#shipping.shipping_fee_vnd`, `#shipping.free_shipping_threshold_vnd`, `#shipping.calculation_semantics`, `#public_contact.<field>` |
| Owned order | `owned-order-authority-v11-r1#order:<runtime-id>#status`, `#payment_status`, `#payment_method`, `#grand_total`, `#created_at` |
| Published knowledge | `<source_id>#section:<registry-derived-section-key>` where the section key is frozen from the exact registered section identity, not inferred from request or answer prose |

For generated knowledge, the existing `citation_index` associates each
structured claim with an accepted source section. An optional evidence-span
checksum may be added from the already validated exact source quote, but it
must not be computed from gold.

### 4.3 Gold boundary

The adapter may read only:

- the lossless runtime capture;
- the pinned source alias/version/authority registry;
- a generic canonical fact-key registry containing no case expectations.

The offline fact contract owns the mapping from each gold-required fact to
one or more acceptable canonical evidence keys. The scorer asks whether the
candidate evidence tuples contain those required keys under the correct
domain, source, version, terminal, and authorization scope.

The adapter must never read Final V11 records, `record_claim_contracts`,
`minimum_facts_required`, expected fields, or gold fields.

### 4.4 Required negative behavior

The V2 scorer design preserves the existing gates:

| Candidate evidence | Result |
|---|---|
| Correct source + correct fact key | PASS |
| Correct source + wrong fact key | FAIL |
| Wrong source + semantically related fact key | FAIL and wrong-topic authority |
| Source only, without a required fact key | FAIL when claim-level evidence is required |
| No source | FAIL for an evidence-requiring answer |
| `NO_EVIDENCE` with no eligible authority | Preserve the frozen no-evidence semantics |
| Unsupported-policy answer without required tuple | Unsupported-policy hallucination |

No request-text parsing, answer-text parsing, LLM, embedding match, Qdrant,
network request, live database lookup, or latest-version lookup is permitted
in the adapter/evaluator/scorer pipeline.

## 5. Candidate-preservation decision

No candidate source change was made.

The minimum change needed to unblock business-outcome observation would be a
new candidate semantic contract that resolves a mutation target such as:

```text
protected_resource = order_state | payment_state | ambiguous
operation = mutate
```

That resolution must happen before the denial result and must define safe
behavior for requests mentioning both resources. It would require new routing
or capability semantics and development tests. Merely adding a telemetry
field would hide that new decision inside instrumentation and would not make
the change observability-only.

This work belongs to a future candidate development phase. It must produce a
new candidate identity and be evaluated without using Final V11 examples as
development data.

## 6. Validation record

| Check | Result |
|---|---|
| Phase 14 candidate identity | PASS — `f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf` |
| V0–V10-safe unit/freeze selection | PASS — 110 tests / 275 assertions |
| Synthetic ORDER/PAYMENT structured collision | REPRODUCED |
| Candidate source modifications | NONE |
| Final V11 utterances accessed | NO |
| Final V11 gold examples accessed | NO |
| Final V11 candidate requests | 0 |
| Final V11 execution | NOT STARTED |
| Business outcome observability | FAIL — semantic target absent |
| Gold-independent claim-provenance design | PASS as a non-frozen contract design |
| Perfect mirror V2 | NOT RUN — business-outcome prerequisite failed |
| Adapter/harness/evaluator/scorer V2 freeze | NOT CREATED |
| Toolchain V2 freeze | NOT CREATED |

## 7. Final decision

**V11 OBSERVABILITY RESOLUTION BLOCKED — SEMANTIC CANDIDATE CHANGE REQUIRED**

Missing semantic distinction:

> A structured mutation target that distinguishes `order_state` from
> `payment_state` before both are collapsed to `order_or_payment`.

Why observability alone cannot solve it:

> The candidate retains only a combined protected resource and combined
> denial result. Existing mention signals can both be true and do not encode
> the requested mutation target. Any adapter or telemetry patch choosing one
> target would introduce new request semantics.

Candidate requests: **0**  
Final V11: **NOT EXECUTED**

Do not run Final V11. Do not freeze a V2 toolchain against this candidate.
