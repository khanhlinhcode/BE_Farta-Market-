<?php

namespace App\Services\Chat;

use Anthropic\Client;
use Anthropic\Messages\TextBlock;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ChatProvider
{
    /** @return array{structured_output: bool, tools: bool, streaming: bool} */
    public function capabilities(): array
    {
        return [
            'structured_output' => $this->supportsStructuredOutput(),
            'tools' => $this->supportsTools(),
            'streaming' => $this->supportsStreaming(),
        ];
    }

    public function supportsStructuredOutput(): bool
    {
        return in_array($this->driver(), ['groq', 'ollama'], true);
    }

    public function supportsTools(): bool
    {
        // Provider tool loops are deliberately not enabled in the database-only baseline.
        return false;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }

    /** @param array<int, array{role: string, content: string}> $messages @param array<string, mixed> $schema */
    public function structured(array $messages, string $systemPrompt, array $schema, string $schemaName = 'catalog_recommendation'): string
    {
        return match ($this->driver()) {
            'ollama' => $this->ollama($messages, $systemPrompt, $schema),
            'anthropic' => $this->anthropic($messages, $systemPrompt),
            'groq' => $this->groq($messages, $systemPrompt, $schema, $schemaName),
            default => throw new RuntimeException('AI_MODEL_UNAVAILABLE:Invalid driver.'),
        };
    }

    public function ensureAvailable(): void
    {
        match ($this->driver()) {
            'ollama' => $this->ensureOllama(),
            'anthropic' => $this->ensureAnthropic(),
            'groq' => $this->ensureGroq(),
            default => throw new RuntimeException('AI_MODEL_UNAVAILABLE:Invalid driver.'),
        };
    }

    private function ollama(array $messages, string $systemPrompt, array $schema): string
    {
        $this->ensureOllama();
        $messages = array_merge([['role' => 'system', 'content' => $systemPrompt]], $messages);
        $messages[array_key_last($messages)]['content'] .= "\n/no_think";
        $response = Http::acceptJson()->connectTimeout(3)->timeout($this->timeout())
            ->post($this->baseUrl().'/api/chat', [
                'model' => config('services.ai_chat.model'),
                'stream' => false,
                'think' => false,
                'keep_alive' => config('services.ai_chat.keep_alive', '30m'),
                'format' => $schema,
                'messages' => $messages,
                'options' => ['temperature' => 0.1, 'num_predict' => 80],
            ])->throw();

        return trim((string) $response->json('message.content'));
    }

    private function anthropic(array $messages, string $systemPrompt): string
    {
        $key = config('services.ai_chat.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('AI_MODEL_UNAVAILABLE:Missing Anthropic key.');
        }
        $client = app(Client::class, [
            'apiKey' => $key,
            'authToken' => '',
            'baseUrl' => $this->baseUrl(),
            'requestOptions' => [
                'timeout' => (float) $this->timeout(),
                'maxRetries' => 0,
                'transporter' => new \GuzzleHttp\Client(['timeout' => $this->timeout(), 'connect_timeout' => 3]),
            ],
        ]);
        $response = $client->messages->create(
            maxTokens: 100,
            messages: $messages,
            model: (string) config('services.ai_chat.model'),
            system: $systemPrompt,
        );

        return collect($response->content)->filter(fn ($block) => $block instanceof TextBlock)
            ->map(fn ($block) => $block->text)->join("\n");
    }

    private function groq(array $messages, string $systemPrompt, array $schema, string $schemaName): string
    {
        $key = config('services.ai_chat.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('AI_MODEL_UNAVAILABLE:Missing Groq key.');
        }
        $response = Http::acceptJson()->withToken($key)->connectTimeout(3)->timeout($this->timeout())
            ->post($this->baseUrl().'/chat/completions', [
                'model' => config('services.ai_chat.model'),
                'messages' => array_merge([['role' => 'system', 'content' => $systemPrompt]], $messages),
                'temperature' => 0.1,
                'max_completion_tokens' => 512,
                'reasoning_effort' => 'low',
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => preg_replace('/[^a-z0-9_]/', '_', strtolower($schemaName)) ?: 'structured_response',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ])->throw();

        return trim((string) $response->json('choices.0.message.content'));
    }

    private function ensureOllama(): void
    {
        $models = Http::acceptJson()->connectTimeout(2)->timeout(3)
            ->get($this->baseUrl().'/api/tags')->throw()->json('models', []);
        if (! collect($models)->pluck('name')->contains(config('services.ai_chat.model'))) {
            throw new RuntimeException('AI_MODEL_UNAVAILABLE:Missing Ollama model.');
        }
    }

    private function ensureAnthropic(): void
    {
        $key = config('services.ai_chat.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('AI_MODEL_UNAVAILABLE:Missing Anthropic key.');
        }
        Http::acceptJson()->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->connectTimeout(2)->timeout(3)
            ->get($this->baseUrl().'/v1/models/'.urlencode(config('services.ai_chat.model')))->throw();
    }

    private function ensureGroq(): void
    {
        $key = config('services.ai_chat.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('AI_MODEL_UNAVAILABLE:Missing Groq key.');
        }
        $models = Http::acceptJson()->withToken($key)->connectTimeout(2)->timeout(3)
            ->get($this->baseUrl().'/models')->throw()->json('data', []);
        if (! collect($models)->pluck('id')->contains(config('services.ai_chat.model'))) {
            throw new RuntimeException('AI_MODEL_UNAVAILABLE:Missing Groq model.');
        }
    }

    private function driver(): string
    {
        return (string) config('services.ai_chat.driver');
    }

    private function timeout(): int
    {
        return min(20, max(1, (int) config('services.ai_chat.timeout', 20)));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.ai_chat.base_url'), '/');
    }
}
