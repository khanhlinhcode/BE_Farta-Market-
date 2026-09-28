# Chat retrieval and evaluation

## Product retrieval

Structured product filters run in SQL before lexical ranking. Search no longer
truncates to the first 20 alphabetical product names; the filtered active set is
ranked by normalized name, category and short-description overlap, then bounded
to five output products. MySQL remains authoritative after provider selection.

## Structured business routes

Deterministic routing remains the primary path even when
`AI_SEMANTIC_ROUTER_ENABLED=false`. Catalog listing reads active products from
MySQL, cart proposals revalidate product and quantity against MySQL, and current
shipping fee/free-shipping threshold read directly from `SiteSetting`. These
authoritative values do not depend on semantic similarity or Qdrant ranking.

## Knowledge retrieval

The local baseline for FAQ, policy, guide, contact and address questions is
sparse retrieval over relevant dynamic `SiteSetting` facts and published
knowledge chunks:

- title weight 5;
- section/heading weight 4;
- topic weight 3;
- retrieval text weight 1;
- maximum query variants 3;
- maximum evidence chunks 5.

Retrieval text is built from title, topic, optional aliases/sample questions,
heading and approved content. It improves matching but is removed before answer
generation, so aliases and example questions cannot become evidence.

For a routed knowledge topic, MySQL selects only published Farta-owned chunks
whose `topic` and `source_id` are present in the immutable evidence-domain
registry. Qdrant queries carry `topic` and allowed `source_id` filters, and IDs
not present in the eligible MySQL candidate set are discarded before fusion.
After ranking, the application repeats the domain/source/owner/status check and
requires the selected section to support the requested claim. These layers are
intentional: vector relevance never establishes authority.

`AI_VECTOR_SEARCH_ENABLED=false` is the safe default. Dense retrieval uses
Qdrant Cloud Inference and `intfloat/multilingual-e5-small` (384 dimensions), so
the backend never sends knowledge text to a separate embedding provider. Dense
and sparse ranks are fused with Reciprocal Rank Fusion (`k=60`). Missing
configuration, timeouts and provider errors set `vector_fallback=true` and
continue with sparse results.

```dotenv
QDRANT_URL=https://your-cluster.cloud.qdrant.io
QDRANT_API_KEY=
QDRANT_COLLECTION=farta_chat_knowledge
QDRANT_INFERENCE_ENABLED=false
QDRANT_INFERENCE_MODEL=intfloat/multilingual-e5-small
AI_VECTOR_SEARCH_ENABLED=false
```

Enable `QDRANT_INFERENCE_ENABLED` only after the dedicated 384-dimensional
collection exists and an inference upsert/query/delete probe passes. Enable
`AI_VECTOR_SEARCH_ENABLED` last. Collection names outside
`farta_chat_knowledge` plus an optional environment suffix are rejected before
any HTTP request, protecting unrelated collections in a shared cluster. Use one
collection per environment because point IDs are database-local chunk IDs.

Model query expansion and generated answers are separate flags:

```dotenv
AI_QUERY_EXPANSION_ENABLED=false
AI_KNOWLEDGE_GENERATION_ENABLED=false
AI_VECTOR_SEARCH_ENABLED=false
```

Semantic intent fallback is also deployed behind an independent gate:

```dotenv
AI_SEMANTIC_ROUTER_ENABLED=false
AI_SEMANTIC_ROUTER_MIN_CONFIDENCE=0.75
```

It runs only when deterministic guards cannot classify a request. Strict JSON,
a closed intent set and the confidence threshold force ambiguous/unavailable
results to `clarification`; downstream tools still enforce authentication and
ownership. It is an optional fallback, not a requirement for clear business
intents. Enable it only after the router/auth regression suite passes.

Without generation, the assistant returns a direct approved-source extract.
With generation, strict claim/citation/evidence JSON, exact quote validation,
semantic verification and one repair are mandatory. No evidence means no
generator call and a fail-closed response.

## Knowledge index

```bash
php artisan chat:knowledge:sync --dry-run
php artisan chat:knowledge:sync
```

Stable `source_id`, document checksum and chunk checksum make synchronization
idempotent. With inference enabled, the command replaces only points matching
that exact `source_id`, then upserts points whose Qdrant ID and payload
`chunk_id` equal the MySQL chunk ID. Moving a previously indexed file to draft
removes that source's vector points and local chunks. Invalid placeholders and
instruction-like content are rejected. The command never deletes records outside
the explicit source and dedicated collection. See
[chat-knowledge-authoring.md](chat-knowledge-authoring.md).

## Evaluation

```bash
php artisan test tests/Feature/ChatEvaluationTest.php
```

The suite reports intent accuracy, HitRate@5, MRR@5, nDCG@5 and local latency.
The current fixture contains 166 intent cases plus 16 product retrieval cases,
including VI/EN, diacritics/no-diacritics, common typos, slang, paraphrases,
ambiguous and multi-intent requests, context follow-ups, prompt injection and
auth/cart/order boundaries. Quality gates are overall intent accuracy ≥95%, the
original intent baseline exactly 100%, expanded natural-variant accuracy ≥95%,
HitRate@5 ≥90%, MRR@5 ≥80%, exact evidence validity 100%, unsupported factual
claims 0 and guest cart mutations 0.

The expanded variants became a development regression set once failures were
used to improve deterministic routing; they are not a blind production sample.
Fixture metrics are regression evidence, not production latency or customer
satisfaction claims. Report Cloud Inference latency separately from local test
latency and keep staging/application validation distinct from direct Qdrant
queries.

## Phase 14 development benchmark

Final V10 became development evidence after its valid frozen evaluation; it is
not a blind score for the Phase 14 runtime. The grouped V0--V9 validation keeps
near-duplicate connected components within one split and measures the improved
deterministic concept router at 96.88% intent accuracy, 96.16% macro-F1, 97.87%
supported-query recall, 100% OOD recall, and 100% privileged recall, with 1.08
ms p50 and 2.57 ms p95 routing latency. That older fixture conflates OOD and
denied actions, so OOD precision is not measurable there.

The full V10 development regression measures the current typed taxonomy and
end-to-end handlers: 99.32% intent, 99.20% macro-F1, 100% handler accuracy,
99.32% business outcome, 100% OOD precision/recall, 100% privileged
precision/recall, 100% evidence-domain accuracy, 98.85% evidence eligibility,
96.42% claim support, and zero wrong-topic, unsupported-policy, unsafe-execution,
or wrong-entity unsafe-action signals. All 17 development gates pass. The two
remaining outcome mismatches are duplicated checkout-review cases whose gold
facts require chatbot-add behavior that the prompt does not ask for; the gold
was not edited.

No live versioned semantic fallback was available for a comparable run. SetFit,
sentence-transformers, torch, sklearn, and transformers were absent, and no
dependency was added. The selected Phase 14 strategy is therefore **KEEP
DETERMINISTIC CONCEPT ROUTER** with the bounded semantic fallback disabled.

Phase 3 release-readiness evidence is recorded in
[chat-phase-3-release-readiness.md](chat-phase-3-release-readiness.md). The
Phase 4 V1 development and independent V2 generalization results are recorded in
[chat-phase-4-generalization.md](chat-phase-4-generalization.md). V2 development
and independent V3 results are recorded in
[chat-phase-5-safe-generalization.md](chat-phase-5-safe-generalization.md).
Phase 6 development V3 and independent V4 results are recorded in
[chat-phase-6-safe-generalization.md](chat-phase-6-safe-generalization.md). The
current scope, measured limitations, latency, and release decision are also
summarized in [cards/chat-model-card.md](cards/chat-model-card.md).
