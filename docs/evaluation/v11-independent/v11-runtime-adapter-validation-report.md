# V11 Runtime Adapter Validation Report

Status: **V11 RUNTIME ADAPTER FREEZE BLOCKED**

Candidate requests: **0 / 200**  
Retries: **0**  
Final V11 candidate execution: **NOT STARTED**

## Scope and decision

This phase inspected the frozen candidate response contract, route DTOs,
serializers, completion telemetry, existing non-V11 execution code, and the
frozen V11 scorer contracts. It did not send any Final V11 utterance to the
candidate.

The adapter and execution harness cannot be frozen without changing evaluation
semantics. The blocking result is recorded in
`v11-runtime-adapter-contract.json`. No adapter implementation, execution
harness, freeze manifest, or Toolchain V2 manifest was created, because a
freeze artifact would incorrectly represent failed gates as passed.

## Frozen identity verification

| Artifact | Expected SHA-256 | Observed SHA-256 | Result |
|---|---|---|---|
| Phase 14 candidate | `f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf` | `f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf` | PASS |
| Final V11 dataset | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | PASS |
| R3 audit report | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | PASS |
| Final manifest | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | PASS |
| Preflight record | `1c6a394098e728d228487ca071adbadb746ca3f55ff8e0ae2230038bf583fdba` | `1c6a394098e728d228487ca071adbadb746ca3f55ff8e0ae2230038bf583fdba` | PASS |
| V11 Evaluator V1 semantic identity | `66963e4fa9d21ac832b8d9c4654ad32909b6fb57239dafabed8aaf8ea4767eb2` | `66963e4fa9d21ac832b8d9c4654ad32909b6fb57239dafabed8aaf8ea4767eb2` | PASS |
| V11 Offline Scorer V1 semantic identity | `adb279e855ee8efd09b56af9994204c9f72eb19368b4b807005f3ad95b7ef06a` | `adb279e855ee8efd09b56af9994204c9f72eb19368b4b807005f3ad95b7ef06a` | PASS |
| Evaluation contract | `f51bc21f66d31c2b03cf94a2ae602896d4a251ad053ba72760c707f6bd8dfa20` | `f51bc21f66d31c2b03cf94a2ae602896d4a251ad053ba72760c707f6bd8dfa20` | PASS |
| Fact-verification contract | `8ee38762fe7d51392bb3ec644a44913077722ef108a38102df648fc8c1a1c604` | `8ee38762fe7d51392bb3ec644a44913077722ef108a38102df648fc8c1a1c604` | PASS |

The candidate identity was recomputed by the existing Phase 14 freeze test. No
candidate endpoint was invoked.

## Runtime contract findings

The normal candidate JSON response exposes the routed intent, semantic intent,
resource, operation, decision state, products, suggested actions, source,
optional order/auth/status/citations/retrieval data, composition, and branch
subresponses. The route DTO additionally contains typed entities, evidence
domain, denial reason, concepts, branches, and routing telemetry. Completion
logs contain source-level accepted/retrieved IDs and the evidence topic.

These observations are sufficient to define deterministic terminal, handler,
entity, evidence-domain, security, source-ID, and source-version mappings. The
source version join can be pinned to the frozen product, site-setting,
knowledge, and authorization registries without a network or live database
lookup.

They are not sufficient for two required scorer semantics.

## Blocking finding 1: business outcome collision

The frozen runtime classifies both an order-state mutation request and a
payment-state mutation request as the same structured state:

```text
intent=unsupported
semantic_intent=privileged_mutation
resource=order_or_payment
operation=mutate
decision_state=denied_action
denial_reason=order_or_payment_mutation
terminal=DENIED
```

The frozen V11 scorer requires two different prediction values:

- `DENY_CHATBOT_ORDER_MUTATION`
- `DENY_PAYMENT_STATE_MUTATION`

No structured runtime discriminator separates these cases. Reading the request
or response text and guessing which category applies would add a heuristic
category outside the frozen candidate contract. Therefore this state must map
to `ADAPTER_UNMAPPABLE_OUTCOME`.

## Blocking finding 2: claim evidence provenance

Runtime citations contain only `source_id`, `title`, and `section`. Completion
telemetry contains accepted source IDs, not claim IDs or retained structured
claim-to-source links. Product, site-setting, and order responses likewise do
not carry scorer claim IDs.

The frozen fact verifier expects each `claim_evidence[].claim_id` to equal:

```text
sha256:<SHA-256(record_id NUL exact minimum_facts_required string)>
```

The exact `minimum_facts_required` string is gold. Looking it up through the
fact contract would violate the zero-gold-access rule. Recovering it from the
candidate's free-text answer would require semantic/heuristic inference, which
the phase explicitly forbids. A source ID by itself is not enough to identify
which record-specific fact was asserted.

The only safe adapter value for the current runtime is an empty
`claim_evidence` list. That correctly prevents fabricated credit, but it makes
the required perfect applicable claim-support mirror impossible. The adapter
therefore cannot pass the freeze gates.

## Validation performed

- Frozen hash rechecks: PASS.
- Phase 14 candidate freeze test: PASS, 1 test / 2 assertions.
- Frozen evaluator/scorer synthetic suite: PASS, 10 tests / 268 assertions.
- Runtime field inventory: COMPLETE.
- Prediction field inventory: COMPLETE.
- Deterministic source-version registry join design: PASS.
- Claim-evidence anti-fabrication analysis: PASS; unsafe construction rejected.
- Zero-gold dependency review: PASS; proposed unsafe gold lookup rejected.
- Candidate requests: 0.
- Final V11 utterances executed: 0.

The following success-only checks were not run because their prerequisites do
not exist:

- Adapter determinism: NOT RUN — no valid total adapter can be implemented.
- Harness raw-to-adapter pipeline: NOT RUN — no valid adapter exists.
- Perfect adapter pipeline mirror: BLOCKED — claim evidence is unavailable.
- Negative adapter suite: NOT RUN — no adapter was frozen.
- Adapter semantic identity: NOT CREATED.
- Harness semantic identity: NOT CREATED.
- Toolchain V2: NOT CREATED.

## Required boundary to unblock

Unblocking would require a new candidate contract that emits both:

1. a structured protected-resource subtype distinguishing order-state from
   payment-state mutation; and
2. structured claim-level provenance carrying stable claim identities and the
   exact source IDs supporting each claim.

That is a candidate instrumentation/semantics change and is expressly outside
this phase. It must not be introduced by the evaluator adapter.

## Final decision

**V11 RUNTIME ADAPTER FREEZE BLOCKED — INSUFFICIENT OBSERVABLE RUNTIME
PROVENANCE.**

Do not run Final V11.
