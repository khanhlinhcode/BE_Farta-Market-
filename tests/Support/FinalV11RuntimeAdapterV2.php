<?php

namespace Tests\Support;

use App\Enums\ChatIntent;
use App\Enums\ChatMutationTarget;
use App\Services\Chat\ChatEvidenceDomain;
use App\Services\Chat\ChatRouteFrame;

final class FinalV11RuntimeAdapterV2
{
    public const VERSION = 'farta-final-v11-runtime-adapter.2.0.0';

    private const RUNTIME_FIELDS = [
        'terminal',
        'authorization_result',
        'evidence',
        'follow_up_state',
        'business_state_before',
        'business_state_after',
        'wrong_entity_unsafe_action',
        'branch_observations',
        'response',
    ];

    private const CLAIM_FIELDS = [
        'authority',
        'source_id',
        'source_version',
        'evidence_ref',
        'evidence_type',
    ];

    /**
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    public static function normalize(ChatRouteFrame|array $route, array $runtime): array
    {
        self::assertRuntimeInput($runtime);
        $frame = self::frame($route);
        $semanticIntent = self::string($frame['semantic_intent'] ?? null, 'route.semantic_intent');
        $terminal = self::string($runtime['terminal'] ?? null, 'runtime.terminal');
        $mutationTarget = self::mutationTarget($frame['mutation_target'] ?? null);
        $ambiguous = ($frame['mutation_target_ambiguous'] ?? false) === true;
        if ($ambiguous && $mutationTarget !== null) {
            throw new FinalV11ContractException('Ambiguous mutation state cannot contain a resolved target.');
        }
        if (($frame['operation'] ?? null) === 'mutate' && ! $ambiguous && $semanticIntent === 'privileged_mutation'
            && $mutationTarget === null && in_array($frame['resource'] ?? null, ['order', 'payment'], true)) {
            throw new FinalV11ContractException('A typed Phase 15 mutation resource requires mutation_target.');
        }

        $claims = self::claims($runtime['evidence'] ?? []);
        $acceptedSourceIds = array_values(array_unique(array_column($claims, 'source_id')));
        sort($acceptedSourceIds, SORT_STRING);
        $acceptedSourceVersions = [];
        foreach ($acceptedSourceIds as $sourceId) {
            $matching = array_values(array_filter($claims, fn (array $claim): bool => $claim['source_id'] === $sourceId));
            $versions = array_values(array_unique(array_column($matching, 'source_version')));
            if (count($versions) !== 1) {
                throw new FinalV11ContractException('Conflicting source versions for '.$sourceId.'.');
            }
            $acceptedSourceVersions[$sourceId] = $versions[0];
        }

        $branches = self::branches($frame, $runtime);
        $intent = self::intent($semanticIntent, $frame);
        $authorization = self::string($runtime['authorization_result'] ?? null, 'runtime.authorization_result');
        $before = self::stateHash($runtime['business_state_before'] ?? null, 'runtime.business_state_before');
        $after = self::stateHash($runtime['business_state_after'] ?? null, 'runtime.business_state_after');

        $observation = [
            'intent' => $intent,
            'entities' => self::entities($frame['entities'] ?? null),
            'entity_scope' => $branches === [] ? 'request' : 'parent',
            'capability' => self::stringList($frame['capabilities'] ?? [], 'route.capabilities'),
            'resource' => self::string($frame['resource'] ?? null, 'route.resource'),
            'operation' => self::string($frame['operation'] ?? null, 'route.operation'),
            'mentioned_resources' => self::stringList($frame['mentioned_resources'] ?? [], 'route.mentioned_resources'),
            'mutation_target' => $mutationTarget,
            'mutation_target_ambiguous' => $ambiguous,
            'business_outcome_category' => self::businessOutcome($semanticIntent, $terminal, $mutationTarget, $frame, $runtime),
            'handler' => self::handler($intent),
            'authorization_result' => $authorization,
            'multi_intent_branches' => $branches,
            'follow_up_state' => self::followUpState($runtime['follow_up_state'] ?? [], $mutationTarget),
            'claims' => $claims,
            'claim_evidence' => self::claimEvidence($claims),
            'accepted_source_ids' => $acceptedSourceIds,
            'accepted_source_versions' => $acceptedSourceVersions,
            'required_evidence_domain' => self::evidenceDomain($frame['required_evidence_domain'] ?? null),
            'security_outcome' => $authorization,
            'terminal' => $terminal,
            'unsafe_execution' => ! hash_equals($before, $after),
            'wrong_entity_unsafe_action' => self::boolean(
                $runtime['wrong_entity_unsafe_action'] ?? null,
                'runtime.wrong_entity_unsafe_action'
            ),
        ];

        FinalV11EvaluationV2::assertPredictionSchema(self::prediction($observation));

        return $observation;
    }

    /** @param array<string, mixed> $observation @return array<string, mixed> */
    public static function prediction(array $observation): array
    {
        $prediction = [
            'intent' => $observation['intent'] ?? null,
            'handler' => $observation['handler'] ?? null,
            'business_outcome_category' => $observation['business_outcome_category'] ?? null,
            'terminal' => $observation['terminal'] ?? null,
            'entities' => $observation['entities'] ?? null,
            'required_evidence_domain' => $observation['required_evidence_domain'] ?? null,
            'accepted_source_ids' => $observation['accepted_source_ids'] ?? null,
            'accepted_source_versions' => $observation['accepted_source_versions'] ?? null,
            'claim_evidence' => $observation['claim_evidence'] ?? null,
            'security_decision' => $observation['security_outcome'] ?? null,
            'unsafe_execution' => $observation['unsafe_execution'] ?? null,
            'wrong_entity_unsafe_action' => $observation['wrong_entity_unsafe_action'] ?? null,
        ];
        FinalV11EvaluationV2::assertPredictionSchema($prediction);

        return $prediction;
    }

    public static function claimId(string $claimKey): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9._:-]{2,159}$/', $claimKey) !== 1) {
            throw new FinalV11ContractException('Canonical claim key has an invalid format.');
        }

        return 'sha256:'.hash('sha256', self::VERSION."\0".$claimKey);
    }

    public static function claimKeyFromProvenance(
        string $sourceId,
        string $evidenceRef,
        string $evidenceType
    ): string {
        if ($evidenceType === 'published_section') {
            return 'knowledge.'.$sourceId.'.section.'.hash('sha256', $evidenceRef);
        }

        if ($evidenceType !== 'structured_field') {
            throw new FinalV11ContractException('Unknown V2 evidence type.');
        }
        if (preg_match('/^product:(\d+):(name|active|category|current_unit_price_vnd|current_stock)$/', $evidenceRef, $match) === 1) {
            $field = match ($match[2]) {
                'current_unit_price_vnd' => 'price.current',
                'current_stock' => 'stock.current',
                default => $match[2],
            };

            return 'product.'.$match[1].'.'.$field;
        }
        if (preg_match('/^category:(\d+):(name|active)$/', $evidenceRef, $match) === 1) {
            return 'category.'.$match[1].'.'.$match[2];
        }
        if (preg_match('/^order:([a-zA-Z0-9_-]+):(status|payment_status|grand_total)$/', $evidenceRef, $match) === 1) {
            $field = $match[2] === 'payment_status' ? 'payment.status' : $match[2];

            return 'order.'.$match[1].'.'.$field;
        }
        $siteSettings = [
            'site-setting:shipping_fee_vnd' => 'shipping.fee.current',
            'site-setting:free_shipping_threshold_vnd' => 'shipping.free_threshold.current',
            'site-setting:contact_email' => 'contact.email.current',
            'site-setting:contact_phone' => 'contact.phone.current',
            'site-setting:support_phone' => 'contact.support_phone.current',
            'site-setting:address_vi' => 'contact.address_vi.current',
            'site-setting:address_en' => 'contact.address_en.current',
        ];
        if (isset($siteSettings[$evidenceRef])) {
            return $siteSettings[$evidenceRef];
        }

        throw new FinalV11ContractException('Unknown structured evidence reference.');
    }

    /** @param array<string, mixed> $runtime */
    private static function assertRuntimeInput(array $runtime): void
    {
        if (array_is_list($runtime) || array_keys($runtime) !== self::RUNTIME_FIELDS) {
            throw new FinalV11ContractException('Runtime observation must contain exactly the ordered V2 fields.');
        }
        $encoded = json_encode(array_keys($runtime), JSON_THROW_ON_ERROR);
        if (preg_match('/gold|expected|minimum_facts_required/i', $encoded) === 1) {
            throw new FinalV11ContractException('Gold-derived fields are forbidden in V2 runtime observations.');
        }
    }

    /** @return array<string, mixed> */
    private static function frame(ChatRouteFrame|array $route): array
    {
        if ($route instanceof ChatRouteFrame) {
            return [
                ...$route->toArray(),
                'intent' => $route->intent->value,
                'required_evidence_domain' => $route->requiredEvidenceDomain?->topic,
                'subrequests' => $route->branches,
            ];
        }
        if (array_is_list($route)) {
            throw new FinalV11ContractException('Route frame must be an object.');
        }
        $intent = $route['intent'] ?? null;
        if ($intent instanceof ChatIntent) {
            $route['intent'] = $intent->value;
        }
        $target = $route['mutation_target'] ?? null;
        if ($target instanceof ChatMutationTarget) {
            $route['mutation_target'] = $target->value;
        }
        $domain = $route['required_evidence_domain'] ?? null;
        if ($domain instanceof ChatEvidenceDomain) {
            $route['required_evidence_domain'] = $domain->topic;
        }

        return $route;
    }

    /** @param array<string, mixed> $frame @param array<string, mixed> $runtime @return array<int, array<string, mixed>> */
    private static function branches(array $frame, array $runtime): array
    {
        $frames = $frame['subrequests'] ?? $frame['branches'] ?? [];
        $observations = $runtime['branch_observations'] ?? null;
        if (! is_array($frames) || ! array_is_list($frames)
            || ! is_array($observations) || ! array_is_list($observations)
            || count($frames) !== count($observations)) {
            throw new FinalV11ContractException('V2 branch frames and observations must be ordered arrays of equal length.');
        }
        $branches = [];
        foreach ($frames as $index => $branch) {
            if (! $branch instanceof ChatRouteFrame && (! is_array($branch) || array_is_list($branch))) {
                throw new FinalV11ContractException('Invalid V2 branch frame at index '.$index.'.');
            }
            if (! is_array($observations[$index]) || array_is_list($observations[$index])) {
                throw new FinalV11ContractException('Invalid V2 branch observation at index '.$index.'.');
            }
            $branches[] = self::normalize($branch, $observations[$index]);
        }

        return $branches;
    }

    /** @param array<string, mixed> $frame */
    private static function intent(string $semanticIntent, array $frame): string
    {
        if ($semanticIntent === 'clarification' || ($frame['mutation_target_ambiguous'] ?? false) === true) {
            return 'clarification';
        }

        return match ($semanticIntent) {
            'catalog_listing' => 'catalog_listing',
            'cart_query' => 'cart_informational',
            'store_contact' => 'knowledge_query',
            'product_search', 'product_detail', 'price', 'stock_availability', 'cart_informational',
            'cart_action_request', 'order_read', 'payment_status_read', 'shipping_current_value',
            'shipping_calculation', 'knowledge_query', 'missing_evidence_query', 'multi_intent',
            'general_chat', 'privileged_mutation', 'unsupported_ood' => $semanticIntent,
            default => throw new FinalV11ContractException('Unknown Phase 15 semantic intent '.$semanticIntent.'.'),
        };
    }

    private static function handler(string $intent): string
    {
        return match ($intent) {
            'product_search' => 'PRODUCT_SEARCH',
            'product_detail' => 'PRODUCT_DETAIL',
            'price' => 'PRODUCT_PRICE',
            'stock_availability' => 'PRODUCT_STOCK',
            'catalog_listing' => 'CATALOG_LIST',
            'shipping_current_value' => 'SHIPPING_SETTINGS',
            'shipping_calculation' => 'DETERMINISTIC_PRICE_SHIPPING_CALCULATOR',
            'cart_informational', 'knowledge_query' => 'KNOWLEDGE_GROUNDED_ANSWER',
            'missing_evidence_query' => 'KNOWLEDGE_EVIDENCE_GATE',
            'cart_action_request' => 'CART_SUGGESTED_ACTION',
            'order_read' => 'AUTHORIZED_ORDER_READ',
            'payment_status_read' => 'AUTHORIZED_PAYMENT_STATUS_READ',
            'general_chat' => 'GENERAL_CONVERSATION',
            'clarification' => 'CLARIFICATION',
            'privileged_mutation' => 'SECURITY_POLICY_DENIAL',
            'multi_intent' => 'MULTI_INTENT_ORCHESTRATOR',
            'unsupported_ood' => 'OOD_BOUNDARY',
            default => throw new FinalV11ContractException('No V2 handler mapping for '.$intent.'.'),
        };
    }

    /** @param array<string, mixed> $frame @param array<string, mixed> $runtime */
    private static function businessOutcome(
        string $intent,
        string $terminal,
        ?string $mutationTarget,
        array $frame,
        array $runtime
    ): string {
        if ($intent === 'privileged_mutation') {
            return match (true) {
                $mutationTarget === 'order' => 'DENY_CHATBOT_ORDER_MUTATION',
                $mutationTarget === 'payment' => 'DENY_PAYMENT_STATE_MUTATION',
                ($frame['denial_reason'] ?? null) === 'authorization_bypass' => 'DENY_AUTH_BYPASS',
                ($frame['denial_reason'] ?? null) === 'internal_instruction_disclosure' => 'DENY_SECRET_DISCLOSURE',
                ($frame['denial_reason'] ?? null) === 'inventory_override' => 'DENY_INVENTORY_MUTATION',
                in_array($frame['denial_reason'] ?? null, ['account_or_role_mutation', 'refund_or_return_mutation'], true) => 'DENY_PROHIBITED_CAPABILITY',
                default => throw new FinalV11ContractException('Privileged mutation lacks an authoritative typed target or denial reason.'),
            };
        }

        return match ($intent) {
            'product_search' => $terminal === 'NO_RESULTS' ? 'NO_MATCH' : 'RETURN_MATCHING_ACTIVE_PRODUCTS',
            'product_detail' => $terminal === 'NO_EVIDENCE' ? 'DO_NOT_ASSERT_UNSOURCED_PRODUCT_CLAIM' : 'RETURN_GROUNDED_PRODUCT_DETAILS',
            'price' => 'RETURN_CURRENT_UNIT_PRICE',
            'stock_availability' => match (true) {
                $terminal === 'UNAVAILABLE' => 'REJECT_QUANTITY_ABOVE_CURRENT_STOCK',
                (int) (($frame['entities']['quantity'] ?? 0)) > 0 => 'COMPARE_REQUESTED_QUANTITY_TO_STOCK',
                default => 'RETURN_CURRENT_AVAILABILITY',
            },
            'catalog_listing' => ($frame['filters']['category'] ?? null) === null
                ? 'RETURN_ACTIVE_CATALOG'
                : 'RETURN_ACTIVE_CATEGORY_PRODUCTS',
            'shipping_current_value' => 'RETURN_CURRENT_SHIPPING_RULE',
            'shipping_calculation' => 'RETURN_DETERMINISTIC_SUBTOTAL_SHIPPING_TOTAL',
            'cart_informational' => 'ANSWER_CART_PROCESS_FROM_APPROVED_GUIDE',
            'cart_action_request' => $terminal === 'AUTH_REQUIRED'
                ? 'REQUIRE_AUTHENTICATION'
                : 'RETURN_ADD_TO_CART_SUGGESTION_WITH_CONFIRMATION',
            'order_read' => match ($terminal) {
                'AUTH_REQUIRED' => 'REQUIRE_AUTHENTICATION',
                'DENIED' => 'DENY_CROSS_ACCOUNT_READ',
                default => is_array($runtime['response']['orders'] ?? null)
                    ? 'LIST_OWN_ORDERS'
                    : (is_array($runtime['response']['order'] ?? null) ? 'READ_OWN_ORDER' : 'READ_OWN_ORDER_OR_NOT_FOUND'),
            },
            'payment_status_read' => match ($terminal) {
                'AUTH_REQUIRED' => 'REQUIRE_AUTHENTICATION',
                'DENIED' => 'DENY_CROSS_ACCOUNT_READ',
                default => 'READ_OWN_PAYMENT_STATUS',
            },
            'knowledge_query' => 'ANSWER_FROM_APPROVED_KNOWLEDGE',
            'missing_evidence_query' => 'DO_NOT_ASSERT_UNSOURCED_POLICY',
            'general_chat' => 'BRIEF_SOCIAL_RESPONSE_OR_CAPABILITY_GUIDANCE',
            'clarification' => ($frame['concepts']['product_facet'] ?? null) === 'price'
                ? 'CLARIFY_PRICE_RANGE'
                : 'ASK_TARGETED_CLARIFYING_QUESTION',
            'multi_intent' => 'PROCESS_EVERY_BRANCH_WITH_EXPLICIT_TERMINAL_STATE',
            'unsupported_ood' => 'DO_NOT_FORCE_INTO_COMMERCE_INTENT',
            default => throw new FinalV11ContractException('No V2 business-outcome mapping for '.$intent.'.'),
        };
    }

    /** @return array<string, mixed> */
    private static function entities(mixed $entities): array
    {
        if (! is_array($entities) || array_is_list($entities)) {
            throw new FinalV11ContractException('Phase 15 route entities must be an object.');
        }
        $normalized = [];
        foreach (FinalV11Evaluation::ENTITY_SLOTS as $slot) {
            $normalized[$slot] = $entities[$slot] ?? null;
        }

        return $normalized;
    }

    /** @return array<int, array<string, mixed>> */
    private static function claims(mixed $evidence): array
    {
        if (! is_array($evidence) || ! array_is_list($evidence)) {
            throw new FinalV11ContractException('Runtime evidence must be an array.');
        }
        $registry = self::authorityRegistry();
        $claims = [];
        foreach ($evidence as $index => $claim) {
            if (! is_array($claim) || array_is_list($claim) || array_keys($claim) !== self::CLAIM_FIELDS) {
                throw new FinalV11ContractException('Runtime evidence '.$index.' does not match the canonical provenance schema.');
            }
            foreach (['authority', 'source_id', 'evidence_ref', 'evidence_type'] as $field) {
                self::string($claim[$field] ?? null, 'runtime.evidence['.$index.'].'.$field);
            }
            $source = $registry[$claim['source_id']] ?? null;
            if (! is_array($source)
                || ! is_int($claim['source_version'] ?? null)
                || $claim['source_version'] !== $source['version']
                || $claim['authority'] !== $source['authority_type']) {
                throw new FinalV11ContractException('Runtime evidence '.$index.' has unregistered authority provenance.');
            }
            $claimKey = self::claimKeyFromProvenance(
                $claim['source_id'],
                $claim['evidence_ref'],
                $claim['evidence_type']
            );
            self::claimId($claimKey);
            $claims[] = ['claim_key' => $claimKey, ...$claim];
        }
        usort($claims, fn (array $left, array $right): int => [
            $left['claim_key'], $left['source_id'], $left['evidence_ref'],
        ] <=> [
            $right['claim_key'], $right['source_id'], $right['evidence_ref'],
        ]);

        return $claims;
    }

    /** @param array<int, array<string, mixed>> $claims @return array<int, array{claim_id: string, source_ids: array<int, string>}> */
    private static function claimEvidence(array $claims): array
    {
        $grouped = [];
        foreach ($claims as $claim) {
            $grouped[$claim['claim_key']][] = $claim['source_id'];
        }
        ksort($grouped, SORT_STRING);
        $evidence = [];
        foreach ($grouped as $claimKey => $sourceIds) {
            $sourceIds = array_values(array_unique($sourceIds));
            sort($sourceIds, SORT_STRING);
            $evidence[] = ['claim_id' => self::claimId($claimKey), 'source_ids' => $sourceIds];
        }

        return $evidence;
    }

    /** @return array<string, array{authority_type: string, version: int}> */
    private static function authorityRegistry(): array
    {
        static $registry;

        if (is_array($registry)) {
            return $registry;
        }
        $contract = json_decode(
            (string) file_get_contents(base_path('docs/evaluation/v11-independent/v11-fact-verification-contract.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $registry = [];
        foreach ($contract['authority_sources'] ?? [] as $source) {
            if (! is_array($source) || ! is_string($source['source_id'] ?? null)
                || ! is_string($source['authority_type'] ?? null) || ! is_int($source['version'] ?? null)) {
                throw new FinalV11ContractException('Invalid frozen authority registry entry.');
            }
            $registry[$source['source_id']] = [
                'authority_type' => $source['authority_type'],
                'version' => $source['version'],
            ];
        }

        return $registry;
    }

    /** @return array{resolved_by_context: bool, mutation_target: ?string, status: string} */
    private static function followUpState(mixed $state, ?string $mutationTarget): array
    {
        if (! is_array($state) || array_is_list($state)
            || array_keys($state) !== ['resolved_by_context', 'mutation_target', 'status']) {
            throw new FinalV11ContractException('V2 follow_up_state has an invalid shape.');
        }
        $resolved = self::boolean($state['resolved_by_context'], 'follow_up_state.resolved_by_context');
        $target = self::mutationTarget($state['mutation_target']);
        $status = self::string($state['status'], 'follow_up_state.status');
        if (! in_array($status, ['not_applicable', 'resolved', 'ambiguous', 'unresolved'], true)) {
            throw new FinalV11ContractException('V2 follow_up_state has an unknown status.');
        }
        if ($resolved && ($target === null || $target !== $mutationTarget)) {
            throw new FinalV11ContractException('Resolved follow-up target must equal the typed route target.');
        }

        return ['resolved_by_context' => $resolved, 'mutation_target' => $target, 'status' => $status];
    }

    private static function evidenceDomain(mixed $domain): ?string
    {
        if ($domain instanceof ChatEvidenceDomain) {
            return $domain->topic;
        }
        if ($domain === null) {
            return null;
        }

        return self::string($domain, 'route.required_evidence_domain');
    }

    private static function mutationTarget(mixed $target): ?string
    {
        if ($target instanceof ChatMutationTarget) {
            $target = $target->value;
        }
        if ($target === null) {
            return null;
        }
        if (! in_array($target, ['order', 'payment'], true)) {
            throw new FinalV11ContractException('Unknown typed mutation target.');
        }

        return $target;
    }

    private static function stateHash(mixed $value, string $path): string
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new FinalV11ContractException($path.' must be a SHA-256 state snapshot.');
        }

        return $value;
    }

    /** @return array<int, string> */
    private static function stringList(mixed $value, string $path): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new FinalV11ContractException($path.' must be an array.');
        }
        foreach ($value as $item) {
            self::string($item, $path.'[]');
        }

        return $value;
    }

    private static function string(mixed $value, string $path): string
    {
        if (! is_string($value) || $value === '') {
            throw new FinalV11ContractException($path.' must be a non-empty string.');
        }

        return $value;
    }

    private static function boolean(mixed $value, string $path): bool
    {
        if (! is_bool($value)) {
            throw new FinalV11ContractException($path.' must be boolean.');
        }

        return $value;
    }
}
