<?php

namespace App\Services\Chat;

use App\Enums\ChatMutationTarget;
use LogicException;

/**
 * Semantic state produced before capability resolution. Resource mentions are
 * deliberately separate from the resource selected for a mutation.
 */
final readonly class ChatMutationTargetResolution
{
    /** @param array<int, string> $mentionedResources */
    public function __construct(
        public string $operation,
        public array $mentionedResources,
        public ?ChatMutationTarget $mutationTarget,
    ) {
        if ($operation !== 'mutate' && $mutationTarget !== null) {
            throw new LogicException('Only mutation operations may select a mutation target.');
        }
    }

    public function needsClarification(): bool
    {
        return $this->operation === 'mutate'
            && count($this->mentionedResources) > 1
            && $this->mutationTarget === null;
    }
}
