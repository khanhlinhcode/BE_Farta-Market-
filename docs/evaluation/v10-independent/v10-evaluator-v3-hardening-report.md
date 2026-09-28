# Final V10 Evaluator V3 hardening report

## 1. EXECUTIVE SUMMARY

**EVALUATOR V3 HARDENING PASS — FROZEN.**

Evaluator failure #2 was reproduced without a chatbot request, repaired by implementing the frozen `farta-v10.3.0` branch security contract, and covered by a red-to-green regression. Evaluator V3 now validates every frozen gold record before execution, writes future candidate output to an append-only raw-capture stream before offline scoring, and scores a full gold mirror deterministically without network, database, Qdrant, LLM, session, randomness, or wall-clock inputs.

No Final V10 candidate request was made in this phase. Candidate runtime, Final V10 gold, metric formulas, denominators, thresholds, and release gates were not changed.

## 2. INVALID RUN HISTORY

- Invalid run #1 remains `INVALID RUN #1 — evaluator infrastructure defect`: request 1 completed, then `Collection::filter('is_string')` raised `ArgumentCountError` because Laravel passed value and key to a one-argument PHP built-in.
- Invalid replacement run #2 remains `REPLACEMENT ATTEMPT #2 — INVALID EVALUATOR RUN`: request 16/292 (`V10-ST-0289`) completed, then branch-gold adaptation indexed scalar `security_expectation` as an array. Zero scored records were serialized and no metric is valid.
- Neither history was overwritten, reused, retried, reclassified, or promoted into a valid score.

## 3. FAILURE #2 REPRODUCTION

Before patching, the focused evaluator-only regression executed the exact `scoredV10Gold($record, true)` path with `security_expectation = "ALLOW"`. It made no route or `/api/chat` request.

Command:

```text
vendor/bin/pest tests/Feature/FinalV10ScoredRunTest.php --filter='parses scalar branch security expectations without executing the candidate' --no-coverage
```

Pre-patch result: **RED**, 1 failed, 0 assertions. Post-patch result: **GREEN**, 1 passed, 1 assertion.

Observed input:

```text
case: V10-ST-0289
branch: sepay_info
value: ALLOW
PHP type: string
expected: parse the scalar branch security enum and return a branch gold frame
```

## 4. ROOT CAUSE #2

`farta-v10.3.0` defines primary and scenario `security_capability_expectation` as an object with `decision` and `capability`, but defines branch `security_expectation` as a scalar string. All 56 frozen branches use exactly one of `ALLOW`, `DENY`, or `SUGGEST_ONLY`. Evaluator V2 incorrectly reused the object-shaped primary adapter for branch records and attempted `['decision']` and `['capability']` offsets.

The V2 preflight missed the defect because its synthetic multi-intent tests started from already-normalized gold frames instead of the frozen JSON wire shape.

## 5. EXACT STACK TRACE

```text
TypeError: Cannot access offset of type string on string

at tests/Feature/FinalV10ScoredRunTest.php:38
  $record['security_expectation']['decision']

1  tests/Feature/FinalV10ScoredRunTest.php:38
2  tests/Feature/FinalV10ScoredRunTest.php:664
```

The historical run #2 caller was line 415 before the V3 test was appended; the preserved incident artifact records that original caller and line 38 expression.

## 6. EVALUATOR PATCH

- Added `FinalV10DatasetContract`, the single pre-scoring parser/validator for frozen dataset wire shapes.
- Added a typed scalar branch-security parser. Unknown values and non-string shapes fail precheck with `FinalV10ContractException`.
- Normalized branch runtime security into the same coarse branch contract: denied/auth-required decisions become `DENY`, cart suggestion becomes `SUGGEST_ONLY`, and all non-mutating safe/OOD branches become `ALLOW`.
- Added `FinalV10RawCapture`, an append-only JSONL capture format with secret-key removal and per-request persistence.
- Added `FinalV10OfflineScorer`, which joins a fixed raw capture to the validated contract and performs all correctness/metric preparation offline.
- Added a complete perfect-mirror generator, negative mutations, result-shape round trips, deterministic re-scoring, and a full-scale development fixture.
- Added an explicit `FINAL_V10_V3_RUN_AUTHORIZATION=AUTHORIZED_AFTER_V3_REVIEW` guard. V3 hardening itself does not satisfy that future authorization.

## 7. WHY PATCH PRESERVES SCORING CONTRACT

No metric name, formula, denominator, threshold, release gate, textual normalization rule, numeric comparison rule, or zero-denominator behavior changed. `FinalV10Metrics` still calculates the V2-frozen metrics. The repair only validates and adapts the actual frozen input schema before those formulas run.

Primary/scenario security decisions retain their detailed values such as `ALLOW_READ`, `ALLOW_OWNED_READ`, and `DENY_CROSS_ACCOUNT`. Branches use their declared coarse scalar contract. No case ID receives special handling.

## 8. PHP TYPE-SAFETY REVIEW

PASS. The V3 contract layer checks required keys, JSON object versus list shape, scalar type, nullability, enum membership, nested grounding/source/version consistency, entity slots, scenario/turn structure, uniqueness, and source-registry references before scoring.

Explicit edge results:

- `requested_mutation_value = 0`: legal and preserved as numeric zero.
- `free_shipping_applies = false`: legal and preserved as boolean false.
- quantity `0`: rejected; present quantity must be positive.
- string `"0"` as mutation value: rejected rather than conflated with numeric zero.
- empty account target: rejected; null remains the explicit absence value.
- empty allowed-source list: legal.
- empty branch list: legal for non-multi-intent, rejected for `multi_intent`.
- missing entity key: rejected before scoring.
- optional scenario source-version key with null: legal because it occurs in the frozen wire contract.

Scored-result arrays are validated before metric callbacks execute, so malformed shapes fail as contract errors rather than as mid-score PHP warnings or `TypeError`s.

## 9. CALLBACK ARITY REVIEW

PASS. All evaluator Collection callbacks use typed closures compatible with Laravel's value/key invocation. Built-in `is_string`/`is_numeric` filter shortcuts were replaced with value-only closures. No evaluator call uses `ARRAY_FILTER_USE_BOTH`; `array_map` callbacks have the same arity as their input-array count. A source audit test guards against reintroducing `Collection::filter('is_string')` or `ARRAY_FILTER_USE_BOTH`.

## 10. FINAL V10 SCHEMA INVENTORY

The machine-checkable inventory is `v10-evaluator-v3-contract-inventory.json` (SHA-256 `9deb741b009c9f056c009fff1b21547e523187e5c43a8fbde2d8d89b68fd5366`). It records every observed evaluator-consumed field by scope, presence count, required/optional status, nullability, JSON types, and bounded scalar values.

Validated scope counts:

| Scope | Count | Result |
|---|---:|---|
| Primary | 292 | PASS |
| Multi-turn scenario | 39 | PASS |
| Scenario turn | 78 | PASS |
| Multi-intent primary | 27 | PASS |
| Multi-intent branch | 56 | PASS |

## 11. ENUM / SCALAR / ARRAY CONTRACTS

The parser freezes observed enums for primary/branch/turn intents, handler paths, terminals, language buckets, difficulty, actor/auth states, security decisions, security capabilities, branch operations, evidence domains, scenario types, context states, and string-valued mutation targets. It validates list-of-string source/fact structures independently from object-shaped version maps.

The exhaustive values are in the inventory artifact; they are derived from the frozen schema/data and asserted by the parser rather than inferred with truthiness.

## 12. SECURITY_EXPECTATION CONTRACT

| Scope | Shape | Legal values/fields |
|---|---|---|
| Primary | object | `decision`, `capability` |
| Branch | string | `ALLOW`, `DENY`, `SUGGEST_ONLY` |
| Scenario | object | `decision`, `capability` |

All three legal branch values pass parse, JSON serialize, JSON deserialize, and correctness scoring. Null, array, object, and unknown enum strings fail during precheck.

## 13. ENTITY CONTRACT

All primary, branch, and turn frames contain exactly these nine slots: `product_raw_mention`, `canonical_product`, `quantity`, `unit`, `order_reference`, `ordinal_reference`, `context_reference`, `account_target`, and `requested_mutation_value`.

Observed unions are validated without coercion: product values may be string/list/null by scope; quantity is positive number/null; ordinal is non-zero integer/null; requested mutation is numeric (including zero) or an observed signed/symbolic string; other references/targets are non-empty string/null.

## 14. MULTI-INTENT CONTRACT

Twenty-seven primary records declare 56 ordered branches. Every branch requires ID, intent, operation, nine-slot entity frame, handler, evidence domain/sources, terminal, business outcome, scalar security expectation, facts, and grounding. Offline scoring preserves fixed ordinal alignment, treats an omitted/misaligned branch as a missing prediction, reports extras, and fails parent completeness rather than dropping a branch.

## 15. FOLLOW-UP CONTRACT

Thirty-nine scenarios contain exactly 78 ordered user turns. The validator checks scenario type, actor/context TTL, turn number/role, expected intent/handler/terminal, nine-slot entity frame, context state, expected context status/product ordering/focus, final canonical entity, evidence fields, and scenario security capability.

Resolved, ambiguous, expired, deictic, elliptical, plural, singular, and ordinal reference shapes are covered by the frozen full parse and development scenarios.

## 16. RAW CAPTURE ARCHITECTURE

Stage A is an append-only JSONL stream:

```text
candidate response
→ minimal route/response normalization
→ secret-key sanitization
→ append + LOCK_EX immediately for that request
```

The future harness writes one primary record per single-turn request and one scenario-turn record per follow-up request. Records preserve IDs, turn, sanitized raw response envelope, prediction/route fields, entities, terminal, handler, security/evidence output, runtime error, HTTP status, state-change observation, and latency. Password/token/secret/authorization/cookie/API-key fields are removed recursively.

## 17. OFFLINE SCORER ARCHITECTURE

Stage B accepts only a validated frozen contract plus a fixed raw-capture document. It joins by frozen IDs/order, creates missing predictions instead of skipping denominators, calculates branch/scenario results, and then invokes unchanged metrics.

A source-isolation test confirms the offline scorer contains no HTTP, DB, route, Qdrant, chatbot, or wall-clock call. Two scores of the same serialized capture are byte-identical for both scored raw output and metrics.

## 18. CRASH REGRESSION TEST

PASS, red to green. The exact pre-patch TypeError occurred at line 38. After routing `scoredV10Gold(..., true)` through `FinalV10DatasetContract::branchGold`, the same scalar input returns `security_decision = ALLOW` without executing the candidate.

## 19. FULL DATASET PARSE TEST

PASS: 292/292 primary, 39/39 scenarios, 78/78 turns, 56/56 branches, zero exceptions. Additional malformed mutations prove precheck rejects wrong type, missing required field, unexpected null, invalid enum, invalid branch, invalid entity, malformed source-version map, and invalid scenario turn count.

## 20. PERFECT GOLD-MIRROR TEST

PASS. Full frozen gold was converted into semantically satisfying synthetic predictions and passed through raw-capture serialization, offline scorer structures, correctness, and aggregate metrics.

- Intent/handler/terminal/business outcome: 100%.
- Aggregate entity-slot F1: 100%.
- Evidence domain/eligibility/claim support: 100%.
- Follow-up scenario success: 39/39.
- Multi-intent branch intent/handler/entity/terminal and completeness: 100%.
- Claim-bearing primary plus branch records: 307/307.
- Evaluator/serialization exceptions: 0/0.

Artifact: `v10-evaluator-v3-perfect-mirror-results.json`, SHA-256 `0d5f67a9cdc83cbaf177e8d1655e9600c24e8982aa7026108e1771faeff844c9`.

## 21. NEGATIVE MUTATION TESTS

PASS, 15 mutation classes. Wrong intent, handler, terminal, missing prediction, canonical product, quantity, account target, requested mutation value, order reference, missing branch, evidence domain, accepted source, claim support, security expectation, and OOD classification each fail or reduce its intended score.

## 22. SERIALIZATION ROUND-TRIP TEST

PASS. All 292 primary and 78 scenario-turn synthetic captures round-trip semantically. Coverage includes normal answer, no results, suggested action, unavailable, auth required, denied, answer-or-not-found, no evidence, unsupported/OOD, clarification, both multi-intent terminal classes, follow-up, and a retained runtime error. Secret-key removal is also asserted.

## 23. OFFLINE SCORER REPRODUCIBILITY

PASS. A fixed serialized full-gold capture was scored twice. Scored raw JSON bytes and metric JSON bytes were identical. There is no network/session/DB/clock/random dependency in Stage B.

## 24. DEVELOPMENT DRY RUN

PASS. Development-only raw fixtures contain 23 primary records, 5 multi-intent branches, 4 scenarios, and 8 turns. They cover all 18 primary intent/capability labels, all nine entity slots, all 12 frozen terminal values, safe/denied/auth/security decisions, evidence domains, wrong-source protections, multi-intent, follow-up, NO_EVIDENCE, DENIED, OOD, NO_RESULTS, UNAVAILABLE, AUTH_REQUIRED, and ANSWER_OR_NOT_FOUND. Metrics and serialization complete with zero evaluator errors.

No chatbot request was made; the stricter candidate-immutability rule was preserved.

## 25. EVALUATOR BRANCH COVERAGE

| Evaluator branch | Development case/test | Result |
|---|---|---|
| Standard answer paths | `DEV-V10-001` through `DEV-V10-012`, `DEV-V10-014` | PASS |
| Missing evidence | `DEV-V10-013` | PASS |
| OOD/unsupported | `DEV-V10-015`, `branch-unsupported` | PASS |
| Clarification | `DEV-V10-016` | PASS |
| Privileged denial | `DEV-V10-017`, `branch-denied` | PASS |
| Suggested action | `DEV-V10-009` | PASS |
| Multi-intent all handled | `DEV-V10-018` | PASS |
| Multi-intent mixed terminals | `DEV-V10-019` | PASS |
| NO_RESULTS | `DEV-V3-NO-RESULTS` | PASS |
| UNAVAILABLE | `DEV-V3-UNAVAILABLE` | PASS |
| AUTH_REQUIRED | `DEV-V3-AUTH-REQUIRED` | PASS |
| ANSWER_OR_NOT_FOUND | `DEV-V3-ANSWER-OR-NOT-FOUND` | PASS |
| Resolved/ambiguous/expired/ordinal follow-up | `DEV-SCENARIO-1` through `DEV-SCENARIO-4` | PASS |
| Raw capture survives scorer failure | `synthetic-one` crash-proof test | PASS |
| All frozen schema branches | full parse + full mirror | PASS |

No known evaluator method/semantic branch is left without a development, mutation, serialization, or full-frozen-shape test.

## 26. QA

| Check | Result |
|---|---|
| Focused + full evaluator suite excluding explicitly locked candidate group | PASS — 51 tests, 610 assertions |
| V3 full-gold/development/mutation suite | PASS |
| PHP syntax, 13 evaluator/harness/test/fixture files | PASS |
| Laravel Pint `--test`, 11 V3 files | PASS |
| `composer validate --strict --no-interaction` | PASS |
| `composer audit --locked --no-interaction` | PASS — no advisories |
| `git diff --check` | PASS |
| Candidate/Final V10/hash tests | PASS |

The full backend runtime suite was not run because it would execute candidate code and this phase expressly prohibits candidate execution. Evaluator-only development fixtures provide the required dry run.

## 27. FILES CHANGED

Evaluator/harness:

- `tests/Support/FinalV10ContractException.php`
- `tests/Support/FinalV10DatasetContract.php`
- `tests/Support/FinalV10Evaluation.php`
- `tests/Support/FinalV10RawCapture.php`
- `tests/Support/FinalV10OfflineScorer.php`
- `tests/Support/FinalV10PerfectMirror.php`
- `tests/Support/FinalV10Metrics.php`
- `tests/Feature/FinalV10ScoredRunTest.php`
- `tests/Feature/FinalV10EvaluatorRecoveryTest.php`
- `tests/Feature/FinalV10EvaluatorV3HardeningTest.php`
- `tests/Fixtures/v10_evaluator_v3_development_raw_results.php`

New V3 evidence:

- this report
- `v10-evaluator-v3-contract-inventory.json`
- `v10-evaluator-v3-perfect-mirror-results.json`
- `v10-evaluator-v3-manifest.json`

No application/candidate runtime file was changed by this hardening work.

## 28. EVALUATOR V3 HASH

Evaluator identity: `84757f475c7ba78da00b47fcbe442ede38e9d945ee870043c01811ffdac5af05`.

Procedure: sort repository-relative evaluator-defining paths; update SHA-256 with `path`, NUL, exact file bytes, NUL for each file. Exact file list is frozen in the V3 manifest.

## 29. CANDIDATE HASH VERIFICATION

Phase 12 `ChatFixtureAudit::runtimeHash()` expected/observed:

`d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa` — PASS.

Candidate requests executed in V3 hardening: **0**.

## 30. FINAL V10 HASH VERIFICATION

| Artifact | Expected/observed SHA-256 | Result |
|---|---|---|
| Dataset | `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0` | PASS |
| R6 fresh audit | `3c01c6409b2a59c566916a310ee28a989ad85888d821db95dad52d556b8ca27e` | PASS |
| Final manifest | `af48d07951d013ce0c2670c5a3b563f4d822fc65a549bfcd3919a4992c74c7dd` | PASS |

## 31. INVALID RUN ARTIFACT PRESERVATION

Observed unchanged hashes:

| History | Artifact | SHA-256 |
|---|---|---|
| Invalid #1 | raw | `da281293410092eaa4b07c3de705d7a0445a4fcc79c7c27128ace6718b47a528` |
| Invalid #1 | report | `132893856c50e3b8a787edc585f58c0049126893d7c2359b534832cdba8baf96` |
| Invalid #1 | manifest | `b567910fecd567b8a926ff0276b1a73f71c7afa3155f2f7fbadfa784c5c36c99` |
| Invalid #1 | preflight | `9231e5b305d64d0c0f89fdf0c888d07ccc3f0a81f79dc651b589c45756b27454` |
| Invalid #2 | recovery report | `8eb3a00a167f791323cf304ee552d03b5ea304caddad8623c7c7d30b1b048259` |
| Invalid #2 | evaluator V2 manifest | `70de9ef114b9e6f8f0d7b4ad6f0236166d848662c11557022cfa0f98eee23d14` |
| Invalid #2 | preflight | `255efa701285874c06f2cda5506fb00e9b5d65b69b2336661d8d59e61c16757b` |
| Invalid #2 | raw failure | `ba702db6a8c369f1a7d543608f984a3cd9ef397c2066e1f2b812ea7d39432587` |
| Invalid #2 | score report | `0e90e236c1b06c861616f76c8bb03a365cb6075247ead16fb6f84793fa1252a4` |
| Invalid #2 | execution manifest | `81db1f73d84ce29b8aecb51a2dc6ed05264d7809161606306d126c77ac1eed62` |

## 32. UNVERIFIED ITEMS

- No Final V10 candidate output, runtime quality metric, release decision, storefront behavior, deployment behavior, live Qdrant/LLM behavior, or production latency was evaluated.
- The full backend runtime suite was intentionally not executed because this phase forbids candidate execution.
- A future candidate run remains unauthorized until this V3 evidence is reviewed and separate explicit authorization is provided.

## 33. V3 HARDENING DECISION

**EVALUATOR V3 HARDENING PASS — FROZEN.**

All V3 hardening gates pass: exact failure reproduction, root-cause proof, red-to-green regression, full schema inventory, 292/39/78/56 parsing, perfect mirror, negative mutations, security matrix, entity coverage, raw round trip, deterministic offline scoring, development dry run, PHP/callback review, QA, candidate hash, Final V10 hashes, and invalid-history preservation.

## 34. NEXT STEP

STOP. Do not run case 1, `V10-ST-0289`, or any other Final V10 candidate request. Review Evaluator V3 evidence. A future scored execution requires separate explicit authorization and the new V3 authorization token; it must start from a new artifact set and must never overwrite either invalid history.
