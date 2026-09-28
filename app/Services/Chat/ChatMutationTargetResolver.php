<?php

namespace App\Services\Chat;

use App\Enums\ChatMutationTarget;

/**
 * Resolves the object of an order/payment mutation from its action, direct
 * object, and state/value relationship. It never selects a resource merely
 * because it was mentioned first, last, or more often.
 */
final class ChatMutationTargetResolver
{
    public function __construct(private readonly ChatEntityExtractor $entities = new ChatEntityExtractor) {}

    public function resolve(string $raw): ChatMutationTargetResolution
    {
        $message = $this->entities->normalize($raw);
        $mentioned = $this->mentionedResources($message);
        $operation = $this->operation($message);
        if ($operation !== 'mutate') {
            return new ChatMutationTargetResolution($operation, $mentioned, null);
        }

        if (count($mentioned) > 1 && $this->coordinatesResources($message)) {
            return new ChatMutationTargetResolution($operation, $mentioned, null);
        }

        $stateTarget = $this->targetFromRequestedState($message);
        if ($stateTarget !== null) {
            return new ChatMutationTargetResolution($operation, $mentioned, $stateTarget);
        }

        $targets = array_keys(array_filter([
            ChatMutationTarget::Order->value => $this->targetsOrder($message),
            ChatMutationTarget::Payment->value => $this->targetsPayment($message),
        ]));
        if (count($targets) === 1) {
            return new ChatMutationTargetResolution($operation, $mentioned, ChatMutationTarget::from($targets[0]));
        }

        // With one protected resource, the action has a single possible
        // object. With both, absence of a direct semantic relation is kept
        // unresolved for clarification rather than defaulted.
        if (count($mentioned) === 1) {
            return new ChatMutationTargetResolution($operation, $mentioned, ChatMutationTarget::from($mentioned[0]));
        }

        return new ChatMutationTargetResolution($operation, $mentioned, null);
    }

    /**
     * Resolve a state-value conjunct only after its parent clause has already
     * established a mutation action. A standalone state word is never treated
     * as a mutation request by resolve().
     */
    public function ellipticalStateTarget(string $raw): ?ChatMutationTarget
    {
        $message = $this->entities->normalize($raw);
        $payment = preg_match('/^(?:paid|unpaid|da tra(?: tien)?|chua tra tien|thanh cong|that bai|hoan tien)$/', $message) === 1;
        $order = preg_match('/^(?:completed|delivered|processing|shipping|confirmed|cancelled|canceled|hoan tat|da giao|dang giao|dang xu ly|da xac nhan|da huy)$/', $message) === 1;

        return match (true) {
            $payment && ! $order => ChatMutationTarget::Payment,
            $order && ! $payment => ChatMutationTarget::Order,
            default => null,
        };
    }

    /** @return array<int, string> */
    private function mentionedResources(string $message): array
    {
        $resources = [];
        if ($this->hasOrder($message)) {
            $resources[] = ChatMutationTarget::Order->value;
        }
        if ($this->hasPayment($message)) {
            $resources[] = ChatMutationTarget::Payment->value;
        }

        return $resources;
    }

    private function operation(string $message): string
    {
        $read = preg_match('/\b(?:xem|coi|kiem tra|tra cuu|show|view|read|check|status|how|what|when|khi nao|can i|chinh sach|policy|quy dinh)\b/', $message) === 1;
        $question = preg_match('/\b(?:how|what|when|khi nao|can i|co the khong|duoc khong|chinh sach|policy|quy dinh)\b/', $message) === 1
            || preg_match('/\bchua$/', $message) === 1;
        $guidance = preg_match('/\b(?:huong dan|quy trinh|how to|guide|guidance|policy|chinh sach|quy dinh)\b/', $message) === 1;
        $mutation = preg_match(
            '/\b(?:cap nhat|danh dau|ghi nhan|chinh sua|sua|doi|thay|xoa|huy|xac nhan|coi nhu|xem nhu|tinh la|set|update|edit|change|delete|mark|record|cancel|confirm|regard as|treat as|override|force)\b/',
            $message,
        ) === 1;
        $targetedTransition = preg_match(
            '/\bchuyen\b(?:\s+\w+){0,3}\s+\b(?:don(?: hang)?|order|trang thai|payment|thanh toan)\b/',
            $message,
        ) === 1;
        $stateCoercion = preg_match(
            '/\b(?:coi|xem|tinh|regard|treat)\b.*\b(?:nhu|la|as)\b.*\b(?:tra tien|thanh toan|paid|hoan tat|completed|da giao|delivered)\b/',
            $message,
        ) === 1;

        if (! $guidance && ! $question && ($stateCoercion || $mutation || $targetedTransition)) {
            return 'mutate';
        }

        return $read ? 'read' : 'unknown';
    }

    private function targetsOrder(string $message): bool
    {
        $order = '(?:don(?: hang)?|order(?: status)?|purchase)';
        $action = '(?:cap nhat|danh dau|ghi nhan|chinh sua|sua|doi|thay|chuyen|xoa|huy|xac nhan|set|update|edit|change|delete|mark|record|cancel|confirm|override|force)';
        $orderState = '(?:huy|cancel(?:led)?|da giao|delivered|dang giao|shipping|processing|hoan tat|completed|confirmed|trang thai don|order status)';
        $resource = '(?:don(?: hang)?|order(?: status)?|purchase|thanh toan|tra tien|payment(?: status| result)?|transaction|transfer|sepay|paid)';

        return preg_match('/\b'.$action.'\b(?:\s+(?!'.$resource.'\b)\w+){0,5}\s+\b'.$order.'\b/', $message) === 1
            || preg_match('/\b'.$order.'\b(?:\s+\w+){0,8}\b(?:thanh|la|to)\b(?:\s+\w+){0,4}\b'.$orderState.'\b/', $message) === 1
            || preg_match('/\b(?:coi|xem|tinh|regard|treat)\b(?:\s+\w+){0,5}\b'.$order.'\b.*\b(?:nhu|la|as)\b.*\b'.$orderState.'\b/', $message) === 1;
    }

    private function targetsPayment(string $message): bool
    {
        $payment = '(?:thanh toan|tra tien|payment(?: status| result)?|transaction|transfer|sepay|paid)';
        $action = '(?:cap nhat|danh dau|ghi nhan|chinh sua|sua|doi|thay|chuyen|xac nhan|set|update|edit|change|mark|record|confirm|override|force)';
        $paymentState = '(?:paid|pending|success|successful|failed|failure|refunded|refund|da tra tien|chua tra tien|thanh cong|that bai|hoan tien|trang thai thanh toan|payment status)';
        $resource = '(?:don(?: hang)?|order(?: status)?|purchase|thanh toan|tra tien|payment(?: status| result)?|transaction|transfer|sepay|paid)';

        return preg_match('/\b'.$action.'\b(?:\s+(?!'.$resource.'\b)\w+){0,5}\s+\b'.$payment.'\b/', $message) === 1
            || preg_match('/\b'.$payment.'\b(?:\s+\w+){0,8}\b(?:thanh|la|to)\b(?:\s+\w+){0,4}\b'.$paymentState.'\b/', $message) === 1
            || preg_match('/\b(?:coi|xem|tinh|regard|treat)\b(?:\s+\w+){0,5}\b'.$payment.'\b.*\b(?:nhu|la|as)\b.*\b'.$paymentState.'\b/', $message) === 1;
    }

    private function hasOrder(string $message): bool
    {
        return preg_match('/\b(?:don(?: hang)?|order|purchase)\b/', $message) === 1;
    }

    private function hasPayment(string $message): bool
    {
        return preg_match('/\b(?:thanh toan|tra tien|payment|paid|pay|transaction|transfer|sepay|cod)\b/', $message) === 1;
    }

    private function targetFromRequestedState(string $message): ?ChatMutationTarget
    {
        $action = '(?:cap nhat|danh dau|ghi nhan|chinh sua|sua|doi|thay|chuyen|xac nhan|coi|xem|tinh|set|update|edit|change|mark|record|confirm|regard|treat|override|force)';
        $paymentState = '(?:paid|unpaid|pending payment|payment (?:success|successful|failed|failure|refunded)|da thanh toan|chua thanh toan|da tra(?: tien)?|chua tra tien|thanh toan thanh cong|thanh toan that bai|hoan tien)';
        $orderState = '(?:delivered|completed|processing|shipping|confirmed|cancelled|canceled|da giao|dang giao|dang xu ly|hoan tat|da xac nhan|da huy)';
        $payment = preg_match('/\b'.$action.'\b.*\b'.$paymentState.'\b/', $message) === 1;
        $order = preg_match('/\b'.$action.'\b.*\b'.$orderState.'\b/', $message) === 1;

        return match (true) {
            $payment && ! $order => ChatMutationTarget::Payment,
            $order && ! $payment => ChatMutationTarget::Order,
            default => null,
        };
    }

    private function coordinatesResources(string $message): bool
    {
        $order = '(?:don(?: hang)?|order|purchase)';
        $payment = '(?:thanh toan|tra tien|payment|transaction|transfer|sepay)';
        $connector = '(?:va|and|voi|with)';

        return preg_match('/\b'.$order.'\b(?:\s+\w+){0,2}\s+\b'.$connector.'\b(?:\s+\w+){0,2}\s+\b'.$payment.'\b/', $message) === 1
            || preg_match('/\b'.$payment.'\b(?:\s+\w+){0,2}\s+\b'.$connector.'\b(?:\s+\w+){0,2}\s+\b'.$order.'\b/', $message) === 1;
    }
}
