<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\Chat\ChatIntentRouter;
use App\Services\Chat\ChatProductTool;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('measures the local router and database retrieval baseline', function () {
    config()->set('services.ai_chat.semantic_router_enabled', false);
    $category = Category::create(['name' => 'Bữa sáng']);
    $definitions = [
        ['slug' => 'tra-nhe', 'name' => 'Trà Nhẹ', 'summary' => 'Đồ uống thanh nhẹ cho buổi sáng; light tea morning drink'],
        ['slug' => 'banh-yen-mach', 'name' => 'Bánh Yến Mạch', 'summary' => 'Món ăn nhẹ ít ngọt phù hợp mang đi làm; oat snack for work'],
        ['slug' => 'nuoc-cam', 'name' => 'Nước Cam', 'summary' => 'Nước trái cây giàu vitamin vị cam; orange juice vitamin fruit'],
        ['slug' => 'ca-phe-den', 'name' => 'Cà Phê Đen', 'summary' => 'Cà phê đậm dùng vào buổi sáng; strong black coffee morning'],
        ['slug' => 'banh-chocolate', 'name' => 'Bánh Chocolate', 'summary' => 'Bánh ngọt vị chocolate; chocolate sweet cake'],
    ];
    foreach ($definitions as $index => $definition) {
        Product::create([
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'img' => '/images/'.$definition['slug'].'.png',
            'price' => 30000 + ($index * 10000),
            'inventory' => 10,
            'is_active' => true,
            'description' => $definition['summary'],
            'sort_description' => $definition['summary'],
            'facebook' => '',
            'twitter' => '',
            'instagram' => '',
            'linkedin' => '',
            'category_id' => $category->id,
        ]);
    }

    $fixture = require base_path('tests/Fixtures/chat_evaluation.php');
    $router = app(ChatIntentRouter::class);
    $intentCases = [...$fixture['intent'], ...$fixture['intent_v2']];
    $productTool = app(ChatProductTool::class);
    $routerStarted = microtime(true);
    $correct = 0;
    $baselineCorrect = 0;
    $expandedCorrect = 0;
    $routerLatencies = [];
    $intentFailures = [];
    foreach ($intentCases as $index => $case) {
        $caseStartedAt = microtime(true);
        $predicted = $router->route($case['query'])['intent']->value;
        $matches = $predicted === $case['expected'];
        if (! $matches) {
            $intentFailures[] = ['query' => $case['query'], 'expected' => $case['expected'], 'predicted' => $predicted];
        }
        $routerLatencies[] = (microtime(true) - $caseStartedAt) * 1000;
        $correct += $matches ? 1 : 0;
        if ($index < count($fixture['intent'])) {
            $baselineCorrect += $matches ? 1 : 0;
        } else {
            $expandedCorrect += $matches ? 1 : 0;
        }
    }
    $routerMs = (microtime(true) - $routerStarted) * 1000;

    $reciprocalRanks = [];
    $hits = [];
    $discountedGains = [];
    $retrievalStarted = microtime(true);
    foreach ($fixture['retrieval'] as $case) {
        $slugs = $productTool->search(['query' => $case['query'], 'limit' => 5])->pluck('slug')->all();
        $rank = null;
        foreach ($slugs as $index => $slug) {
            if (in_array($slug, $case['relevant'], true)) {
                $rank = $index + 1;
                break;
            }
        }
        $reciprocalRanks[] = $rank ? 1 / $rank : 0;
        $hits[] = $rank ? 1 : 0;
        $discountedGains[] = $rank ? 1 / log($rank + 1, 2) : 0;
    }
    $retrievalMs = (microtime(true) - $retrievalStarted) * 1000;

    sort($routerLatencies);
    $percentile = function (array $values, float $percentile): float {
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return round($values[max(0, min($index, count($values) - 1))], 3);
    };
    $metrics = [
        'fixture_version' => 5,
        'intent_cases' => count($intentCases),
        'intent_accuracy' => round($correct / count($intentCases), 4),
        'baseline_intent_accuracy' => round($baselineCorrect / count($fixture['intent']), 4),
        'expanded_intent_accuracy' => round($expandedCorrect / count($fixture['intent_v2']), 4),
        'retrieval_cases' => count($fixture['retrieval']),
        'hit_rate_at_5' => round(array_sum($hits) / count($hits), 4),
        'mrr_at_5' => round(array_sum($reciprocalRanks) / count($reciprocalRanks), 4),
        'ndcg_at_5' => round(array_sum($discountedGains) / count($discountedGains), 4),
        'router_total_ms' => round($routerMs, 2),
        'router_p50_ms' => $percentile($routerLatencies, 50),
        'router_p95_ms' => $percentile($routerLatencies, 95),
        'router_p99_ms' => $percentile($routerLatencies, 99),
        'retrieval_total_ms' => round($retrievalMs, 2),
        'intent_failures' => $intentFailures,
    ];

    fwrite(STDOUT, "\nCHAT_EVALUATION ".json_encode($metrics, JSON_UNESCAPED_SLASHES)."\n");

    expect(count($intentCases) + count($fixture['retrieval']))->toBeGreaterThanOrEqual(150)
        ->and($metrics['intent_accuracy'])->toBeGreaterThanOrEqual(0.95)
        // This older fixture still labels one clear out-of-scope request as
        // clarification. The current contract correctly returns unsupported;
        // retain and print the disagreement instead of changing its gold row.
        ->and($metrics['baseline_intent_accuracy'])->toBeGreaterThanOrEqual(0.95)
        ->and($metrics['expanded_intent_accuracy'])->toBeGreaterThanOrEqual(0.95)
        ->and($metrics['hit_rate_at_5'])->toBeGreaterThanOrEqual(0.9)
        ->and($metrics['mrr_at_5'])->toBeGreaterThanOrEqual(0.8)
        ->and($metrics['ndcg_at_5'])->toBeGreaterThanOrEqual(0.7);
});
