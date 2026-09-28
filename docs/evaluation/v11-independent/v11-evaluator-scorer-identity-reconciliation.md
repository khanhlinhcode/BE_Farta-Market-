# Final V11 Evaluator / Scorer Identity Reconciliation

Date: 2026-09-27  
Decision: **PATH D — V11 EVALUATION TOOLCHAIN RECONCILIATION BLOCKED**  
Candidate requests executed in this phase: **0**

## 1. Executive decision

The blocked V11 run stopped correctly before evaluator preflight, infrastructure
preflight, and candidate request 1. The expected candidate and all three frozen
Final V11 artifacts remain byte-identical to their authorized hashes.

The old identities and the observed identities are both reproducible. There is
no hash-procedure drift. The observed identities result from two exact
file-level changes:

1. the authorized V10 Offline Scorer V4 strict-LF/UTF-8 raw-parser recovery;
2. a later bounded product-name extraction correction that prevents substring
   matches such as `Ổi` inside `tôi`, `hỏi`, or `đổi`.

The metric formulas, denominators, release thresholds, and release-gate code are
byte-unchanged. The product boundary correction can change the entity prediction
produced from a candidate response, but it does not change the score of a fixed
prediction artifact.

Neither the old V3/V4 combination nor the observed current files are a valid
Final V11 toolchain. Both parse the complete V11 structural schema, but both
retain V10-only claim-fact recognition and V10-only candidate/dataset identity
pins. A semantically perfect V11 mirror therefore receives 0/115 claim support,
and correct V11 run metadata is rejected as changed V10 evidence. Freezing the
observed hashes would be a blind acceptance, while restoring V3/V4 would retain
the same V11 incompatibility and would also regress the UTF-8 parser and bounded
product extraction.

No evaluator/scorer freeze manifest was created.

## 2. Frozen identity verification

### Candidate

`ChatFixtureAudit::runtimeHash()` was evaluated without invoking the chatbot.

| Item | Expected | Observed | Result |
|---|---|---|---|
| Phase 14 candidate | `f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf` | `f04745463eb1958f0ce3c0a9c6719ad74c74355feb6f68e1adff077531a8dacf` | PASS |

### Final V11

| Artifact | Expected / observed SHA-256 | Result |
|---|---|---|
| `v11-final-audited-dataset.json` | `94bc6d3930057f7584c80baac66df1f05f98133b94fb733229fe22b49768f3cb` | PASS |
| `v11-r3-fresh-audit-report.md` | `c97a9d4c1e6621cb9c18fc692887b9302de302cc73372f8469e6ca5b22bd70ea` | PASS |
| `v11-final-manifest.json` | `f2ccc7420918cd12957877e305e7a99eecf08b274cd0b3fa4ef63dbba9e35f4e` | PASS |

No frozen V11 file was modified.

## 3. Original hash procedures

The procedures were reconstructed from:

- `v10-evaluator-v3-manifest.json` and
  `v10-evaluator-v3-hardening-report.md`;
- `v10-offline-scorer-v4-manifest.json`,
  `v10-offline-scorer-v4-recovery-report.md`, and
  `v10-final-v4-score-report.md`.

Both identities use SHA-256. Repository-relative paths are sorted in bytewise
ascending order. For each path, the hash context receives:

```text
relative path + NUL + exact file bytes + NUL
```

There is no text normalization, newline normalization, JSON canonicalization,
or separator other than the two NUL bytes per file.

Tests outside the exact lists below, documentation, reports, generated
artifacts, timestamps, and the manifests themselves are not included.

### OLD_EVALUATOR_HASH_FILESET

1. `tests/Feature/FinalV10ScoredRunTest.php`
2. `tests/Support/FinalV10ContractException.php`
3. `tests/Support/FinalV10DatasetContract.php`
4. `tests/Support/FinalV10Evaluation.php`
5. `tests/Support/FinalV10Metrics.php`
6. `tests/Support/FinalV10OfflineScorer.php`
7. `tests/Support/FinalV10RawCapture.php`

Expected Evaluator V3 identity:
`84757f475c7ba78da00b47fcbe442ede38e9d945ee870043c01811ffdac5af05`.

### OLD_SCORER_HASH_FILESET

1. `tests/Support/FinalV10Evaluation.php`
2. `tests/Support/FinalV10Metrics.php`
3. `tests/Support/FinalV10OfflineScorer.php`
4. `tests/Support/FinalV10RawCapture.php`

Expected Offline Scorer V4 identity:
`c7be5acf4f928f50a2a71861371822dde809da9c063f4390dfafd5412e727d87`.

## 4. Identity recomputation

Applying the original procedure to the current contents of the original file
sets produces:

| Identity | Expected historical | Current recomputation | Result |
|---|---|---|---|
| Evaluator | `84757f475c7ba78da00b47fcbe442ede38e9d945ee870043c01811ffdac5af05` | `2512fc321508afbef14bcea718a4cce566dbb98c930be3842d353c38fe473e57` | observed mismatch reproduced |
| Offline scorer | `c7be5acf4f928f50a2a71861371822dde809da9c063f4390dfafd5412e727d87` | `b33b3bbdda1ad774555d13f1e0baeb4252a01b9faf5ebeedf9f8b9fcf8cf3de5` | observed mismatch reproduced |

Classification: **no `HASH_PROCEDURE_DRIFT`**.

## 5. File-level differential

Historical bytes were reconstructed from the recorded file-creation/update
patches and verified by reproducing both authorized combined hashes exactly.

### Evaluator V3 file set

| Path | Historical SHA-256 | Current SHA-256 | Changed | Category | Functional |
|---|---|---|---:|---|---:|
| `tests/Feature/FinalV10ScoredRunTest.php` | `f4fba49e484eb0771518b42a3a2811fe53dd44867f3d652e537d418ed72a84c2` | same | no | F — format/nonfunctional: none | no |
| `tests/Support/FinalV10ContractException.php` | `66aa0dc22fce70ffa897e3fdeb188d55029ef6ae741c5c35f275e790f00fefd9` | same | no | F — format/nonfunctional: none | no |
| `tests/Support/FinalV10DatasetContract.php` | `49f9a25c8603a12ca185a13c53565e5340f9c7fa4493aca64de6332e59f7acef` | same | no | F — format/nonfunctional: none | no |
| `tests/Support/FinalV10Evaluation.php` | `a0600aeec5a2b74c45c6e480730b8a7f74a0890032b6e2694a3e6fee25fd7772` | `3cdf260b0dfa298e04f288aa1a0f59d83489ab4c8c88d3046a6c2ac6a57021dd` | yes | B — previous V10 recovery patch, legitimately retained | yes |
| `tests/Support/FinalV10Metrics.php` | `479b7c84a4cc0f1cb334c045d4b00480e024c7518bc2087b479bc37ff4bcf886` | same | no | F — format/nonfunctional: none | no |
| `tests/Support/FinalV10OfflineScorer.php` | `44e308399221dc10bf23610c87a8cd2ed3303d4145c7f9f542a0ecafed8ad2fa` | same | no | F — format/nonfunctional: none | no |
| `tests/Support/FinalV10RawCapture.php` | `25d739b5627c7a7f2d3167ce3fab0bd9f6aa17fdb80d68e2741f03239c3aca35` | `dcaa99e7fed89f9db368e2674cc6bb6aaf7fa4a97f02ba390c29bfa54edb8c85` | yes | B — authorized Offline Scorer V4 recovery | yes |

The reconstructed seven-file historical combination is exactly
`84757f475c7ba78da00b47fcbe442ede38e9d945ee870043c01811ffdac5af05`.

### Offline Scorer V4 file set

| Path | Historical SHA-256 | Current SHA-256 | Changed | Category | Functional |
|---|---|---|---:|---|---:|
| `tests/Support/FinalV10Evaluation.php` | `a0600aeec5a2b74c45c6e480730b8a7f74a0890032b6e2694a3e6fee25fd7772` | `3cdf260b0dfa298e04f288aa1a0f59d83489ab4c8c88d3046a6c2ac6a57021dd` | yes | B — previous V10 recovery patch, legitimately retained | yes |
| `tests/Support/FinalV10Metrics.php` | `479b7c84a4cc0f1cb334c045d4b00480e024c7518bc2087b479bc37ff4bcf886` | same | no | F — format/nonfunctional: none | no |
| `tests/Support/FinalV10OfflineScorer.php` | `44e308399221dc10bf23610c87a8cd2ed3303d4145c7f9f542a0ecafed8ad2fa` | same | no | F — format/nonfunctional: none | no |
| `tests/Support/FinalV10RawCapture.php` | `dcaa99e7fed89f9db368e2674cc6bb6aaf7fa4a97f02ba390c29bfa54edb8c85` | same | no | B — already part of frozen V4 | no current drift |

The reconstructed four-file historical combination is exactly
`c7be5acf4f928f50a2a71861371822dde809da9c063f4390dfafd5412e727d87`.

## 6. Exact functional deltas

### Raw JSONL parser

Evaluator V3 read the entire file and split with `preg_split('/\\R/',
trim(...))`. Offline Scorer V4/current uses strict physical LF framing,
removes one CR only when it precedes LF, validates UTF-8 before JSON decoding,
preserves scalar values, and reports record, line, and byte offsets.

This affects deserialization only. JSONL serialization and candidate request
semantics are unchanged. The execution runner file is byte-identical.

### Product-name extraction

The old fallback searched normalized product names using unrestricted
`str_contains`. The current fallback caches the normalized message and requires
whitespace/token boundaries around the normalized product name.

Observed probes:

| Response message | Old extraction | Current extraction |
|---|---|---|
| `Tôi muốn hỏi chính sách đổi trả.` | `Ổi` | `null` |
| `Tôi cần hỗ trợ tài khoản.` | `Ổi` | `null` |
| `Cam Tươi còn hàng không?` | `[Ổi, Cam Tươi]` | `Cam Tươi` |
| `Ổi còn hàng không?` | `Ổi` | `Ổi` |

The change is a bounded false-positive correction retained from the V10
development/recovery work. It can affect the canonical-product prediction
created from a raw response. It does not alter `entityEquals`, slot scoring,
metric aggregation, denominators, or thresholds.

## 7. Metric-contract comparison

`FinalV10Metrics.php` and `FinalV10OfflineScorer.php` are byte-identical to the
frozen Scorer V4 files. Within `FinalV10Evaluation.php`, `correctness`,
`slotResult`, `entityEquals`, `factsSatisfied`, `factResult`, terminal mapping,
security adjudication, and first-failure mapping are byte-identical. Only the
response-to-canonical-product adapter changed.

The following remain unchanged:

- Intent Accuracy and confusion-matrix definitions;
- Macro precision, recall, and F1 definitions;
- handler, terminal, and business-outcome denominators;
- entity slot and aggregate entity formulas;
- follow-up and multi-intent definitions;
- OOD, privileged, evidence, claim-support, and security counts;
- all release thresholds and hard-blocker definitions.

Result: **no silent metric-formula or release-gate drift**. A fixed synthetic
V11 artifact produced zero old/current record-score differences and identical
aggregate numerators, denominators, and metrics. The response adapter can still
produce a different input artifact, as documented above.

## 8. V11 schema compatibility

`FinalV10DatasetContract.php` is byte-identical between the old and current
identities. Both therefore produced the same result:

| Scope | Required | Parsed | Result |
|---|---:|---:|---|
| Primary | 160 | 160 | PASS |
| Multi-turn scenarios | 20 | 20 | PASS |
| Scenario turns | 40 | 40 | PASS |
| Multi-intent cases | 20 | 20 | PASS |
| Multi-intent branches | 40 | 40 | PASS |
| Entity objects | 240 | 240 | PASS |
| Exceptions | 0 | 0 | PASS |

Structural result alone is insufficient to declare either toolchain V11
compatible.

## 9. Perfect-gold mirror comparison

A V11 mirror was constructed with exact gold intent, handler, terminal, all
nine entity slots, evidence domain, allowed source IDs, security decision,
branch count, and every minimum required fact included verbatim in its message.
No candidate, database, network, Qdrant, LLM, clock, random, or session input
was used.

Both old and current scoring semantics produced the same result:

| Metric | Result |
|---|---:|
| Intent Accuracy | 160/160 |
| Handler Accuracy | 160/160 |
| Terminal Accuracy | 160/160 |
| Aggregate entity-slot F1 | 1.0 |
| Required evidence domain | 145/145 |
| Evidence eligibility | 200/200 |
| Follow-up direct correctness | perfect where applicable |
| Multi-intent intent/handler/entity/terminal/completeness | perfect where applicable |
| Business Outcome | 79/160 |
| Claim Support | 0/115 |

The existing `FinalV10PerfectMirror` fails even earlier with
`Perfect mirror could not satisfy frozen facts for product_search/ANSWER`.

Root cause: V11 uses new authority-backed full-sentence minimum facts, while
the scorer's deterministic `factResult` allowlist/patterns are V10-specific.
Even verbatim inclusion of a V11 gold fact is not recognized. This is a
legitimate V11 representation that the old/current scorer cannot score.

Outcome for old toolchain: **OLD_TOOLCHAIN_INCOMPATIBLE_WITH_V11**.  
Outcome for observed current toolchain: **INCOMPATIBLE_WITH_V11**.

## 10. Differential negative artifact

One fixed V11-compatible synthetic capture included:

- wrong intent, handler, and terminal;
- wrong canonical product, quantity, unit, account target, and requested
  mutation value;
- one missing branch;
- wrong evidence domain and source;
- an unsupported claim;
- wrong security expectation.

The old frozen evaluation semantics were loaded from reconstructed source bytes
and compared with the current semantics on every primary, branch, and scenario
turn. There were **0 record-score differences**. Aggregate numerators,
denominators, and metrics were identical.

The artifact detected the intent, handler, terminal, five entity-slot, missing
branch, domain, source, unsupported-claim, and security mutations at their
intended record fields. However, claim support was already 0/115 in the
unmutated V11 mirror. Therefore the required negative-mutation gate cannot be
declared PASS against a valid perfect V11 baseline.

Status: **PARTIAL / BLOCKED BY V11 CLAIM-CONTRACT INCOMPATIBILITY**.

## 11. Raw capture and UTF-8 regression

Focused regression result:

```text
Tests\\Unit\\FinalV10RawCaptureV4Test
10 passed, 41 assertions
```

Covered and passed:

- LF and CRLF;
- final line with and without LF;
- Vietnamese and mixed text;
- emoji;
- the multibyte UTF-8 sequence containing byte `0x85`;
- literal Unicode line-separator content;
- escaped newline/carriage-return content;
- blank-record rejection;
- invalid UTF-8 and malformed-JSON diagnostics;
- all 372 frozen V10 records in order;
- deterministic re-score.

No `preg_split('/\\R/')` regression exists in the current parser. The old V3
reader is known incompatible with the `0x85` case; Scorer V4 is the authorized
fix.

## 12. Security synthetic regression

No candidate was executed. Synthetic V11 captures produced:

| Check | Result |
|---|---|
| Cross-account denial | PASS: `v11r1-p017`, `DENIED`, security score true, no unsafe execution |
| Unsafe mutation | PASS: count 1 and hard blocker `unauthorized state mutation` |
| Wrong-entity unsafe action | PASS: count 1 and matching hard blocker |
| Private exposure signal | PASS: hard blocker `private data exposure for another user` |
| Authentication bypass signal | PASS: hard blocker `authentication bypass` |

## 13. V11 metadata incompatibility

The current metric hard-blocker code still pins:

- V10 candidate `d54a66...`; and
- V10 dataset `eb7ea2...`.

When supplied the correct frozen V11 metadata (`f04745...` candidate and
`94bc6d...` dataset), the current scorer reports:

```text
candidate hash changed after verification
Final V10 changed after verification
```

This is not a metric-formula drift. It is an unimplemented V11 identity
contract, and it prevents a valid V11 release decision.

## 14. Determinism and offline isolation

The same fixed V11 synthetic raw capture was scored twice:

| Check | Result |
|---|---|
| Scored result bytes identical | PASS |
| Metrics bytes identical | PASS |
| Scored SHA-256 | `9bb287ffb1ae79b2f190c7d81d447a9d369aee58257653663ec73654cdd8c3f8` |
| Metrics SHA-256 | `d5f64e573f236fd15b70c0ddcd95c279ce2e4dde3693fbdcf28ed782e3a67abd` |
| Network/HTTP/DB/Qdrant call tokens in offline scorer | 0 |

Determinism passes, but deterministic incompatibility is still
incompatibility.

## 15. Hash-scope quality

The historical procedures exclude documentation, generated reports,
timestamps, and manifests. That is appropriate. The evaluator identity includes
the execution runner because it defines capture/request behavior, while the
scorer identity is the smaller offline semantic subset.

An **IDENTITY DESIGN ISSUE** remains: Evaluator V3 and Scorer V4 overlap but
freeze different historical bytes for `FinalV10RawCapture.php`, and neither
manifest records a per-file SHA-256 table. Exact reconstruction was possible
from retained patch history, but future manifests should record every semantic
file hash as required by the V11 prompt. This issue does not justify changing
the procedure merely to force an old identity.

## 16. QA

| Check | Result |
|---|---|
| Raw parser regression | PASS — 10 tests, 41 assertions |
| Focused evaluator/scorer suite excluding locked candidate group | PASS — 51 tests, 609 assertions |
| Offline Scorer V4 artifact writer | SKIPPED by its explicit authorization guard; no artifact rewritten |
| Current V11 structural parse | PASS |
| Current fixed-artifact deterministic re-score | PASS |
| Current security synthetic suite | PASS |
| PHP syntax | PASS — 7 identity files |
| Pint | PASS — 7 identity files |
| `composer validate --strict` | PASS |
| `composer audit --locked` | PASS — no security advisories |
| `git diff --check` and report whitespace check | PASS |
| Candidate execution | NOT RUN |

These QA passes do not make the failed V11 semantic preflight pass.

## 17. Decision-tree result

### PATH A — rejected

The old V3/V4 toolchain parses the shape, but it cannot recognize legitimate
V11 minimum-fact semantics, cannot produce a perfect V11 mirror, retains V10
identity pins, and V3 raw reading regresses UTF-8 handling.

### PATH B — rejected

The two observed changes are legitimate retained corrections and do not alter
metric formulas. Nevertheless, the observed current toolchain is not a valid
V11 revision because claim support and run identity are V10-only.

### PATH C — rejected

The observed hashes are not caused by file-list, ordering, separator, or
nonfunctional-artifact drift. Two executable files changed.

### PATH D — selected

Required safe state cannot be proven. Exact blockers:

1. V11 claim-bearing perfect mirror fails: 0/115 claim support and 79/160
   business outcome.
2. Correct V11 candidate/dataset metadata is rejected by V10 hard-coded
   identity pins.
3. A valid perfect V11 baseline does not exist, so the required negative claim
   mutation gate cannot pass.
4. No reviewed V11 fact-verification contract or versioned V11 evaluator/scorer
   implementation exists to resolve these semantics without inventing rules in
   this reconciliation phase.

## 18. Required next phase

A separate evaluator-only authorization should create a new, explicitly
versioned V11 evaluator/scorer revision that:

- implements deterministic verification for the frozen V11 minimum-fact
  language against the frozen V11 authorities;
- pins the Phase 14 candidate and Final V11 artifact identities without
  changing metric formulas or thresholds;
- retains strict LF/UTF-8 parsing and bounded product extraction;
- passes V11 perfect mirror, negative mutations, security, deterministic
  scoring, and zero-network checks;
- records per-file hashes in new manifests.

Only after that new revision is independently reviewed and frozen may a
separate authorization permit Final V11 candidate execution.

## 19. Final declaration

**V11 EVALUATION TOOLCHAIN RECONCILIATION BLOCKED**

Candidate requests: **0**  
Final V11 candidate execution: **DO NOT RUN**  
Freeze: **NOT CREATED**
