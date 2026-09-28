# Final V10 Offline Scorer V4 Recovery Report

## Recovery decision

**SCORER V4 RECOVERY PASS.** Only the offline JSONL deserializer changed. Candidate/runtime code, Final V10, the raw capture, gold labels, metric formulas, denominators, thresholds, and release gates were not changed.

## Frozen raw capture verification

- Path: `docs/evaluation/v10-independent/v10-final-v3-raw-capture.jsonl`
- Complete SHA-256: `bcf07fa6a9c0e0554237fffad7e7806cef5271c0837a92fd197b61ca59ffe6d3` (exact match to V3 execution manifest)
- Bytes: 731,860
- Physical JSONL records: 372/372
- Execution records: 292 primary + 78 scenario turns; metadata and completion framing records account for the other two physical records.
- First/last primary IDs: `V10-ST-0099` / `V10-ST-0190`
- First/last scenario-turn IDs: `V10-MT-035/turn-1` / `V10-MT-005/turn-2`
- Unique primary IDs: 292/292; unique scenario-turn IDs: 78/78
- Ordered execution-ID checksum (`SHA-256(join("\n", IDs))`): `6a2ec1eea54dcb116d2ecb214f192d4b70f068ce16210a9cff8f9acf0b11fee1`

## Failure reproduction (RED)

The exact pre-patch code path `FinalV10RawCapture::deserialize()` called `preg_split('/\R/', trim($jsonLines))`, then failed at `tests/Support/FinalV10RawCapture.php:104` in `json_decode(..., JSON_THROW_ON_ERROR)`.

- Minimal fixture: a metadata JSON object containing U+0105 `ą`.
- Verified UTF-8 bytes: `C4 85`; the fixture contains byte `0x85`.
- Pre-patch result: `JsonException: Malformed UTF-8 characters, possibly incorrectly encoded`.
- Frozen raw reproduction: same exception and stack through `FinalV10RawCapture::read()`.
- Frozen raw contains 48 occurrences of byte `0x85`.
- First triggering byte: zero-based offset 39,649 (one-based byte 39,650), record index 21, line 22, case `V10-ST-0110`; strict LF extraction of that record decodes successfully.
- Regression was observed RED before the patch and GREEN after it.

## Root cause and PCRE/JSONL contract

The artifact is UTF-8 JSON Lines: one JSON value per physical LF-delimited line. In byte mode, PCRE `\R` recognizes byte `0x85` as a newline sequence. `0x85` is also a legitimate continuation byte within multibyte UTF-8—for U+0105 the sequence is `C4 85`. Splitting at that byte cuts a valid code point into invalid byte fragments, so subsequent JSON decoding reports malformed UTF-8. Adding `/u` would only change regex interpretation; it would still encode the wrong abstraction because JSONL does not define arbitrary Unicode newline code points as record boundaries.

## Scorer V4 patch

`FinalV10RawCapture::read()` now streams with `fopen(..., 'rb')` and `fgets()`. `deserialize()` uses equivalent strict `0x0A` framing. Exactly one terminal `0x0D` is removed only when it precedes an LF. Empty records are rejected. Every record is validated as UTF-8 before `json_decode(..., JSON_THROW_ON_ERROR)`. Failures carry zero-based record index, one-based line, zero-based byte offset, and error type. No generic trim, normalization, case folding, accent conversion, byte replacement, or decoded prediction mutation occurs.

## Regression and contract results

| Check | Result |
|---|---|
| ASCII / English / Vietnamese / mixed VN-EN / emoji | PASS |
| U+0105 containing byte `0x85` and exact round trip | PASS |
| Literal U+0085 and U+2028 content | PASS |
| LF / CRLF / final LF / legal final record without LF | PASS |
| Escaped `\n` / escaped `\r` stay inside the JSON value | PASS |
| Empty string / numeric zero / boolean false / null | PASS |
| Blank record rejected | PASS |
| Invalid UTF-8 rejected before JSON decode | PASS |
| Malformed JSON stops with record/line/byte/type | PASS |
| Frozen capture parse | PASS 372/372; 0 malformed, lost, duplicated, or reordered |
| Per-record deterministic JSON encode/decode semantic equality | PASS 372/372 |
| Perfect mirror | PASS; claim-bearing 307/307 and all applicable correctness metrics 100% |
| Negative mutations | PASS 15/15 detected |
| Fixed-artifact deterministic re-score | PASS; identical record bytes and metrics |
| Full gold-mirror dry run | PASS; 0 exceptions |

## PHP type-safety review

Reviewed the offline-only files for regex line framing, UTF-8 assumptions, scalar array access, `count()`/`foreach` on scalars, implicit truthiness, `0`/`false`/empty/null distinctions, silent JSON errors, and unchecked file reads. Existing frozen scorer contract guards remain intact. The deserializer now checks `fopen`, `fgets` completion/`feof`, UTF-8, object shape, record types, duplicate IDs, prediction shapes, and JSON exceptions explicitly. No production runtime file changed.

## QA

- Focused parser suite: PASS, 10 tests / 41 assertions.
- Full evaluator/scorer suite: PASS, 60 tests / 650 assertions.
- PHP syntax: PASS.
- Pint: PASS (2 touched PHP files).
- `composer validate --strict`: PASS.
- `composer audit`: PASS, no advisories.
- `git diff --check`: PASS.

## Scorer V4 identity

- Hash procedure: sort repository-relative paths; for each file hash `path + NUL + exact bytes + NUL` with SHA-256.
- Files: `tests/Support/FinalV10Evaluation.php`, `tests/Support/FinalV10Metrics.php`, `tests/Support/FinalV10OfflineScorer.php`, `tests/Support/FinalV10RawCapture.php`.
- Scorer V4 SHA-256: `c7be5acf4f928f50a2a71861371822dde809da9c063f4390dfafd5412e727d87`.
- Evaluator V3 historical SHA-256: `84757f475c7ba78da00b47fcbe442ede38e9d945ee870043c01811ffdac5af05`.
- Candidate SHA-256: `d54a66b96a4ae27bd482afc156ad5e03955524ee04fb590ddd697b2d1c8106aa`.
- Final V10 hashes: dataset `eb7ea21cad08cb80a4463f63735d514f40019b1bd804f15a3db13de9bc71eaa0`; audit report `3c01c6409b2a59c566916a310ee28a989ad85888d821db95dad52d556b8ca27e`; manifest `af48d07951d013ce0c2670c5a3b563f4d822fc65a549bfcd3919a4992c74c7dd`.
- JSONL contract SHA-256: `697bc4a0013a50a66df7874cd7891de6d1415db50004102a95b27dad84f01679`.

## Integrity attestation

Candidate hash verification passed. Final dataset, audit report, manifest, and raw-capture hashes passed. **NO CANDIDATE RE-EXECUTION.** Scorer recovery used only frozen local artifacts and made no network, chatbot, router, controller, model, Qdrant, or database-backed candidate request.
