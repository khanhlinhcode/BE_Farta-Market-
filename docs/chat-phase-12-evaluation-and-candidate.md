# Chat Phase 12: evaluator validation and candidate record

## Scope and release boundary

Phase 12 is a backend-local quality pass. It makes no commit, push, merge,
deployment, production request, dependency, vector-schema, embedding, reranker,
or state-changing chat change.

V9 is development data. Its historical blind result is retained, and its
fixture, preflight, and score test were not modified. An independently authored
and audited V10 artifact is not available in this repository. Creating one
after inspecting V0--V9 and tuning runtime would not make it independent, so
Phase 12 freezes a technical candidate but does not create or score a falsely
labelled independent V10. Release remains **NO-GO FOR STAGING**.

## Starting state and retained V9 history

- Branch: `main`
- Starting commit: `ee90385668e263bd7b980a8f86a2280e67f6827c`
- Starting tree: `6ba88e6b1fe80b895a1c2fca51a3f63763aa910d`
- The existing Phase 1--11 dirty worktree was preserved; nothing was reset or
  overwritten.
- V9 artifacts: `tests/Fixtures/chat_blind_v9.php`,
  `tests/Feature/ChatBlindV9PreflightTest.php`, and
  `tests/Feature/ChatBlindV9Test.php`.

The original blind V9 score is historical: intent 61.69%, handler 55.24%,
business outcome 53.23%, required domain 71.43%, evidence eligibility 66.67%,
missing-evidence safety 87.50%, canonical entity 0%, follow-up 26.67%, and
unsafe execution 0. It is not overwritten by this phase.

## Evaluator and handoff validation

The historical field named `canonical_entity_resolution_accuracy` is not a
canonical database-ID comparison. Its exact V9 contract is:

```text
route.intent == cart_action_request
AND route.entities.product_name == gold.entity.product
AND route.entities.quantity == gold.entity.quantity
```

An intent miss, raw normalized product-name miss, or quantity miss therefore
fails the entire case. `ChatRouteFrame` preserves those entity slots without
serialization loss. This is not an evaluator-schema mismatch.

The V9 development trace covers 20 entity cases, including digit quantities,
Vietnamese, English/mixed phrasing, action tails, and names such as `Cải Thìa`
and `Cháo Yến Mạch`. It reports 20/20 for historical joint entity contract,
extractor candidate, quantity, and fixture-product canonical slug lookup.

The V9 follow-up contract requires a successful initial request,
`product_detail`, null code, and exact expected product slug (or an empty result
where expected). Each of 30 scenarios now resets cache and session. A
same-scenario-alone versus same-scenario-after-another experiment matched
exactly, proving no cross-scenario state leakage. The V9 development trace is
30/30.

Focused golden tests cover intent/macro-F1, entity slots, follow-up,
handler/source, missing evidence, multi-branch completeness, and explicit
zero denominators.

## Post-hoc V9 development analysis

This is development re-evaluation, not a replacement blind score:

| Measure | Result |
| --- | ---: |
| Router intent accuracy | 100% (248/248) |
| Router macro-F1 | 100% |
| Historic joint entity contract | 100% (20/20) |
| Extractor product candidate | 100% (20/20) |
| Quantity | 100% (20/20) |
| Fixture product-slug resolution | 100% (20/20) |
| Follow-up resolution | 100% (30/30) |

Three primary cases still disagree with historical `required_domain` gold: two
storage temperature/duration questions and a weather-specific shipping-policy
question are labelled `returns`. Runtime uses the fail-closed `storage` or
`shipping_policy` domain; neither has an approved source. Forcing `returns` to
satisfy the annotation would weaken the authority boundary, so these remain
visible `EVIDENCE_DOMAIN` label disagreements.

No persisted raw prediction log exists for the original V9 candidate, so a
complete first-failure distribution for the historical runtime is **NOT
VERIFIED**. The current V9 development re-evaluation has three earliest
`EVIDENCE_DOMAIN` disagreements and no other first failure; it is not unseen
generalization evidence.

## Proven runtime fixes

The fixes are bounded semantic-frame and lifecycle changes, rather than
per-utterance fall-through rules:

- `ChatConceptExtractor`: separates product/catalog/order/shipping concepts;
  handles checkout review versus payment, storage, contact/account, negated
  return language, safe general chat, and bounded Vietnamese, no-diacritic,
  English, and mixed-language families.
- `ChatEntityExtractor`: removes only a bounded operation/action tail before
  DB canonicalization, preserves legitimate names such as `Cải Thìa`, and
  retains English cart quantities.
- `ChatIntentRouter`: carries general-chat, dependent-shipping, and
  multi-intent precedence in a common frame.
- `ChatCapabilityGuard`: separates read from prohibited mutation and handles
  mixed-language auth bypass, prompt disclosure, inventory mutation, and
  payment override without denying benign duration questions.
- Context handling revalidates referenced products against current DB state;
  expired, deleted, inactive, or ambiguous references clarify rather than
  guess.

Evidence remains immutable by branch:

```text
semantic frame -> required evidence domain -> source/topic filter
-> eligibility -> claim support -> terminal branch result
```

No Qdrant/RRF/embedding/reranker change was made. Structured shipping/contact
data remains authoritative and knowledge requests fail closed without approved
claim-supporting evidence.

## Development regression and benchmark

V0--V8 development router regression: 1,547 cases, intent 97.80%, macro-F1
96.98%, required domain 98.03%, semantic fallback calls 0.

The V0--V9 router benchmark groups normalized token-Jaccard >= 0.82 connected
components so near duplicates cannot cross splits. Seed:
`phase12-router-v1-20260926`; split sizes: 1,086 / 357 / 352.

| Candidate | Result | Decision |
| --- | --- | --- |
| Deterministic/concept router | Intent 98.30%; macro P/R/F1 98.30% / 97.23% / 97.72%; supported recall 99.29%; OOD recall 100%; privileged recall 100%; p50/p95 0.3819/1.1111 ms | Keep |
| Existing semantic fallback | No live, versioned model for a comparable run | NOT VERIFIED |
| SetFit/lightweight classifier | SetFit, sentence-transformers, torch, sklearn, and transformers unavailable; no dependency added | NOT AVAILABLE |

Language results: Vietnamese 98.65% intent / 97.79% macro-F1 (n=223),
no-diacritic 100% / 100% (n=20), English 87.50% / 77.14% (n=8), mixed 98.02%
/ 96.56% (n=101). English support is too small for a general claim. The router
benchmark does not execute handlers, so handler/business-outcome accuracy is
**NOT MEASURED**. OOD precision is also **NOT MEASURED**, because
`unsupported` combines OOD and correct terminal denial labels.

Raw required-domain accuracy is 94.92% because its denominator retains the
known historical domain-label disagreements. It is not retrieval-ranking
evidence. Semantic fallback stays disabled with zero calls.

## QA and candidate freeze

Actual completed checks:

- Full non-historical backend test command: 560 tests, 2,679 assertions, pass.
- Targeted evaluator/integrity/V9-development tests: 8 tests, 32 assertions,
  pass.
- Phase 12 router benchmark: pass.
- Phase 11 development regression: pass.
- `vendor/bin/pint --test`: 239 files, pass.
- `composer validate --strict`: pass.
- `composer audit --no-interaction`: no advisories.
- `env -u NODE_TLS_REJECT_UNAUTHORIZED npm audit --audit-level=high --omit=dev`:
  0 vulnerabilities.
- `git diff --check`: pass.

The full command intentionally excludes every `ChatBlind*Test` score test. An
earlier attempted broad command accidentally ran a historical V5 scorer after
runtime changes; that result is not reported as a blind score. The recorded QA
command corrected this with explicit exclusion.

Technical runtime candidate hash:

```text
d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa
```

Architecture: deterministic/concept router, semantic fallback disabled, no
classifier/model threshold, and no dependency change. No runtime file may
change before independently prepared V10 scoring.

## V10 and release decision

V10 leakage audit, dataset, metrics, confusion matrix, entity/follow-up/
multi-intent/grounding/security breakdowns are **BLOCKED**. There is no
independently authored and audited V10 artifact outside the data used to tune
this candidate. Inventing it locally and calling it independent would violate
the blind-evaluation contract.

Hard blocker: independent release evaluation is absent. Storefront is **NOT
VERIFIED** because this was a backend-local pass.

**Release decision: NO-GO FOR STAGING.**

## Next step

An evaluator who did not participate in Phase 12 should author and freeze V10
with explicit entity-slot, follow-up, multi-branch, evidence-domain/allowed-
source, and security gold; audit it against V0--V9; then run it exactly once
against the frozen runtime hash above. Any runtime change requires a new
candidate and a fresh V10 artifact.
