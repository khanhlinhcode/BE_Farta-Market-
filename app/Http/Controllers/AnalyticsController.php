<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsPageView;
use App\Models\Order;
use App\Services\AnalyticsSessionService;
use App\Support\AnalyticsIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsSessionService $analyticsSessions) {}

    public function issueSession(Request $request)
    {
        if (! $this->hasAllowedOrigin($request) || ! $request->hasSession()) {
            return response()->json(['message' => 'Analytics origin is not allowed.'], 403);
        }

        $binding = (string) $request->session()->token();

        return response()->json($this->analyticsSessions->issue($binding), 201);
    }

    public function store(Request $request)
    {
        if (! $this->hasAllowedOrigin($request) || ! $request->hasSession()) {
            return response()->json(['message' => 'Analytics origin is not allowed.'], 403);
        }

        $data = $request->validate([
            'path' => ['required', 'string', 'max:255', 'regex:/^\/(?!\/)[^?#]*$/'],
            'referrer' => ['nullable', 'url', 'max:1000'],
        ]);

        if (! $this->isAllowedPath($data['path'])) {
            return response()->json(['message' => 'Analytics path is not allowed.'], 422);
        }

        $binding = (string) $request->session()->token();
        $analyticsSession = $binding !== '' ? $this->analyticsSessions->verify(
            $request->header('X-Analytics-Token'),
            $binding
        ) : null;
        if (! $analyticsSession) {
            return response()->json(['message' => 'Analytics token is invalid or expired.'], 401);
        }

        $agent = (string) $request->userAgent();
        if ($this->isBot($agent)) {
            return response()->noContent();
        }

        $sessionHash = AnalyticsIdentifier::hash($analyticsSession['session_id']);
        $dedupeKey = 'analytics:page-view:'.hash('sha256', $sessionHash.'|'.$data['path']);
        if (! Cache::add($dedupeKey, true, now()->addSeconds(5))) {
            return response()->noContent();
        }

        AnalyticsPageView::create([
            'visitor_hash' => AnalyticsIdentifier::hash($analyticsSession['visitor_id']),
            'session_hash' => $sessionHash,
            'user_id' => $request->user('sanctum')?->id,
            'path' => $data['path'],
            'referrer_host' => isset($data['referrer']) ? parse_url($data['referrer'], PHP_URL_HOST) : null,
            'device_type' => $this->device($agent),
            'browser' => $this->browser($agent),
            'os' => $this->os($agent),
            'occurred_at' => now(),
        ]);

        return response()->noContent();
    }

    public function overview(Request $request)
    {
        $data = $request->validate(['range' => ['nullable', Rule::in(['7d', '30d', '90d'])]]);
        $range = $data['range'] ?? '30d';
        $days = (int) $range;
        $from = now()->startOfDay()->subDays($days - 1);
        $views = AnalyticsPageView::query()->where('occurred_at', '>=', $from);
        $pageViews = (clone $views)->count();
        $sessions = (clone $views)->distinct()->count('session_hash');
        $visitors = (clone $views)->distinct()->count('visitor_hash');
        $trackedOrders = Order::query()
            ->whereNotNull('analytics_session_hash')
            ->where('created_at', '>=', $from)
            ->whereIn('analytics_session_hash', AnalyticsPageView::query()
                ->select('session_hash')
                ->where('occurred_at', '>=', $from))
            ->distinct()->count('analytics_session_hash');

        return response()->json([
            'range' => $range,
            'totals' => [
                'page_views' => $pageViews,
                'sessions' => $sessions,
                'visitors' => $visitors,
                'tracked_orders' => $trackedOrders,
                'conversion_rate' => $sessions > 0 ? round(($trackedOrders / $sessions) * 100, 2) : 0,
            ],
            'by_day' => $this->byDay($from),
            'devices' => $this->breakdown('device_type', $from),
            'browsers' => $this->breakdown('browser', $from),
            'operating_systems' => $this->breakdown('os', $from),
            'top_pages' => $this->breakdown('path', $from, 10),
            'referrers' => $this->breakdown('referrer_host', $from, 10, true),
        ]);
    }

    private function byDay(Carbon $from): array
    {
        return AnalyticsPageView::query()
            ->where('occurred_at', '>=', $from)
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as page_views, COUNT(DISTINCT session_hash) as sessions, COUNT(DISTINCT visitor_hash) as visitors')
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'page_views' => (int) $item->page_views,
                'sessions' => (int) $item->sessions,
                'visitors' => (int) $item->visitors,
            ])->all();
    }

    private function breakdown(string $column, Carbon $from, int $limit = 8, bool $withoutNull = false): array
    {
        return AnalyticsPageView::query()
            ->where('occurred_at', '>=', $from)
            ->when($withoutNull, fn ($query) => $query->whereNotNull($column)->where($column, '!=', ''))
            ->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => ['label' => $item->{$column} ?: 'Direct', 'total' => (int) $item->total])
            ->all();
    }

    private function isBot(string $agent): bool
    {
        return $agent === '' || preg_match('/bot|crawler|spider|slurp|headless/i', $agent) === 1;
    }

    private function hasAllowedOrigin(Request $request): bool
    {
        $origin = rtrim((string) $request->headers->get('Origin'), '/');
        $allowed = array_map(
            fn (string $value) => rtrim($value, '/'),
            (array) config('services.analytics.allowed_origins', [])
        );

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            return true;
        }

        $referer = (string) $request->headers->get('Referer');
        $refererOrigin = $referer === '' ? '' : parse_url($referer, PHP_URL_SCHEME).'://'.parse_url($referer, PHP_URL_HOST);
        $port = $referer === '' ? null : parse_url($referer, PHP_URL_PORT);
        if ($port) {
            $refererOrigin .= ':'.$port;
        }

        return $refererOrigin !== '' && in_array($refererOrigin, $allowed, true);
    }

    private function isAllowedPath(string $path): bool
    {
        $exact = [
            '/', '/thongtincanhan', '/san-pham', '/gio-hang', '/thanh-toan',
            '/dat-hang-thanh-cong', '/don-hang-cua-toi', '/yeu-thich', '/dang-nhap',
            '/verify-email',
        ];

        return in_array($path, $exact, true)
            || preg_match('#^/san-pham/chi-tiet/[1-9][0-9]*$#', $path) === 1;
    }

    private function device(string $agent): string
    {
        if (preg_match('/ipad|tablet/i', $agent)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|android/i', $agent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function browser(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Other',
        };
    }

    private function os(string $agent): string
    {
        return match (true) {
            preg_match('/iPhone|iPad|iPod/', $agent) === 1 => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Other',
        };
    }
}
