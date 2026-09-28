# Chat Phase 3 release-readiness report

Date: 25 September 2026

## Baseline V1

- Backend worktree: `main`, tracking `origin/main`, with uncommitted Phase 1/2
  changes. No commit, push, merge, or deployment was performed in Phase 3.
- Storefront worktree: `main`, tracking `origin/main`, with one uncommitted
  citation regression test.
- Qdrant: server 1.19.0, collection `farta_chat_knowledge`, green, 18 points,
  384-dimensional cosine vectors, no Qdrant sparse-vector schema.
- Strict mode is enabled. Only `source_id` has a keyword payload index.
- Embedding: `intfloat/multilingual-e5-small` through Qdrant Cloud Inference.
- Retrieval: top 20 dense IDs, local sparse rank, local RRF `k=60`, maximum five
  evidence chunks.
- Local flags: semantic router off, vector search on, query expansion off,
  knowledge generation off.

## Approved knowledge audit

| Topic | Source | Approved | Indexed | Expected fallback |
| --- | --- | --- | --- | --- |
| Shipping fee/freeship | `SiteSetting` | Yes | Runtime, not Qdrant | Direct authoritative response |
| Shipping policy | No complete document | No | No | Only state verified fee/threshold; do not invent policy |
| Payment/COD/SePay | `payment-guide-vi` v2 | Yes | 5 chunks | Topic-scoped sparse |
| Checkout | Ordering guide, cart-check section | Partial | Under `ordering` | Return only approved checkout excerpt or `NO_EVIDENCE` |
| Account/email/password | `account-guide-vi` v2 | Yes | 4 chunks | Topic-scoped sparse |
| Contact/address | `SiteSetting` | Yes | Runtime, not Qdrant | Topic-scoped sparse without vector call |
| Orders/cancellation | `order-guide-vi` v2 | Yes | 4 chunks | Personal facts still use owner-scoped order service |
| Policy index | `policy-index-vi` v1 | Yes | 1 chunk | Topic-scoped sparse |
| Returns/refund | None | No | No | `NO_EVIDENCE` |

## Controlled deployed-staging smoke

All three public staging/demo domains returned HTTP 200, and the API CSRF cookie
endpoint returned 204. The guest smoke test was low-volume and used normal chat
requests only; it did not perform security scanning or data mutation.

The deployed artifact is not release-equivalent to the dirty local candidate:

| Flow | Result |
| --- | --- |
| Shipping, four queries | 0/4 correct; old cross-topic retrieval bug remains |
| Catalog listing, three queries | 0/3 correct |
| Product price/stock, two queries | 2/2 correct |
| Guest cart, three queries | 2/3 correct; polite cart question loses cart intent |
| Follow-up reference, two follow-ups | 0/2 correct |
| COD/checkout/shipping policy | 0/3 correct |
| Missing returns evidence | Failed; cited unrelated payment content |
| Multi-intent, three queries | 0/3 addressed or safely clarified |

Staging application status: **FAIL for the current release decision**. These
failures describe the deployed artifact, not the uncommitted local candidate.

## Former blind holdout V1

Phase 4 reclassifies this set as **Generalization Development Set V1**. The
numbers below remain the untouched Phase 3 baseline and must not be compared to
Phase 4 as independent release evidence.

The holdout was authored and frozen before its first complete run. The router was
not modified after observing results.

| Metric | Result |
| --- | ---: |
| Intent accuracy, 100 queries | 48% |
| Route/handler accuracy, 10 probes | 70% |
| Entity extraction, 6 cases | 16.67% |
| Multi-intent coverage | 12.5% |
| Follow-up resolution | 25% |
| Unsupported-query safety | 40% |

Group intent accuracy: shipping 50%, catalog 50%, product detail 50%, product
search 50%, cart action 75%, cart query 16.67%, order 37.5%, knowledge 25%,
general 50%, unsupported 40%, clarification 100%, and multi-intent routing 37.5%.

This invalidates a production claim based only on the tuned 166-case regression
set, while preserving that set as a useful no-regression gate.

## Retrieval holdout and missing evidence

Eight supported knowledge queries achieved HitRate@5, MRR@5, and nDCG@5 of
100% in both local sparse and current hybrid evaluation. Two returns/refund
queries produced empty evidence correctly, for 100% missing-evidence accuracy.

The result supports keeping the current retrieval architecture. It does not
justify a reranker, embedding migration, Weighted RRF, or DBSF.

## Cloud Inference reliability and latency

The Phase 3 read-only sample issued 20 controlled dense queries:

- successes: 20;
- connection errors: 0;
- HTTP errors: 0;
- other errors: 0;
- expected topic represented in every successful top-20 response: 20/20;
- combined Cloud Inference and vector query latency: min 774 ms, p50 876 ms,
  p95 1,515 ms, max 2,589 ms.

Sparse fallback selected the expected source for 19/20 sample questions. The
remaining English account query produced no sparse evidence, so failure would
result in `NO_EVIDENCE`, not an incorrect answer. A previous eight-query
exploratory sample had one connection fallback; the samples are too small and
non-random to claim a production error rate.

Cloud Inference is the largest measured knowledge-retrieval component, but it is
not invoked by shipping, catalog, product, cart, or order business routes. Its
actual production invocation frequency is unknown because traffic telemetry was
not sampled.

## Failure, privacy, and security checks

- Qdrant uses a two-second connection timeout and 15-second request timeout.
  There is no automatic retry, so a request cannot create a retry storm.
- Dense exceptions fall back to topic-scoped sparse retrieval. Empty evidence
  returns `NO_EVIDENCE` before generation.
- Logs contain request ID, HMAC user identifier, intent, source IDs, counts, and
  timings. They do not contain raw chat messages, history, cart bodies, cookies,
  credentials, addresses, or payment details.
- Five-minute context contains only product ID, quantity, stage, owner ID, and
  expiry; invalid or expired state is deleted.
- Cart proposals re-query active product state and enforce stock, quantity,
  customer role, login, and verified email. The legacy action remains inert.

## Inference-options decision

Official Qdrant documentation describes three relevant options:

1. Cloud Inference, the current managed path, requires no local inference
   runtime and supports the current free multilingual model.
2. Client-side inference, such as FastEmbed, gives model control but would add a
   Python/ONNX runtime or a new service boundary to this Laravel deployment,
   plus model memory, rollout, monitoring, and re-embedding responsibility.
3. External providers through Qdrant add another credential, provider billing,
   and provider/network failure mode. A different model or dimension requires a
   separate collection and full re-embedding with rollback.

References:

- <https://qdrant.tech/documentation/inference/>
- <https://qdrant.tech/documentation/cloud/inference/>
- <https://qdrant.tech/documentation/fastembed/>

Decision: **KEEP CLOUD INFERENCE**. The measured reliability and retrieval
quality do not justify an inference migration. Continue monitoring p95.

## Topic-filter gate

Current top-k is 20 and the corpus contains 18 points, so dense retrieval sees
the complete corpus before local topic intersection. Keep the simpler local
constraint now. Add a Qdrant `topic` keyword payload index and server-side filter
before the corpus grows beyond top-k=20, or earlier if a new untouched holdout
shows topic Recall@20 below 100%. Strict mode must remain enabled.

## Release matrix

| Gate | Decision |
| --- | --- |
| Existing functional regression | PASS |
| Blind holdout | FAIL |
| Qdrant Cloud read-only reliability | PASS |
| Retrieval holdout | PASS |
| Deployed staging E2E | FAIL / revision mismatch |
| Auth/cart boundary | PASS locally; partial on deployed artifact |
| Failure fallback | PASS locally |
| Knowledge grounding | PASS locally; deployed artifact fails returns isolation |
| Latency | Acceptable for current small knowledge path; monitor p95 |
| Security/secrets | PASS for reviewed diff and telemetry |

## Release decision

**NO-GO for production.** Do not solve this by adding a reranker, new embedding,
server-side payload index, or Agentic RAG. The next implementation phase should
address intent/entity generalization and bounded follow-up composition, rerun the
existing regression suite, then evaluate against a second untouched holdout and
deploy the exact reviewed revision to staging for the same smoke suite.
