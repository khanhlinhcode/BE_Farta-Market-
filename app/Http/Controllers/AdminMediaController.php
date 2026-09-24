<?php

namespace App\Http\Controllers;

use App\Services\AdminMediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMediaController extends Controller
{
    public function __construct(private readonly AdminMediaLibraryService $media) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['product', 'category', 'banner'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 24);
        $media = $this->media->paginate($filters['q'] ?? null, $filters['kind'] ?? null, $page, $perPage);

        return response()->json([
            'data' => $media->items(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'total' => $media->total(),
            ],
        ]);
    }
}
