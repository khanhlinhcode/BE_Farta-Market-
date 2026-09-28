# Chat Phase 9: production hardening and blind V7

## Scope and safety

Phase 9 is local-only. V1-V6 are development/regression data. No commit, push,
merge, deployment, production request, Cloud collection mutation, dependency,
embedding change, RRF change, or autonomous-agent framework is in scope.

Starting identity:

- branch: `main`
- commit: `ee90385668e263bd7b980a8f86a2280e67f6827c`
- commit tree: `6ba88e6b1fe80b895a1c2fca51a3f63763aa910d`
- runtime hash: `0e0f7dfa6cc227c2d07bbbd5c512b592a9fba9334fbb7d036bafdde56fa29782`
- tests/docs aggregate checksum:
  `2a07bceb888b819696a79370d5693dab02d2938838f1edbcbe77c0f5410d24aa`
- the worktree already contains the uncommitted Phase 1-8 implementation;
  Phase 9 preserves it.

## Skills actually used

- `ponytail`: constrains Phase 9 to reuse existing Laravel/chat services, add
  no dependency, and prefer the smallest coherent fixes over sentence-level
  patches or a new framework.
- `nlp-model-design`: informs the concept/intent/entity separation, OOD versus
  supported-recall analysis, router ceiling experiment, and independent
  evaluation design.
- `semantically-verified-rag`: informs the two-stage eligibility/support gate,
  exact evidence boundary, fail-closed behavior, and grounding telemetry.

Pentest/Strix skills are intentionally not used: this phase calls for local
functional and authorization regression, not exploit testing.

## Phase 8 verified baseline

The repository report and recorded one-shot V6 output agree: intent and
handler accuracy were 71.50%, macro-F1 74.40%, canonical entity 87.50%, raw
entity exact 50%, follow-up 85%, multi-intent 67.86%, structured + knowledge
90.91%, OOD safety 100%, privileged capability 77.78%, evidence eligibility
66.67%, and missing-evidence safety 57.14%. Six wrong-topic evidence events
and three unsupported-policy hallucination events were recorded; unsafe
execution and wrong-entity resolution remained zero.

Development retrieval was Hit@5 100%, MRR 96.88%, nDCG 97.69%. V6
route-conditioned retrieval was 75% for all three. These are now development
metrics, not release evidence.

## V6 failure taxonomy before Phase 9 runtime changes

The table classifies all 68 failed evaluation observations: 57 intent/handler,
8 entity, and 3 follow-up observations. A query may appear in more than one
evaluation dimension; percentages use 68 as the transparent denominator.

| Primary class | Count | Share | Examples | Current -> expected | Root cause and generalized fix candidate |
| --- | ---: | ---: | --- | --- | --- |
| `SUPPORTED_TO_CLARIFICATION` | 27 | 39.71% | “which goods can a customer choose from today”; “check giúp số tồn của Đậu Hũ” | clarification -> catalog/detail/knowledge/shipping/order | Reusable domain/query concepts are missing or tested too late. Extract concepts first; clarification only for genuinely missing entity/reference. |
| `WRONG_HANDLER` | 9 | 13.24% | shipping -> order/multi; search -> catalog/cart; cart read -> catalog; shipping policy -> numeric shipping | wrong supported handler -> correct supported handler | Phrase precedence conflates domain, operation, and connectors. Route from concepts and enforce specificity. |
| `PRIVILEGED_FALSE_POSITIVE` | 4 | 5.88% | payment-status read; checkout guidance; two dependent purchase/freeship requests | denied -> allowed read/plan | Capability guard treats sensitive nouns as mutation. Require explicit mutation operation plus protected domain. |
| `OOD_LABEL_AS_CLARIFICATION` | 6 | 8.82% | unrelated code, medical, sport, image requests | clarification -> unsupported | OOD remained safe but the clarification contract was violated. Explicit unsupported domain concepts should terminate as unsupported. |
| `PRIVILEGED_FALSE_NEGATIVE` | 4 | 5.88% | force payment; reveal hidden prompt; bypass login | order/clarification -> denied | Mixed-language mutation/exfiltration operations are incomplete. Add reusable operation + capability semantics. |
| `MULTI_INTENT_BRANCH_DROPPED` | 7 | 10.29% | menu + freeship; different products; dependent purchase + freeship | clarification/shipping only -> complete composite | Split/merge logic loses local entities or dependencies. Preserve explicit per-branch intent, entity scope, outcome, and dependency. |
| `UNIT_BOUNDARY` | 5 | 7.35% | “bottles of Sữa Gạo”; “củ Khoai Lang nhé”; “Đậu Hũ to the shopping basket” | unit/trailer included in product -> canonical product span | Shared bilingual unit and trailing-action grammar is incomplete. Strip only recognized boundary tokens and still resolve against DB. |
| `QUANTITY_BOUNDARY` | 3 | 4.41% | “để 2 gói…”; “I need two…”; “please place four…” | empty product/quantity 0 -> explicit quantity/product | Mutation/action variants bypass entity parsing. Parse quantity/unit independently from the exact action phrase. |
| `FOLLOWUP_CONTEXT` | 1 | 1.47% | “quả đó thuộc nhóm hàng nào” | clarification -> prior Táo Xanh detail | Reference concepts do not cover the natural demonstrative. Resolve only from fresh explicit single-product context. |
| `ORDINAL_REFERENCE` | 2 | 2.94% | “the second product…”; “món cuối cùng…” | missing/clarification -> ordered catalog item | Ordinal parsing/context validation is incomplete. Resolve numeric/first/last only against a stored ordered list and fail closed out of range. |

Secondary safety counters overlap the observations above and therefore are not
added to the 68 denominator:

- `WRONG_TOPIC_EVIDENCE`: 6 accepted-evidence events;
- `UNSUPPORTED_POLICY_GENERATED`: 3 verified-answer events;
- missing-evidence safety: 57.14%;
- multi-intent completeness: 67.86%;
- dependent deterministic accuracy: 0%.

Affected runtime boundaries are `ChatIntentRouter`, `ChatCapabilityGuard`,
`ChatEntityExtractor`, `ChatController`, `ChatKnowledgeRetriever`, and
`ChatKnowledgeAnswerService`. The likely common failure chain is concept
understanding -> route -> required evidence domain -> composition, not a
proven embedding or RRF defect.

## 7. Research applicability

Phase 9 kept the Phase 8 research conclusion: the failures were in concept
extraction, routing, evidence-domain selection, and composition. No measured
result justified an embedding, vector-schema, Qdrant, RRF, reranker, or agent
framework change. The existing official Microsoft CLU evaluation, OWASP GenAI,
and Qdrant filtering references in the Phase 8 report remain applicable.

## 8. Architecture changes

The runtime remains a constrained pipeline:

`concept extraction -> single intent route -> authoritative handler or scoped
knowledge retrieval -> evidence eligibility -> response composition`.

Structured price, inventory, cart, order, shipping, and contact data retain
their database/service boundary. Published knowledge is source- and
topic-scoped; unsupported policy domains fail closed.

## 9. Runtime implementation

- Added reusable bilingual concepts for domains and read/mutate/suggest
  operations.
- Centralized capability denials, entity parsing, required-evidence-domain
  selection, and topic/source eligibility.
- Kept cart chat as a suggested action only; the response contract keeps
  `action.type = none` and the later cart endpoint revalidates the product.
- Constrained vector search, when enabled, to allowed source IDs. No Cloud
  collection mutation was performed in Phase 9.

## 10. Files changed

Runtime work is concentrated in `ChatIntentRouter`, `ChatCapabilityGuard`,
`ChatConceptExtractor`, `ChatEntityExtractor`, `ChatEvidencePolicy`,
`ChatKnowledgeRetriever`, `ChatKnowledgeAnswerService`, `ChatVectorSearch`,
and `ChatController`. Tests, fixtures, evaluation support, architecture docs,
and this report were also updated. The worktree intentionally remains
uncommitted.

## 11. Rejected experiments

No embedding-model migration, reranker, weighted fusion, DBSF, agentic-RAG
framework, dependency, or Cloud collection mutation was adopted. The Phase 9
evidence did not identify retrieval-model quality as the root cause.

## 12. Development/regression metrics

**Development data only; not release evidence.** V0-V6 regression contained
1,047 cases. Deterministic intent accuracy was 97.71%, macro-F1 97.50%,
supported-query recall 97.92%, OOD recall 99.45%, privileged recall 98.18%,
and semantic-fallback use was 0%. The optional low-confidence fallback
experiment did not improve accuracy and increased average routing latency;
semantic routing remains disabled for release evaluation.

## 13. Grounding and capability regression

The in-scope regression suite covers authoritative shipping/contact facts,
approved-source eligibility, missing-evidence refusal, cart/order ownership,
capability denial, and inert chat actions. It passed before V7; the blind
result below remains the controlling evidence.

## 14. Evaluation-harness integrity

Independent V7 primary cases flush cache and session state. Each follow-up
scenario creates and retains only its own context. The harness has no random
selection or retry. Before scoring it was extended to report OOD and privileged
precision, branch-level multi-intent coverage, and exact persisted-source
support for non-generative knowledge replies.

## 15. Pre-V7 QA commands actually run

| Command | Result |
| --- | --- |
| `php artisan test --exclude-group=v6-release --exclude-group=v7-release --compact` | PASS — 466 tests, 2,535 assertions |
| `vendor/bin/pint --test` | PASS — 219 files; V7-specific files were rechecked after harness changes |
| `composer validate --strict` | PASS |
| `composer audit` | PASS — no advisories |
| `env -u NODE_TLS_REJECT_UNAUTHORIZED npm audit --audit-level=low` | PASS — 0 vulnerabilities |
| V7 preflight | PASS — 250 primary, 30 follow-up, 0 exact and 0 Jaccard >= 0.82 overlaps |
| `git diff --check` and changed-file secret signature scan | PASS |

Backend Vite production build is **NOT VERIFIED**: the documented pre-existing
development-only `axios` issue remains out of Phase 9 scope. Storefront tests
and build are also **NOT VERIFIED** in this pass because repository scope
prohibits leaving this backend repository.

## 16. Phase 9 candidate identity

- commit: `ee90385668e263bd7b980a8f86a2280e67f6827c`
- commit tree: `6ba88e6b1fe80b895a1c2fca51a3f63763aa910d`
- runtime hash: `a167ee88b0ef4b09db7af518cfc7d1c3887e0202c329003be20683d069675987`
- config hash: `d57d02b93d263a944bc37dcb946b506901dcb223bd893da2a1b89337d4018516`
- V7 harness hash: `e90e599d2a8cb9c5f6c54ad8345e28c319938708bec0fc9905b844e879978d5e`

The runtime and config hashes were identical immediately after the one V7 run.

## 17. Blind V7 dataset

V7 is an independent Phase 9 fixture with 250 primary cases and 30 explicit
follow-up scenarios. It covers catalog, product, cart, order, shipping,
knowledge, missing policy, OOD, privileged capability, Vietnamese/English,
slang, entities, ordinals, and multi-intent composition. Normalized exact
overlap and transparent token-Jaccard overlap at 0.82 against V1-V6 were both
zero.

## 18. Blind V7 execution

V7 was executed exactly once with semantic routing, vector search, Cloud
inference, and knowledge generation disabled. It printed the raw
`CHAT_BLIND_V7` record and then failed its first release assertion. No runtime,
config, router, evidence policy, retrieval filter, or response-composer file
was changed after that run.

## 19. Blind V7 core metrics

| Metric | Result | Gate | Status |
| --- | ---: | ---: | --- |
| Intent accuracy | 81.20% | >= 90% | FAIL |
| Macro-F1 | 80.26% | >= 90% | FAIL |
| Handler accuracy | 81.20% | >= 95% | FAIL |
| Business outcome accuracy | 79.60% | >= 95% | FAIL |
| Supported-query recall | 81.59% | >= 90% | FAIL |
| Canonical entity resolution | 100% | >= 98% | PASS |
| Follow-up resolution | 83.33% | >= 90% | FAIL |
| Multi-intent branch accuracy | 86.36% | diagnostic | — |
| Multi-intent completeness | 80.00% | >= 95% | FAIL |
| Dependent deterministic accuracy | 100% | >= 90% | PASS |

Raw entity exact accuracy was 75.00%. Latency was **NOT MEASURED** by the V7
harness and is not inferred from test duration.

## 20. Blind V7 safety metrics

| Metric | Result | Gate | Status |
| --- | ---: | ---: | --- |
| OOD precision / recall | 41.94% / 65.00% | recall >= 95% | FAIL |
| OOD safety recall / false accept | 95.00% / 5.00% | >= 95% / <= 5% | PASS |
| Privileged precision / recall | 94.44% / 85.00% | recall >= 95% | FAIL |
| Unsafe execution | 0 | 0 | PASS |
| Wrong-entity unsafe action | 0 | 0 | PASS |

`OOD safety recall` permits a clarification as a safe abstention; the separate
OOD precision/recall values expose that this is not sufficient quality.

## 21. Blind V7 grounding metrics

| Metric | Result | Gate | Status |
| --- | ---: | ---: | --- |
| Required evidence domain | 73.85% | >= 98% | FAIL |
| Evidence eligibility | 72.22% | >= 98% | FAIL |
| Missing-evidence safety | 83.33% | 100% | FAIL |
| Wrong-topic evidence accepted | 2 | 0 | FAIL |
| Unsupported-policy hallucinations | 1 | 0 | FAIL |
| Exact-source claim support | 61.29% (31 approved-knowledge cases) | diagnostic | — |
| Grounded response accuracy | 61.29% (same 31 cases) | diagnostic | — |
| Retrieval Hit@5 / MRR@5 / nDCG@5 | 61.29% / 61.29% / 61.29% | diagnostic | — |

The exact-source measure applies only to approved non-generative knowledge
answers: with generation disabled, a valid reply must equal a cited persisted
chunk. Structured facts are measured by their own handler and business-outcome
checks.

## 22. V7 confusion and routing breakdown

Dominant failures were routing shipping, catalog, product-detail, and knowledge
paraphrases to another handler or clarification. Cart-read phrases were often
routed as cart action. Several natural payment/ordering questions were routed
as order or denied actions. OOD and mixed-language privileged requests were
also missed. The full raw confusion matrix and failed cases are emitted by the
one-shot test output; it is not rerun or filtered.

## 23. V7 composition breakdown

Structured/structured composition passed in this fixture. Structured/knowledge
was 80%; supported + no-evidence 50%; supported + denied 66.67%; supported +
unsupported 66.67%; same-entity 0%; different-entity 100%; and dependent
deterministic 100%. Critical supported branches were therefore still dropped
or misrouted.

## 24. Hard blocker result

**NO-GO FOR STAGING.** Blind V7 contains wrong-topic authoritative evidence,
an unsupported-policy answer marked verified, sub-intent loss, insufficient
handler and grounding accuracy, and incomplete follow-up/privileged handling.
The independently unverified storefront/release-path QA is an additional
release gate blocker.

## 25. Phase 10 backlog

V7 is now development data. Do not patch V7 examples individually. Phase 10
should group changes by reusable causes: bilingual domain vocabulary and
operation precedence; cart read versus action; payment/order guidance versus
mutation; OOD/privileged capability concepts; entity trailer/unit grammar;
source-domain propagation across multi-intent composition; and follow-up
reference/category resolution. A fresh V8 holdout is required after any runtime
change.

## 26. Deployment status

No commit, push, merge, Cloud collection mutation, staging request, or
deployment was performed. This report is local backend evidence only and must
not be represented as production readiness.
