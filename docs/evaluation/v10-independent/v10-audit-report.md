# Blind V10 Independent Leakage and Gold Audit

## 1. Executive summary

**Decision: AUDIT FAIL — LEAKAGE + GOLD + DISTRIBUTION / SCHEMA.**

The independent author freeze is byte-consistent, JSON-valid, and complete for
V0--V9 leakage coverage. The candidate itself is not acceptable for blind
execution. The audit found four contaminated primary cases and nine
contaminated multi-turn scenarios, material gold defects, nonexistent approved
knowledge source identifiers, nondeterministic authentication preconditions,
and systematic generator/ID artifacts.

The chatbot candidate was not executed. No predictions were read. No runtime,
router, prompt, dependency, or configuration was changed. The immutable author
dataset was not modified. No final audited dataset or final manifest was
created.

Boundary disclosure: during initial task discovery, the IDE-active
`docs/chat-phase-12-evaluation-and-candidate.md` record was displayed before
the audit source filter was applied, and a broad authority search surfaced a
few isolated runtime source-line matches. No candidate prediction was present
or used, and those runtime lines were not used as audit evidence, but this is
not pristine auditor isolation. It is another reason this failed revision must
not be treated as a final independent freeze; a fresh auditor should audit the
next author revision.

## 2. Auditor scope

Read scope was limited to:

- the three frozen V10 author artifacts;
- V0--V9 fixture data for leakage comparison;
- published behavioral/authority documentation;
- current product, category, SiteSetting, and knowledge registry rows, queried
  read-only from MySQL;
- the published knowledge JSON registry and its approved content.

The initial boundary exposure disclosed in section 1 was excluded as audit
evidence. All reported leakage findings come from fixture comparisons, and all
reported gold findings come from the business contract or authoritative
structured/source data.

The audit did not run the chatbot, any blind scorer, any prediction-producing
test, or any candidate endpoint. It did not inspect predictions or use candidate
behavior to define gold.

## 3. Author artifact integrity

Author freeze integrity is **PASS**.

| Artifact | SHA-256 | Result |
| --- | --- | --- |
| `v10-candidate-dataset.json` | `13558286c9d61237ab968c82da81943524cd2db34ded4584a978b2759943b2b2` | Matches expected and manifest |
| `v10-authoring-report.md` | `a4ded044ebfa4cd33da19e98268e30e3c00057e0a70f020a2ac2c05910549fdb` | Matches expected and manifest |
| `freeze-manifest.json` | `ee4662cd605e54ca8744b5f25a10a34d04e395d36e2482472dc2449ea55c4eda` | Valid manifest |

JSON parsing passed. Schema is `farta-v10.1.1`. Counts independently verified:
292 primary cases, 39 scenarios, 78 turns, 27 multi-intent cases, and 56
annotated branches. IDs are unique.

## 4. Historical datasets audited

Leakage coverage is **COMPLETE** for the repository's V0--V9 fixtures. The
extractor retained repeated references so provenance was not lost.

| Version | Fixture | Extracted utterance references |
| --- | --- | ---: |
| V0 | `tests/Fixtures/chat_evaluation.php` | 187 |
| V1 | `tests/Fixtures/chat_blind_holdout.php` | 130 |
| V2 | `tests/Fixtures/chat_blind_v2.php` | 144 |
| V3 | `tests/Fixtures/chat_blind_v3.php` | 159 |
| V4 | `tests/Fixtures/chat_blind_v4.php` | 216 |
| V5 | `tests/Fixtures/chat_blind_v5.php` | 259 |
| V6 | `tests/Fixtures/chat_blind_v6.php` | 284 |
| V7 | `tests/Fixtures/chat_blind_v7.php` | 410 |
| V8 | `tests/Fixtures/chat_blind_v8.php` | 366 |
| V9 | `tests/Fixtures/chat_blind_v9.php` | 328 |

In total, 2,483 historical utterance references and 152 historical follow-up
sequences were compared with 370 V10 individual utterances and 39 V10 complete
two-turn sequences.

## 5. Exact duplicate results

- Exact raw-text matches: **0**.
- Exact complete-scenario matches: **0**.

## 6. Normalized duplicate results

Normalization used Unicode NFC, Unicode-aware lowercase, punctuation-to-space,
and whitespace collapse. It did not remove semantic words, identities, or
quantities.

One normalized exact match was found:

| V10 reference | Historical reference | Classification |
| --- | --- | --- |
| `V10-MT-001:T2` | `V7:follow_up:019:t2` | **TRUE LEAKAGE** — only case/punctuation differ |

No normalized exact complete-scenario match was found.

An additional accent-folded diagnostic was run only to catch accent-only
copies. It found:

| V10 reference | Historical reference | Classification |
| --- | --- | --- |
| `V10-ST-0210` | `V0:intent_v2:086` | CANONICAL BUSINESS PHRASE — very short greeting; retained |
| `V10-MT-023:T2` | `V5:follow_up:019:t2` | **TRUE LEAKAGE** — exact deictic stock follow-up after accent removal |

## 7. Near-duplicate method

The primary lexical method was dependency-free normalized token-set Jaccard:

`|tokens(A) intersect tokens(B)| / |tokens(A) union tokens(B)|`

The flagging threshold was **0.82**, matching the prior transparent project
threshold. Comparisons covered V10-to-history, V10-to-V10, and complete
two-turn sequences. A graph connected all pairs at or above the threshold.

A separate semantic-template diagnostic replaced only known product identities
and explicit numeric/number-word quantities, then applied the same 0.82
threshold. It did not remove other words or use stemming/translation.

## 8. Near-duplicate results

Raw token Jaccard produced one cross-version pair at or above 0.82: the same
`V10-MT-001:T2` normalized duplicate above, score 1.0. Complete sequences
produced zero pairs at or above 0.82.

The template diagnostic produced 39 pair references covering three V10 items:

| V10 reference | Historical family | Score | Classification |
| --- | --- | ---: | --- |
| `V10-ST-0043` | `V7:follow_up:001:t1`; `V8:intent:041,045,...,077` | 0.8571--0.8750 | **TRUE LEAKAGE** — historical price skeleton with product substitution/word reorder |
| `V10-ST-0049` | `V9:intent:041,045,...,077` and corresponding V9 follow-up turn-1 family | 0.8333 | **TRUE LEAKAGE** — historical generated price skeleton with one function word removed |
| `V10-MT-027:T1` | `V5:follow_up:002:t1`; `V7:follow_up:002,008:t1`; `V8:follow_up:025:t1` | 1.0 | **TRUE LEAKAGE** — exact skeleton with only product substitution |

Manual review also found meaningful sub-threshold semantic copies that token
Jaccard underweights on short sentences:

| V10 reference | Historical reference | Classification |
| --- | --- | --- |
| `V10-ST-0051` | `V3:intent:025` | **TRUE LEAKAGE** — English current-price template with only product substitution |
| `V10-ST-0239` | `V9:intent:206` | **TRUE LEAKAGE** — niche OOD sentence with a one-word edit |
| `V10-MT-007:T2` | `V5:follow_up:014:t2` | **TRUE LEAKAGE** — one quantifier substitution |
| `V10-MT-018:T2` | `V6:follow_up:013:t2` | **TRUE LEAKAGE** — one noun synonym in an ordinal follow-up |
| `V10-MT-022:T2` | `V4:follow_up:010:t2` | **TRUE LEAKAGE** — one noun synonym in a deictic price follow-up |
| `V10-MT-032:T2` | `V7:follow_up:005:t2` | **TRUE LEAKAGE** — near-verbatim deictic stock follow-up |
| `V10-MT-036:T2` | `V5:follow_up:018:t2` | **TRUE LEAKAGE** — one noun synonym in a deictic price follow-up |
| `V10-MT-038:T2` | `V6:follow_up:015:t2` | **TRUE LEAKAGE** — minor reorder/synonym in the same availability template |

Short fee/threshold, greeting/thanks, and bare availability phrases were
reviewed and classified as benign domain overlap or canonical business phrases
when they did not preserve a distinctive historical template.

## 9. Semantic template review

The connected-component review found these material families:

- `V10-ST-0043` connected to the V8 generated current-price family.
- `V10-ST-0049` connected to the V9 generated selling-price family.
- `V10-MT-027:T1` connected to the same product-availability turn-1 template
  across V5, V7, and V8.
- `V10-MT-001:T2` formed a direct normalized component with its V7 reference.

Two V10-only components also showed generator reuse:
`V10-MT-006:T2`/`V10-MT-032:T2` and
`V10-MT-009:T1`/`V10-MT-038:T1`. They do not independently prove historical
leakage, but they support the generated-data quality finding.

## 10. Cases removed

None were removed from the immutable author artifact. No audited final dataset
was produced because gold/schema defects remain after leakage removal.

The following whole primary cases/scenarios are marked for removal from the
next independently authored revision:

- Primary: `V10-ST-0043`, `V10-ST-0049`, `V10-ST-0051`, `V10-ST-0239`.
- Scenarios: `V10-MT-001`, `V10-MT-007`, `V10-MT-018`, `V10-MT-022`,
  `V10-MT-023`, `V10-MT-027`, `V10-MT-032`, `V10-MT-036`, `V10-MT-038`.

Removing a contaminated turn removes its complete scenario so isolation and
sequence semantics remain valid.

## 11. Replacements required

An independent author must provide **4 primary replacements** and **9 complete
two-turn scenario replacements**. Do not provide the author historical wording.

Required primary buckets:

- 2 product-price cases: one Vietnamese formal and one Vietnamese
  conversational;
- 1 English product-price case;
- 1 English TRUE_OOD case.

Required scenario buckets:

- 1 Vietnamese-formal singular-reference scenario;
- 1 Vietnamese-conversational plural-reference scenario;
- 1 Vietnamese-formal ordinal-last scenario;
- 1 Vietnamese-conversational deictic scenario;
- 1 Vietnamese-no-diacritics deictic scenario;
- 1 Vietnamese-conversational ellipsis scenario;
- 1 Vietnamese-formal expired-context scenario;
- 1 Vietnamese-conversational ambiguous-reference scenario;
- 1 English ambiguous-reference scenario.

Each replacement must conform to `farta-v10.1.1`, be authored without V0--V9
access, be independently frozen, and be re-audited from the start.

## 12. Intent gold audit

Most top-level intent boundaries are coherent, including catalog vs search,
current shipping values vs calculations, order/payment reads, missing evidence,
OOD, and privileged operations.

Material intent defects:

- `V10-MT-027:T1` asks stock availability but is golded `product_search`.
- `V10-MT-028:T1` asks price but is golded `product_search`.
- `V10-MT-029:T1` asks price but is golded `product_search`.
- `V10-MT-030:T1` asks stock availability but is golded `product_search`.

`V10-ST-0245` and `V10-ST-0246` sit on an undocumented search-vs-clarification
boundary. The revision must state a grading rule for subjective discovery
requests and apply it consistently rather than relying only on an author note.

## 13. Business outcome gold audit

Structured handlers and terminal categories are generally separated correctly.
One definite outcome error exists:

- `V10-ST-0019` expects `NO_MATCH` / `NO_RESULTS` for fruit under 30,000 VND,
  but the frozen authority contains `Chuối` at 17,800 VND and `Ổi` at 25,000
  VND. The correct path returns matching active products.

Auth-dependent outcomes are nondeterministic where the precondition says
`anonymous_or_authenticated`; see sections 15 and 16.

## 14. Entity gold audit

All named canonical products used by V10 exist and are active in the current
structured authority. Prices, inventory, and categories match the snapshot.
Deterministic quantity/stock and shipping arithmetic checked correctly.

Entity defects:

- All 39 scenario turn-1 records contain explicit product mention(s) but set
  `product_raw_mention` to null, systematically collapsing raw mention and
  canonical identity.
- `V10-ST-0241` contains explicit quantity 2 but leaves quantity null.
- `V10-ST-0248` contains an identifiable milk mention while the product slots
  are null; only quantity is genuinely unresolved.
- `V10-ST-0254`, `V10-ST-0255`, and `V10-ST-0256` omit explicit product and
  numeric mutation targets from entity gold. Denial does not erase entities.
- `V10-ST-0263` invents order reference `ORD-OTHER-SYMBOLIC` although the
  utterance identifies another customer, not an order. The security target
  must not be stored in the wrong entity slot.

## 15. Follow-up gold audit

Positive findings:

- Every scenario contains its own turn 1 and expected context.
- Ordinal cases contain ordered product lists.
- Singular/plural/ambiguous antecedents are explicit.
- Expired-context setup is recorded.
- No hidden prior V10 scenario is required.

Failures:

- The four turn-1 intent defects listed in section 12 invalidate their
  per-turn gold.
- `V10-MT-004`, `V10-MT-012`, `V10-MT-016`, `V10-MT-020`, `V10-MT-024`, and
  `V10-MT-031` expect `SUGGESTED_ACTION` while actor preconditions permit an
  anonymous user. The business contract permits cart suggestions only for an
  authenticated, email-verified customer. Each scenario is therefore not
  deterministically executable as written.
- The systematic turn-1 raw-mention omission fails separate entity-slot gold.

## 16. Multi-intent gold audit

All 27 cases contain branch arrays, and all 56 branches contain the required
intent, operation, entity, handler, domain, source, terminal, security, minimum
facts, and grounding objects. Same-entity and separate-entity scopes are
usually explicit. Whole-request terminals distinguish complete from mixed
terminal outcomes.

Eight cases have nondeterministic auth setup:

- Cart-suggestion branches with `anonymous_or_authenticated`:
  `V10-ST-0270`, `V10-ST-0272`, `V10-ST-0273`, `V10-ST-0288`.
- Owner order/payment answer branches with `anonymous_or_authenticated`:
  `V10-ST-0277`, `V10-ST-0278`, `V10-ST-0279`, `V10-ST-0290`.

These require explicit authenticated/verified/owner preconditions or different
auth-required terminal gold.

## 17. Evidence-domain audit

Knowledge domains correctly distinguish account, orders, payment, ordering,
policy index, returns/refund, delivery SLA, cold chain, certification,
provenance, and other missing-policy topics. The missing-evidence cases do not
reuse generic policy evidence. Structured product, price, inventory, shipping,
contact, and owner-order facts use structured authority.

No historical mislabeled evidence domain was copied as authority.

## 18. Source-authority audit

Structured snapshots match current read-only MySQL data: 11 active products,
5 categories, shipping fee 20,000 VND, free-shipping threshold 200,000 VND,
and the recorded contact fields.

Approved knowledge source IDs **do not match the authoritative registry**. The
registry stores stable `source_id` and separate integer `version`; the published
IDs are:

- `account-guide-vi` version 2;
- `order-guide-vi` version 2;
- `payment-guide-vi` version 2;
- `shopping-guide-vi` version 2;
- `policy-index-vi` version 1.

V10 instead uses nonexistent IDs such as `account-guide-vi@v2` and
`policy-index-vi@v1`. This affects 55 primary cases, 6 scenarios, and 7
multi-intent branches (the branch count is a subset of the affected primary
cases). A revision must use exact source IDs and represent the version in a
separate field if version pinning is required.

Affected primary ranges/IDs are `V10-ST-0119`--`V10-ST-0146`,
`V10-ST-0171`--`V10-ST-0190`, and `V10-ST-0270`, `0272`, `0273`, `0281`,
`0282`, `0288`, `0289`. Affected scenarios are `V10-MT-004`, `012`, `016`,
`020`, `024`, and `031`.

## 19. Claim-support audit

After mapping to the real source ID/version pairs, the published account,
order, payment, ordering, and policy-index contents support the main knowledge
facts in `V10-ST-0171`--`V10-ST-0190`. The missing-evidence topics have no
approved supporting document and correctly terminate `NO_EVIDENCE`.

Defects:

- `V10-ST-0126` and `V10-ST-0127` ask about pre-checkout/cart review but their
  minimum-facts gold instead requires the chatbot cart-suggestion/auth flow.
  The published shopping guide supports product, quantity, address, payment
  method, total review, and server-side price/stock recheck.
- `V10-ST-0036` asks whether a product can be consumed directly, but its stored
  product text does not establish that claim. Gold must explicitly require a
  limitation/not-stated response rather than permit an unsupported yes/no.

## 20. OOD/denied state audit

The schema successfully separates `unsupported_ood`, missing evidence,
clarification, privileged denial, and normal supported cases. The 23 primary
OOD cases terminate `UNSUPPORTED`; 18 unsupported-policy cases terminate
`NO_EVIDENCE`; privileged cases terminate `DENIED`; ambiguous cases terminate
`CLARIFICATION_REQUIRED`.

The state taxonomy is sound. It does not cure the auth-precondition and entity
defects above.

## 21. Privileged gold audit

Payment mutation, inventory mutation, order mutation, role change, auth bypass,
cross-account access, and secret disclosure are denied. Benign own-order,
own-payment, and stock reads are separately allowed subject to authentication
and ownership. No informational read is denied merely for containing a
sensitive noun.

The privileged decisions pass; entity annotation defects remain for
`V10-ST-0254`--`0256` and `V10-ST-0263`.

## 22. Language distribution

Primary cases:

| Bucket | Count | Share |
| --- | ---: | ---: |
| Vietnamese formal | 67 | 22.9% |
| Vietnamese conversational | 58 | 19.9% |
| Vietnamese no-diacritics | 51 | 17.5% |
| English | 68 | 23.3% |
| Mixed VN/EN | 48 | 16.4% |

Scenario counts are 8 formal Vietnamese, 8 conversational Vietnamese, 8
no-diacritics, 9 English, and 6 mixed. English is large enough for diagnostic
breakdowns, but this authored sample is not evidence of population-level
representativeness.

## 23. Capability distribution

Primary, non-exclusive rollups:

| Capability | Count |
| --- | ---: |
| Product search/detail/price/stock | 78 |
| Catalog listing | 12 |
| Shipping value/calculation | 28 |
| Cart informational/action | 28 |
| Order/payment read | 24 |
| Knowledge + missing evidence | 38 |
| TRUE_OOD | 23 |
| Privileged/security | 16 |
| Multi-intent | 27 |
| Entity-bearing, including branch-only entities | 160 |
| Follow-up scenarios | 39 |

Critical underrepresentation: there is no deterministic single-turn guest or
unverified-user cart-action case expecting auth-required behavior. Inactive or
deleted-product follow-ups are also absent. These are gaps, not a request for
uniform class balancing.

## 24. Generated-data quality

Difficulty counts are 147 straightforward, 37 normal paraphrase, 47 moderately
ambiguous, 34 edge case, and 27 security/OOD. Query lengths are concentrated:
5 cases have 1--3 tokens, 248 have 4--10, 39 have 11--20, and none exceed 20.
The set is not dominated by very short or very long inputs, but it lacks long
natural customer requests and is 50.3% straightforward.

Generator artifacts are material:

- IDs are assigned in contiguous intent blocks, so the numeric ID range reveals
  the gold class (`0001`--`0024` search, `0025`--`0042` detail, and so on).
- Language buckets cycle mechanically within those blocks.
- Several price/follow-up skeletons repeat within V10 and overlap generated
  V7--V9 families.
- Some mixed-language utterances read as constructed code-switching rather than
  observed commerce language.

Even if a correct executor sends only `utterance` to the candidate, the revised
artifact should use opaque randomized IDs, shuffle order, and explicitly assert
that no metadata/gold fields are candidate-visible.

## 25. Schema / consistency results

Structural checks passed for JSON parsing, required primary fields, required
branch fields, unique IDs, counts, multi-intent branch count, empty evidence
lists on `NO_EVIDENCE`, active canonical products, structured price/inventory
values, and shipping arithmetic.

Consistency failed for:

- nonexistent approved source IDs;
- one structured `NO_RESULTS` contradiction (`V10-ST-0019`);
- auth-sensitive terminals with non-deterministic actor preconditions;
- four follow-up turn-1 intent contradictions;
- systematic raw/canonical entity-slot collapse in follow-up turn 1;
- explicit quantity/product omissions in entity gold;
- claim-support/minimum-facts defects;
- ID-to-label generator correlation.

## 26. Final case counts

No final dataset was frozen.

- Immutable author candidate: 292 primary, 39 scenarios, 78 scenario turns, 27
  multi-intent cases, 56 branches.
- Hypothetical removal-only working set: 288 primary, 30 scenarios, 60 scenario
  turns, 27 multi-intent cases, 56 branches.

The removal-only set is not a final artifact. Nine scenario removals materially
reduce follow-up coverage, so independent replacements are required.

## 27. Final V10 SHA-256

**NOT CREATED — audit failed.**

## 28. Final audit report SHA-256

Reported out-of-band with delivery. A file cannot embed its own stable SHA-256
without changing the bytes being hashed.

## 29. Final manifest SHA-256

**NOT CREATED — audit failed.**

## 30. Audit decision

**AUDIT FAIL — LEAKAGE + GOLD + DISTRIBUTION / SCHEMA.**

Historical coverage is complete; `AUDIT FAIL — INCOMPLETE HISTORICAL COVERAGE`
does not apply.

## 31. Next step

Return this revision to the independent author. The author must:

1. remove and independently replace the 4 primary cases and 9 scenarios listed
   above without receiving historical wording;
2. correct the listed intent, outcome, entity, auth-precondition,
   claim-support, and exact-source-ID defects;
3. randomize opaque IDs/order and declare candidate-visible fields;
4. freeze a new dataset/report/manifest with new hashes;
5. send the new frozen revision to a fresh independent leakage/gold auditor who
   has not read Phase 12 candidate analysis, for a complete re-audit.

Do not run Blind V10 before a later audit returns `AUDIT PASS` and produces the
final audited dataset and final manifest.
