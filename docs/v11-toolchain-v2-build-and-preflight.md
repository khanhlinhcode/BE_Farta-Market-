# V11 Toolchain V2 — Build and Preflight

## 1. Scope

V11 Toolchain V2 translates the Phase 15 typed runtime route, structured handler outcome, and actual evidence provenance into a deterministic normalized evaluation observation and the frozen V11 prediction shape. It adds an offline evidence harness, schema guard, canonical claim IDs, scorer-boundary reconciliation, focused tests, hashes, and a build-only preflight command.

This work does not change chatbot candidate source, Phase 15 semantic logic, the V11 dataset, V11 audit, V11 final manifest, evaluation contract, or fact-verification contract. It does not send a candidate request, create a V11 raw capture, score V11, produce V11 business or claim-support results, deploy, commit, or push.

## 2. Previous V1 blocker

V1 correctly froze dataset parsing, raw JSONL behavior, evaluation primitives, fact-verification rules, metrics, negative tests, identity validation, and offline scoring. It lacked a candidate-runtime adapter and execution harness.

Two values could not be produced safely:

- ORDER and PAYMENT mutations both collapsed to `order_or_payment_mutation`, so `DENY_CHATBOT_ORDER_MUTATION` and `DENY_PAYMENT_STATE_MUTATION` were observationally indistinguishable.
- Runtime responses exposed source-level provenance, while V1 claim IDs were record-local hashes of `record_id + exact gold fact`. A candidate-blind adapter could not construct those IDs without gold.

Phase 15 resolved the first runtime problem by adding `ChatMutationTarget`, `mutation_target`, `mutation_target_ambiguous`, branch-local targets, and follow-up target state. V2 resolves the evaluation boundary without modifying that candidate.

The V1 evaluator and scorer remain byte-identical at semantic identities `66963e4f...` and `adb279e8...`. The old V1 identity test still names the Phase 14 parent candidate by design; V2 uses a separate Phase 15 candidate gate.

## 3. Architecture

Discovered before implementation:

- V1 evaluator/scorer: `tests/Support/FinalV11*.php`.
- Frozen contracts and authority registries: `docs/evaluation/v11-independent/`.
- Phase 15 typed route: `ChatRouteFrame` with semantic intent, capability, resource, operation, entities, evidence domain, branches, `mutationTarget`, and ambiguity state.
- Phase 15 follow-up: `ChatContextResolver` restores the typed target from bounded session context.
- Runtime provenance: structured product/order response objects, typed handler identity, accepted citation source/section, and frozen authority versions.

Before:

```text
candidate
   ↓
runtime
   ↓
??? missing adapter ???
   ↓
frozen evaluator/scorer
```

After:

```text
candidate
   ↓
typed runtime state + structured provenance
   ↓
V2 evidence harness + runtime adapter
   ↓
normalized evaluation observation
   ↓
frozen evaluation schema
   ↓
V2 schema/provenance guard
   ↓
preserved V1 metric core
```

The implementation is evaluation-only under `tests/Support`; `ChatFixtureAudit::runtimeHash()` therefore remains the Phase 15 candidate identity.

## 4. Mutation target mapping

The adapter reads only the Phase 15 typed `mutation_target`:

| Runtime state | Normalized state |
| --- | --- |
| `mutation_target=order` | `mutation_target=order` |
| `mutation_target=payment` | `mutation_target=payment` |
| `mutation_target=null`, ambiguous | null target plus explicit ambiguity |

`mentioned_resources` is retained only for diagnostics. It never selects or overrides the target. Tests deliberately reverse mention order and prove the typed target wins. A mutating ORDER/PAYMENT resource without its required Phase 15 target fails closed.

## 5. Business outcome mapping

The exact categories come from the frozen sanitized schema and business-capability contracts:

| Typed semantic state | V11 category |
| --- | --- |
| `privileged_mutation`, ORDER, `DENIED` | `DENY_CHATBOT_ORDER_MUTATION` |
| `privileged_mutation`, PAYMENT, `DENIED` | `DENY_PAYMENT_STATE_MUTATION` |
| unresolved/ambiguous target | `ASK_TARGETED_CLARIFYING_QUESTION` |
| multi-intent parent | `PROCESS_EVERY_BRANCH_WITH_EXPLICIT_TERMINAL_STATE` |

All other V1 outcome mappings remain closed enum mappings. No answer-text parser, mutation classifier, keyword fallback, resource-frequency rule, or ORDER/PAYMENT default exists in V2.

## 6. Claim evidence

The evidence harness observes only typed route semantics and structured runtime fields:

```text
structured product/order/site-setting field or published citation section
    ↓
source_id + authority + source_version + evidence_ref + evidence_type
    ↓
canonical claim_key
    ↓
content-addressed claim_id
```

Examples:

- `product:8:current_unit_price_vnd` → `product.8.price.current`;
- `product:8:current_stock` → `product.8.stock.current`;
- `site-setting:shipping_fee_vnd` → `shipping.fee.current`;
- a published section → `knowledge.<source_id>.section.<section-ref-sha256>`.

The claim ID is `sha256:<SHA-256(adapter-version NUL canonical-claim-key)>`. Callers cannot supply `claim_key`. The normalized observation preserves `claim_key`, authority, version, evidence reference, and evidence type; the frozen prediction projection contains only `claim_id` and `source_ids`.

At the scorer boundary, V2 injectively reconciles canonical claim IDs to the existing frozen record-local IDs using exact required-source sets. The adapter never receives record IDs or gold facts. Missing, duplicate, extra, wrong-source, wrong-version, wrong-domain, and wrong-terminal evidence still reaches the preserved V1 fact verifier and fails closed.

`accepted_source_versions` comes from the frozen authority registry and is checked against actual provenance. Missing, conflicting, unknown, or fabricated versions fail preflight validation.

This is gold-independent because the adapter/harness source contains no V11 dataset lookup, author-row lookup, record-claim lookup, case-ID lookup, or expected-label input. A test varies hypothetical gold labels outside the adapter input and obtains byte-identical output.

## 7. Multi-intent

Every `ChatRouteFrame` branch receives its own runtime observation and is normalized recursively. ORDER and PAYMENT targets, outcomes, authorization results, evidence, and terminals remain branch-local. Parent mention order and parent resource frequency cannot change either branch.

## 8. Follow-up

V2 consumes the context-resolved target already present on the Phase 15 route. When `follow_up_state.resolved_by_context=true`, the follow-up target must equal the typed route target. Missing or ambiguous context remains null and cannot be guessed from the latest utterance.

## 9. Tests

| Suite | Result |
| --- | ---: |
| V2 focused preflight | 16/16 tests, 68/68 assertions |
| Preserved V1 core regression | 9/9 tests, 263/263 assertions |
| Phase 15 mutation target + candidate freeze | 25/25 tests, 189/189 assertions |
| Focused Pint | 6/6 files |
| Combined PHP tests | 50/50 tests, 520/520 assertions |

Focused V2 coverage includes ORDER, PAYMENT, reversed mention order, ambiguity, multi-intent branches, follow-up context, multiple fact keys, structured and published authority provenance, missing/false provenance, gold independence, exact schema, canonical-to-frozen scorer reconciliation, V1 compatibility, determinism, offline source audit, candidate identity, all frozen V11 identities, and the zero-request guard.

The frontend build was not run because no frontend file changed; the task records the pre-existing unrelated missing-`axios` blocker and forbids installing unrelated dependencies.

## 10. Hashes

All semantic identities use the existing project convention: sort relative paths and hash each relative path, NUL, exact bytes, NUL with SHA-256.

| Artifact | SHA-256 / semantic identity |
| --- | --- |
| Toolchain V2 | `9fcf7f1de8f42156074a65ac71255098c1945144bcbb940cfc222a02ecbdbd9b` |
| Evaluator V2 | `df40ceb8336814629eeb5ae93a0dce5e623a2c3d8e2f964f24e5789f3a1ba2de` |
| Scorer V2 | `6dd5ebece571b35eb89b4c9d244300e64b9a2c9491294f1b13277f6d6a406f09` |
| Runtime adapter + evidence harness | `4567cd4db35fbd34bac3f82a536b24bb20032227aa8dc6808964fd2399ec899e` |
| Evaluation contract | `f51bc21f66d31c2b03cf94a2ae602896d4a251ad053ba72760c707f6bd8dfa20` |
| Fact-verification contract | `8ee38762fe7d51392bb3ec644a44913077722ef108a38102df648fc8c1a1c604` |
| Adapter V2 contract | `53b1ae811888f62d09a379c6e8905f2604528ac6c1bb9e22ffd7b1420aaa20cb` |
| Synthetic fixture set | `4effa7741d2f2d1bdc0eb0c855aeee05ddc793c4a9eae73973ebe614418ec054` |
| Focused test suite | `bba763bd79805ec31bec9d6476e2dbf38ec5a6a61e5b74ae94b2a5d3148e5386` |
| Test report | `e63690e65d7d46c5632ff15b781210c5409ec591850d1d9f4016128db128aa91` |
| Preflight report | `b8ee3c5094be044ca1c6c17e18ad284c82ffd4cc8cd67ddad9b35eca4861abb3` |
| Toolchain manifest file | `eb94601be1454807d64029ee71dc0f957004f2ac0b536e8b60fdd461246eccf1` |
| Preflight command | `90d4ee8615ef81678e9a3c81f986020acd40e76b0e49e8172e07046c2a1d0634` |

## 11. Frozen V11 identities

| Frozen artifact | Expected | Observed | Status |
| --- | --- | --- | --- |
| Dataset | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | same | UNCHANGED |
| Audit | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | same | UNCHANGED |
| Final manifest | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | same | UNCHANGED |

No V11 raw capture, scored result, business-outcome result, claim-support result, or execution manifest was created.

## 12. Preflight result

```text
PASS
```

All 18 gates passed. The dedicated command exits if execution environment flags request V11 execution and prints:

```text
V11_REQUESTS = 0
V11_EXECUTED = false
```

## 13. V11 status

```text
FROZEN / NOT EXECUTED
```

## 14. Candidate status

```text
Phase 15 candidate unchanged.
```

Candidate identity: `af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9`.

Commit: NO  
Push: NO  
Deploy: NO

## Machine-readable summary

```text
V11 TOOLCHAIN V2
================

Candidate:
af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9

Toolchain V2:
9fcf7f1de8f42156074a65ac71255098c1945144bcbb940cfc222a02ecbdbd9b

Evaluator V2:
df40ceb8336814629eeb5ae93a0dce5e623a2c3d8e2f964f24e5789f3a1ba2de

Scorer V2:
6dd5ebece571b35eb89b4c9d244300e64b9a2c9491294f1b13277f6d6a406f09

Runtime Adapter:
4567cd4db35fbd34bac3f82a536b24bb20032227aa8dc6808964fd2399ec899e

Evaluation Contract:
f51bc21f66d31c2b03cf94a2ae602896d4a251ad053ba72760c707f6bd8dfa20

Fact Verification Contract:
8ee38762fe7d51392bb3ec644a44913077722ef108a38102df648fc8c1a1c604

Adapter Contract:
53b1ae811888f62d09a379c6e8905f2604528ac6c1bb9e22ffd7b1420aaa20cb

Tests:
16/16

Assertions:
68/68

Mutation Target:
PASS

ORDER/PAYMENT Mapping:
PASS

Business Outcome Mapping:
PASS

Claim Evidence:
PASS

Gold Independence:
PASS

Multi-intent:
PASS

Follow-up:
PASS

Schema:
PASS

Determinism:
PASS

Negative Tests:
PASS

Offline:
PASS

V11 Dataset:
UNCHANGED

V11 Audit:
UNCHANGED

V11 Manifest:
UNCHANGED

Candidate:
UNCHANGED

V11 Requests:
0

V11 Executed:
false

PREFLIGHT:
PASS

V11 Status:
FROZEN / NOT EXECUTED
```
