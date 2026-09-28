<?php

namespace App\Services\Chat;

use App\Enums\ChatIntent;
use App\Enums\ChatMutationTarget;
use ArrayAccess;
use LogicException;

/**
 * The route contract between NLU, context enrichment and handler dispatch.
 * ArrayAccess preserves the existing internal/test read API while callers are
 * migrated away from mutable associative route arrays.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ChatRouteFrame implements ArrayAccess
{
    /**
     * @param  array{category: ?string, min_price: ?int, max_price: ?int, in_stock: ?bool}  $filters
     * @param  array<string, mixed>  $entities
     * @param  array<int, string>  $detectedIntents
     * @param  array<int, string>  $capabilities
     * @param  array<string, mixed>  $concepts
     * @param  array<int, self>  $branches
     * @param  array<string, int>  $telemetry
     */
    public function __construct(
        public ChatIntent $intent,
        public string $query,
        public array $filters,
        public array $entities,
        public bool $requiresAuth,
        public float $confidence,
        public string $routingMode,
        public ?string $clarificationReason,
        public bool $needsClarification,
        public string $decisionState,
        public ?string $denialReason,
        public array $detectedIntents = [],
        public array $capabilities = [],
        public array $concepts = [],
        public ?ChatEvidenceDomain $requiredEvidenceDomain = null,
        public array $branches = [],
        public ?string $composition = null,
        public ?int $contextProductId = null,
        public ?int $contextCategoryId = null,
        public bool $requiresCartConfirmation = false,
        public array $telemetry = [],
        public ?string $semanticIntent = null,
        public string $resource = 'none',
        public string $operation = 'unknown',
        public array $mentionedResources = [],
        public ?ChatMutationTarget $mutationTarget = null,
        public bool $mutationTargetAmbiguous = false,
    ) {
        if ($intent === ChatIntent::KnowledgeQuery && $requiredEvidenceDomain === null) {
            throw new LogicException('Knowledge routes require an evidence domain.');
        }
        foreach ($branches as $branch) {
            if (! $branch instanceof self) {
                throw new LogicException('Composite routes contain only route frames.');
            }
        }
        foreach ($mentionedResources as $resource) {
            if (! is_string($resource) || ChatMutationTarget::tryFrom($resource) === null) {
                throw new LogicException('Mentioned mutation resources must use the protected resource enum values.');
            }
        }
        if ($mutationTargetAmbiguous && ($operation !== 'mutate' || $mutationTarget !== null)) {
            throw new LogicException('An ambiguous mutation target must be unresolved and mutating.');
        }
    }

    /** @param array<int, int> $productIds */
    public function withContext(?int $productId, ?int $categoryId, bool $clarification = false, array $productIds = []): self
    {
        $resolvedSemanticIntent = $this->semanticIntent;
        if (! $clarification && (is_int($productId) || $productIds !== []) && $resolvedSemanticIntent === 'clarification') {
            $resolvedSemanticIntent = $this->intent === ChatIntent::CartActionRequest
                ? 'cart_action_request'
                : match ($this->concepts['product_facet'] ?? 'detail') {
                    'price' => 'price',
                    'stock' => 'stock_availability',
                    default => 'product_detail',
                };
        }

        return new self(
            $clarification ? ChatIntent::Clarification : $this->intent,
            $this->query,
            $this->filters,
            [...$this->entities, 'context_product_ids' => $clarification ? [] : $productIds],
            $this->requiresAuth,
            $clarification ? 0.0 : $this->confidence,
            $clarification ? 'context' : $this->routingMode,
            null,
            $clarification,
            $clarification ? 'clarification' : $this->decisionState,
            $this->denialReason,
            $this->detectedIntents,
            $this->capabilities,
            $this->concepts,
            $this->requiredEvidenceDomain,
            $this->branches,
            $this->composition,
            $productId,
            $categoryId,
            $this->requiresCartConfirmation,
            $this->telemetry,
            $clarification ? 'clarification' : $resolvedSemanticIntent,
            $this->resource,
            $this->operation,
            $this->mentionedResources,
            $this->mutationTarget,
            $this->mutationTargetAmbiguous,
        );
    }

    public function withTelemetry(string $key, int $value): self
    {
        return new self(
            $this->intent,
            $this->query,
            $this->filters,
            $this->entities,
            $this->requiresAuth,
            $this->confidence,
            $this->routingMode,
            $this->clarificationReason,
            $this->needsClarification,
            $this->decisionState,
            $this->denialReason,
            $this->detectedIntents,
            $this->capabilities,
            $this->concepts,
            $this->requiredEvidenceDomain,
            $this->branches,
            $this->composition,
            $this->contextProductId,
            $this->contextCategoryId,
            $this->requiresCartConfirmation,
            [...$this->telemetry, $key => $value],
            $this->semanticIntent,
            $this->resource,
            $this->operation,
            $this->mentionedResources,
            $this->mutationTarget,
            $this->mutationTargetAmbiguous,
        );
    }

    /** @param array<string, mixed> $entities */
    public function withEntities(array $entities): self
    {
        return new self(
            $this->intent,
            $this->query,
            $this->filters,
            $entities,
            $this->requiresAuth,
            $this->confidence,
            $this->routingMode,
            $this->clarificationReason,
            $this->needsClarification,
            $this->decisionState,
            $this->denialReason,
            $this->detectedIntents,
            $this->capabilities,
            $this->concepts,
            $this->requiredEvidenceDomain,
            $this->branches,
            $this->composition,
            $this->contextProductId,
            $this->contextCategoryId,
            $this->requiresCartConfirmation,
            $this->telemetry,
            $this->semanticIntent,
            $this->resource,
            $this->operation,
            $this->mentionedResources,
            $this->mutationTarget,
            $this->mutationTargetAmbiguous,
        );
    }

    /** @param array<int, self> $branches */
    public function withBranches(array $branches): self
    {
        return new self(
            $this->intent,
            $this->query,
            $this->filters,
            $this->entities,
            $this->requiresAuth,
            $this->confidence,
            $this->routingMode,
            $this->clarificationReason,
            $this->needsClarification,
            $this->decisionState,
            $this->denialReason,
            $this->detectedIntents,
            $this->capabilities,
            $this->concepts,
            $this->requiredEvidenceDomain,
            $branches,
            $this->composition,
            $this->contextProductId,
            $this->contextCategoryId,
            $this->requiresCartConfirmation,
            $this->telemetry,
            $this->semanticIntent,
            $this->resource,
            $this->operation,
            $this->mentionedResources,
            $this->mutationTarget,
            $this->mutationTargetAmbiguous,
        );
    }

    public function withMutationTarget(?ChatMutationTarget $target, bool $ambiguous = false): self
    {
        $clarification = $ambiguous;
        $resolved = $target !== null;

        return new self(
            $clarification ? ChatIntent::Clarification : $this->intent,
            $this->query,
            $this->filters,
            $this->entities,
            $this->requiresAuth,
            $clarification ? 0.0 : $this->confidence,
            $clarification ? 'context' : $this->routingMode,
            $clarification ? 'mutation_target' : ($resolved ? null : $this->clarificationReason),
            $clarification || (! $resolved && $this->needsClarification),
            $clarification ? 'clarification' : $this->decisionState,
            $this->denialReason,
            $this->detectedIntents,
            $this->capabilities,
            $this->concepts,
            $this->requiredEvidenceDomain,
            $this->branches,
            $this->composition,
            $this->contextProductId,
            $this->contextCategoryId,
            $this->requiresCartConfirmation,
            $this->telemetry,
            $clarification ? 'clarification' : $this->semanticIntent,
            $target?->value ?? $this->resource,
            $this->operation,
            $this->mentionedResources,
            $target,
            $ambiguous,
        );
    }

    public function withDeniedMutation(): self
    {
        if ($this->mutationTarget === null || $this->operation !== 'mutate') {
            throw new LogicException('Only a resolved mutation target can enter the mutation denial path.');
        }

        return new self(
            ChatIntent::Unsupported,
            $this->query,
            $this->filters,
            $this->entities,
            $this->requiresAuth,
            $this->confidence,
            $this->routingMode,
            null,
            false,
            'denied_action',
            $this->mutationTarget->value.'_mutation',
            $this->detectedIntents,
            [$this->mutationTarget->value.'_mutation'],
            $this->concepts,
            $this->requiredEvidenceDomain,
            $this->branches,
            $this->composition,
            $this->contextProductId,
            $this->contextCategoryId,
            $this->requiresCartConfirmation,
            $this->telemetry,
            'privileged_mutation',
            $this->mutationTarget->value,
            'mutate',
            $this->mentionedResources,
            $this->mutationTarget,
            false,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'semantic_intent' => $this->semanticIntent ?? $this->intent->value,
            'resource' => $this->resource,
            'operation' => $this->operation,
            'mentioned_resources' => $this->mentionedResources,
            'mutation_target' => $this->mutationTarget?->value,
            'mutation_target_ambiguous' => $this->mutationTargetAmbiguous,
            'query' => $this->query,
            'filters' => $this->filters,
            'requested_product' => $this->entities['product_name'] !== '' ? $this->entities['product_name'] : null,
            'requires_auth' => $this->requiresAuth,
            'confidence' => $this->confidence,
            'routing_mode' => $this->routingMode,
            'clarification_reason' => $this->clarificationReason,
            'needs_clarification' => $this->needsClarification,
            'decision_state' => $this->decisionState,
            'denial_reason' => $this->denialReason,
            'detected_intents' => $this->detectedIntents,
            'primary_intent' => $this->branches[0]->intent->value ?? $this->intent->value,
            'secondary_intents' => array_map(fn (self $branch): string => $branch->intent->value, array_slice($this->branches, 1)),
            'subrequests' => $this->branches,
            'composition' => $this->composition,
            'capabilities' => $this->capabilities,
            'concepts' => $this->concepts,
            'required_evidence_domain' => $this->requiredEvidenceDomain,
            'entities' => $this->entities,
            'context_product_id' => $this->contextProductId,
            'context_category_id' => $this->contextCategoryId,
            'requires_cart_confirmation' => $this->requiresCartConfirmation,
            '_telemetry' => $this->telemetry,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Route frames are immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Route frames are immutable.');
    }
}
