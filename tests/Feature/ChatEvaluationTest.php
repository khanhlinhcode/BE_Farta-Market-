<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\Chat\ChatIntentRouter;
use App\Services\Chat\ChatProductTool;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('measures the local router and database retrieval baseline', function () {
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
    $productTool = app(ChatProductTool::class);
    $routerStarted = microtime(true);
    $correct = 0;
    foreach ($fixture['intent'] as $case) {
        $correct += $router->route($case['query'])['intent']->value === $case['expected'] ? 1 : 0;
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

    $metrics = [
        'fixture_version' => 2,
        'intent_cases' => count($fixture['intent']),
        'intent_accuracy' => round($correct / count($fixture['intent']), 4),
        'retrieval_cases' => count($fixture['retrieval']),
        'hit_rate_at_5' => round(array_sum($hits) / count($hits), 4),
        'mrr_at_5' => round(array_sum($reciprocalRanks) / count($reciprocalRanks), 4),
        'ndcg_at_5' => round(array_sum($discountedGains) / count($discountedGains), 4),
        'router_total_ms' => round($routerMs, 2),
        'retrieval_total_ms' => round($retrievalMs, 2),
    ];

    fwrite(STDOUT, "\nCHAT_EVALUATION ".json_encode($metrics, JSON_UNESCAPED_SLASHES)."\n");

    expect(count($fixture['intent']) + count($fixture['retrieval']))->toBeGreaterThanOrEqual(50)
        ->and($metrics['intent_accuracy'])->toBeGreaterThanOrEqual(0.95)
        ->and($metrics['hit_rate_at_5'])->toBeGreaterThanOrEqual(0.9)
        ->and($metrics['mrr_at_5'])->toBeGreaterThanOrEqual(0.8)
        ->and($metrics['ndcg_at_5'])->toBeGreaterThanOrEqual(0.7);
});
