# V11 p012 Schema Failure Diagnosis

## Execution State

- V11 was executed exactly once under run ID `final-v11-20260928T060655Z`.
- The locked raw capture contains its metadata record plus 11 completed primary-request records, `v11r1-p001` through `v11r1-p011`.
- Request `v11r1-p012` was started and failed before its record could be appended to the raw capture.
- `v11-final-execution-state.json` records `cases_started = 12`, `cases_completed = 11`, `cases_failed = 1`, `last_case = v11r1-p012`, and `execution_state = EXECUTION_INCOMPLETE`.
- V11 was not rerun, resumed, or replayed during this diagnosis. No score was generated from the incomplete capture.

## Exact Error

The persisted execution-state error is:

```text
Tests\Support\FinalV11ContractException: V2 prediction security_decision contains an unknown enum value.
```

The exception is thrown by `FinalV11EvaluationV2::assertPredictionSchema()` at `tests/Support/FinalV11EvaluationV2.php:49-50` while recursively normalizing a safe branch of the p012 dependent-shipping route. The failure occurs inside `FinalV11ExecutionHarness::observe()` before `FinalV11RawCapture::appendPrimary()`, which explains why p012 is absent from the locked JSONL capture while the execution guard records it as the failed request.

## Observed Value

The exact observed `security_decision` value was:

```text
ALLOW
```

This value is established without using p012 gold:

1. `FinalV11ExecutionHarness::runtimeEnvelope()` is the sole producer of `runtime.authorization_result` for this execution path (`tests/Support/FinalV11ExecutionHarness.php:286-307`).
2. For a safe branch, `FinalV11ExecutionHarness::authorization()` returns `ALLOW` (`tests/Support/FinalV11ExecutionHarness.php:361-395`, specifically line 394 for the public product/shipping branches).
3. `FinalV11RuntimeAdapterV2::normalize()` first requires that value to be a non-empty string, copies it unchanged to both `authorization_result` and `security_outcome`, and then projects `security_outcome` unchanged to prediction `security_decision` (`tests/Support/FinalV11RuntimeAdapterV2.php:69,85,93,120`).
4. Every other string the harness can return at that boundary is in the rejecting enum. `ALLOW` is the only harness-produced authorization value excluded from the parent `security_decision` set. A null or non-string value would have failed earlier with `runtime.authorization_result must be a non-empty string`, not with the persisted enum error.

The candidate did not emit a `security_decision` field. It emitted typed route/response state; the execution harness derived `ALLOW` for the safe branch.

## Allowed Enum

At the failing V2 prediction validation boundary, the allowed `security_decision` set was:

```text
ALLOW_OWNED_READ
ALLOW_READ
ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES
DENY
DENY_CROSS_ACCOUNT
NOT_APPLICABLE
PER_BRANCH
REQUIRE_AUTH
SUGGEST_ONLY
```

`FinalV11EvaluationV2::enums()` loads this set from `docs/evaluation/v11-independent/v11-sanitized-schema-contract.json`, `enum_definitions.security_decision` (`tests/Support/FinalV11EvaluationV2.php:108-133`; contract lines 1316-1332).

The same frozen schema contract separately defines the branch-only enum `enum_definitions.branch_security_expectation` as:

```text
ALLOW
DENY
SUGGEST_ONLY
```

That branch enum is attached to `primary_cases[].multi_intent_branches[].security_expectation` (contract lines 1364-1373). `FinalV10DatasetContract::branchGold()` preserves this distinction and projects a valid branch expectation such as `ALLOW` into the branch prediction field named `security_decision` (`tests/Support/FinalV10DatasetContract.php:309-329`). V11 deliberately reuses that frozen structural parser through `FinalV11DatasetContract::parse()`.

Therefore, `ALLOW` is contract-valid for a branch even though it is not valid for a parent prediction.

## Provenance

The value path is:

```text
candidate typed dependent-shipping route
  -> harness branch runtime envelope: authorization_result = ALLOW
  -> Runtime Adapter V2: authorization_result = ALLOW
  -> Runtime Adapter V2: security_outcome = ALLOW
  -> V2 branch prediction: security_decision = ALLOW
  -> Evaluator V2 validates against parent security_decision enum
  -> rejection: unknown enum value
```

Detailed boundary ownership:

1. **Candidate runtime contract.** `ChatRouteFrame` permits typed composite branches and validates that every branch is itself a route frame (`app/Services/Chat/ChatRouteFrame.php:17-73`). The deterministic dependent-shipping path constructs a product-price branch and a shipping-current-value branch with `composition = shipping_eligibility` (`app/Services/Chat/ChatIntentRouter.php:456-475`). This is an existing, valid candidate path; the candidate does not define or emit evaluation enum `security_decision`.
2. **Execution harness.** `FinalV11ExecutionHarness::observe()` recursively creates a runtime envelope for each typed branch. Its closed authorization map deliberately uses the branch vocabulary and returns `ALLOW` for a safe public branch (`tests/Support/FinalV11ExecutionHarness.php:163-190,286-307,361-395`). This is where the exact value is first produced.
3. **Runtime Adapter V2.** `FinalV11RuntimeAdapterV2::normalize()` recursively normalizes branches (`tests/Support/FinalV11RuntimeAdapterV2.php:67,225-246`) and copies `authorization_result` unchanged through `security_outcome` to `security_decision`. It does not transform `ALLOW` into another value.
4. **Prediction schema.** `FinalV11EvaluationV2::assertPredictionSchema()` has no parent-versus-branch context parameter. It validates every prediction's `security_decision` against only `enum_definitions.security_decision` (`tests/Support/FinalV11EvaluationV2.php:34-51,108-133`). It never selects `enum_definitions.branch_security_expectation` for recursively normalized branches.
5. **Validator rejection.** Recursive branch normalization calls `assertPredictionSchema()` before returning the branch observation (`tests/Support/FinalV11RuntimeAdapterV2.php:102-104`). The first safe p012 branch therefore fails before the parent observation and raw-capture record can be completed.

No serialization or JSONL normalization changed the value; serialization was never reached for p012.

## Contract-Layer Comparison

| Layer | Treatment of `ALLOW` | Result |
| --- | --- | --- |
| Candidate runtime contract | Does not emit evaluation security enums; valid safe composite branches are permitted | Candidate contract not violated |
| Execution harness authorization map | Emits `ALLOW` for an allowed branch | Valid branch decision |
| Runtime Adapter V2 contract/code | Preserves each branch's authorization result and projects it to `security_decision` | `ALLOW` preserved unchanged |
| Frozen sanitized schema contract | `ALLOW` is in `branch_security_expectation`; not in parent `security_decision` | Context-sensitive valid value |
| Evaluator V2 prediction schema | Applies only parent `security_decision` enum to both parent and branch predictions | Incorrect rejection |
| Frozen evaluation/scoring contract | Branch gold uses branch security expectation but scores it through the common `security_decision` field | Requires branch-aware validation |
| Toolchain V2 tests | Exercise parent `ALLOW_READ` and denied branches, not a branch `ALLOW` prediction | Gap present |
| Phase 15 tests | Exercise valid supported branches and dependent-shipping composition only at candidate/router/API boundaries | Candidate path covered; evaluator bridge not covered |
| Security regressions | Exercise denials, authorization boundaries, and safe sibling routing, but do not send a safe branch through V2 schema validation | No protection against this schema mismatch |

The exact value was already represented in the repository:

- frozen schema: `branch_security_expectation = [ALLOW, DENY, SUGGEST_ONLY]`;
- inherited dataset parser: `FinalV10DatasetContract::BRANCH_SECURITY_EXPECTATIONS` includes `ALLOW`;
- inherited branch-gold projection: `branchGold()` maps it to branch `security_decision`;
- older evaluator hardening tests and Phase 14 development mapping also represent `ALLOW`.

It was not represented as a safe branch `authorization_result` in either V11 Toolchain V2 synthetic fixtures or the final-execution harness synthetic fixtures.

## Root Cause Classification

**CLASS C — Evaluator/Prediction Schema Bug**

Evidence:

- `ALLOW` is explicitly valid under the frozen branch security contract.
- The harness emits it only in branch context.
- Runtime Adapter V2 preserves it unchanged.
- Evaluator V2 applies the parent enum to a branch prediction and rejects the valid branch enum.
- The candidate does not emit the rejected evaluation field.
- JSONL capture/serialization was not reached for p012.

This is not CLASS A because the candidate did not emit an invalid evaluation enum. It is not CLASS B because the adapter did not transform a valid value into a different invalid value. It is not CLASS D because no serialization behavior altered the value. The evidence is sufficient, so it is not CLASS E.

## Why Preflight Missed It

The coverage gap is concrete and cross-layer:

- The 16/16 final-execution harness tests and 76/76 assertions used an all-denied synthetic multi-intent fixture. Both branch observations carried `DENY`; there was no safe branch carrying `ALLOW`.
- The Toolchain V2 synthetic fixture likewise represented multi-intent only with two denied mutation branches. Its other security values were parent values such as `ALLOW_READ`, `NOT_APPLICABLE`, and `ALLOW_SAFE_BRANCHES_DENY_PROHIBITED_BRANCHES`.
- The V2 schema negative test changed `terminal` to `LATEST`; it did not exercise branch-context selection for `security_decision`.
- The harness multi-intent test asserted branch count and parent intent/terminal, but did not assert safe branch authorization vocabulary.
- Phase 15 and the relevant security regressions covered supported sibling branches and the dependent-shipping candidate path, but stopped at route/API behavior. They did not pass those candidate branches through `FinalV11ExecutionHarness -> FinalV11RuntimeAdapterV2 -> FinalV11EvaluationV2`.
- Accordingly, the reported 57/57 relevant regression tests and 333/333 assertions protected candidate routing, authorization, and response behavior, but not the evaluator's parent-versus-branch enum selection. Their passing result is consistent with this cross-layer gap.
- The preserved evaluator tests knew that `ALLOW` is a valid branch value, but the new V2 prediction-schema assertion introduced a single context-free enum check. Existing tests did validate enum values; they validated the wrong enum scope for branches.

Thus the enum was not absent from the contracts or older tests. It was absent specifically from the V2 synthetic safe-branch fixtures. p012 exercised a valid candidate path that had been tested at the candidate layer but had never been tested end-to-end through the V2 branch prediction boundary.

`ALLOW` is not genuinely invalid. It is invalid only when incorrectly interpreted as a parent-level decision.

## Gold Isolation Check

V11 gold was **not needed** to identify the observed value or prove the schema violation.

The value `ALLOW` follows from the persisted exact error, the exhaustive harness authorization producer, the adapter's identity projection, and the schema enum sets. The p012 raw record was not available because validation failed before append. Dataset structure and frozen contract definitions were inspected only to compare parent and branch contracts; no p012 expected answer or p012 gold security decision was used to derive the runtime value.

## Candidate Impact

The evidence proves an **infrastructure/toolchain bug in Evaluator V2 prediction-schema validation**. It does not prove a candidate bug.

This diagnosis makes no claim about what p012's eventual score would have been and does not score the incomplete 11-request capture. It establishes only that the recorded schema failure cannot be attributed to the candidate because the rejected field was derived by the harness and was valid for its branch context.

## Frozen Artifact Integrity

All identities were recomputed read-only during diagnosis and match the frozen values:

| Artifact/component | Observed SHA-256 / semantic identity | Status |
| --- | --- | --- |
| Candidate | `af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9` | unchanged |
| V11 dataset | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | unchanged |
| V11 audit | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | unchanged |
| V11 manifest | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | unchanged |
| Locked raw capture | `2f5f7cac8fdc0fad0f01d491b31f10423e28c0fff3aac638a090b36fe407f081` | unchanged |
| Evaluator V2 | `df40ceb8336814629eeb5ae93a0dce5e623a2c3d8e2f964f24e5789f3a1ba2de` | unchanged |
| Scorer V2 | `6dd5ebece571b35eb89b4c9d244300e64b9a2c9491294f1b13277f6d6a406f09` | unchanged |
| Toolchain V2 | `9fcf7f1de8f42156074a65ac71255098c1945144bcbb940cfc222a02ecbdbd9b` | unchanged |
| Runtime Adapter V2 | `4567cd4db35fbd34bac3f82a536b24bb20032227aa8dc6808964fd2399ec899e` | unchanged |
| Final-execution harness | `5bf892ec4ba037bb2054984996ccd398d21409f1d4227155bac32cdd5bc53f87` | unchanged |

The raw capture remains read-only and was not rewritten, appended, normalized, or regenerated. No candidate, dataset, audit, manifest, evaluator, scorer, toolchain, adapter, harness, or test file was modified. This diagnosis report is the only created artifact.

## Recommended Next Action

Create and separately freeze a new Evaluator V2 revision whose prediction validator is context-aware and validates branch `security_decision` against `branch_security_expectation` (including a synthetic safe-branch `ALLOW` regression in that same revision).

Do not implement that action as part of this diagnosis, and do not rerun or resume V11 under the current execution state.
