# Chat Phase 4: Natural-language generalization

Date: 25 September 2026

## 1. Phase 3 baseline

Phase 3 established that the retrieval layer was not the active bottleneck:

| Measure | Phase 3 result |
| --- | ---: |
| Tuned intent regression | 166/166 |
| Development product HitRate@5 | 100% |
| Development product MRR@5 | 96.88% |
| Development product nDCG@5 | 97.69% |
| Knowledge holdout HitRate/MRR/nDCG@5 | 100% / 100% / 100% |
| Missing-evidence safety | 100% |

The original Phase 3 blind set scored only 48% intent accuracy, 70% handler
accuracy, 16.67% entity accuracy, 12.5% multi-intent coverage, 25% follow-up
resolution, and 40% unsupported-query safety. In Phase 4 it is explicitly
reclassified as **Generalization Development Set V1**. It is not independent
release evidence anymore.

## 2. V1 failure taxonomy

All 52 original intent failures were reviewed before implementation. The
primary categories are mutually exclusive at the intent level:

| Primary category | Count | Affected groups |
| --- | ---: | --- |
| Missing reusable concept/paraphrase signal | 44 | shipping 5, catalog 5, product detail 5, product search 5, cart action 3, cart query 5, order 5, knowledge 9, general 2 |
| Unsupported/OOD guard gap | 3 | unsafe order/payment mutation and medical advice |
| Multi-intent decomposition gap | 5 | product + shipping, catalog + policy, cart + order |

Separate evaluation dimensions exposed five entity-boundary failures out of
six cases, three handler errors out of ten probes, and three failed follow-ups.
The entity failures came from politeness/recipient prefixes, measure words,
English cart suffixes, and quantity words. Follow-up measurement also exposed a
test-harness gap: stateful browser headers are required for the Sanctum session
context. The runtime additionally cleared the safe product reference after a
guest auth-required cart response.

The implementation addresses failure classes, not the individual sentences in
V1.

## 3. Root causes

1. The router relied on narrow surface phrases and returned clarification for
   many valid paraphrases.
2. Intent classification and entity extraction were coupled inside the router.
3. Cart quantity parsing existed separately in the controller and was more
   capable than router extraction.
4. Multi-intent detection counted too few domains and sometimes confused
   business words such as `đặt`, `mua`, and `đơn` with actions.
5. Unsupported mutation/advice rules described whole phrases rather than
   protected action + protected object concepts.
6. Guest auth failure discarded the non-sensitive product reference needed for
   later informational follow-ups.

The official Microsoft CLU guidance supports the adopted separation: actions
and queries are intents, while information needed to fulfil them is represented
as entities. It also recommends a None/out-of-domain path, confidence-based
abstention, independently held test data, per-intent precision/recall/F1, and a
confusion matrix. Laravel's testing guidance supports feature tests for flows
that cross routing, sessions, database state, and handlers.

References:

- <https://learn.microsoft.com/en-us/azure/ai-services/language-service/conversational-language-understanding/concepts/best-practices>
- <https://learn.microsoft.com/en-us/azure/ai-services/language-service/conversational-language-understanding/concepts/evaluation-metrics>
- <https://learn.microsoft.com/en-us/azure/ai-services/language-service/conversational-language-understanding/how-to/deploy-model>
- <https://laravel.com/framework/docs/12.x/testing>

## 4. Architecture changes

Before:

```text
normalized message
  -> phrase-oriented router
  -> inline digit-only cart entity extraction
  -> controller quantity parser
  -> handler
```

After:

```text
raw + conservatively normalized message
  -> deterministic safety and concept composition
  -> multi-domain detection / safe clarification
  -> single intent
  -> shared ChatEntityExtractor
  -> authoritative DB/service handler
  -> suggested cart action only
```

`ChatEntityExtractor` handles Vietnamese and English whole-number quantities,
politeness/recipient spans, measure words, and cart suffixes. Extracted product
text remains a candidate only; the controller reloads and resolves active
products from MySQL before returning facts or a suggested action.

The optional semantic router remains a closed-schema fallback. All acceptance
and holdout tests run with `AI_SEMANTIC_ROUTER_ENABLED=false`.

## 5. Files changed in Phase 4

- `app/Services/Chat/ChatEntityExtractor.php`: shared quantity and cart-entity
  extraction.
- `app/Services/Chat/ChatIntentRouter.php`: composable intent signals,
  unsupported guards, multi-domain detection, and safe abstention.
- `app/Http/Controllers/ChatController.php`: reuse shared quantity extraction
  and retain only a safe product reference after guest auth failure.
- `tests/Support/ChatNluMetrics.php`: per-intent precision, recall, F1, macro-F1,
  and confusion matrix.
- `tests/Feature/ChatBlindHoldoutTest.php`: reclassify and measure V1 as
  development data.
- `tests/Feature/ChatBlindV2Test.php` and `tests/Fixtures/chat_blind_v2.php`:
  independent frozen-candidate evaluation.
- `tests/Unit/ChatEntityExtractorTest.php`: entity and unsafe-quantity coverage.

## 6. Code removed

The controller-local `numberWords()` implementation and duplicated quantity
parser were removed. The router's inline cart prefix/suffix parser and
digit-only quantity extraction were also removed. One shared extractor now
owns those mechanics; business validation remains in the handler.

## 7. V1 development results

| Metric | Before | After |
| --- | ---: | ---: |
| Intent accuracy | 48% | 100% |
| Macro-F1 | not measured | 100% |
| Route/handler accuracy | 70% | 100% |
| Entity extraction | 16.67% | 100% |
| Multi-intent safe handling | 12.5% | 100% |
| Follow-up resolution | 25% | 100% |
| Unsupported/OOD safety | 40% | 100% |

V1 is development evidence only. Its perfect result must not be presented as
unseen-user performance.

## 8. Per-intent V2 metrics

V2 contains 115 new natural-language queries and was first evaluated only after
runtime freeze.

| Intent | Precision | Recall | F1 | Support |
| --- | ---: | ---: | ---: | ---: |
| cart_action_request | 83.33% | 83.33% | 83.33% | 12 |
| cart_query | 87.50% | 87.50% | 87.50% | 8 |
| catalog_list | 100% | 60% | 75% | 10 |
| clarification | 42.42% | 82.35% | 56% | 17 |
| general_chat | 100% | 60% | 75% | 5 |
| knowledge_query | 100% | 73.33% | 84.62% | 15 |
| order_query | 76.92% | 100% | 86.96% | 10 |
| product_detail | 83.33% | 50% | 62.50% | 10 |
| product_search | 77.78% | 70% | 73.68% | 10 |
| shipping_info | 88.89% | 80% | 84.21% | 10 |
| unsupported | 100% | 62.50% | 76.92% | 8 |

Overall intent accuracy is **74.78%** and macro-F1 is **76.88%**.

## 9. V2 blind holdout results

| Metric | Result | Project target | Gate |
| --- | ---: | ---: | --- |
| Intent accuracy | 74.78% | >= 85% | FAIL |
| Route/handler accuracy | 91.67% | >= 90% | PASS |
| Entity extraction accuracy | 58.33% | tracked separately | needs work |
| Canonical DB entity resolution | 100% | >= 80% | PASS |
| Follow-up resolution | 60% | >= 80% | FAIL |
| Multi-intent safe handling | 70% | >= 80% | FAIL |
| Unsupported/OOD classification safety | 62.50% | >= 95% | FAIL |
| Clarification correctness | 82.35% | tracked separately | informative |
| Unsafe cart/order/auth execution | 0 | 0 | PASS |

The first V2 run produced the same intent metrics above. Its HTTP handler probes
were initially contaminated by the real 20-request/minute chat throttle because
the evaluator sent 37 independent probes from one test IP. The corrected
handler measurement disables only `ThrottleRequests` inside this test; runtime,
fixture, and intent outputs were not changed. The production limiter retains its
existing independent coverage.

## 10. Confusion matrix summary

The dominant error is safe over-abstention: 19 valid non-clarification queries
became `clarification`. Product detail recall is the weakest supported intent at
50%. Three unsupported requests were missed: two became owner-scoped order
queries and one became clarification; none executed a mutation. Three of ten
multi-intent cases were not decomposed and were routed to a single domain.

Detailed expected-row/predicted-column counts are emitted as
`CHAT_BLIND_V2.confusion_matrix` by the test.

## 11. Entity extraction

Exact router entity extraction is 7/12 (58.33%). Remaining boundary errors
involve polite prefixes (`làm ơn`), colloquial `giùm`, measure words after a
pronoun, and `Trà Nhẹ` losing its adjective because `nhẹ` resembles a polite
suffix. Despite this, canonical DB resolution is 12/12 because the handler
searches the original user message and revalidates the selected active product.
No extractor output is trusted as product authority.

## 12. Multi-intent

V2 safe multi-intent handling is 7/10. Missed compositions were cart + cart
read, order + shipping, and cart + order. The system did not execute a hidden
second action, but it silently selected one domain, so the release gate fails.

## 13. Follow-up

V2 follow-up resolution is 3/5 including the initial grounding turn, or two of
four follow-up utterances. Explicit price/stock references work; category-style
reference wording remains under-specified and can clear the bounded context.

## 14. Unsupported/OOD safety

Unsupported intent recall is 5/8 (62.50%), below the 95% target. All eight HTTP
probes returned zero suggested actions, inert legacy action output, and no order
payload. Therefore unsafe execution remains zero even when classification is
imperfect. Phase 5 must improve classification before staging.

## 15. RAG regression check

No Qdrant, embedding, sparse retrieval, RRF, top-k, knowledge schema, or
generation setting changed in Phase 4. The development retrieval regression
remains HitRate@5 100%, MRR@5 96.88%, and nDCG@5 97.69%. Knowledge holdout and
missing-evidence checks remain unchanged.

## 16. Full QA results

| Command/check | Result |
| --- | --- |
| `php artisan test` | **FAIL**: 356 passed, 1 failed, 2,037 assertions |
| Failure | Semantic-routing safety regression: injection-wrapped mark-paid request became safe clarification instead of `unsupported`; no tool/action executed |
| Chat regression V5 | PASS: 166/166 intent; retrieval Hit@5 100%, MRR@5 96.88%, nDCG@5 97.69% |
| Generalization Development V1 | PASS: all tracked metrics 100% |
| Independent V2 evaluator | PASS structurally; quality gates fail as documented above |
| Storefront `npm test -- --run` | PASS: 26 files, 103 tests |
| Storefront `npm run build` | PASS: 306 modules transformed |
| Storefront lint/typecheck | Not available in `package.json`; no command invented |
| `vendor/bin/pint` | PASS: 199 files |
| `composer validate --strict` | PASS |
| `git diff --check` | PASS in backend and storefront |
| `composer audit --no-interaction` | No advisories |
| `npm audit --omit=dev` | Reported zero vulnerabilities, but the inherited environment disabled TLS certificate verification; rerun in a secure shell before release |

The backend suite failure is intentionally not patched after V2 runtime freeze.
It is a Phase 5 blocker and reinforces the NO-GO decision.

## 17. Security check

Cart remains suggestion-only. Product and quantity candidates are revalidated
from MySQL; auth, verified-email, role, stock, and quantity boundaries are
unchanged. No prompt, token, credential, address, payment detail, or browser
history was added to telemetry. Secret-pattern scans of tracked diffs and all
untracked candidate files found no credential-like assignments. Existing
security-header, auth, ownership, rate-limit, and chat telemetry tests passed.

## 18. Candidate revision

The candidate is a dirty local tree based on backend commit
`ee90385668e263bd7b980a8f86a2280e67f6827c` and storefront commit
`e07b086a3e2294c7bf5f74d0c56f49a55676cf4b`. No commit, push, merge, build
upload, or deployment was performed. The final content hash is reported in the
handoff after QA.

## 19. Release decision

**LOCAL CANDIDATE: NO-GO FOR STAGING.**

V2 misses four project gates: intent accuracy, follow-up resolution,
multi-intent safe handling, and unsupported/OOD safety. The candidate improves
V1 coherently and preserves business safety, but Phase 4 cannot honestly
recommend staging yet.

## 20. Next step

Phase 5 should use the V2 failure classes—not its individual sentences—to
improve reusable lexical features and entity spans, then freeze a new runtime
and evaluate against a fresh V3 holdout. Do not add Agentic RAG, a new embedding
model, reranker, or vector database for these NLU failures.
