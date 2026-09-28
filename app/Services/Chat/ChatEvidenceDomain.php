<?php

namespace App\Services\Chat;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use LogicException;

/**
 * Immutable evidence authority selected by routing. Retrieval may validate it,
 * but may not replace it with a broader domain.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
final readonly class ChatEvidenceDomain implements ArrayAccess, IteratorAggregate
{
    /** @param array<int, string> $allowedSourceIds */
    public function __construct(
        public string $topic,
        public string $claimType,
        public array $allowedSourceIds,
        public string $allowedAuthority,
        public ?string $structuredSource,
    ) {}

    /** @param array<string, mixed> $domain */
    public static function fromArray(array $domain): self
    {
        return new self(
            (string) ($domain['topic'] ?? 'unknown'),
            (string) ($domain['claim_type'] ?? 'unknown'),
            array_values(array_filter($domain['allowed_source_ids'] ?? [], 'is_string')),
            (string) ($domain['allowed_authority'] ?? 'none'),
            is_string($domain['structured_source'] ?? null) ? $domain['structured_source'] : null,
        );
    }

    /** @return array{topic: string, claim_type: string, allowed_source_ids: array<int, string>, allowed_authority: string, structured_source: ?string} */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'claim_type' => $this->claimType,
            'allowed_source_ids' => $this->allowedSourceIds,
            'allowed_authority' => $this->allowedAuthority,
            'structured_source' => $this->structuredSource,
        ];
    }

    public function sameAs(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['topic', 'claim_type', 'allowed_source_ids', 'allowed_authority', 'structured_source'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Evidence domains are immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Evidence domains are immutable.');
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }
}
