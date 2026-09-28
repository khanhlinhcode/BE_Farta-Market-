# Final V10 V3 scored execution report

## 1. EXECUTIVE SUMMARY

The officially authorized Final V10 V3 candidate execution completed exactly once: 292 primary requests and 78 turns across 39 scenarios were serialized immediately to an append-only JSONL capture with zero operator retries. The capture is complete and LF-delimited JSON validation passes for all 372 records.

Evaluator V3 then failed before offline scoring in `FinalV10RawCapture::read()` with `JsonException: Malformed UTF-8 characters`. The frozen parser uses byte-mode `preg_split('/\R/', ...)`; 48 `0x85` continuation bytes inside valid UTF-8 were treated as line breaks, producing 420 invalid segments from 372 valid LF-delimited records. No parser repair, alternate scorer, candidate rerun, score estimate, or threshold change was made.

The candidate, Evaluator V3, and all Final V10 identities remained unchanged. Backend QA passed. Because offline scoring and formal release gates were not completed, no staging GO is issued.

## 2. AUTHORIZATION / RUN ID

- Authorization: official Final V10 scored execution after Evaluator V3 review.
- Run ID: `final-v10-v3-20260926T201110Z`.
- Start: `2026-09-26T20:11:10+00:00`.
- Raw capture completion: `2026-09-26T20:11:12+00:00`.
- Git HEAD: `ee90385668e263bd7b980a8f86a2280e67f6827c`.
- Branch: `main`.
- Worktree: dirty before execution, containing the frozen candidate/evaluator/evaluation work; no candidate, evaluator, Final V10, gate, prompt, metric, or threshold edit was made after authorization.

## 3. HISTORICAL INVALID RUNS PRESERVED

PASS. Invalid run #1 and invalid run #2 remain unchanged at every hash frozen in `v10-evaluator-v3-manifest.json`. Their predictions were not read, merged, resumed, or reused.

## 4. CANDIDATE HASH PRE-RUN

- Expected: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`.
- Observed: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`.
- Result: PASS.

## 5. EVALUATOR V3 HASH

- Expected/observed seven-file Evaluator V3 identity: `84757f475c7ba78da00b47fcbe442ede38e9d945ee870043c01811ffdac5af05` — PASS.
- Version: `farta-final-v10-evaluator.3.0.0`.
- Runner guard closure: `f3bfb2759f1de1bdd77b05b90ddd0a6ff3e0147211b2eff034dca54ac97a7d9a`.
- The runner guard closure is a broader legacy file set recorded separately; it is not substituted for the official frozen Evaluator V3 identity.
- Manifest assertions for full parse, perfect mirror, negative mutations, serialization, deterministic offline scoring, and development dry run were PASS before execution.

## 6. FINAL V10 HASHES

| Artifact | SHA-256 | Result |
| --- | --- | --- |
| Dataset | `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0` | PASS |
| R6 fresh audit | `3c01c6409b2a59c566916a310ee28a989ad85888d821db95dad52d556b8ca27e` | PASS |
| Final manifest | `af48d07951d013ce0c2670c5a3b563f4d822fc65a549bfcd3919a4992c74c7dd` | PASS |

Schema: `farta-v10.3.0`.

## 7. INFRASTRUCTURE PREFLIGHT

| Check | Result |
| --- | --- |
| Database | PASS; local MySQL health and isolated `sqlite::memory:` harness |
| Cache | PASS; local database store and isolated array harness |
| Session | PASS; local database table present and isolated array harness |
| Qdrant | PASS; HTTP 200; not required during frozen capture |
| Required inference provider | NOT REQUIRED by frozen harness |
| Local Ollama observation | HTTP 200, configured `qwen3:4b` absent; non-blocking because frozen harness forbids network/model calls |
| Evaluator V3 suite | PASS; 24 tests, 101 assertions |
| Generic non-V10 chat smoke | PASS; 1 test, 6 assertions |

The frozen run disabled semantic routing, vector search, Qdrant inference, knowledge generation, and query expansion; stray HTTP was blocked.

## 8. DATASET COUNTS

| Scope | Count | Verification |
| --- | ---: | --- |
| Primary | 292 | PASS |
| Multi-turn scenarios | 39 | PASS |
| Scenario turns | 78 | PASS |
| Multi-intent cases | 27 | PASS |
| Multi-intent branches | 56 | PASS |

## 9. RAW CAPTURE COMPLETION

COMPLETE. The append-only capture contains 1 metadata record, 292 primary records, 78 scenario-turn records, and 1 completion record. All 372 LF-delimited JSON records decode successfully. Manual/operator retries: 0.

The completion record reports `private_data_exposure_count = 1` and `authentication_bypass_count = 0`. The exposure signal is preserved and disclosed, but case-level formal adjudication was not performed because the frozen offline scorer did not start.

## 10. RAW RESULTS SHA-256

- Frozen candidate-output capture: `v10-final-v3-raw-capture.jsonl`.
- Capture SHA-256: `bcf07fa6a9c0e0554237fffad7e7806cef5271c0837a92fd197b61ca59ffe6d3`.
- Capture size: 731,860 bytes.
- Partial-invalid scored-results descriptor SHA-256: `f5043d2ba2428dfc88a2c43397dfa349c6f4f3d7cfac7222923a7807a3b63181`.

## 11. OFFLINE SCORER STATUS

FAILED BEFORE SCORING. `FinalV10RawCapture::read()` threw `JsonException` at `tests/Support/FinalV10RawCapture.php:104`.

Root cause: byte-mode `\R` split 48 UTF-8 continuation bytes `0x85` in addition to physical LF boundaries. The same capture split strictly on LF yields 372 valid JSON records with zero decode failures. No evaluator edit or alternate score path was used. A parser repair and rescore require separate authorization and must consume the exact frozen capture hash.

## 12. INTENT ACCURACY

NOT COMPUTED.

## 13. MACRO P/R/F1

NOT COMPUTED.

## 14. CONFUSION MATRIX

NOT COMPUTED.

## 15. PER-INTENT METRICS

NOT COMPUTED. No per-intent precision, recall, F1, or support was estimated.

## 16. HANDLER ACCURACY

NOT COMPUTED.

## 17. BUSINESS OUTCOME ACCURACY

NOT COMPUTED.

## 18. PRODUCT ENTITY METRICS

Product raw mention and canonical product metrics: NOT COMPUTED.

## 19. QUANTITY/UNIT METRICS

Quantity and unit metrics: NOT COMPUTED.

## 20. ACCOUNT_TARGET METRICS

NOT COMPUTED.

## 21. REQUESTED_MUTATION_VALUE METRICS

NOT COMPUTED.

## 22. ORDER/CONTEXT METRICS

Order reference, ordinal reference, and context reference metrics: NOT COMPUTED.

## 23. FOLLOW-UP METRICS

NOT COMPUTED.

## 24. FOLLOW-UP SUBTYPES

NOT COMPUTED.

## 25. MULTI-INTENT METRICS

Branch intent, handler, terminal, and entity accuracy: NOT COMPUTED.

## 26. MULTI-INTENT COMPLETENESS

NOT COMPUTED.

## 27. WHOLE-REQUEST COMPLETION

NOT COMPUTED.

## 28. OOD METRICS

OOD precision, recall, and F1: NOT COMPUTED.

## 29. SUPPORTED QUERY RECALL

NOT COMPUTED.

## 30. CLARIFICATION / ABSTENTION

Clarification precision/recall and abstention metrics: NOT COMPUTED.

## 31. PRIVILEGED METRICS

Privileged capability precision/recall: NOT COMPUTED.

## 32. BENIGN READ FALSE-DENIAL

NOT COMPUTED.

## 33. REQUIRED EVIDENCE DOMAIN

NOT COMPUTED.

## 34. EVIDENCE ELIGIBILITY

NOT COMPUTED.

## 35. MISSING-EVIDENCE SAFETY

NOT COMPUTED.

## 36. CLAIM SUPPORT

NOT COMPUTED.

## 37. WRONG-TOPIC AUTHORITY

NOT COMPUTED.

## 38. POLICY HALLUCINATION

NOT COMPUTED.

## 39. RETRIEVAL METRICS

Hit@5, MRR, and nDCG: NOT COMPUTED. No new retrieval metric was introduced.

## 40. LANGUAGE BREAKDOWN

NOT COMPUTED for `en`, `mixed`, `vi_conversational`, `vi_formal`, or `vi_no_diacritics`.

## 41. CAPABILITY BREAKDOWN

NOT COMPUTED.

## 42. LATENCY

Router, total, Qdrant, and inference p50/p95: NOT MEASURED. Per-request telemetry remains preserved in the frozen capture, but no aggregate was calculated outside the frozen scorer.

## 43. RUNTIME/INFRASTRUCTURE ERRORS

- Candidate runtime error aggregates: NOT COMPUTED.
- Evaluation infrastructure errors: 1.
- Error: byte-unsafe raw-capture deserialization before offline scoring.
- Candidate outputs lost: 0.

## 44. UNSAFE EXECUTION

Formal unsafe-execution count: NOT COMPUTED.

## 45. WRONG-ENTITY UNSAFE ACTION

NOT COMPUTED.

## 46. RELEASE GATES

Every score-dependent release gate is NOT EVALUATED. Backend QA passed, but it cannot substitute for missing frozen scores. The gate bundle therefore cannot pass and no staging GO can be issued.

| Gate group | Status |
| --- | --- |
| Intent / macro-F1 / supported recall / OOD | NOT EVALUATED |
| Handler / business outcome | NOT EVALUATED |
| Entity / follow-up / multi-intent | NOT EVALUATED |
| Evidence / hallucination / safety | NOT EVALUATED |
| Backend QA | PASS |

## 47. HARD BLOCKERS

- Candidate, evaluator, Final V10, and raw-capture integrity changes: none observed.
- Metric fabrication: none; metrics remain null.
- Capture-time security signal: `private_data_exposure_count = 1`. It is a potential hard blocker and must not be suppressed; formal case-level adjudication against the same capture is pending a separately authorized offline rescore.
- Evaluation infrastructure failure prevents a valid release decision and independently blocks staging approval.

## 48. BACKEND QA

| Command/check | Result |
| --- | --- |
| Full non-historical backend tests excluding every `ChatBlind*Test` and `FinalV10ScoredRunTest` | PASS; 611 tests, 3,296 assertions |
| Chat regression: `ChatTest`, `ChatKnowledgeTest`, `ChatSemanticRoutingTest`, `GroundedChatTest`, `ChatAdversarialSafetyTest` | PASS; 175 tests, 1,111 assertions |
| `vendor/bin/pint --test` | PASS; 253 files |
| `composer validate --strict --no-interaction` | PASS |
| `composer audit --locked --no-interaction` | PASS; no advisories |
| `env -u NODE_TLS_REJECT_UNAUTHORIZED npm audit --audit-level=high --omit=dev` | PASS; 0 vulnerabilities |
| High-signal repository secret scan | PASS; no matches |
| `git diff --check` | PASS |

No runtime repair, commit, push, deployment, or storefront action occurred.

## 49. POST-RUN HASH VERIFICATION

| Identity | Post-run result |
| --- | --- |
| Candidate `d54a66b...8106aa` | PASS; unchanged |
| Evaluator V3 `84757f47...5af05` | PASS; unchanged |
| Dataset `eb7ea21c...1eaa0` | PASS; unchanged |
| Audit `3c01c640...a27e` | PASS; unchanged |
| Manifest `af48d079...c7dd` | PASS; unchanged |
| Historical invalid-run artifacts | PASS; unchanged |

## 50. FIRST-FAILURE DISTRIBUTION

Candidate-case first-failure attribution was not performed because metrics were not frozen. The only valid execution-level failure classification is:

| Failure layer | Count | Share of execution-level failures | Representative ID | Business impact | Security impact |
| --- | ---: | ---: | --- | --- | --- |
| INFRASTRUCTURE | 1 | 100% | `final-v10-v3-20260926T201110Z` | Blocks all aggregate scores and staging decision | Prevents formal adjudication of the preserved exposure signal |

## 51. POST-HOC FAILURE ANALYSIS

Earliest wrong layer: `INFRASTRUCTURE`, specifically Evaluator V3 raw-capture deserialization. This is not attributed to candidate normalization, intent, entity, handler, evidence, retrieval, composer, or authorization behavior. No downstream candidate symptom was double-counted and no score was modified.

## 52. STOREFRONT STATUS

**STOREFRONT NOT VERIFIED.**

## 53. UNVERIFIED ITEMS

All Final V10 quality/security aggregates, confusion matrix, per-intent results, entity/follow-up/multi-intent/retrieval/language/capability breakdowns, release gates, candidate case-level failure taxonomy, storefront/mobile UI, controlled staging, and production behavior remain unverified.

## 54. EXECUTION MANIFEST HASHES

- Preflight record SHA-256: `c33cdd44372c032f7df37599660025cdff1e0bd98b613b9258a5b3e975299289`.
- Frozen JSONL capture SHA-256: `bcf07fa6a9c0e0554237fffad7e7806cef5271c0837a92fd197b61ca59ffe6d3`.
- Partial-invalid scored-results descriptor SHA-256: `f5043d2ba2428dfc88a2c43397dfa349c6f4f3d7cfac7222923a7807a3b63181`.
- This report's SHA-256 is recorded in `v10-final-v3-execution-manifest.json` after the report is closed.
- The execution manifest is not self-hashed inside its own byte content.

## 55. RELEASE DECISION

**FINAL V10 V3 EVALUATION INVALID / INCOMPLETE.**

No backend GO is granted.

## 56. NEXT STEP

STOP. Do not patch or rerun the candidate and do not deploy. Preserve the exact capture hash. Under separate authorization, repair only the offline JSONL deserializer and rerun the frozen offline scorer against this same capture; then complete release gates and formally adjudicate the recorded private-data-exposure signal. Any candidate change requires a fresh independent blind holdout.
