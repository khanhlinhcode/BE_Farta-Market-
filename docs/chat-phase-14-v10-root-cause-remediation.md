# Phase 14 — Final V10 root-cause remediation and new candidate development

Date: 27 September 2026  
Status: **PHASE 14 CANDIDATE FREEZE COMPLETE (LOCAL ONLY)**

V10 is development evidence from this phase onward. Nothing in this report is a
blind, independent, staging, or production-performance claim.

## 1. Executive summary

The valid frozen V10 result exposed a semantic discrimination failure, not a
general failure to recognize supported traffic. Phase 14 classified every
failed primary case by its earliest observable cause before changing runtime
code, then repaired reusable routing, entity, capability, context,
multi-intent, and evidence-authority boundaries. The new local candidate passes
all 17 V10 development gates, including every P0 safety objective. No commit,
push, merge, deployment, or V11 work was performed.

## 2. Final V10 baseline

| Metric | Frozen V10 result |
| --- | ---: |
| Business outcome | 37.33% (109/292) |
| Handler accuracy | 36.30% (106/292) |
| Intent accuracy | 35.27% (103/292) |
| Macro-F1 | 34.06% |
| Supported-query recall | 99.63% (268/269) |
| Required evidence domain | 27.84% (71/255) |
| Evidence eligibility | 63.22% (220/348) |
| Missing-evidence safety | 21.74% (5/23) |
| Wrong-topic authority | 63 |
| Unsupported-policy hallucination | 18 |
| Unsafe execution / wrong-entity unsafe action | 0 / 0 |

The archived candidate hash is
`d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`.
The 372-record raw capture SHA-256 is
`bcf07fa6a9c0e0554237fffad7e7806cef5271c0837a92fd197b61ca59ffe6d3`;
the Offline Scorer V4 identity is
`c7be5acf4f928f50a2a71861371822dde809da9c063f4390dfafd5412e727d87`;
the scored-results SHA-256 is
`f3a72fda1507f8b3a27e9054d3063bf0ab2482eeaffaea9237653f9caf92eec1`.

## 3. Root-cause taxonomy

The audit used the required one-cause taxonomy and assigned the earliest
observable failure only. An intent miss was not also counted as a handler,
domain, or composition miss. The complete case assignment is in
[chat-phase-14-root-cause-baseline.md](chat-phase-14-root-cause-baseline.md).

## 4. First-failure distribution

| Failure layer | Cases | Share of 269 failed cases | Representative IDs | Primary impact |
| --- | ---: | ---: | --- | --- |
| INTENT | 163 | 60.59% | `V10-ST-0172`, `0204`, `0034` | Wrong route/handler/domain |
| ENTITY_EXTRACTION | 47 | 17.47% | `V10-ST-0259`, `0002`, `0156` | Missing/wrong business object |
| ENTITY_CANONICALIZATION | 18 | 6.69% | `V10-ST-0089`, `0081`, `0211` | Mention not bound to DB identity |
| MULTI_INTENT_DECOMPOSITION | 16 | 5.95% | `V10-ST-0289`, `0280`, `0285` | Lost sibling branch |
| EVIDENCE_ELIGIBILITY | 11 | 4.09% | `V10-ST-0099`, `0101`, `0093` | Authority identity mismatch |
| CAPABILITY | 10 | 3.72% | `V10-ST-0265`, `0155`, `0177` | Security boundary |
| CLAIM_SUPPORT | 3 | 1.12% | `V10-ST-0174`, `0269`, `0173` | Unsupported factual claim |
| EVIDENCE_DOMAIN | 1 | 0.37% | `V10-ST-0182` | Wrong authority domain |

Language counts for the largest INTENT cluster were English 40, mixed 28,
Vietnamese conversational 31, Vietnamese formal 35, and Vietnamese without
diacritics 29. The problem was cross-lingual rather than locale-specific.

## 5. Confusion matrix analysis

The largest frozen confusions were price → generic product detail (14), stock →
product detail (11), product detail → clarification (11), missing evidence →
clarification (10), product search → clarification (10), shipping calculation →
current shipping value (7), payment-status read → order read (6), and
multi-intent → one shipping branch (6). These pairs justified typed semantic
operations while retaining the existing public enum compatibility.

The Phase 14 V10 matrix has only two off-diagonal cases: `knowledge_query` →
`cart_informational`. Both are near-duplicate taxonomy disagreements; their
handlers and business outcomes are correct.

## 6. Intent / handler failure analysis

Supported recall near 100% in frozen V10 meant the runtime broadly accepted
supported requests but poorly discriminated which route should own them.
Concept families now distinguish PRICE, STOCK, SEARCH, CATALOG, CART_READ,
CART_MUTATE, ORDER_READ, PAYMENT_READ, SHIPPING_VALUE, SHIPPING_CALCULATION,
POLICY, ACCOUNT, OOD, and PRIVILEGED_MUTATION. Handler selection consumes the
route frame rather than constructing a competing taxonomy.

## 7. Wrong-topic authority analysis

The 63 frozen signals contained 12 authority-ID contract mismatches and 51 real
semantic route/domain contaminations. The new route chooses an immutable
`RequiredEvidenceDomain`; MySQL and Qdrant candidate selection are constrained
to its topic and source registry, followed by a deterministic final check.
Development result: **0 wrong-topic authority acceptances**.

## 8. Policy hallucination analysis

Frozen V10 had 18 clear no-evidence requests that returned another terminal,
including generic policy-index evidence used for specific claims. The policy
index can establish only that a verified category exists; it cannot prove a
refund period, cold-chain promise, SLA, certification, provenance, warranty,
privacy, or similar fact. Missing domains now carry an explicit empty authority
set and terminate `NO_EVIDENCE`. Development result: **0 unsupported-policy
hallucinations**.

## 9. Cross-account analysis

`V10-ST-0156` requested another account's order. Frozen behavior dropped the
account target and fell back to the actor's own recent order. It did not expose
the third party, but it still violated request binding. Phase 14 preserves
account targets (including email-like targets), classifies other-user data as a
protected resource, and denies the request before the private handler. Owner
scoping remains enforced again in the order service. Cross-account regression:
**PASS**.

## 10. Entity analysis

The pipeline is raw mention → bounded candidates → active-product DB
canonicalization. Purchase `quantity` and `requested_mutation_value` are
separate; order numbers cannot become quantities. Canonical product candidates
retain mention order and are resolved per branch. Account target, order
reference, context reference, unit, and mutation value are explicit slots.

## 11. Context/follow-up analysis

The context resolver only attaches references or requests clarification. It
does not choose a business intent. Session state is five-minute, owner-bound,
and limited to ordered product/category identities; every product is reloaded
from the current DB. Singular, plural read, first/second/last, deictic,
possessive, ellipsis, cart continuation, expired, and ambiguous cases are
covered.

## 12. Multi-intent analysis

Every independently actionable clause becomes a child `ChatRouteFrame` with
branch-local intent, resource/operation, entities, terminal, handler, and
evidence domain. Compatible branches may share one product; distinct mentions
remain local. A denied, no-evidence, or clarification branch cannot remove its
safe siblings.

## 13. Architecture before

The prior runtime already had deterministic security and DB authority, but its
route vocabulary was coarse. The controller re-parsed several entities, context
could not reliably resolve ordered/plural references, multi-intent decomposition
lost branches, and retrieval relevance could be mistaken for authority or claim
support.

## 14. Architecture after

```text
text -> normalize -> concepts -> entities -> capability(resource, operation)
     -> intent/domain -> immutable RouteFrame -> context enrichment
     -> active DB canonicalization -> handler/structured authority
     -> source eligibility -> claim support -> response composition
```

There is no agentic loop and no unbounded LLM router.

## 15. Route frame changes

`ChatRouteFrame` now carries public intent, typed semantic intent, resource,
operation, all entity slots, decision state, confidence/source, immutable
evidence domain, explicit branch frames, composition mode, bounded context, and
safe timing telemetry. It is readonly; array access exists only for compatibility.

## 16. Capability resource/operation changes

The guard models protected resource plus operation rather than denying a noun.
Resources include authorization, internal instructions, order/payment, returns,
inventory, account/role, and other-user data. Operations include read, mutate,
disclose, bypass, and unknown. Benign stock/order reads remain supported while
mutations, exfiltration, bypass, and cross-account reads fail closed.

## 17. Evidence-domain changes

`ChatEvidenceDomain` is immutable and registered by `ChatEvidencePolicy`.
Downstream callers may retain the routed domain but cannot broaden or substitute
it. A mismatch canonicalizes to an empty unknown domain. Structured domains map
to Product DB, `SiteSetting`, or owner-scoped orders; approved knowledge maps to
specific source IDs.

## 18. Eligibility changes

Stored knowledge candidates are filtered by published status, Farta ownership,
locale, topic, and allowed source ID. Qdrant receives topic and source filters.
Dense IDs are intersected with eligible MySQL IDs, and every ranked result is
rechecked for owner, status, topic, authority, source ID, and evidence flag.

## 19. Claim-support changes

Eligibility alone is insufficient. Approved guides now require a deterministic
claim-to-section match before they may answer. Empty-authority domains and
related but non-supporting sections return no evidence. Generated answers retain
strict structured claims, exact evidence quotes, semantic verification, and one
bounded repair.

## 20. Controller responsibility changes

The controller's high-value path is router → context resolver → entity
canonicalizer → dispatch. Routing truth is consumed from the frame, while
handlers continue deterministic authorization and DB revalidation. Some legacy
response shaping and catalog helpers remain in the controller; it was not
rewritten wholesale.

## 21. Tests added

Coverage includes a machine-checkable candidate freeze, concept extraction,
capability read-vs-mutate boundaries,
immutable route/evidence domains, bilingual entity extraction, ordered DB
canonicalization through the end-to-end evaluator, bounded context, independent
multi-intent branches, authority matrices, adversarial capability denial,
structured authority, missing evidence, and the full V10 development scorer.
The evaluator also reports per-slot, per-language, per-capability, latency, and
all 17 gate results.

## 22. Multilingual coverage

Reusable tests and V10 development cases cover Vietnamese formal,
conversational Vietnamese, Vietnamese without diacritics, English, mixed VN/EN,
word-order changes, politeness, quantities expressed as words/digits, entity
variation, and multi-clause requests. No case ID or fixture label is a runtime
feature.

## 23. Router benchmark design

Architecture A uses the improved deterministic concept router. V0--V9 uses a
fixed grouped split (1,086 train / 357 tuning / 352 validation) whose
near-duplicate connected components, defined by normalized token Jaccard ≥
0.82, remain in one split. V10 is a separate full development regression.
Architecture B requires an existing live, versioned bounded semantic fallback.
Architecture C probes for a lightweight classifier environment. All results are
development benchmarks.

## 24. Deterministic router results

| Development set | Intent | Macro-P | Macro-R | Macro-F1 | Supported recall | OOD P/R | Privileged recall | p50/p95 |
| --- | ---: | ---: | ---: | ---: | ---: | --- | ---: | --- |
| Grouped V0--V9 validation | 96.88% | 96.85% | 95.67% | 96.16% | 97.87% | n/a / 100% | 100% | 1.09 / 2.62 ms |
| V10 full development | 99.32% | 99.07% | 99.44% | 99.20% | 100% | 100% / 100% | 100% | 1.32 / 2.55 ms |

The V0--V9 fixture combines OOD and denied actions under `unsupported`, so its
OOD precision is not measurable. V10 keeps them separate.

## 25. Classifier/fallback results

Architecture B: **NOT VERIFIED** — no live, versioned semantic model was
available for a comparable benchmark. Architecture C: **NOT AVAILABLE IN THE
CURRENT ENVIRONMENT** — SetFit, sentence-transformers, torch, sklearn, and
transformers are absent. No dependency was installed. Classifier authorization,
ownership, truth, or evidence authority would remain out of scope even if one
were later benchmarked.

## 26. Router strategy decision

**KEEP DETERMINISTIC CONCEPT ROUTER.** It is the only measured strategy, passes
the P0 objectives, generalizes across all language buckets, and has low local
latency. The bounded semantic fallback stays disabled. This is an evidence-based
development decision, not a claim that classifiers can never help.

## 27. Development intent metrics

V10 intent accuracy is 290/292 = **99.3151%**. Macro precision is 99.0741%,
macro recall 99.4444%, and macro-F1 99.2026%. Supported-query recall is 269/269
= 100%; OOD and privileged precision/recall are all 100%.

## 28. Development handler metrics

V10 handler accuracy is **292/292 = 100%** and terminal accuracy is **292/292 =
100%**. The two intent-label disagreements still dispatch the correct handler.

## 29. Development business outcome

V10 business outcome is **290/292 = 99.3151%**. The two failures,
`V10-ST-0126` and `V10-ST-0127`, are duplicate checkout-review prompts whose
gold requires chatbot-suggestion/UI-confirmation/login facts instead of the
requested checkout-review facts. Runtime answers the correct approved checkout
section. Gold was not edited.

## 30. Entity metrics

| Slot | Accuracy/recall | Precision | F1 |
| --- | ---: | ---: | ---: |
| Canonical product | 156/156 = 100% | 83.87% | 91.23% |
| Quantity | 56/56 = 100% | 80.00% | 88.89% |
| Account target | 11/12 = 91.67% | 100% | 95.65% |
| Requested mutation value | 17/17 = 100% | 73.91% | 85.00% |

Aggregate slot precision/recall/F1 is 82.94% / 83.60% / 83.27%. Strict joint
frame accuracy is 120/256 = 46.88%; unit recall (12.50%) and order-reference
recall (58.82%) are the largest remaining weaknesses.

## 31. Follow-up metrics

Scenario success, reference detection, canonical resolution, and terminal
correctness are each **39/39 = 100%**. Every measured subtype passes: expired,
singular, deictic, ellipsis, ambiguous, plural, first, second, and last.

## 32. Multi-intent metrics

Branch intent is 55/56 = 98.21%; branch handler 54/56 = 96.43%; branch entity
correctness 31/56 = 55.36%; branch terminal 56/56 = 100%; completeness 27/27 =
100%; whole-request completion 22/27 = 81.48%. No sibling branch disappears,
but exact branch entity/handler matching remains a quality risk.

## 33. Evidence domain

Required evidence-domain accuracy is **255/255 = 100%**. One diagnostic record
has no gold domain and is excluded from that denominator; the product-catalog
route is otherwise correct.

## 34. Evidence eligibility

Eligibility accuracy is **344/348 = 98.8506%**, above the 98% development
objective. Claim-support accuracy is **296/307 = 96.4169%** and is reported
separately rather than hidden inside eligibility.

## 35. Missing-evidence safety

**23/23 = 100%**. Clear unsupported factual/policy claims reach `NO_EVIDENCE`
rather than clarification, Product DB, shipping settings, or the generic policy
index.

## 36. Wrong-topic authority count

**0**, reduced from 63 in frozen V10.

## 37. Policy hallucination count

**0**, reduced from 18 in frozen V10.

## 38. Security results

Unsafe execution: **0**. Wrong-entity unsafe action: **0**. Privileged
capability precision/recall: **100% / 100%**. OOD precision/recall: **100% /
100%**. Cross-account access, anonymous private reads, payment/order mutations,
inventory overrides, role mutations, prompt/secret disclosure, and auth bypass
terminate safely. Authorization and ownership remain deterministic backend
checks, independent of router predictions.

## 39. Language breakdown

| Language | n | Intent | Handler | Business outcome |
| --- | ---: | ---: | ---: | ---: |
| English | 68 | 98.53% | 100% | 98.53% |
| Mixed VN/EN | 48 | 100% | 100% | 100% |
| Vietnamese conversational | 58 | 98.28% | 100% | 100% |
| Vietnamese formal | 67 | 100% | 100% | 100% |
| Vietnamese without diacritics | 51 | 100% | 100% | 98.04% |

## 40. Capability breakdown

Sixteen of eighteen capability groups have 100% intent, handler, and outcome.
`knowledge_query` intent is 90% but handler/outcome are 100% because two cases
map to the semantically narrower cart-information route. `cart_informational`
outcome is 80% because the same two duplicated gold claim contracts require
unasked chatbot-add facts. Every privileged, OOD, missing-evidence,
multi-intent, order, payment, product, and shipping group reaches 100% outcome.

## 41. Latency

On the local V10 development run, primary routing measured 1.32 ms p50 / 2.55
ms p95; total primary requests 3.32 / 5.04 ms; scenario turns 3.63 / 4.57 ms.
Qdrant inference was disabled and **NOT MEASURED**. These are local test timings,
not production SLO evidence.

## 42. Backend QA

- Focused current chat suite: **362 passed, 1,526 assertions**.
- Full backend non-historical suite: **616 passed, 1 skipped, 3,327 assertions**.
- V0--V9 grouped benchmark + full V10 development regression: **2 passed, 23
  assertions**.
- Pint: **PASS, 258 files**.
- `composer validate --strict`: PASS.
- `composer audit`: no advisories.
- `env -u NODE_TLS_REJECT_UNAUTHORIZED npm audit --audit-level=high`: 0
  vulnerabilities.
- Secret signature/diff scan: 0 findings.
- `git diff --check`: PASS.

Historical blind tests intentionally lock earlier runtime hashes and are not
non-historical QA for this candidate. The one-shot Final V10 scored run was not
re-executed. Its evaluator test now preserves archived dataset/report/manifest
identities without incorrectly requiring the active Phase 14 runtime to equal
the retired candidate hash.

## 43. Storefront status

**STOREFRONT NOT VERIFIED.** Phase 14 changed backend/NLP behavior only.

## 44. Files changed

Primary runtime files include `ChatIntent`, `ChatController`,
`ChatIntentRouter`, `ChatConceptExtractor`, `ChatEntityExtractor`,
`ChatEntityCanonicalizer`, `ChatCapabilityGuard`, `ChatContextResolver`,
`ChatRouteFrame`, `ChatEvidenceDomain`, `ChatEvidencePolicy`,
`ChatKnowledgeRetriever`, `ChatKnowledgeAnswerService`, `ChatVectorSearch`, and
the existing product/order tools. Tests cover feature, unit, evaluator, and
benchmark layers. Documentation updated includes this report,
`chat-phase-14-root-cause-baseline.md`, `chat-architecture.md`, `chat-rag.md`,
and `cards/chat-model-card.md`.

## 45. Dependency changes

No production or development dependency was added for Phase 14. No classifier,
agent framework, embedding model, vector dimension, fusion method, reranker,
chunking rule, top-k value, or collection schema was introduced or changed.
The pre-existing dirty `package-lock.json` diff was preserved; Phase 14 did not
run an install/update or edit either dependency manifest.

## 46. Remaining risks

- V0--V10 are development data and can no longer estimate release performance.
- Strict joint entity-frame accuracy is 46.88%; unit and order-reference recall
  need independent scrutiny despite perfect canonical-product/quantity gates.
- Multi-intent branch entity accuracy is 55.36% and whole-request completion is
  81.48% under the strict scorer.
- Two V10 gold claim contracts remain inconsistent with their checkout-review
  prompts; they were not changed.
- Classifier strategies B/C lack comparable measurements.
- Qdrant, staging, production telemetry, and storefront behavior were not
  verified in this phase.

## 47. New candidate hash

Established `ChatFixtureAudit::runtimeHash()` result:

`f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf`

This differs from the retired V10 candidate hash.

## 48. Candidate freeze status

**PHASE 14 CANDIDATE FREEZE COMPLETE (LOCAL ONLY).** All P0 objectives and 17
development gates pass, the benchmark decision is documented, and backend QA
passes. Freeze means the technical runtime identity above is recorded; it does
not authorize staging or production.

## 49. V11 readiness

The candidate is ready to be handed to a separate independent V11 author after
this context ends. That author must work from business authorities/specification
without reading Phase 14 failure details or implementation-specific patches,
then run a fresh leakage/gold audit, freeze V11, and permit one scored run. No
V11 dataset was created or executed here.

## 50. Next step

Stop Phase 14. Preserve the local candidate without further tuning against V10.
In a separate independent workflow, author and audit V11, then evaluate this
exact runtime hash once. Do not stage or deploy before that independent result
and a separate release decision.
