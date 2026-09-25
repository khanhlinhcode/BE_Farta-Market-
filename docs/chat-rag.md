# Chat retrieval and evaluation

## Product retrieval

Structured product filters run in SQL before lexical ranking. Search no longer
truncates to the first 20 alphabetical product names; the filtered active set is
ranked by normalized name, category and short-description overlap, then bounded
to five output products. MySQL remains authoritative after provider selection.

## Knowledge retrieval

The local baseline is sparse retrieval over dynamic `SiteSetting` facts and
published knowledge chunks:

- title weight 5;
- section/heading weight 4;
- topic weight 3;
- retrieval text weight 1;
- maximum query variants 3;
- maximum evidence chunks 5.

Retrieval text is built from title, topic, optional aliases/sample questions,
heading and approved content. It improves matching but is removed before answer
generation, so aliases and example questions cannot become evidence.

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
ownership. Enable it after the router/auth regression suite passes.

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
The target fixture should contain at least 50 reviewed VI/EN queries including
diacritics/no-diacritics, common typos, paraphrases, missing evidence, prompt
injection and auth/cart/order boundaries. Quality gates are intent accuracy
≥95%, HitRate@5 ≥90%, MRR@5 ≥80%, exact evidence validity 100%, unsupported
factual claims 0 and guest cart mutations 0.

Fixture metrics are regression evidence, not production latency or customer
satisfaction claims. Dense retrieval cannot be claimed as tested until valid
Qdrant credentials are supplied and the Cloud Inference upsert/query/delete path
is exercised.
