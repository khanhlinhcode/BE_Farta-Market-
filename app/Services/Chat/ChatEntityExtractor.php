<?php

namespace App\Services\Chat;

use Illuminate\Support\Str;

final class ChatEntityExtractor
{
    /**
     * Extract a bounded entity frame. Values remain untrusted mentions until a
     * handler resolves them against the authoritative database.
     *
     * @return array<string, mixed>
     */
    public function extract(string $raw): array
    {
        $message = $this->normalize($raw);
        $quantity = $this->quantity($raw, $this->productPhrases());
        $product = $this->productMention($message);
        $order = $this->orderReference($message, $raw);

        return [
            'product_raw_mention' => $product,
            // Compatibility key used by existing handlers. This is a mention,
            // not a canonical database product.
            'product_name' => $product,
            'canonical_product' => null,
            'quantity' => $quantity['status'] === 'valid' ? (int) $quantity['value'] : 0,
            'quantity_status' => $quantity['status'],
            'unit' => $this->unit($message),
            'order_reference' => $order,
            'order_id' => $order,
            'ordinal_reference' => $this->ordinalReference($message),
            'context_reference' => $this->contextReference($message),
            'account_target' => $this->accountTarget($message, $raw),
            'requested_mutation_value' => $this->mutationValue($message),
        ];
    }

    /**
     * Extract cart entities without treating the extracted values as authority.
     * Product availability and quantity are revalidated by the cart handler.
     *
     * @return array{product_name: string, quantity: int}
     */
    public function cart(string $raw): array
    {
        $message = $this->normalize($raw);
        $quantity = $this->quantity($raw);
        $product = (string) preg_replace('/^(?:would|could|can)\s+(?:you\s+)?/', '', $message);

        $product = preg_replace('/^(?:toi|minh|tui|em|i|we)\s+(?:can|muon|need|want)\s+/', '', $product, 1);
        $product = preg_replace('/^(?:toi|minh|tui|em|i|we)\s+(?:them|mua|dat|lay|bo|dua|add|put|buy|order|place|purchase|prepare)\s+/', '', (string) $product, 1);
        $product = preg_replace(
            '/^(?:cho|dua)\s+(?:toi|minh|tui|em|i|we)\s+(?:them|mua|dat|lay|bo|add|put|buy|order|place|purchase|prepare)\s+/',
            '',
            (string) $product,
            1,
        );
        // Commands are grammar at the beginning of a request.  Searching for
        // an action word anywhere corrupts legitimate product names such as
        // "Nước Dừa" ("đưa" and "dừa" normalize similarly), so consume only
        // a bounded command prefix.
        $product = preg_replace(
            '/^(?:(?:if\s+(?:i|we)\s+)|(?:(?:nho|xin|vui long|co the|please|could you|can you|would you)\s+))?(?:shop\s+)?(?:them|mua|dat|lay|cho|bo|dua|de|muon|add|put|buy|order|place|purchase|prepare|want|need)\b\s*/',
            '',
            $product,
            1
        );

        // A desire verb may precede the actual purchase verb ("muốn mua").
        $product = preg_replace('/^(?:them|mua|dat|lay|bo|dua|de|add|put|buy|order|place|purchase|prepare)\s+/', '', (string) $product, 1);

        // Imperative Vietnamese commonly leaves its recipient immediately
        // after the verb ("cho em 2 ...").  It is not part of a product name.
        $product = preg_replace('/^(?:toi|minh|tui|em|i|we)\s+/', '', (string) $product, 1);

        $product = preg_replace(
            '/^(?:(?:cho|giup|gium|dum|ho)\s+(?:toi|minh|tui|em)|giup|gium|dum|ho|shop)\s+/',
            '',
            (string) $product
        );
        $product = preg_replace('/^(?:me\s+|i\s+(?:need|want)\s+|we\s+(?:need|want)\s+)/', '', (string) $product);
        $product = preg_replace(
            '/^(?:(?:vao|vo|do|to|into|in)\s+)?(?:(?:my|cua toi|cua minh)\s+)?(?:gio(?: hang)?|cart|basket)\s+/',
            '',
            (string) $product
        );
        $product = $this->removeFirstQuantity((string) $product);
        $product = Str::of((string) $product)->squish()->toString();
        // "cải" normalizes to "cai", so a bare "cai" cannot be removed
        // as a unit without corrupting canonical product names such as Cải Thìa.
        $product = preg_replace('/^(?:(?:packs?|bottles?|units?|pieces?)\s+of\s+|phan|qua|hop|hu|chai|goi|tui|kg|cu|mon|san pham|items?|units?)\s*/', '', (string) $product);
        $product = $this->trimActionTail((string) $product);
        if (preg_match('/^(?:of\s+)?(?:it|this|that|those|these|the one|them)|^(?:cai|mon|san pham)\s+(?:nay|do|ay)$/', (string) $product) === 1) {
            $product = '';
        }

        return [
            'product_name' => Str::of((string) $product)->squish()->toString(),
            'quantity' => $quantity['status'] === 'valid' ? (int) $quantity['value'] : 0,
        ];
    }

    /** @return array{status: 'valid'|'invalid'|'ambiguous'|'missing', value: ?int} */
    public function quantity(string $raw, array $productAliases = []): array
    {
        $text = strtolower(Str::ascii(str_replace(['−', '–', '—'], '-', $raw)));
        // Vietnamese question-ending "không?" is not a numeric zero.
        $text = preg_replace('/\b(?:duoc )?khong[?.!\s]*$/', '', $text);
        // In questions, "không" is negation rather than the quantity zero.
        // Numeric 0 and English "zero" remain invalid quantities.
        $text = preg_replace('/\bkhong\b/', ' ', (string) $text);
        // "bây giờ" is a time expression, not a request for seven items.
        $text = preg_replace('/\bbay gio\b/', ' ', (string) $text);
        $text = preg_replace('/\bgio sau\b/', ' ', (string) $text);
        // "sau" commonly means "after"; only a standalone number word is a
        // quantity. These bounded contexts avoid double-counting shipping
        // calculations such as "3 items after shipping".
        $text = preg_replace('/\bsau\s+(?:phi|khi|do|nay|day|giao|shipping)\b/', ' ', (string) $text);
        // "theo thứ tự" describes an ordering guide, not a quantity of four.
        $text = preg_replace('/\bthu tu\b/', ' ', (string) $text);
        // Order identifiers are entity references, never cart quantities.
        $text = preg_replace('/\b(?:don(?: hang)?|order|purchase)\s*(?:(?:number|so|ma|code)\s+|#)?\d{1,18}\b/', ' ', (string) $text);
        $text = preg_replace('/\b(?:ma|so|code|number)\s+(?:don(?: hang)?|order)\s*\d{1,18}\b/', ' ', (string) $text);
        $text = preg_replace('/(?:^|\s)#\d{1,18}\b/', ' ', (string) $text);
        $text = preg_replace('/\b(?:kiem tra|tra cuu|check|look up)\s+\d{4,18}\b/', ' ', (string) $text);
        $text = preg_replace('/\b(?:payment|thanh toan|tra tien)\b[^0-9]{0,40}\d{1,18}\b/', ' ', (string) $text);
        $text = preg_replace('/\b(?:cai|mon|san pham)?\s*(?:thu nhat|thu hai|thu ba|dau tien|mon dau|cai dau|cuoi cung|mon cuoi|cai cuoi)\b/', ' ', (string) $text);
        $text = preg_replace('/\b(?:the\s+)?(?:first|second|third|last|final)(?:\s+(?:one|item|product))?\b/', ' ', (string) $text);
        foreach ($productAliases as $alias) {
            $text = preg_replace('/\b'.preg_quote($this->normalize((string) $alias), '/').'\b/', ' ', (string) $text);
        }
        if (preg_match('/\d\p{L}|\p{L}\d/u', (string) $text)
            || preg_match('/\b(am|minus|negative)\b/', $this->normalize((string) $text))) {
            return ['status' => 'invalid', 'value' => null];
        }

        preg_match_all('/[+\-−]?\s*\d+(?:\s*[.,]\s*\d+)*/u', (string) $text, $digits);
        $quantities = [];
        foreach ($digits[0] as $token) {
            $token = trim($token);
            if (! preg_match('/^\d+$/', $token) || strlen($token) > 3 || (int) $token < 1 || (int) $token > 100) {
                return ['status' => 'invalid', 'value' => null];
            }
            $quantities[] = (int) $token;
        }

        $words = $this->numberWords();
        $numberPhrases = array_keys($words);
        usort($numberPhrases, fn (string $left, string $right) => strlen($right) <=> strlen($left));
        $pattern = '(?:'.implode('|', array_map(fn ($word) => preg_quote($word, '/'), $numberPhrases)).')';
        preg_match_all('/\b'.$pattern.'\b/', $this->normalize((string) $text), $groups);
        foreach ($groups[0] as $group) {
            if (! isset($words[$group]) || $words[$group] < 1) {
                return ['status' => 'invalid', 'value' => null];
            }
            $quantities[] = $words[$group];
        }

        return [
            'status' => count($quantities) === 1 ? 'valid' : (count($quantities) > 1 ? 'ambiguous' : 'missing'),
            'value' => count($quantities) === 1 ? $quantities[0] : null,
        ];
    }

    public function quantityOnly(string $message): bool
    {
        $words = implode('|', array_map(fn ($word) => preg_quote($word, '/'), array_keys($this->numberWords())));

        return preg_match('/^(?:\d+|'.$words.')(?: (?:qua|cai|hop|kg|san pham|items?|units?))?(?: nhe|please)?$/', $message) === 1;
    }

    public function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }

    /** @return array<int, string> */
    public function productPhrases(): array
    {
        return [
            'rau cu tuoi', 'combo rau cu', 'vegetable combo', 'fresh vegetables',
            'thit bo nat', 'thit bo nac', 'lean beef',
            'sua hop', 'boxed milks', 'boxed milk',
            'cam tuoi', 'fresh oranges', 'fresh orange',
            'tao uc', 'australian apples', 'australian apple',
            'nho tim', 'purple grapes', 'purple grape',
            'dua hau', 'watermelon',
            'xoai keo', 'mango',
            'hamburger', 'burgers', 'burger',
            'tra gung', 'ginger tea',
            'chuoi', 'bananas', 'banana',
            'oi', 'guavas', 'guava',
        ];
    }

    /** @return array<int, string> canonical lexical concepts mentioned */
    public function productMentions(string $raw): array
    {
        $message = $this->normalize($raw);
        $groups = $this->productAliasGroups();

        return collect($groups)->filter(function (array $aliases) use ($message): bool {
            return collect($aliases)->contains(
                fn (string $alias): bool => preg_match('/(?:^|\s)'.preg_quote($alias, '/').'(?=$|\s)/', $message) === 1
            );
        })->keys()->values()->all();
    }

    /**
     * Return normalized canonical product-name candidates in mention order.
     * These remain candidates until matched against active database products.
     *
     * @return array<int, string>
     */
    public function canonicalProductMentions(string $raw): array
    {
        $message = $this->normalize($raw);
        $matches = [];
        foreach ($this->productAliasGroups() as $canonical => $aliases) {
            $positions = [];
            foreach ($aliases as $alias) {
                if (preg_match('/(?:^|\s)('.preg_quote($alias, '/').')(?=$|\s)/', $message, $found, PREG_OFFSET_CAPTURE) === 1) {
                    $positions[] = (int) $found[1][1];
                }
            }
            if ($positions !== []) {
                $matches[] = ['canonical' => $canonical, 'position' => min($positions)];
            }
        }
        usort($matches, fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return array_values(array_map(fn (array $match): string => $match['canonical'], $matches));
    }

    /** @return array<string, array<int, string>> */
    private function productAliasGroups(): array
    {
        return [
            'rau cu tuoi' => ['rau cu tuoi', 'rau cu', 'combo rau cu', 'vegetable combo', 'fresh vegetables'],
            'thit bo nat' => ['thit bo nat', 'thit bo nac', 'lean beef', 'beef'],
            'sua hop' => ['sua hop', 'hop sua', 'boxed milks', 'boxed milk', 'milk'],
            'cam tuoi' => ['cam tuoi', 'cam', 'fresh oranges', 'fresh orange', 'oranges', 'orange'],
            'tao uc' => ['tao uc', 'tao', 'australian apples', 'australian apple'],
            'nho tim' => ['nho tim', 'nho', 'purple grapes', 'purple grape'],
            'dua hau' => ['dua hau', 'watermelon'],
            'xoai keo' => ['xoai keo', 'xoai', 'mango'],
            'hamburger' => ['hamburger', 'burgers', 'burger'],
            'tra gung' => ['tra gung', 'ginger tea'],
            'chuoi' => ['chuoi', 'bananas', 'banana'],
            'oi' => ['oi', 'guavas', 'guava'],
        ];
    }

    private function productMention(string $message): string
    {
        $phrases = $this->productPhrases();
        usort($phrases, fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($phrases as $phrase) {
            if (preg_match('/(?:^|\s)('.preg_quote($phrase, '/').')(?=$|\s)/', $message, $match) === 1) {
                return $match[1];
            }
        }

        $patterns = [
            '/\b(?:find|search(?: for)?|show me|do you (?:sell|carry)|quote(?: the price of)?|price of)\s+(.+?)(?:\s+(?:please|for|under|below|in stock)|[?.!]|$)/',
            '/\b(?:tim|kiem|hien thi|bao gia)\s+(?:giup\s+)?(?:minh\s+|toi\s+)?(.+?)(?:\s+(?:nhe|nha|giup|minh|duoi|trong danh muc)|$)/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $match) === 1) {
                return trim($match[1]);
            }
        }

        return '';
    }

    private function orderReference(string $message, string $raw): string
    {
        if (preg_match('/\b(?:don(?: hang)?|order|purchase)\s*(?:(?:number|so|ma|code)\s+|#)?(\d{1,18})\b/', $message, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/\b(?:don(?: hang)?|order|purchase)\b/', $message) === 1
            && preg_match('/\b(?:ma|code)\s+(\d{1,18})\b/', $message, $match) === 1) {
            return $match[1];
        }
        // Reversed Vietnamese syntax: "mã đơn 12345", "số đơn 12345"
        if (preg_match('/\b(?:ma|so|code|number)\s+(?:don(?: hang)?|order)\s*(\d{1,18})\b/', $message, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/\b(?:payment(?: status)?|thanh toan|tra tien)\b[^0-9]{0,40}(\d{1,18})\b/', $message, $match) === 1) {
            return $match[1];
        }
        // Keep the raw input here because normalize() intentionally removes '#'.
        if (preg_match('/(?:^|\s)#(\d{1,18})\b/', $raw, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/\b(?:kiem tra|tra cuu|check|look up)\s+(\d{4,18})\b/', $message, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    private function unit(string $message): ?string
    {
        $number = '(?:\d{1,3}|mot|hai|ba|bon|nam|sau|bay|tam|chin|one|two|three|four|five|six|seven|eight|nine)';
        $unitWord = '(?:hop|phan|qua|kg|chai|goi|lon|tui|bich|thung|lo|units?|items?|pieces?|packs?|bottles?|cans?|bags?|cases?)';
        // Match "3 hộp" (quantity before unit) and "hộp 3" (unit before quantity, common in Vietnamese)
        if (preg_match('/\b'.$number.'\s+('.$unitWord.')\b/', $message, $match) === 1
            || preg_match('/\b('.$unitWord.')\s+'.$number.'\b/', $message, $match) === 1
            || preg_match('/\b(?:theo|per)\s+('.$unitWord.')\b/', $message, $match) === 1) {
            return $this->normalizeUnit($match[1]);
        }

        return null;
    }

    private function normalizeUnit(string $raw): string
    {
        return match ($raw) {
            'units', 'unit', 'items', 'item', 'pieces', 'piece' => 'unit',
            'packs', 'pack' => 'pack',
            'bottles', 'bottle', 'chai' => 'bottle',
            'cans', 'can', 'lon' => 'can',
            'bags', 'bag', 'tui', 'bich', 'goi' => 'bag',
            'cases', 'case', 'thung' => 'case',
            'lo' => 'lo',
            default => $raw,
        };
    }

    private function ordinalReference(string $message): ?int
    {
        return match (true) {
            preg_match('/\b(?:thu nhat|dau tien|cai dau|mon dau|first(?: one| item| product)?|mon first)\b/', $message) === 1 => 1,
            preg_match('/\b(?:thu hai|cai thu hai|mon thu hai|second(?: one| item| product)?)\b/', $message) === 1 => 2,
            preg_match('/\b(?:thu ba|cai thu ba|mon thu ba|third(?: one| item| product)?)\b/', $message) === 1 => 3,
            preg_match('/\b(?:cuoi cung|cai cuoi|mon cuoi|last(?: one| item| product)?)\b/', $message) === 1 => -1,
            preg_match('/\b(?:mon|san pham|mat hang)\s+(?:(?:dung|o vi tri)\s+)?cuoi(?:\s+danh sach)?\b/', $message) === 1 => -1,
            default => null,
        };
    }

    private function contextReference(string $message): ?string
    {
        if (preg_match('/\b(?:cai|mon|san pham|mat hang|don|order)\s+(nay|do|ay|kia|vua xem|vua nhac)\b/', $message, $match) === 1) {
            return $match[0];
        }
        if (preg_match('/\b(?:san pham|mat hang)\s+vua\s+(?:xem|noi|nhac)\b/', $message, $match) === 1) {
            return $match[0];
        }
        if (preg_match('/\b(?:this|that|it|its|those|these)(?:\s+(?:item|product|order|one))?\b/', $message, $match) === 1) {
            return $match[0];
        }

        return null;
    }

    private function accountTarget(string $message, string $raw): ?string
    {
        if (preg_match('/\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b/i', $raw, $match) === 1) {
            return strtolower($match[0]);
        }
        $targets = [
            'nguoi khac', 'khach khac', 'tai khoan khac', 'ban minh', 'ban toi', 'ban cua toi',
            'tai khoan toi', 'tai khoan cua toi', 'tai khoan minh', 'tai khoan cua minh',
            'vo toi', 'chong toi', 'dong nghiep', 'another user', 'another customer', 'other user',
            'other customer', 'another account', 'other account', 'someone else', 'my friend', 'my wife', 'my husband', 'my colleague',
        ];
        foreach ($targets as $target) {
            if (preg_match('/(?:^|\s)'.preg_quote($target, '/').'(?=$|\s)/', $message) === 1) {
                return $target;
            }
        }
        if (preg_match('/\bcho\s+(toi|minh)\s+quyen\b/', $message, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function mutationValue(string $message): int|string|null
    {
        if (preg_match('/\bcho\s+(?:toi|minh)\s+quyen\s+(?:admin|administrator|quan tri vien)\b/', $message) === 1) {
            return 'admin';
        }
        if (preg_match('/\b(?:set|mark|change|update|override|adjust|cancel|grant|danh dau|cap nhat|dieu chinh|tang|nang|doi|chuyen|huy|cho quyen)\b/', $message) !== 1) {
            return null;
        }
        if (preg_match('/\b(?:danh dau|mark)\b.*\b(?:thanh toan|payment|paid)\b/', $message) === 1) {
            return 'paid';
        }
        if (preg_match('/\b(admin|administrator|quan tri vien|paid|delivered|cancelled|canceled|da thanh toan|da tra|da giao|da huy)\b/', $message, $match) === 1) {
            return match ($match[1]) {
                'administrator', 'quan tri vien' => 'admin',
                'da thanh toan', 'da tra' => 'paid',
                'da giao' => 'delivered',
                'huy', 'da huy', 'canceled' => 'cancelled',
                default => $match[1],
            };
        }
        if (preg_match('/\bhuy\b|\bcancel\b/', $message) === 1) {
            return 'cancelled';
        }
        if (preg_match('/(?:\+\s*)?(\d{1,6})\b/', $message, $match) === 1) {
            $value = (int) $match[1];

            return preg_match('/\b(?:tang|increase|add)\b/', $message) === 1 ? '+'.$value : $value;
        }

        return null;
    }

    private function trimActionTail(string $product): string
    {
        $product = preg_replace(
            '/\s+(?:(?:vao|vo|do|cho|in|to|into|sang)\s+)?(?:the\s+)?(?:(?:my|cua toi|cua minh)\s+)?(?:gio(?: hang| mua sam)?|shopping basket|shopping cart|cart|basket)\b.*$/',
            '',
            $product,
        );
        $product = preg_replace(
            '/\s+(?:cho\s+don\s+(?:sap toi|nay|hien tai)|for\s+(?:my\s+)?(?:upcoming|this)\s+(?:order|purchase))\b.*$/',
            '',
            (string) $product,
        );
        $product = preg_replace(
            '/\s+(?:cho|trong)\s+lan\s+(?:mua|dat)(?:\s+(?:cua\s+)?(?:toi|minh|tui|em))?\b.*$/',
            '',
            (string) $product,
        );
        $product = preg_replace(
            '/\s+(?:cho toi|cho minh|cho tui|cho em|giup toi|giup minh|giup tui|giup em|for(?: me| my cart| this customer| customer)?|sang|voi(?: nha)?|nha|nhe(?: shop)?|please|duoc khong|co duoc khong|khong)\s*$/',
            '',
            (string) $product,
        );
        $product = preg_replace(
            '/\s+(?:thi\s+)?(?:tong|don|hoa don|purchase|that purchase|don do).*\b(?:freeship|mien cuoc|mien phi|free delivery|free shipping|qualify)\b.*$/',
            '',
            (string) $product,
        );
        $product = preg_replace(
            '/\s+will\s+(?:courier|shipping|delivery)\b.*$/',
            '',
            (string) $product,
        );

        return (string) preg_replace('/\s+qualify\b.*$/', '', (string) $product);
    }

    /** @return array<string, int> */
    private function numberWords(): array
    {
        $vi = [1 => 'mot', 'hai', 'ba', 'bon', 'nam', 'sau', 'bay', 'tam', 'chin'];
        $en = [1 => 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
        $teens = [10 => 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = [2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $words = ['zero' => 0, 'mot tram' => 100, 'one hundred' => 100, 'a hundred' => 100];
        for ($number = 1; $number < 100; $number++) {
            $ten = intdiv($number, 10);
            $unit = $number % 10;
            $vietnamese = $ten === 0 ? $vi[$unit] : ($ten === 1 ? 'muoi' : $vi[$ten].' muoi');
            if ($ten > 0 && $unit > 0) {
                $vietnamese .= ' '.($unit === 5 ? 'lam' : $vi[$unit]);
            }
            $english = $number < 10 ? $en[$unit] : ($number < 20 ? $teens[$number] : $tens[$ten].($unit > 0 ? ' '.$en[$unit] : ''));
            $words[$vietnamese] = $number;
            $words[$english] = $number;
            $words[str_replace('lam', 'nam', $vietnamese)] = $number;
        }

        return $words;
    }

    private function removeFirstQuantity(string $message): string
    {
        if (preg_match('/\b(?:[1-9]\d?|100)\b/', $message) === 1) {
            return (string) preg_replace('/\b(?:[1-9]\d?|100)\b/', ' ', $message, 1);
        }

        $words = array_keys($this->numberWords());
        usort($words, fn (string $left, string $right) => strlen($right) <=> strlen($left));

        return (string) preg_replace(
            '/\b(?:'.implode('|', array_map(fn ($word) => preg_quote($word, '/'), $words)).')\b/',
            ' ',
            $message,
            1
        );
    }
}
