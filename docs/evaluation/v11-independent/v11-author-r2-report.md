# V11 Independent Holdout Author R2 Gold Remediation Report

Status: **FROZEN**  
Frozen at (UTC): `2026-09-27T09:59:41Z`

## Scope and isolation

V11 Author R2 is a gold-only remediation copied from frozen V11 Author R1. It changes exactly the twelve authorized entity-gold records/turns and makes no utterance, schema, capability, intent, handler, terminal, business-outcome, evidence, authorization, or security change. No candidate/chatbot, Phase 14 runtime, router, handler, RAG implementation, candidate output, or V0-V10 artifact was executed or inspected.

Parent revision: `V11-author-r1`  
Parent dataset SHA-256: `81214b6d7fb877116d6e19298410c2d2c64096875e813d2a2139a6dfe926240a`  
Dataset schema: `farta-v10.3.0`  
Sanitized schema contract: `farta-v11-sanitized-schema-contract.1.0`

## Input integrity

All R1 and sanitized-input hashes were recomputed before creating R2 and matched their frozen expected values. Both sanitized manifests and the R1 manifest report `FROZEN`; JSON parsing, schema compatibility, lineage, and the R1 structural counts passed.

| Input | Verified SHA-256 |
|---|---|
| `v11-author-r1-candidate-dataset.json` | `81214b6d7fb877116d6e19298410c2d2c64096875e813d2a2139a6dfe926240a` |
| `v11-author-r1-report.md` | `ea117f758ab9b6407f08d10349fa728988f8a3b921432ff35afc72f0c5ba2ee5` |
| `v11-author-r1-freeze-manifest.json` | `bca7f76f94720425cbde2a2c7c718747654cbac9c326ceec16955e2ff8fa79c9` |
| `v11-sanitized-schema-contract.json` | `2d1e6cd805ed9914d6811f130f9c1cc0909eb2f6707e30f5f66b13c61f6bea5d` |
| `v11-sanitized-schema-contract.md` | `216e1f80789770d4f48e6a852d05ee1e7b4909b035b9b223107a4afc5f31b57b` |
| `v11-sanitized-schema-freeze-manifest.json` | `d5eddc122a536cace2e7ac773f1f686ddaaa96e6178902d65acfa88b43c7be06` |
| `v11-sanitized-business-capability-contract.json` | `ddaf57dcb1fd0db48b7466370c498d52db729cf8a4714f99c0098548fc1e4b7b` |
| `v11-sanitized-business-capability-contract.md` | `f647a0554563366e160a040870a6ad493edc7baef90d2ea1baff3a03f6bfc9e6` |
| `v11-sanitized-product-authority.json` | `809923e43f8919446b8123ea3ab0cac2f39cc3b50d89bad89f584d3768799f0a` |
| `v11-sanitized-sitesetting-authority.json` | `92e69a3c93bfef417fce7ab76a12e191558cbbe50ec9e645c6422e69bb69f2cb` |
| `v11-sanitized-knowledge-registry.json` | `dc8b013d3235a8fb04cedda63575354ab25fd6482316798952ff7161e6d599e7` |
| `v11-sanitized-authorization-contract.json` | `de05e7d4ebd0429b20c73fd9bb0927a9012a9e358b80a195c878549e56639dc4` |
| `v11-sanitized-business-contract-freeze-manifest.json` | `440f40b5dfc91fd06e1751d7231883503ff1e6d2246a900dbddd1a53c2efd5fa` |

## Authorized remediation

| Record/turn | Entity field | R1 | R2 |
|---|---|---|---|
| `v11r1-p033` | `quantity`, `unit` | `null`, `null` | `1`, `phần` |
| `v11r1-p061` | `quantity`, `unit` | `null`, `null` | `1`, `hop` |
| `v11r1-p089` | `quantity` | `null` | `1` |
| `v11r1-p118` | `quantity`, `unit` | `null`, `null` | `1`, `phần` |
| `v11r1-p124` | `unit` | `phần` | `null` |
| `v11r1-s05/t2` | `unit` | `quả` | `cái` |
| `v11r1-s14/t1` | `quantity`, `unit` | `null`, `null` | `1`, `unit` |
| `v11r1-p018` | `account_target` | `tài khoản của tôi` | `null` |
| `v11r1-p025` | `account_target` | `tài khoản của tôi` | `null` |
| `v11r1-p081` | `account_target` | `cua toi` | `null` |
| `v11r1-p130` | `account_target` | `account mình` | `null` |
| `v11r1-p156` parent | `account_target` | `null` | `my account` |

The R2 semantic diff contains 16 field-level changes across exactly these 12 authorized records/turns. No mechanically derived entity mirror required a value change. For `v11r1-p156`, branch 1 retains `my account`; branch 2 remains null because its branch-local entity is the deictic order reference rather than a separately expressed account target.

## Entity validation

The full gold-only sweep covered 160 primary cases, 40 multi-intent branches, and 40 scenario turns.

- Quantity forward invariant: **PASS**
- Quantity reverse invariant: **PASS**
- Unit forward invariant: **PASS**
- Unit reverse invariant: **PASS**
- Unauthorized unit conversion/invention check: **PASS**
- `account_target` forward invariant: **PASS**
- `account_target` reverse invariant: **PASS**
- Actor/auth/ownership separation: **PASS**
- Parent/branch entity-scope validation: **PASS**
- Additional out-of-scope entity defect found: **NO**

### Entity counts before and after

| Measure | R1 | R2 |
|---|---:|---:|
| entity-bearing primary cases | 106 | 104 |
| entity-bearing branches | 25 | 25 |
| entity-bearing scenario turns | 40 | 40 |
| entity-bearing primary + branch records | 131 | 129 |
| entity-bearing records in all three scopes | 171 | 169 |
| non-null `quantity` (combined) | 28 | 33 |
| non-null `unit` (combined) | 28 | 31 |
| non-null `account_target` (combined) | 26 | 23 |
| non-null `requested_mutation_value` (combined) | 6 | 6 |

R2 scope detail: primary `quantity=26`, `unit=24`, `account_target=22`; branch `quantity=1`, `unit=1`, `account_target=1`; scenario-turn `quantity=6`, `unit=6`, `account_target=0`.

## Zero-change proofs

Utterances were compared byte-for-byte in collection order and also hashed as ordered JSON string arrays.

| Scope | Count | R1/R2 utterance-array SHA-256 | Changes |
|---|---:|---|---:|
| primary | 160 | `295a9fa9fea08f6dad47ca8d943eae8d6d3ba614a3e05b2d9709db930e88004c` | 0 |
| scenario turns | 40 | `c50f2e94342543ff538aad21dd21f0dae291f25f265f005800e00963f2d5265d` | 0 |
| multi-intent parents | 20 | `23cb93ffcc42b274d243ca6b62edb0d0c9a8e2fa3cc11c6efd91c36367c53b91` | 0 |

- Schema changes: **0**
- Intent changes: **0**
- Handler changes: **0**
- Terminal changes: **0**
- Business-outcome changes: **0**
- Evidence-domain/source/claim-support changes: **0**
- Actor/auth/security changes: **0**
- Reordering/addition/deletion changes: **0**
- Exact semantic change budget: **PASS**

## Structural counts and distributions

| Collection | R2 count | Required | Status |
|---|---:|---:|---|
| primary cases | 160 | 160 | PASS |
| multi-turn scenarios | 20 | 20 | PASS |
| scenario turns | 40 | 40 | PASS |
| multi-intent parents | 20 | 20 | PASS |
| multi-intent branches | 40 | 40 | PASS |

Language distribution was recomputed and is unchanged: each primary bucket has 32 cases, each scenario bucket has 4 scenarios, and each scenario-turn bucket has 8 turns across `vi_formal`, `vi_conversational`, `vi_no_diacritics`, `en`, and `mixed`.

Primary capability distribution is unchanged: `multi_intent=20`, `order_read=15`, `stock_availability=15`, `clarification=10`, `knowledge_query=10`, `missing_evidence_query=10`, `price=10`, `privileged_mutation=10`, `product_detail=10`, `product_search=10`, and each of `cart_action_request`, `cart_informational`, `catalog_listing`, `general_chat`, `payment_status_read`, `shipping_calculation`, `shipping_current_value`, and `unsupported_ood` is 5.

Primary terminal distribution is unchanged: `ANSWER=65`, `DENIED=16`, `ALL_BRANCHES_HANDLED=15`, `NO_EVIDENCE=15`, `CLARIFICATION_REQUIRED=11`, `ANSWER_OR_NOT_FOUND=8`, `AUTH_REQUIRED=7`, `NO_RESULTS=5`, `PARTIAL_MIXED_TERMINALS=5`, `UNAVAILABLE=5`, `UNSUPPORTED=5`, and `SUGGESTED_ACTION=3`. Branch and scenario-final terminal distributions are also unchanged.

Recomputed coverage remains: privileged primary/branches `10/3`; OOD primary/branches `5/1`; NO_EVIDENCE primary/branches `15/2`; clarification primary intents `10`, primary clarification terminals `11`, and scenario-final clarifications `5`.

## Regression validation

- JSON parse and sanitized schema validation: **PASS**
- Exact nine-slot entity shapes and unique IDs: **PASS**
- Source registration, version pins, and grounding mirrors: **PASS**
- Product, SiteSetting, and five published-knowledge authority mirrors: **PASS**
- Canonical product authority checks: **PASS** (145 non-null canonical occurrences)
- Authority-requiring supported answers: **PASS** (`60` primary, `32` branches, `13` scenario finals)
- NO_EVIDENCE regression: **PASS** (`15` primary, `2` branches)
- Evidence-domain/source eligibility: **PASS**
- Deterministic SiteSetting arithmetic: **PASS** (5 primary calculations; scenario calculations unchanged)
- Authorization/security regression: **PASS**
- Follow-up regression: **PASS** (20 scenarios, 40 turns; context lifecycle and final outcomes unchanged)
- Multi-intent regression: **PASS** (20 parents, 40 branches; all branch payloads unchanged)
- Quantity/unit/account-target invariant sweep: **PASS**
- Exact R1-to-R2 semantic diff: **PASS**

## Known limitations

- This revision is intentionally limited to the twelve authorized entity-gold corrections; it does not rewrite or expand V11.
- Historical leakage was not rerun because every utterance is byte-identical to R1. A fresh independent auditor remains responsible for the post-freeze audit.
- The sanitized schema still withholds the content-bearing string allowlist for `requested_mutation_value`; this revision does not change mutation values.
- No candidate performance is available because candidate execution is forbidden for this authoring revision.

## Freeze

Dataset SHA-256: `b89b78105caad702ebefe1a301f6a1b91a64cf13b5af7659a091e562de9320aa`  
Authorized affected records/turns: **12**  
Utterance changes: **0**  
Schema changes: **0**  
Candidate executed: **NO**  
Freeze: **FROZEN**
