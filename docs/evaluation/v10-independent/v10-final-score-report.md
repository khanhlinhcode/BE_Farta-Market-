# Final V10 scored evaluation report

## 1. EXECUTIVE SUMMARY

**FINAL V10 EVALUATION INVALID / INCOMPLETE.**

The first V10 request executed exactly once. Immediately afterward, the frozen evaluator raised an `ArgumentCountError` while serializing accepted source IDs, before the first scored record was persisted. Per the absolute integrity contract, the evaluator was not changed and the run was not retried. No aggregate metric or staging conclusion is estimated from this partial execution.

## 2. RUN INTEGRITY

- One-scored-run declaration: `TRUE`
- Primary requests attempted: `1/292`
- Primary scored records serialized: `0/292`
- Scenarios executed: `0/39`
- Manual retries: `0`
- First attempted case: `V10-ST-0099`
- Attempt time: `2026-09-26T18:28:19Z`
- Run ID: not persisted before the harness failure

## 3. CANDIDATE HASH VERIFICATION

- Expected: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`
- Pre-run observed: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`
- Result: PASS

## 4. FINAL V10 HASH VERIFICATION

| Artifact | SHA-256 | Result |
|---|---|---|
| Final dataset | `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0` | PASS |
| Final audit report | `3c01c6409b2a59c566916a310ee28a989ad85888d821db95dad52d556b8ca27e` | PASS |
| Final manifest | `af48d07951d013ce0c2670c5a3b563f4d822fc65a549bfcd3919a4992c74c7dd` | PASS |

Schema `farta-v10.3.0`, freeze status `FROZEN`, manifest lineage, and the five headline counts passed before execution.

## 5. EVALUATOR/HARNESS VERIFICATION

- Version: `farta-final-v10-evaluator.1.0.0`
- Frozen hash: `0a6f745b6be0daefde5f7cd46674cd1b0d4ffe1ebbc9ea15cc99a978b5653220`
- Pre-run golden validation: PASS — 15 tests, 54 assertions
- Live execution: FAIL — `is_string() expects exactly 1 argument, 2 given`
- Failure location: `tests/Support/FinalV10Evaluation.php:319`
- Failure stage: accepted source ID serialization
- Post-start evaluator changes: none

## 6. INFRASTRUCTURE PREFLIGHT

PASS before scoring: 42 non-V10 smoke/development tests and 261 assertions. SQLite in-memory database, array cache/session, knowledge sync, sparse retrieval, and mocked Qdrant fallback paths passed. Semantic routing, vector search, Qdrant inference, and knowledge generation were disabled by the frozen evaluation configuration, so no external inference service was required.

## 7. SCORED RUN IDENTITY

- Candidate hash: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`
- Dataset hash: `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0`
- Evaluator hash: `0a6f745b6be0daefde5f7cd46674cd1b0d4ffe1ebbc9ea15cc99a978b5653220`
- Execution identity: incomplete; run ID was not serialized before failure

## 8. RAW RESULTS ARTIFACT + HASH

- Artifact: `docs/evaluation/v10-independent/v10-final-scored-raw-results.json`
- SHA-256: `da281293410092eaa4b07c3de705d7a0445a4fcc79c7c27128ace6718b47a528`
- Contents: immutable partial failure record and the non-secret application-log observation for the first request
- No scored prediction record was available to preserve or aggregate.

## 9. DATASET COUNTS

| Item | Frozen count | Executed/scored |
|---|---:|---:|
| Primary cases | 292 | 1 attempted / 0 scored |
| Multi-turn scenarios | 39 | 0 |
| Scenario turns | 78 | 0 |
| Multi-intent cases | 27 | 0 |
| Multi-intent branches | 56 | 0 |

## 10. INTENT METRICS

NOT COMPUTED — incomplete denominator and zero serialized scored records.

## 11. CONFUSION MATRIX

NOT COMPUTED.

## 12. PER-INTENT PRECISION/RECALL/F1

NOT COMPUTED.

## 13. HANDLER ACCURACY

NOT COMPUTED.

## 14. BUSINESS OUTCOME ACCURACY

NOT COMPUTED.

## 15. PRODUCT ENTITY METRICS

NOT COMPUTED.

## 16. QUANTITY / UNIT METRICS

NOT COMPUTED.

## 17. ACCOUNT_TARGET METRICS

NOT COMPUTED.

## 18. REQUESTED_MUTATION_VALUE METRICS

NOT COMPUTED.

## 19. ORDER / CONTEXT REFERENCE METRICS

NOT COMPUTED.

## 20. FOLLOW-UP METRICS

NOT COMPUTED — no scenario began.

## 21. FOLLOW-UP SUBTYPE BREAKDOWN

NOT COMPUTED.

## 22. MULTI-INTENT BRANCH METRICS

NOT COMPUTED.

## 23. MULTI-INTENT COMPLETENESS

NOT COMPUTED.

## 24. WHOLE-REQUEST COMPLETION

NOT COMPUTED.

## 25. OOD PRECISION/RECALL/F1

NOT COMPUTED.

## 26. SUPPORTED QUERY RECALL

NOT COMPUTED.

## 27. ABSTENTION / CLARIFICATION

NOT COMPUTED.

## 28. PRIVILEGED CAPABILITY METRICS

NOT COMPUTED.

## 29. BENIGN READ FALSE-DENIAL RATE

NOT COMPUTED.

## 30. REQUIRED EVIDENCE DOMAIN

NOT COMPUTED.

## 31. EVIDENCE ELIGIBILITY

NOT COMPUTED.

## 32. WRONG-TOPIC AUTHORITY COUNT

NOT COMPUTED; no run-wide count is asserted.

## 33. MISSING-EVIDENCE SAFETY

NOT COMPUTED.

## 34. CLAIM SUPPORT

NOT COMPUTED.

## 35. GROUNDEDNESS

NOT COMPUTED.

## 36. RESPONSE COMPLETENESS

NOT COMPUTED.

## 37. POLICY HALLUCINATION COUNT

NOT COMPUTED; no run-wide count is asserted.

## 38. RETRIEVAL METRICS

NOT MEASURED. Final V10 provides allowed authorities but no ranked relevance list, and the run did not reach aggregation.

## 39. LANGUAGE BREAKDOWN

NOT COMPUTED.

## 40. CAPABILITY BREAKDOWN

NOT COMPUTED.

## 41. LATENCY

The unscored first request logged router latency `2 ms` and total latency `9 ms`. No p50/p95 is reported from one unscored observation. Qdrant/inference latency was not applicable under the frozen configuration.

## 42. INFRASTRUCTURE/RUNTIME ERRORS

| Error class | Count | Detail |
|---|---:|---|
| Candidate/runtime | 0 observed | First HTTP request completed with status 200 |
| Infrastructure | 0 observed | Preflight passed |
| Evaluation harness | 1 | `ArgumentCountError` after first request |

## 43. UNSAFE EXECUTION COUNT

NOT COMPUTED across the frozen dataset. The single attempted case was a public shipping read and provides no security denominator.

## 44. WRONG-ENTITY UNSAFE ACTION COUNT

NOT COMPUTED across the frozen dataset.

## 45. RELEASE GATE TABLE

All project release gates are **NOT EVALUATED** because their frozen denominators were not completed. No gate is marked PASS or FAIL.

## 46. HARD BLOCKERS

- Known scoring corruption/incomplete serialization.
- Frozen evaluator failed after the scored run began.

These invalidate the evaluation itself; they do not establish candidate GO or candidate NO-GO.

## 47. BACKEND QA

- Full post-score regression QA: NOT RUN because scoring did not complete.
- Post-failure `git diff --check`: PASS.
- No runtime repair, commit, push, or deployment occurred.

## 48. POST-RUN CANDIDATE HASH

- Observed: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`
- Match: PASS

## 49. FIRST-FAILURE DISTRIBUTION

NOT COMPUTED. There are no serialized scored failures to attribute.

## 50. POST-HOC FAILURE TAXONOMY

The execution-level failure is attributed to `OTHER` / evaluation harness serialization, not to the candidate pipeline. Candidate failure attribution is unavailable.

## 51. STOREFRONT STATUS

**STOREFRONT NOT VERIFIED.**

## 52. UNVERIFIED ITEMS

All Final V10 aggregate metrics, release gates, candidate failure taxonomy, ranked retrieval metrics, full backend QA, storefront/mobile UI, staging, and production behavior remain unverified.

## 53. EXECUTION MANIFEST HASHES

- Partial raw artifact SHA-256: `da281293410092eaa4b07c3de705d7a0445a4fcc79c7c27128ace6718b47a528`
- Score report SHA-256 is recorded in `v10-final-execution-manifest.json` after this report is closed.
- The execution manifest is not self-hashed inside its own byte content.

## 54. RELEASE DECISION

**FINAL V10 EVALUATION INVALID / INCOMPLETE.**

## 55. NEXT STEP

Stop. Do not patch the candidate and do not rerun this Final V10. Any valid release evaluation now requires a separately authorized fresh independent holdout and a newly validated evaluator before execution.
