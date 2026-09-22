# Catalog-grounded chat: sparse RAG pilot

The recommendation path now follows retrieval, augmentation, generation, and verification. This is **sparse lexical RAG**, not vector or hybrid retrieval. The current catalog is small, so the API ranks the active product records already loaded for deterministic catalog answers. It sends at most five retrieved records to the model. The shopping/cart path remains deterministic and does not grant the model authority to create actions.

## Evidence and answer boundary

Each product is one source document, built from its current database ID, name, category, short description (capped at 300 characters), price, and inventory. The retriever normalizes accents and case, removes common query words, and scores token overlap with weights of 5 for name, 3 for category, and 1 for description. Multi-term queries require at least two weighted points, so an incidental match to one description word does not trigger generation. A query without enough evidence returns no sources and makes no model request. The corpus is read on each request, so product edits and deactivation do not depend on an embedding refresh or background index.

The prompt contains only the retrieved source documents. Groq returns strict JSON with `kind` and up to three product IDs. The server rejects extra fields, invalid IDs, IDs outside the retrieved set, and IDs that are no longer active. It then re-queries the database and formats all user-visible product facts itself. Model prose, prices, quantities, discounts, and cart actions are never rendered or authorized. The existing `/api/chat` response contract is unchanged.

This design is grounded by a structural allowlist and deterministic database facts. It does **not** claim independent semantic verification of arbitrary model-generated prose or visible citations. If the product data itself contains an unsupported marketing claim, this mechanism cannot validate that claim; catalog editors remain responsible for source quality.

## Evaluation and limits

`tests/Feature/ChatTest.php` checks retrieval scope, inactive products, bounded top-five context, updates to descriptions, no-evidence refusal, out-of-source IDs, malformed model output, fresh price and inventory, and purchase authorization. These are regression checks, not a representative relevance benchmark. Before a production claim about recommendation quality, label a Vietnamese/English query set and measure Precision@5, Recall@5, MRR, abstention accuracy, and p95 retrieval/inference latency. Sparse token matching will miss synonyms and some follow-up questions. Add an independently evaluated dense retriever and fusion only if those measurements show a material gap.

The retrieval-augmented generation pattern is described by [Lewis et al., 2020](https://arxiv.org/abs/2005.11401). This implementation uses its retrieve-then-generate structure with a small catalog and provider-side generation, without claiming to reproduce that paper's dense retriever or training method.
