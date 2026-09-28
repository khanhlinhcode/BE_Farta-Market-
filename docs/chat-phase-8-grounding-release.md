# Chat Phase 8: grounding and release evaluation

## Scope and safety

Phase 8 is a local-only stabilization pass. V5 is development data and must
not be presented as an independent holdout again. No commit, push, merge,
deployment, production request, model migration, vector-schema change, new
dependency, or state-changing chatbot action is in scope.

Pre-change candidate evidence:

- branch: `main`
- starting commit: `ee90385668e263bd7b980a8f86a2280e67f6827c`
- starting commit tree: `6ba88e6b1fe80b895a1c2fca51a3f63763aa910d`
- runtime/config/routes/resources/database content checksum:
  `17c674b36c86ecbdb6356f9ff97982d25463c5414f55e348e91ac5a99caafce7`
- tests/docs content checksum:
  `dfdb815f156778bb820777f6d4b6c0a0941a04ca3ce51bd0f904e3bb8ea394d4`
- the worktree already contained the documented Phase 1-7 changes; Phase 8
  preserves them and does not overwrite unrelated work.

## Phase 7 baseline

The following is **development data** after inspection of V5:

| Measure | V5 result | Phase 8 interpretation |
| --- | ---: | --- |
| Intent accuracy | 91.67% | Passed the old intent gate; not release evidence |
| Macro-F1 | 92.08% | Passed the old intent gate; not release evidence |
| Handler accuracy | 88.89% | Failed |
| Multi-intent composition | 95.00% | Passed overall, mixed structured/knowledge cases remained weak |
| Follow-up resolution | 100% reported | Invalid for release until session isolation and overlap are fixed |
| Canonical entity resolution | 100% | Passed |
| OOD safety | 92.31% | Failed |
| Privileged-capability detection | 94.44% | Failed |
| Missing-evidence safety | 33.33% | Critical failure |
| Wrong-entity resolution | 0% | Passed |
| Unsafe execution | 0 | Passed |
| Semantic fallback use | 0% | Deterministic path handled the evaluated traffic |

Retrieval ranking remained healthy: HitRate@5 100%, MRR@5 96.88%, and
nDCG@5 97.69%. These metrics do not establish that a retrieved chunk is
authorized to support the requested business claim.

## V5 failure taxonomy (recorded before Phase 8 runtime changes)

| Class | Observed symptom | Root-cause hypothesis | Correct boundary |
| --- | --- | --- | --- |
| `EVIDENCE_INELIGIBLE` | A return/refund question was answered from the generic policy index | Retrieval relevance was treated as authority for a more specific claim | Missing approved return/refund source must produce `NO_EVIDENCE` |
| `TOPIC_UNDER_SPECIFIED` | Damaged/opened-goods questions could route to broad policy or be split incorrectly | Return/refund concepts were not represented strongly enough in deterministic topic routing | Route the claim to `returns`; constrain evidence to that topic and registry |
| `HANDLER_PRODUCT_DETAIL_AS_CATALOG` | Stock-count/detail wording selected catalog listing | Catalog detection ran before the more specific product-detail decision | Most specific deterministic intent wins |
| `HANDLER_CART_READ_AS_ACTION` | A request to open/view a cart selected cart mutation | A broad action cue such as “cho” outweighed read semantics | Read intent wins unless an explicit mutation verb is present |
| `HANDLER_ORDER_AS_CATALOG` | Natural order-history wording selected catalog | Order concept coverage omitted common Vietnamese variants | Central router recognizes order concepts before product/catalog fallbacks |
| `HANDLER_KNOWLEDGE_AS_CLARIFICATION` | Natural COD/capability queries were not resolved | Generalized concept groups were incomplete | Add reusable concepts, not single full-sentence exceptions |
| `HANDLER_MULTI_DROPPED_OR_WRONG` | A structured + knowledge request lost or misclassified a branch | Incidental conjunctions and whole-message decisions were confused with independent sub-intents | Resolve and report every meaningful branch; unsupported evidence stays explicit |
| `OOD_FALSE_ACCEPT` | Medical/unrelated requests reached product/cart handlers | Product/search cues were too broad and OOD concepts too narrow | OOD guard precedes business fallbacks and fails safely |
| `PRIVILEGED_FALSE_NEGATIVE` | Inventory-override paraphrase was not denied | Guard matched phrases rather than capability + prohibited operation | Detect privileged capability semantics; backend remains final authority |
| `HARNESS_SESSION_CONTAMINATION` | Independent handler cases could inherit previous context | Shared cache/session was not reset per independent case | Flush conversation state before every independent case; retain it only inside an explicit follow-up scenario |
| `HOLDOUT_OVERLAP` | Two short V5 follow-ups matched older data | Exact/near-duplicate audit was incomplete | Audit V6 against all development fixtures before first and only scored run |

## Knowledge source registry and authority model

Authority is claim-specific. A source approved for one topic cannot authorize a
different topic merely because it is semantically similar.

| Level | Source | Allowed claims | Release behavior |
| --- | --- | --- | --- |
| L1 | `SiteSetting`, database records, and existing business services | Current shipping fee/threshold, contact information, product price/stock, cart/order data | Highest precedence; handler must re-read current server state |
| L2 | Published, Farta-owned knowledge documents explicitly registered for the requested topic | Explanatory account, order, payment, ordering, and high-level policy-index content | May support only registered topic/claim scope; citation content comes from the approved section |
| L3 | Draft/example/unregistered/wrong-topic documents, generic index used for a detailed claim, or model memory | No authoritative business-policy claim | Never eligible evidence; return `NO_EVIDENCE` when no L1/L2 source exists |

Current registry to enforce:

| Required topic | Eligible source IDs |
| --- | --- |
| `account` | `account-guide-vi` |
| `orders` | `order-guide-vi` |
| `payment` | `payment-guide-vi` |
| `ordering` | `shopping-guide-vi` |
| `policy` | `policy-index-vi`, only for the overview actually stated there |
| `shipping` | dynamic `site-settings` values only |
| `shipping_policy` | none; current numeric settings do not establish a broader policy |
| `contact` | dynamic `site-settings` values only |
| `returns` | none; must return `NO_EVIDENCE` |
| `storage` | none; no approved storage document is present |

Existing knowledge JSON has `status`, `owner`, `topic`, and `source_id`, but no
separate authority field. A small application-level allow-list is preferable
to a schema/database migration for this fixed, small corpus. It makes the
security boundary explicit and fail-closed while leaving embedding, dense
search, sparse search, RRF, top-k, and Qdrant collection structure unchanged.

## Evidence eligibility rules

1. Determine the requested claim topic before retrieval.
2. Structured L1 handlers answer current business state directly.
3. Knowledge retrieval may rank only candidates in the requested topic.
4. A ranked chunk is usable only when its source is in the topic registry,
   remains published, and is owned by Farta Market.
5. `policy-index-vi` can enumerate verified policy groups but cannot prove a
   detailed return/refund rule.
6. If no eligible source supports the claim, return `NO_EVIDENCE` with no
   fabricated citation or model-memory completion.
7. In multi-intent answers, supported branches remain available and every
   unsupported branch carries its explicit limitation.
8. Retrieval scores measure relevance, not authority. Grounding safety is
   measured separately.

## Evaluation-harness audit

- Independent primary, handler, entity, missing-evidence, OOD, and privileged
  cases must clear cached conversation context before every request.
- An intentional follow-up scenario starts with a fresh cache/session and then
  preserves context only between its own turns.
- Exact duplicate detection uses normalized Unicode/case/punctuation text.
- Near-duplicate detection must be deterministic and reported transparently;
  it is a contamination review tool, not a semantic model.
- V6 must be compared against all prior fixtures before its sole scored run.
- Any session-contamination regression is a hard blocker.

## Model and retrieval freeze

Phase 8 keeps the existing embedding model, vector dimensions, dense/sparse
retrieval, RRF, Cloud Inference, chunking, top-k, and reranker configuration.
No V5 finding demonstrates that changing these components would repair
evidence authorization or handler selection.

## Research applicability

- Microsoft Conversational Language Understanding evaluation guidance treats
  out-of-domain examples as a `None` intent and recommends held-out evaluation
  with precision, recall, F1, and confusion analysis. This supports explicit
  OOD test cases and separate intent metrics:
  <https://learn.microsoft.com/en-us/azure/ai-services/language-service/conversational-language-understanding/concepts/evaluation-metrics>
- OWASP Excessive Agency recommends minimizing functionality, permissions, and
  autonomy and enforcing authorization in downstream systems. This supports
  suggested actions plus server-side validation rather than direct chat
  mutation:
  <https://genai.owasp.org/llmrisk/llm062025-excessive-agency/>
- OWASP Prompt Injection states that RAG does not eliminate prompt injection
  and recommends deterministic validation and least privilege. Retrieved text
  therefore cannot grant policy or action authority:
  <https://genai.owasp.org/llmrisk/llm01-prompt-injection/>
- Qdrant supports hybrid fusion and payload filtering. Those facilities remain
  useful ranking constraints, but this small corpus can enforce claim-specific
  authority at the application boundary without changing the frozen retrieval
  stack:
  <https://qdrant.tech/documentation/search/hybrid-queries/>
  <https://qdrant.tech/documentation/search/filtering/>

## Backend Vite classification

`resources/js/app.js` imports `bootstrap.js`, which imports `axios`, while the
backend `package.json` does not declare `axios`. The backend CI workflow and
Northflank API launch path do not run the Vite build; the Blade welcome page
also has a no-manifest fallback. This is classified as a development-only,
non-chat build issue, not a Phase 8 release-path blocker. Adding a dependency
or changing that surface would not improve chatbot quality, so Phase 8 records
but does not mix it into chat metrics.

## Semantic fallback audit

The semantic router remains an optional, constrained fallback. It is called
only after deterministic routing has no decision, is disabled in `.env.example`
and `phpunit.xml`, validates a strict enum/schema and confidence threshold, and
has dedicated tests for malformed, low-confidence, and out-of-domain output.
V5 used it 0% because all evaluated cases resolved deterministically; that does
not make the explicitly configured fallback dead code. Phase 8 therefore keeps
it disabled for release evaluation and does not delete it speculatively.

## Development results before candidate freeze

V5 is **DEVELOPMENT DATA**. After the generalized fixes and complete
session/cache isolation it reported:

| Measure | Before | After |
| --- | ---: | ---: |
| Intent accuracy | 91.67% | 100% |
| Macro-F1 | 92.08% | 100% |
| Handler accuracy | 88.89% | 100% |
| Multi-intent complete handling | 95.00% | 100% |
| Follow-up resolution | 100% reported on contaminated harness | 100% on isolated scenarios |
| Raw/canonical entity | 93.75% / 100% | 100% / 100% |
| OOD safety | 92.31% | 100% |
| Privileged-capability detection | 94.44% | 100% |
| Missing-evidence safety | 33.33% | 100% |
| Wrong entity / unsafe execution | 0 / 0 | 0 / 0 |

The existing retrieval evaluation remained stable at HitRate@5 100%, MRR@5
96.88%, and nDCG@5 97.69%. Grounding tests separately reject unregistered,
wrong-topic, and missing-policy evidence.

## Pre-V6 QA and implementation freeze

- backend: 456 tests, 2,501 assertions — PASS (V6 scoring test excluded)
- storefront: 26 files / 103 tests — PASS
- storefront production build — PASS
- Laravel Pint: 212 files — PASS
- Composer validate strict and audit — PASS
- backend and storefront npm audit with TLS verification enabled — 0 findings
- changed-file secret signature scan — no token/key/private-key match
- `git diff --check` — PASS
- V6 static preflight: 200 primary cases, 20 follow-up scenarios, zero exact
  overlaps and zero token-Jaccard overlaps at 0.82 against V1-V5

The backend Vite build was not added to this release gate for the documented
development-only reason above.

Phase 8 candidate runtime hash:

`0e0f7dfa6cc227c2d07bbbd5c512b592a9fba9334fbb7d036bafdde56fa29782`

The hash covers `app`, `config`, `routes`, `resources/chat`, and database
migrations with sorted relative paths and file contents. Runtime is frozen from
this point until the one V6 evaluation completes.

## Implementation and release results

### 1. Phase 7 baseline

Phase 7 ended **NO-GO FOR STAGING**. V5 had acceptable intent metrics but
failed handler selection, OOD, privileged-capability, and especially
missing-evidence safety. The exact baseline is recorded above.

### 2. V5 failure taxonomy

**DEVELOPMENT DATA.** The pre-change taxonomy identified evidence
ineligibility, under-specified topics, wrong handler precedence, dropped
multi-intent branches, OOD false accepts, missed privileged capabilities,
session leakage, and holdout overlap. V5 is not reused as blind evidence.

### 3. Evidence and grounding root causes

The main development-set defect was treating semantic relevance as authority.
The fix separated retrieval ranking from claim eligibility and made missing
approved policy fail closed. Blind V6 exposed a remaining upstream problem:
wrong routing/topic extraction can still bypass the intended evidence domain.

### 4. Handler root causes

Deterministic vocabulary and precedence remain too brittle for unseen natural
phrasing. V6 produced excessive clarification, false whole-message denial,
duplicate sub-intents, and catalog/cart/order confusion. These are routing and
composition failures, not evidence that the embedding model should change.

### 5. OOD root causes

Development fixes generalized medical, legal, finance, programming, image,
and sports boundaries. V6 OOD safety reached 100%; unsupported requests that
abstained as clarification were still safe under the predefined OOD metric.

### 6. Privileged-capability root causes

The capability guard still misses English/mixed-language auth bypass, prompt
exfiltration, and payment-override paraphrases, while one legitimate payment
status query was denied. V6 privileged-capability accuracy was 77.78%.

### 7. Evaluation-harness root causes

The old harness reused conversation state and had overlap with development
data. Independent cases now flush cache and session state; only explicit
follow-up scenarios retain context. The V6 preflight found no exact or near
duplicate against V1-V5.

### 8. Changes implemented

- Added an explicit approved-source registry and application-level evidence
  eligibility filter.
- Made answer generation fail closed unless a chunk is explicitly eligible.
- Split `shipping`, `shipping_policy`, `returns`, and `storage` authority.
- Centralized and expanded deterministic intent/entity/capability handling.
- Added explicit general-chat and multi-branch response handling.
- Added isolated development, grounding, safety, follow-up, and blind-holdout
  evaluation coverage.

### 9. Files changed

Phase 8 runtime changes are concentrated in:

- `app/Services/Chat/ChatIntentRouter.php`
- `app/Services/Chat/ChatCapabilityGuard.php`
- `app/Services/Chat/ChatEntityExtractor.php`
- `app/Services/Chat/ChatKnowledgeRetriever.php`
- `app/Services/Chat/ChatKnowledgeAnswerService.php`
- `app/Http/Controllers/ChatController.php`

Evaluation and documentation changes include the chat feature/unit tests,
`tests/Support/ChatFixtureAudit.php`, `tests/Fixtures/chat_blind_v6.php`, the
V6 preflight/scoring tests, and this report. The worktree also contains earlier
Phase 1-7 changes and remains intentionally uncommitted.

### 10. Code removed

Duplicate greeting/capability decisions were removed from the catalog path in
favor of the routed general-chat handler. No retrieval fallback, embedding
integration, or compatibility path was removed speculatively.

### 11. Knowledge source registry and authority model

The registry and L1/L2/L3 authority rules are recorded above. Current numeric
shipping/contact/product/order data comes from structured sources. Approved
knowledge is source- and topic-scoped. Returns, broader shipping policy, and
storage currently have no approved source.

### 12. Evidence eligibility rules

Eligibility requires the requested topic, a registered source ID, published
status, Farta Market ownership, and actual support for the claim. A high vector
score alone is insufficient. No eligible source means `NO_EVIDENCE`.

### 13. V5 development before/after

**DEVELOPMENT DATA.** After the generalized fixes, V5 reached 100% on its
reported intent, macro-F1, handler, multi-intent, follow-up, entity, OOD,
privileged-capability, and missing-evidence measures. This result was used only
to freeze a candidate; it is not independent release evidence.

### 14. Handler results

Pre-freeze development regression passed. Blind V6 handler accuracy was
71.50% (143/200), below the 95% release gate. The dominant wrong destination
was clarification, followed by several whole-message denials and duplicate or
missed multi-intent routes.

### 15. Grounding results

Development tests reject unregistered, wrong-topic, and missing-policy
evidence. Blind V6 evidence eligibility was 66.67%; missing-evidence safety was
57.14%; six wrong-topic citations were accepted and three unsupported policy
answers were marked verified. These are hard blockers.

### 16. OOD results

Blind V6 OOD safety was 100% and false-accept rate was 0%. This satisfies the
OOD gate but does not compensate for failures in other release gates.

### 17. Privileged-action results

Blind V6 privileged-capability accuracy was 77.78%, below the 95% gate.
Unsafe execution count remained 0, so no evaluated request mutated state or
returned a state-changing action.

### 18. Multi-intent and composition results

Blind V6 complete multi-intent handling was 67.86%. Structured + knowledge
composition was 90.91%. Type-level accuracy was:

| Composition type | Accuracy |
| --- | ---: |
| Structured + structured | 57.14% |
| Structured + knowledge | 85.71% |
| Same entity | 100% |
| Different entities | 50% |
| Supported + no evidence | 100% |
| Supported + denied | 100% |
| Supported + unsupported | 50% |
| Dependent deterministic | 0% |

### 19. Session-isolation results

Independent cases were isolated and no cross-case leakage was observed.
Blind V6 follow-up resolution was 85% (17/20), below the 90% gate, due to
three real context/ordinal failures rather than leaked state. Wrong-entity
resolution count remained 0.

### 20. Duplicate-audit results

The V6 preflight compared 200 primary cases and 20 follow-up scenarios with
all V1-V5 fixtures. Exact overlap: 0. Token-Jaccard overlap at 0.82: 0.

### 21. Backend Vite status

The missing backend `axios` dependency is a pre-existing development-only
issue for the current deployment path. Backend CI and the Northflank API path
do not build Vite, and Blade has a no-manifest fallback. No dependency was
added because it would not affect chatbot release quality.

### 22. Full QA results

Before the candidate freeze:

- Backend excluding the one-shot V6 scoring test: 456 tests / 2,501 assertions
  — PASS.
- Storefront: 26 files / 103 tests — PASS.
- Storefront production build — PASS.
- Pint: 212 files — PASS.
- Composer strict validation and audit — PASS.
- Backend/storefront npm audit with TLS verification — 0 findings.
- Changed-file secret signature scan and `git diff --check` — PASS.

The V6 release test itself failed its release assertions, as intended when a
hard metric gate is missed.

### 23. RAG regression results

The established development retrieval evaluation remained Hit@5 100%, MRR@5
96.88%, and nDCG@5 97.69%. Blind V6 route-conditioned retrieval was 75% for
Hit@5, MRR@5, and nDCG@5. The drop aligns with incorrect topic routing; Phase 8
does not change the frozen embedding, dense/sparse, RRF, or reranker stack.

### 24. Phase 8 candidate hash

`0e0f7dfa6cc227c2d07bbbd5c512b592a9fba9334fbb7d036bafdde56fa29782`

The runtime hash matched the fixture at execution. No runtime file was changed
after V6 began.

### 25. Blind V6 results

**BLIND V6 DATA.** V6 was executed exactly once against the frozen candidate.

| Measure | Result | Gate | Status |
| --- | ---: | ---: | --- |
| Intent accuracy | 71.50% | >= 90% | FAIL |
| Macro-F1 | 74.40% | >= 90% | FAIL |
| Handler accuracy | 71.50% | >= 95% | FAIL |
| Canonical entity resolution | 87.50% | >= 98% | FAIL |
| Raw entity exact accuracy | 50.00% | diagnostic | — |
| Follow-up resolution | 85.00% | >= 90% | FAIL |
| Multi-intent complete handling | 67.86% | >= 95% | FAIL |
| Structured + knowledge composition | 90.91% | >= 95% | FAIL |
| OOD safety | 100% | >= 95% | PASS |
| Privileged capability | 77.78% | >= 95% | FAIL |
| Evidence eligibility | 66.67% | >= 98% | FAIL |
| Missing-evidence safety | 57.14% | 100% | FAIL |
| Wrong-topic evidence accepted | 6 | 0 | FAIL |
| Unsupported-policy hallucinations | 3 | 0 | FAIL |
| Unsafe execution | 0 | 0 | PASS |
| Wrong-entity resolution | 0 | 0 | PASS |

Semantic routing was disabled and semantic fallback usage was 0%.

### 26. V6 confusion matrix

The largest confusion pattern was valid business queries routed to
`clarification`: catalog 7/12, product detail 6/16, and multiple knowledge,
order, shipping, unsupported, and multi-intent cases. Other material errors
were shipping -> order/multi, product search -> catalog/cart/multi, cart action
-> cart read, order -> denial, and multi-intent -> shipping/denial. Per-intent
F1 ranged from 32.79% for clarification precision to 93.33% for general chat;
cart action was 90.32%.

### 27. V6 grounding breakdown

- Approved evidence or correct refusal: 66.67% overall.
- Missing approved evidence correctly refused: 57.14%.
- Wrong-topic evidence accepted: 6.
- Unsupported policy marked verified: 3.
- Route-conditioned retrieval Hit@5/MRR@5/nDCG@5: 75%/75%/75%.

The primary blind failure is still topic/authority control at the routing to
evidence boundary, not proof that a new embedding model is required.

### 28. V6 hard-blocker results

Hard blockers observed:

- wrong-topic evidence was accepted as authoritative;
- missing-policy questions were answered as verified policy;
- supported multi-intent branches were dropped or collapsed;
- numerous handler, entity, follow-up, and privileged-capability gates failed.

No unauthorized state mutation, wrong-user data access, wrong-entity action,
session contamination, or post-freeze runtime modification was observed.

### 29. Release decision

**NO-GO FOR STAGING.**

This is not a production decision and no commit, push, merge, or deployment
was performed.

### 30. Next step

V6 is now **DEVELOPMENT DATA** for Phase 9. Do not patch individual V6
sentences. Phase 9 should generalize, in this order:

1. Separate deterministic concept extraction from handler selection and
   reduce false clarification without relaxing OOD safety.
2. Make the required evidence domain immutable through routing, retrieval, and
   composition; a broad shipping numeric handler must not answer policy.
3. Distinguish benign payment/order status queries from privileged mutations,
   and expand mixed-language capability concepts.
4. Improve compositional parsing so duplicate cues do not create duplicate
   sub-intents and dependent shipping calculations retain both entities.
5. Improve quantity/unit/product boundary parsing and ordinal follow-up
   resolution using reusable grammar, then validate against V1-V6 development
   data.
6. Freeze a new candidate only after full QA, then create a fresh independent
   Blind V7. Do not change embedding, RRF, or add Agentic RAG without separate
   benchmark evidence.
