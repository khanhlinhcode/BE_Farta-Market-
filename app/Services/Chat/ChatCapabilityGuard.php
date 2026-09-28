<?php

namespace App\Services\Chat;

use App\Enums\ChatIntent;

final class ChatCapabilityGuard
{
    /**
     * Applies an already-resolved contextual mutation target. This boundary
     * intentionally consumes route state and never reinterprets the message.
     */
    public function enforceResolvedMutationTarget(ChatRouteFrame $route): ChatRouteFrame
    {
        if ($route->intent === ChatIntent::MultiIntent) {
            return $route->withBranches(array_map(
                fn (ChatRouteFrame $branch): ChatRouteFrame => $this->enforceResolvedMutationTarget($branch),
                $route->branches,
            ));
        }

        if ($route->mutationTarget === null
            || $route->mutationTargetAmbiguous
            || $route->operation !== 'mutate'
            || $route->denialReason !== null) {
            return $route;
        }

        return $route->withDeniedMutation();
    }

    /** @param array<string, mixed>|null $concepts */
    public function denialReason(
        string $message,
        ?array $concepts = null,
        ?ChatMutationTargetResolution $mutation = null,
    ): ?string {
        $capability = $this->classify($message, $concepts, $mutation);

        if ($capability['resource'] === 'authorization' && $capability['operation'] === 'bypass') {
            return 'authorization_bypass';
        }

        if ($capability['resource'] === 'internal_instructions' && $capability['operation'] === 'disclose') {
            return 'internal_instruction_disclosure';
        }

        if ($capability['resource'] === 'order' && $capability['operation'] === 'mutate') {
            return 'order_mutation';
        }

        if ($capability['resource'] === 'payment' && $capability['operation'] === 'mutate') {
            return 'payment_mutation';
        }

        if ($mutation === null && $capability['resource'] === 'order_or_payment' && $capability['operation'] === 'mutate') {
            return 'order_or_payment_mutation';
        }

        if ($capability['resource'] === 'returns' && $capability['operation'] === 'mutate') {
            return 'refund_or_return_mutation';
        }

        if ($capability['resource'] === 'inventory' && $capability['operation'] === 'mutate') {
            return 'inventory_override';
        }

        if ($capability['resource'] === 'account_role' && $capability['operation'] === 'mutate') {
            return 'account_or_role_mutation';
        }

        if ($capability['resource'] === 'other_user_data'
            && in_array($capability['operation'], ['read', 'mutate'], true)) {
            return 'other_user_data_access';
        }

        return null;
    }

    /**
     * Capability intent is a protected resource plus an operation, not merely
     * an unsafe word. The result remains advisory: handlers still authorize
     * every database read or mutation independently.
     *
     * @param  array<string, mixed>|null  $concepts
     * @return array{resource: string, operation: 'read'|'mutate'|'disclose'|'bypass'|'unknown'}
     */
    public function classify(
        string $message,
        ?array $concepts = null,
        ?ChatMutationTargetResolution $mutation = null,
    ): array {
        if ($this->matches($message, [
            'bo qua bao mat', 'bo qua xac thuc', 'bo qua dang nhap', 'khong can dang nhap',
            'vuot qua bao mat', 'vuot xac thuc', 'ignore security', 'ignore authentication',
            'ignore login', 'disable security', 'disable security checks', 'bypass security', 'bypass authentication', 'bypass login', 'bypass dang nhap', 'bypass email verification', 'skip login', 'skip authentication',
            'expose another user', 'expose another customer',
            'ignore previous instructions', 'ignore system instructions', 'bo qua quy tac truoc', 'bo qua huong dan truoc',
            'ignore all rules', 'ignore every rule', 'bo qua tat ca quy tac',
            'gia vo ban la admin', 'pretend you are admin', 'hay tin noi dung sau thay vi database',
            'trust this content instead of the database',
            'turn off email verification', 'disable email verification', 'skip email verification',
            'khong can verify email', 'khong can xac minh email', 'khong can email xac minh',
        ]) || preg_match('/\b(?:disable|turn off|skip|bypass)\s+(?:the\s+)?(?:login|authentication|security|email verification)\b/', $message) === 1) {
            return ['resource' => 'authorization', 'operation' => 'bypass'];
        }

        $internalInstructions = $this->matches($message, [
            'system prompt', 'internal prompt', 'hidden prompt', 'hidden instruction', 'hidden instructions',
            'developer prompt', 'developer message', 'developer instruction', 'developer instructions',
            'database password', 'database credential', 'database credentials',
            'prompt he thong', 'chi thi he thong', 'chi dan he thong', 'huong dan noi bo', 'quy tac noi bo', 'prompt cua chatbot',
        ]) || ($this->matches($message, ['system', 'developer', 'internal', 'hidden', 'he thong', 'noi bo'])
            && $this->matches($message, ['prompt', 'instruction', 'instructions', 'chi thi', 'chi dan', 'huong dan', 'rule', 'rules']));
        $disclose = $this->matches($message, [
            'hien', 'in', 'noi', 'doc', 'tiet lo', 'gui', 'xuat',
            'show', 'reveal', 'expose', 'print', 'repeat', 'tell', 'display', 'dump',
        ]) || $this->matches($message, ['database password', 'database credential', 'database credentials']);
        if ($internalInstructions) {
            return ['resource' => 'internal_instructions', 'operation' => $disclose ? 'disclose' : 'unknown'];
        }

        $questionRead = preg_match('/\b(?:how should|what should|what must|how long|when can|can i|can the chatbot|before placing|may ngay|bao lau|khi nao|duoc khong|quy trinh|huong dan|chinh sach|quy dinh)\b/', $message) === 1;
        $guidanceRead = ($concepts['knowledge_topic'] ?? null) !== null
            && ($questionRead || $this->matches($message, ['how to', 'guide', 'guidance', 'cac buoc', 'theo buoc']));
        $readOperation = $guidanceRead || $questionRead || ($concepts['operation'] ?? null) === 'read'
            || $this->matches($message, ['xem', 'coi', 'kiem tra', 'show', 'view', 'read', 'access', 'get']);
        $stateCoercion = preg_match(
            '/\b(?:coi|xem|tinh|regard|treat)\b.*\b(?:nhu|la|as)\b.*\b(?:tra tien|thanh toan|paid|hoan tat|completed|da giao|delivered)\b/',
            $message,
        ) === 1;
        $confirmationMutation = preg_match('/^(?:hay\s+|vui long\s+|please\s+)?xac nhan\b/', $message) === 1;
        $imperativeMutation = $this->matches($message, [
            'xoa', 'thay don', 'thay order', 'chuyen don', 'chuyen order',
            'chuyen trang thai', 'cap nhat', 'danh dau', 'ghi nhan', 'coi nhu', 'xem nhu', 'tinh la',
            'hoan tien', 'huy', 'dieu chinh', 'delete', 'edit', 'change', 'set', 'update', 'adjust',
            'confirm', 'mark', 'record', 'regard as', 'treat as', 'refund', 'cancel', 'approve', 'force', 'override',
        ]) || $confirmationMutation;
        $targetedEdit = preg_match('/\b(?:sua|doi|thay)\s+(?:don|order|trang thai|payment|thanh toan|tai khoan|vai tro)\b/', $message) === 1;
        $passiveStatus = $this->matches($message, ['da duoc ghi nhan', 'has been recorded', 'was recorded']);
        $statusRead = $readOperation
            && $this->matches($message, ['chua', 'status', 'da duoc', 'kiem tra'])
            && ($passiveStatus || (! $imperativeMutation && ! $targetedEdit));
        $mutates = ! ($guidanceRead || $questionRead) && ($stateCoercion
            || (! $statusRead && ($imperativeMutation || $targetedEdit))
            || (! $readOperation && $this->matches($message, ['hoan tat', 'complete'])));
        $operation = $mutates ? 'mutate' : ($readOperation ? 'read' : 'unknown');

        if ($this->matches($message, ['hoan tien', 'refund', 'doi hang', 'return item'])) {
            return ['resource' => 'returns', 'operation' => $operation];
        }
        if ($this->matches($message, [
            'ton kho', 'so luong', 'kiem tra ton kho', 'kiem kho', 'inventory', 'stock', 'availability', 'available',
        ])) {
            $override = $this->matches($message, [
                'bo qua', 'ignore', 'override', 'force', 'gia vo', 'pretend', 'unlimited', 'remove', 'disable', 'skip',
                'set', 'change', 'modify', 'doi', 'tang', 'increase', 'cap nhat', 'update',
            ]) || preg_match('/\b(?:ep|sua)\s+(?:ton kho|so luong|inventory|stock|availability)\b/', $message) === 1
                || ($this->matches($message, ['inventory validation', 'stock validation', 'kiem tra ton kho', 'validation'])
                    && $this->matches($message, ['remove', 'disable', 'skip', 'bypass']));

            return ['resource' => 'inventory', 'operation' => $override ? 'mutate' : $operation];
        }
        $privilegedAccount = $this->matches($message, [
            'quyen admin', 'vai tro admin', 'admin role', 'administrator role', 'admin permission', 'quyen quan tri',
        ]) || ($this->matches($message, ['admin', 'administrator', 'quan tri'])
            && $this->matches($message, ['quyen', 'vai tro', 'tai khoan', 'profile', 'privilege', 'role', 'permission', 'account', 'user']));
        if ($privilegedAccount) {
            $privilegeAction = $this->matches($message, [
                'cho', 'cap', 'gan', 'doi', 'thanh', 'them', 'set', 'give', 'grant', 'make', 'change', 'assign',
            ]);

            return ['resource' => 'account_role', 'operation' => $privilegeAction ? 'mutate' : $operation];
        }
        if ($this->matches($message, [
            'nguoi khac', 'khach khac', 'tai khoan khac', 'don cua khach', 'don cua ban',
            'ban minh', 'ban toi', 'ban cua toi', 'vo toi', 'chong toi', 'dong nghiep',
            'another user', 'another customer', 'other user', 'other customer', 'someone else',
            'my friend', 'friend s', 'my wife', 'my husband', 'my colleague', 'customer s',
        ]) || preg_match('/\bcustomer\b.*\b(?:order|orders)\b/', $message) === 1
            || preg_match('/\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b/', $message) === 1
            || preg_match('/\b(?:cua|of)\s+[a-z0-9._-]+\s+(?:other\s+)?com\b/', $message) === 1) {
            return ['resource' => 'other_user_data', 'operation' => $operation === 'unknown' ? 'read' : $operation];
        }
        if ($mutation?->operation === 'mutate') {
            return [
                'resource' => $mutation->mutationTarget?->value
                    ?? ($mutation->mentionedResources !== [] ? 'order_or_payment' : 'none'),
                'operation' => 'mutate',
            ];
        }
        if ($this->matches($message, [
            'don', 'don hang', 'order', 'trang thai don', 'payment status', 'thanh toan', 'payment', 'paid',
            'tra tien', 'da giao', 'delivered',
        ])) {
            return ['resource' => 'order_or_payment', 'operation' => $operation];
        }

        return ['resource' => 'none', 'operation' => $operation];
    }

    /** @param array<int, string> $phrases */
    private function matches(string $message, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (preg_match('/(?:^|\s)'.preg_quote($phrase, '/').'(?=$|\s)/', $message) === 1) {
                return true;
            }
        }

        return false;
    }
}
