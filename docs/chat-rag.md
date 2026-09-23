# Chat retrieval and evaluation

> The filename is retained for existing links. The implemented feature is
> documented as a **Grounded Conversational AI Assistant**, not as vector,
> hybrid, or agentic search.

## Current retrieval baseline

The assistant uses database-only retrieval. Active products are filtered by
structured constraints such as price and stock. The bounded candidate set is
then ranked through normalized lexical overlap:

- product name weight: 5;
- category weight: 3;
- short description weight: 1;
- maximum provider evidence: 5 products;
- maximum accepted provider recommendation: 3 distinct IDs.

Only current-question text is used for retrieval. Browser history is not
evidence and is not forwarded to a provider. If retrieval finds no support, the
endpoint abstains without a model request.

The provider can only return `kind` and `product_ids`. Laravel rejects unknown
fields, duplicates, strings in place of integer IDs, IDs outside evidence, and
stale/inactive IDs. Approved IDs are reloaded from MySQL before any name, price,
stock, image, or category is returned. Provider prose is never used for product
facts or cart authorization.

## Local evaluation fixture

Run:

```bash
php artisan test tests/Feature/ChatEvaluationTest.php
```

The test prints one `CHAT_EVALUATION` JSON line and asserts conservative floors.
On 24 September 2026, the local SQLite run against fixture version 1 produced:

```json
{
  "fixture_version": 1,
  "intent_cases": 14,
  "intent_accuracy": 1.0,
  "retrieval_cases": 6,
  "hit_rate_at_5": 1.0,
  "mrr_at_5": 0.9167,
  "ndcg_at_5": 0.9385
}
```

Timing is intentionally not copied into this document because it varies by
machine and test process. Read it from the current test output instead.

This dataset is small and curated for regression. It does not establish p95
production latency, broad Vietnamese/English relevance, synonym coverage, or
real customer satisfaction. Expand it with reviewed production-like queries
before making stronger quality claims.

## Why Qdrant is not included

The measured database/lexical baseline clears the current fixture thresholds,
so adding a vector service would increase deployment, indexing, stale-data, and
fallback complexity without demonstrated benefit. A future experiment should
compare the same labeled set plus harder synonym and similarity queries. Adopt
hybrid retrieval only if it materially improves recall/ranking while preserving
MySQL as the final source of truth.

See [chat-architecture.md](chat-architecture.md) for request flow, tools,
provider capabilities, security boundaries, response schema, and fallback.
