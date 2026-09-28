# V11 Toolchain V2-r1 — Context-aware branch security

## Result

Toolchain V2-r1 is implemented, tested, and frozen without executing or scoring V11. The revision fixes the evaluator boundary that rejected a valid safe branch with `security_decision=ALLOW`.

The historical execution remains incomplete and unscored: it was attempted exactly once, completed 11 records, and stopped while processing the twelfth request. The locked raw capture and execution-state artifact remain byte-identical.

## Root cause and fix

Evaluator V2 had one context-free prediction validator. It applied the frozen parent `security_decision` enum to both parent and branch predictions. The runtime adapter correctly preserved the safe branch value `ALLOW`, but the evaluator interpreted it as a parent value and rejected it.

V2-r1 introduces an explicit closed validation scope:

- `PARENT` selects the existing parent enum;
- `BRANCH` selects the existing `branch_security_expectation` enum;
- missing or unknown scope fails closed.

Scope comes only from runtime/capture structure. It is passed as a validator argument and is not added to the scorer-compatible prediction payload. It is never inferred from enum values, answer text, keywords, record IDs, or gold labels.

The parent enum remains unchanged. In particular, parent `ALLOW` remains invalid. The branch enum remains exactly `ALLOW`, `DENY`, and `SUGGEST_ONLY`; branch `PER_BRANCH` remains invalid.

## Versioned implementation

The historical V2 components remain immutable. The revision adds:

- `FinalV11EvaluationV2R1`, which requires explicit scope and preserves every non-security V2 schema rule;
- `FinalV11RuntimeAdapterV2R1`, which validates the top-level observation as parent and recursively validates branches as branches;
- `FinalV11OfflineScorerV2R1`, which selects scope from capture nesting and preserves V2 claim reconciliation plus the frozen V1 metric core;
- `FinalV11ExecutionHarnessR1`, a synthetic-only harness boundary with no V11 runner or execution entry point.

The prediction fields, field order, claim IDs, score formulas, thresholds, metric definitions, and gold comparison behavior are unchanged.

## Regression coverage

The direct regression proves both sides of the diagnosed defect:

- original Evaluator V2 rejects synthetic branch `ALLOW` through its context-free path;
- Evaluator V2-r1 accepts the same synthetic prediction under explicit `BRANCH` scope;
- the revised synthetic harness independently produces one `ALLOW` safe branch and one `DENY` prohibited branch and validates both.

Negative coverage rejects parent `ALLOW`, branch `ALLOW_READ`, branch `PER_BRANCH`, unknown enum values, missing scope, unknown scope, and an invalid sibling hidden beside a valid branch.

## Validation

| Suite | Tests | Assertions | Result |
| --- | ---: | ---: | --- |
| V2-r1 remediation | 27/27 | 63/63 | PASS |
| Original Toolchain V2 | 16/16 | 68/68 | PASS |
| Preserved evaluator/scorer core | 9/9 | 263/263 | PASS |
| Phase 15 candidate regression | 25/25 | 189/189 | PASS |
| Combined | 77/77 | 583/583 | PASS |
| Focused PHP style | 6/6 files | — | PASS |

Determinism, offline operation, gold-independent scope selection, prediction-schema compatibility, scorer-semantics compatibility, and frozen-identity integrity all pass.

## Frozen identities

| Component | Semantic identity |
| --- | --- |
| Evaluator V2-r1 | `0fcce11e5650340c6112372e3f879948555696ed66edb2c64ecfd60e68dee9ef` |
| Runtime Adapter V2-r1 | `ff1db1e229c5498a0599a8cbf3c3223dc9c3e1b972c448107c8bd97fbcfd9ed8` |
| Scorer V2-r1 | `adff5766ebed64125561be979c72f691240f563d54bfe3ff8b81a415fb8bf798` |
| Harness R1 | `b5f628d48b6adae8f52b44997a087616d64f36aeadd2cfcdd7ddc63a25f78fe2` |
| Toolchain V2-r1 | `29714cce29739581347fe36c3fd2ab179a88d8f81cf1dfdb07fba6e0ea639418` |

The exact semantic file sets are recorded in `v11-toolchain-v2-r1-manifest.json`.

## Execution and artifact integrity

- Candidate: unchanged.
- V11 dataset, audit, and final manifest: unchanged.
- Original Toolchain V2, Evaluator V2, Runtime Adapter V2, Scorer V2, and harness: unchanged.
- Locked raw capture: unchanged.
- Historical execution state: unchanged.
- Historical V11 execution count: `1`.
- V11 requests during remediation: `0`.
- V11 executed during remediation: `false`.
- V11 scored: `false`.
- V11 status: `NOT_EVALUATED`.
- Commit, push, deploy: not performed.

No V11 execution runner was created or invoked. A future execution requires a separate authorization and must not reuse or overwrite the historical incomplete execution artifacts.
