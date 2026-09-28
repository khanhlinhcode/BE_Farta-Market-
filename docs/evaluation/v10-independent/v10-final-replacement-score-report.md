# Final V10 replacement evaluation report

## 1. EXECUTIVE SUMMARY

**FINAL V10 REPLACEMENT EVALUATION INVALID / INCOMPLETE.**

Evaluator v2 recovery preflight passed, so the one authorized replacement run began from case 1. The evaluator stopped on primary request 16, `V10-ST-0289`, when its branch-gold adapter treated the frozen string field `security_expectation: "ALLOW"` as an object/array and attempted `['decision']`. No record was serialized by the running harness, no aggregate metric is valid, and no backend staging decision can be made.

There was no retry, runtime edit, evaluator edit, gold edit, metric edit, or reuse of invalid run #1 output.

## 2. INVALID RUN #1 RECORD

Invalid run #1 remains preserved as an evaluator-infrastructure failure after one attempted request (`V10-ST-0099`), zero serialized records, and zero retries. Its four historical artifacts retain these hashes:

- raw: `da281293410092eaa4b07c3de705d7a0445a4fcc79c7c27128ace6718b47a528`
- report: `132893856c50e3b8a787edc585f58c0049126893d7c2359b534832cdba8baf96`
- manifest: `b567910fecd567b8a926ff0276b1a73f71c7afa3155f2f7fbadfa784c5c36c99`
- preflight: `9231e5b305d64d0c0f89fdf0c888d07ccc3f0a81f79dc651b589c45756b27454`

## 3. ROOT CAUSE

The original evaluator incident was caused by `Collection::filter('is_string')`: Laravel supplied `(value, key)` while PHP `is_string` accepts one argument.

The replacement incident is a distinct evaluator schema-adapter defect. Frozen `farta-v10.3.0` branch records encode `security_expectation` as a scalar string (`"ALLOW"`/`"DENY"`), while `scoredV10Gold()` expected an object-shaped value with `decision` and `capability` offsets. The synthetic multi-intent fixture tested normalized gold records rather than this wire shape, so preflight did not expose the mismatch.

## 4. STACK TRACE LOCATION

```text
TypeError: Cannot access offset of type string on string
tests/Feature/FinalV10ScoredRunTest.php:38
  $record['security_expectation']['decision']
tests/Feature/FinalV10ScoredRunTest.php:415
  scoredV10Gold($goldBranch, true)
```

Input: case `V10-ST-0289`, branch `sepay_info`, `security_expectation` type `string`, value `ALLOW`. Expected evaluator behavior was to consume the frozen scalar schema without throwing.

## 5. EVALUATOR PATCH

Evaluator v2 changed the erroneous Collection predicate calls to typed value-only closures, added throwing JSON round-trip helpers, rejected unknown metric entity fields, and introduced replacement-only artifact names. The post-start branch-schema defect was not patched.

## 6. FILES CHANGED

Evaluator recovery changed only evaluation support, evaluation tests/fixtures, replacement harness/reporting, and evaluation documentation. Candidate runtime and Final V10 files were not changed. The exact evaluator file set is frozen in `v10-evaluator-v2-manifest.json`.

## 7. WHY PATCH DOES NOT CHANGE SCORING CONTRACT

The v2 patch changed callback invocation and validation/serialization mechanics only. It did not alter metric formulas, denominators, normalization semantics, thresholds, gold, or release gates. The replacement failure occurred before any post-freeze change; no corrective interpretation was applied after observing Final V10.

## 8. CRASH REGRESSION TEST

The original `is_string` regression failed before the patch with `ArgumentCountError` and passed afterward. It covered string, null, integer, boolean, array, object/structured value, and missing field. Canonical-product filtering received parallel coverage.

## 9. GOLDEN METRIC TESTS

PASS before replacement: 75% sanity example; exact macro P/R/F1 and confusion matrix; independent intent/handler, product/quantity, account target, and mutation-value scoring; missing prediction failure; `NO_EVIDENCE`; wrong-topic authority; privileged denial; OOD/privileged metrics; and zero denominators.

## 10. SERIALIZATION TEST

PASS before replacement. Synthetic raw results round-tripped case ID, gold, prediction, nine entity slots, terminal, handler, business outcome, evidence fields, runtime error, and latency with exact structure.

## 11. ENTITY-SCHEMA TEST

PASS before replacement for all nine `farta-v10.3.0` entity fields. Unknown metric entity fields throw instead of being silently discarded. The replacement failure was outside the entity schema, in branch security-gold adaptation.

## 12. MULTI-INTENT EVALUATOR TEST

Synthetic two/three-branch normalized records passed supported + `NO_EVIDENCE`, supported + `DENIED`, and supported + unsupported scoring. They did not reproduce the frozen dataset's scalar `security_expectation` wire representation; this preflight coverage gap caused invalidation.

## 13. FOLLOW-UP EVALUATOR TEST

PASS before replacement for resolved, ambiguous, expired, and ordinal reference fixtures.

## 14. DEVELOPMENT DRY RUN

PASS: 19 synthetic primary records, all 18 capability/intent labels, all entity slots, 2 multi-intent parents/5 branches, and 4 scenarios/8 turns. V9 development checks also completed 248 primary routes and 30 follow-up scenarios. These are development results only.

## 15. REPRODUCIBILITY TEST

PASS: scoring the same serialized/deserialized development raw fixture twice produced identical metric structures.

## 16. EVALUATOR QA

- Focused evaluator/development/V9: PASS, 29 tests and 517 assertions.
- Pre-run non-historical backend QA: PASS, 587 tests and 3,195 assertions.
- PHP syntax: PASS.
- Pint on seven evaluator files: PASS.
- `composer validate --strict --no-interaction`: PASS.
- `git diff --check`: PASS.

## 17. EVALUATOR V2 HASH

`b99c121399112ca4d138f44a5bf6804d100658536c17c571efeb55b0ce89eda1` before and after the replacement attempt.

## 18. CANDIDATE HASH PRE-RUN

Expected/observed: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa` — PASS.

## 19. FINAL V10 HASH VERIFICATION

| Artifact | SHA-256 | Result |
|---|---|---|
| Dataset | `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0` | PASS |
| Audit report | `3c01c6409b2a59c566916a310ee28a989ad85888d821db95dad52d556b8ca27e` | PASS |
| Manifest | `af48d07951d013ce0c2670c5a3b563f4d822fc65a549bfcd3919a4992c74c7dd` | PASS |

## 20. RECOVERY DECISION

Pre-run decision: **EVALUATOR RECOVERY PASS**. Post-start observation proved the recovery suite incomplete for the frozen multi-intent branch wire schema. This does not authorize a patch or another run.

## 21. REPLACEMENT RUN IDENTITY

- Classification: `REPLACEMENT ATTEMPT #2 — INVALID EVALUATOR RUN`.
- Persisted run ID: none; failure occurred before harness persistence.
- Started: `2026-09-26T18:50:42Z`.
- Primary requests attempted: 16/292.
- Completed only in process before failure: 15; not serialized and unusable.
- Serialized primary records: 0.
- Scenarios/turns executed: 0/39 and 0/78.
- Branches scored: 0/56.
- Manual retries: 0.
- Invalid run #1 output reused: no.

## 22. RAW RESULTS HASH

Failure record: `v10-final-replacement-scored-raw-results.json`.

SHA-256: `ba702db6a8c369f1a7d543608f984a3cd9ef397c2066e1f2b812ea7d39432587`.

This artifact contains incident metadata only; it contains no serialized candidate score record.

## 23. INTENT METRICS

NOT COMPUTED — no valid serialized denominator.

## 24. CONFUSION MATRIX

NOT COMPUTED.

## 25. HANDLER ACCURACY

NOT COMPUTED.

## 26. BUSINESS OUTCOME

NOT COMPUTED.

## 27. ENTITY METRICS

NOT COMPUTED for product, quantity, unit, order/context references, `account_target`, or `requested_mutation_value`.

## 28. FOLLOW-UP METRICS

NOT COMPUTED — no Final V10 scenario executed.

## 29. MULTI-INTENT METRICS

NOT COMPUTED — evaluator failed before the first branch record was scored.

## 30. OOD METRICS

NOT COMPUTED.

## 31. PRIVILEGED METRICS

NOT COMPUTED.

## 32. EVIDENCE / GROUNDING METRICS

NOT COMPUTED for required domain, eligibility, missing-evidence safety, claim support, wrong-topic authority, or policy hallucination.

## 33. LANGUAGE BREAKDOWN

NOT COMPUTED.

## 34. CAPABILITY BREAKDOWN

NOT COMPUTED.

## 35. SECURITY HARD BLOCKERS

Candidate security blockers are NOT EVALUATED. Scoring corruption/evaluator failure is an evaluation-integrity hard blocker and makes any backend staging conclusion invalid.

## 36. BACKEND QA

Post-run non-historical backend QA, excluding every `ChatBlind*Test` and `FinalV10ScoredRunTest`: PASS, 587 tests and 3,195 assertions. `git diff --check`: PASS. Backend QA cannot substitute for missing Final V10 scores.

## 37. POST-RUN CANDIDATE HASH

`d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa` — PASS, unchanged.

## 38. FAILURE TAXONOMY

Post-hoc attribution after freezing the raw incident record: `OTHER` — evaluator schema-adapter failure. No candidate-layer attribution is valid because there are no serialized scored records. No downstream failure is counted.

## 39. STOREFRONT STATUS

**STOREFRONT NOT VERIFIED.**

## 40. RELEASE GATES

All declared release gates are **NOT EVALUATED**. None is marked PASS or FAIL because the required denominators were not completed. Thresholds were not changed.

## 41. RELEASE DECISION

**FINAL V10 REPLACEMENT EVALUATION INVALID / INCOMPLETE.**

Do not claim backend GO, backend NO-GO based on candidate metrics, staging readiness, or production readiness.

## 42. NEXT STEP

Stop. Preserve both invalid executions and all frozen hashes. Do not fix the chatbot and do not rerun Final V10. Any future evaluation must first establish a newly validated evaluator process and obtain explicit authorization plus an evaluation artifact/run policy that does not violate the completed one-replacement constraint.
