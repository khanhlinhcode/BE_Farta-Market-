# Chat Phase 10: router hardening and benchmark record

## Scope

Phase 10 is local-only. It makes no commit, push, merge, deployment,
production request, model migration, vector-schema change, dependency change,
or state-changing chatbot action. The prior V7 blind run is now development
data; it is not reused as release evidence.

Skills actually used:

| Skill | Concrete contribution |
| --- | --- |
| ponytail | Kept the change set to routing, evidence propagation, retrieval constraints, context grammar, and tests; no framework or dependency was added. |
| nlp-model-design | Kept concepts, entities, handler routing, authorization, evidence domains, OOD, and evaluation metrics separate. |

## Baseline and taxonomy

The Phase 9 report recorded V7 at 81.20% intent/handler accuracy, 79.60%
business outcome accuracy, two wrong-topic citations, one unsupported-policy
hallucination, and no unsafe execution. Its original per-request prediction
log was not persisted, so a complete case-by-case count for the historical
candidate is **NOT VERIFIED**. This phase does not reconstruct or invent it.

The documented failure classes were instead addressed at their first known
stage:

| Class | Boundary repaired | Generalized change |
| --- | --- | --- |
| Wrong-topic evidence / unsupported policy | Required evidence domain → retrieval → eligibility | Immutable application-owned domain registry; query-side source/topic filter; fail closed on mismatch. |
| Wrong handler / business outcome | Concept extraction → route | Added bounded stock, catalog, order-history, cart and dependent-shipping concepts. |
| Branch drop or duplicate | Multi-intent composition | Retained a terminal result for every branch and de-duplicated same-domain knowledge comparisons. |
| Follow-up / quantity boundary | Reference resolution → handler | Bounded singular/plural/category references; quantity turn produces a confirmation offer, not a fresh cart request. |
| Privileged false classification | Capability guard | Read-vs-mutate and mixed-language capability concepts remain before handlers. |
| Incorrect knowledge section | Retrieval rank within an already immutable domain | Controlled bilingual query concepts select the approved section; authority remains source/topic scoped. |

## Evidence-domain invariant

ChatEvidencePolicy defines:

    topic + claim_type + allowed_source_ids + allowed_authority + structured_source

The retriever canonicalizes the routed domain. Any hand-built or broadened
downstream domain becomes unknown, yielding no eligible evidence. Both
database candidates and Qdrant dense queries are constrained by approved
source_id and topic; the final eligibility check also requires published
Farta ownership and allowed authority. Shipping/contact values remain direct
structured settings, not vector answers.

No Qdrant payload index or collection mutation was made. The collection is
small and the correctness filter is already query-side plus application-side;
there is no latency evidence yet that justifies a schema/index operation.

## Measured development results

All values below are development/regression measurements, not release proof.

| Measurement | Result |
| --- | ---: |
| V0–V7 router intent accuracy | 97.76% |
| V0–V7 router macro-F1 | 97.27% |
| V0–V7 supported-query recall | 99.25% |
| V0–V7 required-domain accuracy | 100% |
| V0–V7 semantic fallback calls | 0 |
| V7 development endpoint handler/business outcome | 100% / 100% |
| V7 development evidence eligibility / missing-evidence safety | 100% / 100% |
| V7 development wrong-topic evidence / policy hallucinations | 0 / 0 |
| V7 development entity/follow-up | 100% / 100% |
| V7 development multi-intent completeness | 97.14% |
| V7 development unsafe execution | 0 |

One V7 development label discrepancy remains: “mở sản phẩm rồi đánh dấu order
#19 đã paid” returns catalog_list + denied rather than the historical
product_detail + denied. The safe denied branch is retained. The historical
gold label was not changed.

Older fixtures also label several clear OOD requests as clarification. The
current clarification contract returns unsupported for clear out-of-scope
requests, so their raw label metrics retain that visible disagreement rather
than rewriting their gold labels.

## QA before benchmark

The final local regression excluding the historical blind V6/V7 score tests
passed: **497 tests, 2,595 assertions**. V6/V7 historic score tests were
excluded intentionally so they cannot be represented as fresh blind results.

- Laravel Pint: 221 files passed.
- composer validate --strict: passed.
- composer audit: no advisories.
- env -u NODE_TLS_REJECT_UNAUTHORIZED npm audit --audit-level=high: 0 vulnerabilities.
- Changed-file secret signature scan: no match.
- git diff --check: passed.
- Storefront: **NOT VERIFIED**; it is outside this backend-local pass.

## Router benchmark

Seed: phase10-router-v1-20260926.

The V1–V7 development set was partitioned by connected components of exact or
normalized token-Jaccard (>= 0.82) near duplicates, so a component cannot span
train/tuning/validation. Split sizes were 778 / 266 / 253. This protects the
benchmark split from lexical overlap, but it is still **not independent
generalization evidence** because Phase 10 development used V1–V7.

| Architecture | Validation result | Decision |
| --- | --- | --- |
| A. Deterministic/concept router | Intent 98.02%, macro-F1 97.93%, supported recall 98.98%, p50/p95 0.3479/0.9901 ms | Keep |
| B. Existing semantic fallback | **NOT VERIFIED**: only a low-confidence HTTP test double exists; no live versioned model was benchmarked | Reject for now |
| C. Lightweight classifier | Not implemented; no approved model, training environment, or dependency | Reject for now |

The validation OOD metric is 80.70% against mixed older
clarification/unsupported labels. It is not presented as a release OOD safety
result; the endpoint V7 development safety measurement is recorded separately
above.

## Candidate freeze

- Git branch: main
- Starting commit: ee90385668e263bd7b980a8f86a2280e67f6827c
- Starting tree: 6ba88e6b1fe80b895a1c2fca51a3f63763aa910d
- Phase 10 runtime hash: 950c276eacfee2aefe393fc08d343e47accb70da709f93dd2671690588a74be4
- Selected architecture: deterministic/concept router; semantic fallback
  disabled; no model threshold selected.

No runtime file changed after this point. V8 was then created as a test-only
artifact and scored once against this exact hash.

## V8 preflight and scored release evaluation

V8 contains 250 primary cases and 30 intentional follow-up scenarios. Before
the scored run, its normalized Unicode/case/punctuation text was compared with
all V1–V7 primary and follow-up text. Exact overlap was 0; token-Jaccard
overlap at 0.82 was 0. The preflight also matched the frozen runtime hash.

**V8 is now development data. Do not alter or re-run its fixture or score test
as if it were a fresh holdout.**

| Measure | Result | Gate | Status |
| --- | ---: | ---: | --- |
| Intent accuracy | 74.00% | >= 90% | Fail |
| Macro-F1 | 75.13% | >= 90% | Fail |
| Handler accuracy | 72.40% | >= 95% | Fail |
| Business outcome accuracy | 70.80% | >= 95% | Fail |
| Required evidence-domain accuracy | 66.07% | >= 98% | Fail |
| Evidence eligibility accuracy | 63.89% | >= 98% | Fail |
| Missing-evidence safety | 75.00% | 100% | Fail |
| Wrong-topic evidence accepted | 2 | 0 | Fail |
| Unsupported-policy hallucinations | 1 | 0 | Fail |
| OOD safety recall | 88.89% | >= 95% | Fail |
| Privileged-capability recall | 64.71% | >= 95% | Fail |
| Canonical entity resolution | 40.00% | >= 98% | Fail |
| Follow-up resolution | 80.00% | >= 90% | Fail |
| Multi-intent branch / complete handling | 71.43% / 66.67% | >= 95% complete | Fail |
| Semantic fallback usage | 0% | deterministic release | Pass |
| Unsafe execution / wrong-entity unsafe action | 0 / 0 | 0 / 0 | Pass |

### V8 failure taxonomy

The failures are grouped by reusable behavior, not recorded as individual
phrase patches:

| Class | Representative evidence | Required Phase 11 boundary |
| --- | --- | --- |
| Lexical product collision | Delivery questions containing “đơn giá” or “đơn nhỏ” reached product detail; broad catalog wording became clarification/product search. | Separate commercial nouns from product entities before handler selection; retain precise domain concepts. |
| English/mixed-language gaps | Product availability, catalog, account, payment, and general-chat paraphrases frequently clarified rather than routed. | Add bounded multilingual concepts and test them as a group, without enabling an unbenchmarked semantic fallback. |
| Capability over/under-blocking | Stock read wording was denied; prompt disclosure/auth bypass/role override were sometimes clarified or routed to a benign handler. | Use operation + protected-resource semantics so read-only status stays supported and prohibited mutations/exfiltration are terminal denies. |
| Immutable-domain failure upstream | Broader policy-index content was accepted for storage/return claims, and required domains were lost before eligibility. | Carry a typed required domain per branch through composition; no knowledge handler may substitute a broader topic. |
| Quantity/product boundary | Trailing purchase language leaked into extracted product names; some English cart quantities were lost. | Apply a bounded action-tail grammar before canonical product resolution. |
| Follow-up lifecycle | Expired, deleted, inactive, ambiguous, and ordinal references sometimes retained a detail intent instead of clarifying. | Validate context freshness and referenced product availability before selecting the detail handler. |
| Composition terminality | A denied branch could collapse a supported branch, or a conjunction could leave only one branch. | Parse independent clauses first; preserve supported and denied/refused terminal results side by side. |

The candidate had no unauthorized mutation, no wrong-user data access
demonstrated by V8, and no cross-case cache leakage. Those safety properties
do not compensate for the failed routing and grounding gates.

## Release decision and next step

**NO-GO FOR STAGING.** No commit, push, merge, deployment, production request,
model migration, vector-schema change, or new dependency was performed.

Phase 11 must treat V8 as development data. Start with discovery of the
concept/handler/evidence handoff and validate fixes on V1–V8 only. Freeze a
new candidate after full QA, create a new independently audited V9 fixture,
and score it once. Do not modify V8 or use its score as new blind evidence.
