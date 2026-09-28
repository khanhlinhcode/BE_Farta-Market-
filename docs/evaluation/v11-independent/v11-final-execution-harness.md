# V11 Final Execution Harness

## Purpose and boundary

The final-execution harness closes the orchestration gap between the frozen V11 dataset and Toolchain V2. It is a separate component with identity `5bf892ec4ba037bb2054984996ccd398d21409f1d4227155bac32cdd5bc53f87`; it does not replace or modify Toolchain V2.

This build prepares a future one-shot execution. It has not executed a V11 case, created a V11 raw capture, or scored V11. The frozen runner requires a separate authorization value and the dedicated `scripts/v11-final-execution` command.

## Local execution architecture

The candidate entry point is the existing `POST /api/chat` route, dispatched through Laravel's in-process test kernel. The runner uses an isolated migrated test database, the frozen sanitized product and SiteSetting snapshots, local knowledge resources, and disabled external-generation/vector services. Stray HTTP requests are prohibited.

The pipeline is:

```text
frozen dataset input identity
  -> isolated session and actor preconditions
  -> local POST /api/chat
  -> typed Phase 15 route observation
  -> structured response and state snapshot capture
  -> frozen Runtime Adapter V2
  -> immutable JSONL raw capture
  -> frozen Offline Scorer V2
  -> scored results, metrics, report, and execution manifest
```

The harness contains no raw-text intent classifier, entity extractor, mutation-target resolver, handler router, or authorization bypass. It calls the existing Phase 15 services to observe the same typed route used by the local candidate. Terminal and authorization capture are closed mappings of explicit typed route state, response codes/status, actor state, and structured output. No expected V11 label participates in candidate invocation.

## Candidate input and gold isolation

Candidate request bodies contain only `message`. Case, scenario, turn, session, actor, language, symbolic fixture, and branch identifiers are orchestration metadata outside the request body. Keys representing expected intent, entities, handlers, business outcomes, claims, labels, branches, grounding gold, or minimum facts are rejected at the candidate-input boundary.

Symbolic order references are replaced with isolated local order IDs before invocation and restored in captured typed entities afterward. This is an execution-fixture identity mapping, not a semantic prediction and not a gold-label input.

## Multi-turn handling

All turns in one scenario share one session identifier and execute in declared order. The runner preserves the candidate's local session cookie and reconstructs the observer input only from the candidate's prior structured response and typed route state. It never reconstructs context from expected answers. A declared expired runtime context clears the isolated candidate cache before the later turn.

## Multi-intent handling

The original user utterance is sent once. The candidate's typed route branches and structured `subresponses` are captured in order; the harness does not split the utterance. Branch evidence is scoped by typed canonical product IDs. Dataset branch IDs are attached only as scorer join identities after candidate execution. A count mismatch receives observed branch IDs and therefore fails completeness rather than being hidden.

## Runtime observation and Toolchain V2

The capture includes the typed route, entity scope, capabilities, resource, operation, exact Phase 15 `mutation_target`, ambiguity state, terminal, authorization result, branch observations, mutation follow-up state, structured evidence, accepted source versions, response, protected-state hashes, unsafe execution, and wrong-entity adjudication.

`mentioned_resources` is diagnostic only. The harness never derives or overrides `mutation_target`. A protected state change without an explicit wrong-entity adjudication fails closed as `MISSING OBSERVABILITY`.

Toolchain V2 remains frozen at `9fcf7f1de8f42156074a65ac71255098c1945144bcbb940cfc222a02ecbdbd9b`. The harness invokes Runtime Adapter V2 and Offline Scorer V2 without changing them.

## Raw capture

The future runner uses `FinalV11RawCapture` JSONL schema `farta-v11-evaluator-v1-raw-capture.1.0`. Metadata records candidate, dataset, toolchain, evaluator, scorer, adapter, target, run ID, and retry count. Every record preserves input identity, executed request, runtime observation, adapter observation, prediction, final response, status, error, latency, and state-change result.

The raw path is exclusive-create. On completion or failure, its SHA-256 is calculated and the file is changed to mode `0444`. The normal runner has no overwrite, resume, or retry path.

## Exactly-once protection and failure handling

Before the first request, `FinalV11ExecutionGuard` exclusively creates `v11-final-execution-state.json`. Its states are:

```text
EXECUTION_LOCK_ACQUIRED -> EXECUTING -> COMPLETED
                                   \-> EXECUTION_INCOMPLETE
```

An existing state file refuses every later attempt. Progress persists `cases_started`, `cases_completed`, `cases_failed`, and `last_case`. An exception freezes any partial capture, records the failure, transitions to `EXECUTION_INCOMPLETE`, and stops. There is no automatic retry, resume, failed-case rerun, or second-run reset.

## Synthetic validation and determinism

Synthetic fixtures cover single-turn, multi-turn, multi-intent, ORDER mutation, PAYMENT mutation, ambiguous mutation, claim evidence, and security denial. Tests also cover gold isolation, local-target integrity, exactly-once refusal, incomplete state, JSONL round-trip and freezing, deterministic observation/content hashing, frozen identities, schema/count compatibility, and offline operation.

Normalized observations and canonical content hashes are deterministic. Wall-clock fields (`started_at_utc`, `completed_at_utc`, `recorded_at_utc`) and `latency_ms` are explicitly excluded from deterministic content hashing.

## V11 immutability and authorization

Preflight reads V11 only to validate existence, SHA-256, schema, and aggregate counts. It does not execute or derive gold. Candidate, dataset, audit, final manifest, Toolchain V2, Evaluator V2, Scorer V2, Runtime Adapter V2, and contracts remain unchanged.

`scripts/v11-final-execution-harness-preflight` rejects execution flags and performs no V11 request. The frozen runner refuses to start unless a separate task explicitly supplies:

```text
V11_FINAL_EXECUTION_AUTHORIZATION=RUN_FROZEN_V11_ONCE
```

That value must not be supplied during build, test, documentation, or preflight.
