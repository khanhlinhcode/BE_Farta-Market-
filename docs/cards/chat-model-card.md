# Farta Market Chat Model Card

## Scope

This assistant supports Farta Market product discovery, current product facts,
catalog listing, cart proposals, customer-owned order lookup, current shipping
fees, and approved store knowledge. It is not a general-purpose agent and has no
authority to mutate orders, payments, admin data, or the cart directly.

## Architecture and models

- Deterministic `ChatIntentRouter` is the primary route. The semantic classifier
  is an optional, closed-schema fallback and is disabled in the validated local
  configuration.
- Capability denial is evaluated before multi-intent and normal intent routing.
  The API exposes `supported`, `clarification`, `unsupported`, or
  `denied_action` as the decision state; authorization remains in deterministic
  application and service code.
- Current product, inventory, shipping, cart, and order facts come from MySQL or
  `SiteSetting`; they do not depend on embeddings.
- Knowledge retrieval uses local sparse ranking plus Qdrant Cloud Inference with
  `intfloat/multilingual-e5-small`, 384-dimensional cosine vectors, and local
  reciprocal-rank fusion with `k=60`.
- Knowledge generation is disabled in the validated local configuration. If it
  is enabled, exact evidence quotes and semantic verification are mandatory.

## Evaluation status

Latest development measurement: 27 September 2026.

| Dataset | Status | Intent | Route | Entity | Multi-intent | Follow-up | Unsupported safety |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Canonical development regression | Tuned regression | 100% | Covered by feature tests | Covered | Covered | Covered | Covered |
| Generalization Development V1 | Reclassified and used for Phase 4 development | 100% | 100% | 100% | 100% | 100% | 100% |
| Generalization Development / Adversarial V2 | Reclassified and used for Phase 5 development | 100% | 100% | 100% exact / 100% canonical | 100% | 100% | 100% |
| Generalization Development / Adversarial V3 | Reclassified and used for Phase 6 development | 100% | 100% | 100% exact / 100% canonical | 100% | 100% | 100% |
| Independent blind holdout V4 | Frozen Phase 6 candidate | 83.13% | 85.00% | 75% exact / 100% canonical | 71.43% | 85.71% | 85.00% |
| V4 development after Phase 7 | Phase 7 regression only | 100% | 100% | 100% exact / 100% canonical | 100% | 100% | 100% |
| Independent holdout V5 | Frozen Phase 7 candidate | 91.67% | 88.89% | 93.75% exact / 100% canonical | 95.00% composition | 100% | 92.31% |
| Final V10 (historical) | Valid frozen Phase 13 result; now development data | 35.27% | 36.30% | Canonical 52.56% | 40.74% completeness | 5.13% | OOD recall 47.83% |
| V10 after Phase 14 | Development regression, not a release estimate | 99.32% | 100% | 100% canonical / 100% quantity | 100% completeness | 100% | OOD P/R 100% / 100% |

V1 contains 100 intent queries, 10 handler probes, six entity cases, and a
bounded follow-up sequence. It was blind in Phase 3 but is development data in
Phase 4. V2 contains 115 intent queries, 12 handler probes, 12 entity cases,
and a bounded follow-up sequence. It was independent evidence for Phase 4 and
became development data in Phase 5. V3 contains 126 new intent queries, 14
handler probes, 12 entity cases, two bounded follow-up sequences, and no exact
V1/V2 utterance duplicates. It became development/adversarial data after its
Phase 5 report. V4 contains 160 new intent queries, 20 handler probes, 16 entity
cases, and 10 follow-up scenarios with zero exact V1/V2/V3 sentence overlap.
V4 was evaluated only after the Phase 6 runtime hash was frozen. All sets are
internally authored, not production traffic or external benchmarks.

V5 contains 180 primary intent queries, 20 follow-up scenarios, 16 entity
probes, and three missing-evidence probes. Its primary intent set is new, but two
short follow-up strings exactly overlap earlier fixtures. The handler harness
also retained one test session across its single-turn probes. These validity
defects are reported as release blockers; V5 must not be rerun and presented as
independent evidence.

| Retrieval dataset | HitRate@5 | MRR@5 | nDCG@5 | Missing-evidence accuracy |
| --- | ---: | ---: | ---: | ---: |
| Development product retrieval | 100% | 96.88% | 97.69% | n/a |
| Knowledge holdout, local sparse | 100% | 100% | 100% | 100% |
| Knowledge holdout, current hybrid pipeline | 100% | 100% | 100% | 100% |

The corpus has only 18 vector points. Perfect retrieval scores must not be
extrapolated to a larger corpus.

## Latency and reliability

A controlled, read-only 20-query Cloud Inference sample completed 20/20 dense
queries successfully. Combined inference-and-search latency was 774 ms minimum,
876 ms p50, 1,515 ms p95, and 2,589 ms maximum. The current REST call does not
expose separate embedding and vector-search timings, so they must not be reported
as independently measured.

The 10-case hybrid knowledge holdout measured 805 ms p50 and 1,031 ms p95 total
retrieval latency. Structured commerce routes bypass Qdrant. Production traffic
frequency and customer-perceived p95 are not yet measured.

## Knowledge and safety boundaries

- No approved return/refund document exists. Those questions must return
  `NO_EVIDENCE` rather than borrow payment, checkout, or shipping content.
- There is no complete approved shipping-policy document. Only current shipping
  fee and free-shipping threshold are authoritative.
- Checkout guidance is partial and lives under the approved ordering guide.
- Chat context expires after 300 seconds and stores only product ID, quantity,
  stage, owner ID, and expiry.
- Logs exclude prompts, history, cart content, tokens, credentials, addresses,
  and payment details.
- Cart proposals reload the product and verify active state, stock, quantity,
  customer role, authentication, and email verification.

## Known limitations and release status

Phase 14 replaces coarse route semantics with an immutable typed frame carrying
semantic intent, resource, operation, entities, branch frames, and an immutable
evidence domain. Product mentions remain untrusted until active-product DB
canonicalization. Capability detection uses resource plus operation and does not
authorize handlers. Knowledge retrieval is constrained by registered sources,
then rechecked for eligibility and claim support. Bounded context resolves
references only; it never supplies stale business truth or chooses a new intent.

The Phase 14 V10 development run passed all 17 development gates: wrong-topic
authority, unsupported-policy hallucination, unsafe execution, and wrong-entity
unsafe action are zero; missing-evidence and cross-account safety are 100%.
Canonical product, quantity, and follow-up accuracy are 100%. Strict joint
entity-frame accuracy remains 46.88%, primarily because unit and order-reference
slots are incomplete; multi-intent whole-request completion remains 81.48% even
though branch completeness is 100%. These remain development risks.

Release decision: **LOCAL PHASE 14 CANDIDATE FROZEN; NOT APPROVED FOR
STAGING**. V10 is no longer independent evidence. The candidate requires a new
independently authored and audited V11 before any staging claim. No V11 was
created or run in Phase 14. The selected router remains deterministic; no
classifier dependency was available or added.
