# Phase 15 — Semantic mutation-target remediation

## Scope and holdout protection

This phase changes the local candidate only. Development work used the existing
runtime architecture, Phase 14 documentation, synthetic cases, and V0--V10
development fixtures. Final V11 remained frozen: no V11 utterance or gold record
was read, no V11 request was executed, and no V11 dataset, audit, evaluator, or
scorer artifact was modified. Candidate requests against V11: **0**.

Parent candidate:
`f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf`

Phase 15 candidate:
`af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9`

The parent hash remains the immutable historical Phase 14 identity. The new
hash is produced by `ChatFixtureAudit::runtimeHash()` and has its own freeze
test.

## Root cause

The previous route model exposed a capability shaped as
`order_or_payment / mutate`. Concept extraction could establish that ORDER and
PAYMENT were mentioned, but capability resolution collapsed those mentions
before preserving which object the action and requested state actually changed.
The shared security terminal consequently emitted only
`order_or_payment_mutation`. Mention presence was being used where an
operation-specific semantic target was required.

This was a candidate architecture problem. It was not an evaluator adapter,
gold, evidence, or answer-text problem.

## Mutation target flow map

Before:

`raw message -> concepts/entities -> capability(order_or_payment, mutate) -> generic denial -> handler`

The target was lost when capability classification combined two mentioned
resources into `order_or_payment`.

After:

`raw message -> normalized concepts/entities -> ChatMutationTargetResolver -> typed route frame -> branch construction -> context resolution -> capability(resource, operation) -> deterministic authorization/guard -> canonicalization-preserving frame -> handler -> terminal response`

The authoritative target is resolved once, before capability selection.
Capability consumes it; it does not infer it again. `withEntities()` and the
other immutable frame transformations preserve the target during
canonicalization and telemetry enrichment.

## Semantic contract

`ChatRouteFrame` now carries three distinct fields:

- `mentionedResources`: every protected resource explicitly mentioned;
- `operation`: the operation selected for the route or branch;
- `mutationTarget`: nullable `ChatMutationTarget::Order` or
  `ChatMutationTarget::Payment`.

`ChatMutationTargetResolution` enforces that a selected target belongs to a
mutation operation. `ChatRouteFrame` validates protected resource vocabulary
and ambiguous-state invariants. API responses expose
`mentioned_resources` and `mutation_target` for deterministic outcome and audit
classification.

Target resolution uses the relationship among the mutation action, its direct
object, and the requested state/value. It does not choose the first or last
resource, count keywords, use confidence ranking, or default to ORDER/PAYMENT.
Entity extraction remains responsible only for what was mentioned; the new
resolver owns which resource is operated on.

## ORDER/PAYMENT and read/mutation behavior

An ORDER state such as delivered/completed resolves to the ORDER target even
when payment appears as context. A payment state such as paid/failed/refunded
resolves to PAYMENT even when an order reference is present. ORDER and PAYMENT
reads retain `operation=read` and have no mutation target. Policy questions and
bank-transfer wording are not promoted to execution requests merely because
they contain words that can also appear in state-changing commands.

For state coercion, Vietnamese and English action/state relations are handled
symmetrically. A state-only conjunct is resolved only when its parent already
establishes a mutation action; the same standalone state word is not treated as
a command.

## Ambiguity

When a mutating request contains ORDER and PAYMENT but provides no unique
action/object/state relationship, `mutationTarget` remains null and the route
uses the existing `clarification` terminal with reason `mutation_target`.
Neither protected resource is selected by default.

## Capability, handler, and business outcome

`ChatCapabilityGuard` consumes the typed target and produces distinct
`order / mutate / order_mutation` or
`payment / mutate / payment_mutation` capabilities. The guard never grants a
mutation. The existing security-denial handler receives the resolved target in
the immutable route or branch frame and returns it in response metadata, so
ORDER and PAYMENT outcomes are distinguishable without answer-text or
evaluation-specific mapping.

No duplicate mutation handler was introduced. Chat mutation attempts continue
through the existing denial path; safe reads continue through the existing
order tool and its ownership checks.

## Multi-intent

Target resolution is branch-local. Explicit ORDER and PAYMENT mutation clauses
retain separate enum targets, capabilities, denial reasons, handler inputs, and
subresponse metadata. Mixed read/mutation requests preserve the supported read
while denying only the prohibited branch. Coordinated state values such as an
ORDER completion plus a PAYMENT paid state become two independently targeted
branches. A target-free mutation conjunct may inherit only the unique semantic
target established by its full compound request; an explicit branch target is
never overwritten by its parent.

## Follow-up context

The existing session context now stores a resolved mutation-target enum value
under its own `mutation_target` stage. A target-free follow-up such as an
equivalent of “update again” restores that value before capability enforcement.
If no valid target context exists, the follow-up clarifies. Product context and
mutation-target context remain one bounded context mechanism rather than two
parallel systems.

## Authorization and security

Semantic target resolution does not grant permission. Chat ORDER/PAYMENT
mutations remain denied for anonymous, customer, wrong-account, owner, and
privileged actors. Authorized reads still require the existing verified actor,
ownership, and account-scope checks in `ChatOrderTool`. Focused tests snapshot
ORDER and PAYMENT state before requests and verify that no database state or
external HTTP state changes.

Final V10 development security results remain:

| Gate | Result |
| --- | ---: |
| Unsafe execution | 0 |
| Wrong-entity unsafe action | 0 |
| Wrong-topic authority | 0 |
| Unsupported-policy hallucination | 0 |
| Privileged capability precision / recall | 100% / 100% |
| OOD precision / recall | 100% / 100% |

## Development test matrix

Focused synthetic coverage varies Vietnamese/English wording, word order,
entity order, ORDER/PAYMENT co-occurrence, explicit and implicit state targets,
read versus mutation, genuine ambiguity, branch ordering, elliptical conjuncts,
and follow-up wording. It also covers wrong-account ORDER and PAYMENT attempts,
anonymous and privileged actors, benign owner reads, handler metadata, and
no-state-change assertions.

The final combined backend run executed unit, adversarial security, grounded
chat, Phase 15, grouped V0--V9, and full V10 suites successfully. The dedicated
Phase 15 suite contains 24 passing tests. PHP style validation passes for all 16
affected files.

## V0--V10 regression and Phase 14 comparison

The canonical grouped V0--V9 benchmark and full V10 development regression
both pass. Minor aggregate classification movement is recorded rather than
hidden; all safety invariants and required release gates remain passing.

| Metric | Phase 14 | Phase 15 |
| --- | ---: | ---: |
| V0--V9 intent accuracy | 96.88% | 96.31% |
| V0--V9 macro-F1 | 96.16% | 95.48% |
| V0--V9 supported-query recall | 97.87% | 97.87% |
| V0--V9 OOD recall | 100% | 100% |
| V0--V9 privileged recall | 100% | 100% |
| V10 intent accuracy | 99.32% | 98.97% |
| V10 macro-F1 | 99.20% | 98.72% |
| V10 handler accuracy | 100% | 99.66% |
| V10 business outcome | 99.32% | 99.32% |
| V10 terminal accuracy | 100% | 100% |
| V10 canonical product resolution | 100% | 100% |
| V10 requested mutation-value recall | 100% | 100% |
| V10 follow-up resolution | 100% | 100% |
| V10 multi-intent completeness | 100% | 100% |
| V10 required evidence domain | 100% | 99.61% |
| V10 evidence eligibility | 98.85% | 98.85% |
| V10 claim support | 96.42% | 96.42% |

All 19 V10 release assertions pass. No V10 gold or evaluator contract was
changed.

## Performance

The resolver is deterministic, bounded regex/enum logic and performs no model,
network, database, or vector-search call. No dependency was added.

| Benchmark | Phase 14 p50 / p95 | Phase 15 p50 / p95 |
| --- | ---: | ---: |
| Grouped V0--V9 router | 1.09 / 2.62 ms | 1.14 / 2.71 ms |
| V10 primary router | 1.32 / 2.55 ms | 1.54 / 3.35 ms |

These are local development measurements rather than a production SLO. The
bounded cost and absence of semantic-model fallback satisfy the Phase 15
performance gate.

## QA note

`npm run build` was attempted because it is the only package script besides
`dev`; the unrelated existing frontend configuration cannot resolve the
`axios` import from `resources/js/bootstrap.js` because `axios` is absent from
`package.json` and the current installation. Phase 15 did not change frontend
files or add a dependency. No lint or typecheck npm scripts exist.

## Phase status

- Mutation target, ORDER/PAYMENT distinction, ambiguity, capability and handler
  propagation: **PASS**.
- Deterministic authorization, branch-local multi-intent, and follow-up target
  preservation: **PASS**.
- V0--V10 development and security regression: **PASS**.
- Performance: **PASS**.
- Final V11: **FROZEN / NOT EXECUTED / UNCHANGED**.
- Commit, push, deploy: **NOT PERFORMED**.
