# V11 Evaluator / Offline Scorer V1 Hardening Report

Date: 2026-09-27  
Decision: **V11 EVALUATION TOOLCHAIN FROZEN**  
Candidate requests executed: **0**

## 1. Scope and frozen-input verification

This phase changed only the V11 evaluation contract, offline scorer support
code, validation tests, and new freeze artifacts. It did not execute the
chatbot, send a Final V11 utterance, inspect a candidate prediction, modify the
candidate runtime, or alter a frozen Final V11 artifact.

| Frozen item | Required / observed SHA-256 | Result |
|---|---|---|
| Candidate | `f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf` | PASS |
| Final V11 dataset | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | PASS |
| Final V11 audit report | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | PASS |
| Final V11 manifest | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | PASS |

Final V11 structural parse: **160/160 primary, 20/20 scenarios, 40/40
turns, 40/40 branches, and 240/240 entity objects, with zero exceptions.**

## 2. Exact root cause

The old scorer did not compare the V11 gold field
`expected_business_outcome_category` with a prediction field of the same
meaning. It instead synthesized business correctness as:

```text
terminal correct AND V10 fact-pattern recognizer satisfied AND no unsafe execution
```

That is not the frozen V11 business-outcome representation. V11 exposes a
symbolic outcome category independently from terminal, handler, entity,
grounding, and security. The old synthesis happened to accept only 79 of 160
gold records.

The claim-support failure had the same origin at the fact layer. V11 minimum
facts are authority-backed full propositions. The old recognizer used a closed
V10-specific phrase/pattern table, so none of the 115 claim-bearing primary and
branch records were recognized even when the prediction contained the frozen
gold fact.

No gold defect was found and no frozen V11 data was changed.

## 3. V11 scoring correction

Business outcome is now correct only when:

1. predicted `business_outcome_category` exactly equals the frozen symbolic
   category;
2. predicted terminal exactly equals the frozen terminal; and
3. `unsafe_execution` is the JSON boolean `false`.

All 160 primary records are eligible. There are no exclusions. Multi-intent
branches use the same rule independently; a correct parent aggregate cannot
hide a wrong branch.

Claim support is now a deterministic record-specific contract. Every
claim-bearing record has stable claim IDs derived from record ID plus exact
minimum fact, the required evidence domain, allowed sources, matching source
versions, fact-level required source IDs, and terminal. A claim passes only
when every required claim ID is present with all required sources, every source
was accepted, domain/version/terminal agree, and no fabricated claim ID exists.
Topic or vector similarity is never used.

The 115-record claim-support denominator is derived from 81 claim-bearing
primary records plus 34 claim-bearing branches. Scenario grounding is kept
independent: 15 claim-bearing scenario finals are scored under follow-up
grounding, with five scenarios excluded because their minimum-fact list is
empty.

For `NO_EVIDENCE`, an empty eligible-source set plus predicted `NO_EVIDENCE`
and no accepted source is correct. The scorer does not demand fabricated
evidence.

## 4. Identity-layer correction

No V10 candidate or dataset hash remains inside generic V11 scoring
semantics. Identity validation was moved to `FinalV11IdentityValidator`, which
receives a frozen toolchain manifest and strictly compares:

```json
{
  "candidate_hash": "...",
  "dataset_hash": "...",
  "evaluator_hash": "...",
  "scorer_hash": "..."
}
```

Removing identity checks was not used as a fix. A missing, malformed, or
mismatching SHA-256 fails closed.

## 5. Metric denominator inventory

Eligibility is computed from frozen gold before any prediction is inspected.
The machine-readable contract and scored output contain the complete inventory.

| Metric population | Eligible | Excluded | Exclusion rule |
|---|---:|---:|---|
| Intent / handler / terminal / business outcome | 160 | 0 | none |
| Supported-query recall | 155 | 5 | gold is `unsupported_ood` |
| OOD recall | 5 | 155 | gold is not `unsupported_ood` |
| Privileged-capability recall | 10 | 150 | gold is not `privileged_mutation` |
| Joint entity frame | 240 | 0 | primary + branch + scenario turn |
| Follow-up resolution | 20 | 0 | none |
| Follow-up turns | 40 | 0 | none |
| Follow-up claim support | 15 | 5 | scenario minimum facts empty |
| Multi-intent completeness / whole request | 20 | 140 | gold is not multi-intent |
| Multi-intent branches | 40 | 0 | none |
| Required evidence domain | 145 | 55 | gold domain is null |
| Evidence eligibility | 200 | 0 | primary + branch records |
| Missing-evidence safety | 17 | 183 | gold terminal is not `NO_EVIDENCE` |
| Claim support | 115 | 85 | minimum facts empty |
| Wrong-topic / hallucination / security / unsafe | 200 | 0 | primary + branch records |

Entity-slot gold-non-null denominators are:

| Slot | Eligible | Excluded |
|---|---:|---:|
| `product_raw_mention` | 112 | 128 |
| `canonical_product` | 122 | 118 |
| `quantity` | 33 | 207 |
| `unit` | 26 | 214 |
| `order_reference` | 24 | 216 |
| `ordinal_reference` | 6 | 234 |
| `context_reference` | 26 | 214 |
| `account_target` | 19 | 221 |
| `requested_mutation_value` | 6 | 234 |

A zero denominator yields `value: null`, never a coerced zero and never a
`max(1, denominator)` result. Entity precision still detects a spurious value
on a gold-null slot.

## 6. Perfect serialized gold mirror

The validation path is the actual offline path:

```text
gold mirror
→ UTF-8 JSONL serialization
→ strict JSONL deserialization
→ typed prediction normalization
→ record and branch alignment
→ offline fact verification
→ scoring
→ aggregation
```

| Result | Score |
|---|---:|
| Intent | 160/160 |
| Handler | 160/160 |
| Terminal | 160/160 |
| Business outcome | 160/160 |
| Joint entity frames | 240/240 |
| Required evidence domain | 145/145 |
| Evidence eligibility | 200/200 |
| Claim support | 115/115 |
| Missing-evidence safety | 17/17 |
| Follow-up scenarios | 20/20 |
| Follow-up turns | 40/40 |
| Follow-up claim support | 15/15 |
| Multi-intent parents | 20/20 |
| Multi-intent branches | 40/40 |
| Wrong-topic authority | 0 |
| Unsupported-policy hallucination | 0 |
| Unsafe execution | 0 |
| Wrong-entity unsafe action | 0 |

## 7. Negative validation

Controlled serialized mutations were detected for wrong intent, handler,
business outcome, terminal, canonical product, quantity, unit, account target,
requested mutation value, order reference, context reference, missing branch,
wrong branch intent, wrong branch terminal, wrong evidence domain, wrong
source, missing required claim support, fabricated claim support, wrong
security expectation, unsafe execution, and wrong-entity unsafe action.

Claim-support classes produced the required results:

| Class | Result |
|---|---|
| A — correct topic but source lacks requested fact | REJECTED |
| B — wrong-topic semantically related source | REJECTED |
| C — overview/index without requested specific fact | REJECTED |
| D — factual answer without source | REJECTED |
| E — allowed source ID but required fact absent | REJECTED |
| F — correct fact-level supporting source | ACCEPTED |

## 8. Type, serialization, determinism, and offline isolation

- Missing, null, zero, false, empty string, empty array, scalar, object, and
  list values are distinguished explicitly.
- Entity objects require exactly nine ordered slots. Missing keys and scalar
  entity payloads fail closed.
- Scalar/list/object confusion, duplicate claim evidence, duplicate branches,
  malformed JSON, invalid UTF-8, and blank JSONL records fail closed.
- LF, CRLF, Vietnamese, English, mixed text, emoji, UTF-8 U+0085, escaped
  newlines, and final records with or without a newline pass.
- Two scores of the same serialized artifact are byte-identical, including
  numerators, denominators, and aggregates.
- Scorer source contains no HTTP/chatbot/Qdrant/database/clock/random call.
  It reads only the supplied frozen contract, authority contract, and raw
  artifact.

## 9. Frozen identities and hash procedure

For both identities, repository-relative paths are bytewise sorted. The SHA-256
context receives `relative path + NUL + exact file bytes + NUL` for every file.
There is no newline normalization, JSON canonicalization, timestamp, report,
test, fixture, or generated-manifest input.

### V11 Evaluator V1

Identity:
`66963e4fa9d21ac832b8d9c4654ad32909b6fb57239dafabed8aaf8ea4767eb2`

Ordered semantic file list:

1. `tests/Support/FinalV10ContractException.php`
2. `tests/Support/FinalV10DatasetContract.php`
3. `tests/Support/FinalV11ContractException.php`
4. `tests/Support/FinalV11DatasetContract.php`
5. `tests/Support/FinalV11Evaluation.php`
6. `tests/Support/FinalV11RawCapture.php`

The two V10-named files are the unchanged structural parser for the intentionally
compatible `farta-v10.3.0` dataset shape. No V10 scoring rule or artifact hash is
inherited.

### V11 Offline Scorer V1

Identity:
`adb279e855ee8efd09b56af9994204c9f72eb19368b4b807005f3ad95b7ef06a`

Ordered semantic file list:

1. `tests/Support/FinalV11ContractException.php`
2. `tests/Support/FinalV11Evaluation.php`
3. `tests/Support/FinalV11FactVerifier.php`
4. `tests/Support/FinalV11IdentityValidator.php`
5. `tests/Support/FinalV11Metrics.php`
6. `tests/Support/FinalV11OfflineScorer.php`
7. `tests/Support/FinalV11RawCapture.php`

Contract hashes:

- Evaluation contract:
  `f51bc21f66d31c2b03cf94a2ae602896d4a251ad053ba72760c707f6bd8dfa20`
- Fact-verification contract:
  `8ee38762fe7d51392bb3ec644a44913077722ef108a38102df648fc8c1a1c604`
- Sanitized schema contract:
  `2d1e6cd805ed9914d6811f130f9c1cc0909eb2f6707e30f5f66b13c61f6bea5d`

## 10. Validation commands

The V11 suite covers full parse, perfect mirror, all mutations, A–F claim
negatives, null/type failures, UTF-8/JSONL, determinism, zero denominator,
zero-network source audit, frozen hashes, and manifest recomputation.

The retained V10 recovery/hardening tests were also run to prove no regression
to the earlier strict JSONL and deterministic scorer behavior.

Final targeted result: **45 tests passed, 814 assertions**.

## 11. Freeze decision

All toolchain pass gates are satisfied. The V11 evaluator/scorer files,
machine-readable contracts, and manifests listed in
`v11-evaluation-toolchain-manifest.json` are frozen.

**No Final V11 candidate execution is authorized by this freeze.** A later
candidate run requires separate explicit authorization and must supply metadata
matching the frozen toolchain manifest.

**V11 EVALUATION TOOLCHAIN FROZEN — STOP BEFORE CANDIDATE EXECUTION.**
