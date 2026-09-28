# Final V10 evaluator recovery report

## 1. Executive summary

**EVALUATOR RECOVERY PASS.** Evaluator v1 failed after the first candidate request because a Laravel Collection supplied both value and key to PHP's one-argument `is_string` predicate. The defect was reproduced on a minimal synthetic citation, fixed only in evaluation code, covered by a red-to-green regression test, and validated with golden, serialization, schema, multi-intent, follow-up, metric-contract, development, and reproducibility tests.

Candidate runtime, Final V10, gold data, release gates, and business behavior were not modified. Invalid run #1 remains historical evidence and is not a source for replacement results.

## 2. Invalid run #1 record

- Status: `INVALID RUN #1 — evaluator infrastructure defect`.
- Attempted case: `V10-ST-0099`.
- Candidate requests attempted: 1; serialized scored records: 0.
- Retry count: 0.
- Aggregate metrics: none valid.
- Historical raw hash: `da281293410092eaa4b07c3de705d7a0445a4fcc79c7c27128ace6718b47a528`.
- Historical score-report hash: `132893856c50e3b8a787edc585f58c0049126893d7c2359b534832cdba8baf96`.
- Historical execution-manifest hash: `b567910fecd567b8a926ff0276b1a73f71c7afa3155f2f7fbadfa784c5c36c99`.
- Historical preflight-record hash: `9231e5b305d64d0c0f89fdf0c888d07ccc3f0a81f79dc651b589c45756b27454`.

All four files remain at their original paths and were not overwritten.

## 3. Root cause and stack trace

Minimal input shape:

```php
[
    'citations' => [
        ['source_id' => 'SYNTHETIC_SOURCE'],
    ],
]
```

Executed evaluator path:

```text
FinalV10Evaluation::acceptedSourceIds()
  -> Collection::pluck('source_id')
  -> Collection::filter('is_string')
  -> Arr::where()
  -> array_filter(..., ARRAY_FILTER_USE_BOTH)
  -> is_string('SYNTHETIC_SOURCE', 0)
```

Reproduced stack:

```text
ArgumentCountError: is_string() expects exactly 1 argument, 2 given
#0 [internal function]: is_string('SYNTHETIC_SOURC...', 0)
#1 vendor/laravel/framework/src/Illuminate/Collections/Arr.php:1240
   array_filter(Array, 'is_string', ARRAY_FILTER_USE_BOTH)
#2 vendor/laravel/framework/src/Illuminate/Collections/Collection.php:415
   Illuminate\Support\Arr::where(Array, 'is_string')
#3 tests/Support/FinalV10Evaluation.php:319
   Illuminate\Support\Collection->filter('is_string')
#4 minimal synthetic invocation
   Tests\Support\FinalV10Evaluation::acceptedSourceIds(...)
```

Expected behavior was to retain string source IDs and discard null, number, boolean, array, object, and missing values. The second argument was the collection key supplied by Laravel, not a requested strictness flag or an evaluator semantic.

## 4. Evaluator patch

Before:

```php
$ids = collect($citations)->pluck('source_id')->filter('is_string');
```

After:

```php
$ids = collect($citations)->pluck('source_id')
    ->filter(fn (mixed $value): bool => is_string($value));
```

The same callback-contract defect was removed individually from canonical-product filtering, branch-product filtering, and scenario latency filtering so later development/final stages cannot fail for the identical arity reason. Direct `array_filter(..., 'is_string')` calls were retained because PHP's default array-filter mode passes only the value.

No scoring comparison, denominator, threshold, normalization rule, runtime route, handler, evidence policy, gold label, or Final V10 byte changed.

## 5. Files changed

- `tests/Support/FinalV10Evaluation.php`
- `tests/Support/FinalV10Metrics.php`
- `tests/Support/FinalV10Report.php`
- `tests/Feature/FinalV10ScoredRunTest.php`
- `tests/Unit/FinalV10EvaluationContractTest.php`
- `tests/Feature/FinalV10EvaluatorRecoveryTest.php`
- `tests/Fixtures/v10_evaluator_development_raw_results.php`
- this recovery report and the evaluator v2 manifest

## 6. Crash regression and golden tests

The focused regression failed before the patch at the original method with `ArgumentCountError` and passed after the patch. It covers string, null, integer, boolean, array, object/structured value, and missing `source_id`. A parallel canonical-product test covers the same input shapes for product names.

Golden tests cover:

- 3/4 correct equals 75%; exact macro precision, recall, F1, and confusion matrix.
- correct intent with wrong handler.
- correct product with wrong quantity.
- missing prediction as failure in the denominator.
- missing multi-intent branch as incomplete.
- safe `NO_EVIDENCE` and wrong-topic authority rejection.
- correct `DENIED` security result while entity extraction remains independently correct.
- wrong `account_target` failure.
- `requested_mutation_value` independent from purchase quantity.
- zero denominators remain null/failing, never NaN or 100%.

## 7. Serialization and entity schema

Raw JSON round-trip is exact, including `case_id`, gold, prediction, all nine entity fields, terminal, handler, business-outcome category/score, evidence fields, runtime error, and latency. Encoding preserves UTF-8 and zero-fraction numeric values and throws on serialization errors.

All `farta-v10.3.0` entity slots are enumerated and scored. Missing known fields are scored according to applicability. Unknown metric-bearing entity fields raise an evaluator exception instead of being silently discarded.

## 8. Multi-intent and follow-up tests

Synthetic requests cover two and three branches with supported + `NO_EVIDENCE`, supported + `DENIED`, and supported + unsupported combinations. Five branches round-trip and score with branch intent/handler/entity/terminal metrics, parent completeness, and whole-request completion.

Follow-up development fixtures cover resolved, ambiguous, expired, and ordinal references, with reference detection, canonical resolution, terminal correctness, subtype metrics, and eight serialized turns.

## 9. Development dry run and reproducibility

The fixed development raw fixture contains all 18 declared capability/intent labels, all nine entity slots, OOD, privileged denial, evidence eligibility, missing-evidence safety, two multi-intent parents/five branches, and four follow-up scenarios/eight turns. It contains no Final V10 case or utterance.

Results:

- evaluator exceptions: 0
- serialization errors: 0
- missing required primary/scenario output fields: 0
- development primary records: 19
- multi-turn scenarios/turns: 4/8
- multi-intent parents/branches: 2/5
- two metric calculations from the same deserialized raw fixture: identical
- V9 development runtime checks: 248 primary routes and 30 follow-up scenarios completed; no evaluator modification resulted from their scores

## 10. QA

- Focused evaluator plus development/V9 checks: PASS, 29 tests and 517 assertions.
- PHP syntax: PASS for all seven evaluator/harness/test files.
- Pint: PASS for all seven evaluator/harness/test files.
- `composer validate --strict --no-interaction`: PASS.
- `git diff --check`: PASS.

## 11. Freeze identities

- Evaluator version: `farta-final-v10-evaluator.2.0.0`.
- Evaluator v2 SHA-256: `b99c121399112ca4d138f44a5bf6804d100658536c17c571efeb55b0ce89eda1`.
- Development fixture SHA-256: `28e388187061052ff359297e1ee5d8d9c2932ed6c80d9d58fcce766192a41e03`.
- Candidate pre-run SHA-256: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa` — PASS.
- Final V10 dataset SHA-256: `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0` — PASS.
- Final V10 audit SHA-256: `3c01c6409b2a59c566916a310ee28a989ad85888d821db95dad52d556b8ca27e` — PASS.
- Final V10 manifest SHA-256: `af48d07951d013ce0c2670c5a3b563f4d822fc65a549bfcd3919a4992c74c7dd` — PASS.

## 12. Recovery decision

**EVALUATOR RECOVERY PASS.** Evaluator v2 is frozen. A single clean replacement run may begin from Final V10 case 1, with no reuse of invalid run #1 output and no evaluator/runtime/gold/metric changes after start.
