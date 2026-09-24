<?php

namespace App\Http\Controllers;

use App\Exceptions\CloudinaryException;
use App\Models\Banner;
use App\Services\AdminMediaLibraryService;
use App\Services\CloudinaryImageService;
use App\Support\AdminImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class BannerController extends Controller
{
    public function __construct(
        private readonly CloudinaryImageService $cloudinary,
        private readonly AdminMediaLibraryService $media,
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'placement' => ['nullable', Rule::in(Banner::PLACEMENTS)],
            'active' => ['nullable', 'boolean'],
        ]);

        return response()->json(Banner::query()
            ->when($data['placement'] ?? null, fn ($query, $placement) => $query->where('placement', $placement))
            ->when(array_key_exists('active', $data), fn ($query) => $query->where('is_active', (bool) $data['active']))
            ->orderBy('placement')->orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request, true);
        $image = $data['image'] ?? null;
        $mediaSource = $this->extractMediaSource($data);
        unset($data['image']);
        $banner = Banner::create([...$data, 'image_url' => '', 'image_public_id' => '']);
        $uploaded = null;

        try {
            $uploaded = $image
                ? $this->cloudinary->uploadToFolder($image, 'farta/banners/'.$banner->id)
                : $this->media->resolve($mediaSource['type'], $mediaSource['id']);
            $banner->update(['image_url' => $uploaded['url'], 'image_public_id' => $uploaded['public_id']]);
        } catch (Throwable $exception) {
            $banner->delete();
            if ($uploaded && $image) {
                $this->destroyCloudinaryImage($uploaded['public_id']);
            }
            throw $exception;
        }

        Cache::forget(SiteContentController::CACHE_KEY);

        return response()->json($banner->fresh(), 201);
    }

    public function show(Banner $banner)
    {
        return response()->json($banner);
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $this->validatedData($request);
        $image = $data['image'] ?? null;
        $mediaSource = $this->extractMediaSource($data);
        unset($data['image']);
        $uploaded = $image
            ? $this->cloudinary->uploadToFolder($image, 'farta/banners/'.$banner->id)
            : ($mediaSource ? $this->media->resolve($mediaSource['type'], $mediaSource['id']) : null);
        $oldPublicId = $banner->image_public_id;

        try {
            $banner->update($uploaded ? [
                ...$data,
                'image_url' => $uploaded['url'],
                'image_public_id' => $uploaded['public_id'],
            ] : $data);
        } catch (Throwable $exception) {
            if ($uploaded && $image) {
                $this->destroyCloudinaryImage($uploaded['public_id']);
            }
            throw $exception;
        }

        if ($uploaded && $oldPublicId && $oldPublicId !== $uploaded['public_id'] && ! $this->media->isUsed($oldPublicId)) {
            $this->destroyCloudinaryImage($oldPublicId);
        }
        Cache::forget(SiteContentController::CACHE_KEY);

        return response()->json($banner->fresh());
    }

    public function destroy(Banner $banner)
    {
        $publicId = $banner->image_public_id;
        $banner->delete();
        if ($publicId && ! $this->media->isUsed($publicId)) {
            $this->destroyCloudinaryImage($publicId);
        }
        Cache::forget(SiteContentController::CACHE_KEY);

        return response()->noContent();
    }

    private function validatedData(Request $request, bool $imageRequired = false): array
    {
        $data = $request->validate([
            'placement' => ['required', Rule::in(Banner::PLACEMENTS)],
            'title_vi' => ['nullable', 'string', 'max:160'],
            'title_en' => ['nullable', 'string', 'max:160'],
            'subtitle_vi' => ['nullable', 'string', 'max:300'],
            'subtitle_en' => ['nullable', 'string', 'max:300'],
            'button_label_vi' => ['nullable', 'string', 'max:80'],
            'button_label_en' => ['nullable', 'string', 'max:80'],
            'alt_text_vi' => ['required', 'string', 'max:160'],
            'alt_text_en' => ['required', 'string', 'max:160'],
            'link_url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                $isInternalPath = str_starts_with($value, '/') && ! str_starts_with($value, '//');
                if ($value !== null && $value !== '' && ! $isInternalPath && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('Liên kết phải là đường dẫn nội bộ hoặc URL HTTPS.');
                }
                if (filter_var($value, FILTER_VALIDATE_URL) && parse_url($value, PHP_URL_SCHEME) !== 'https') {
                    $fail('Liên kết bên ngoài phải dùng HTTPS.');
                }
            }],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image' => AdminImageUpload::rules('nullable'),
            'media_source_type' => [
                $imageRequired ? 'required_without:image' : 'nullable',
                'prohibits:image',
                Rule::in(AdminMediaLibraryService::SOURCE_TYPES),
            ],
            'media_source_id' => [
                $imageRequired ? 'required_without:image' : 'nullable',
                'required_with:media_source_type',
                'integer',
                'min:1',
            ],
        ], AdminImageUpload::messages('image'));

        return $data;
    }

    private function extractMediaSource(array &$data): ?array
    {
        $type = $data['media_source_type'] ?? null;
        $id = $data['media_source_id'] ?? null;
        unset($data['media_source_type'], $data['media_source_id']);

        return $type && $id ? ['type' => $type, 'id' => (int) $id] : null;
    }

    private function destroyCloudinaryImage(string $publicId): void
    {
        try {
            $this->cloudinary->destroy($publicId);
        } catch (CloudinaryException $exception) {
            Log::warning('Could not clean up a banner image.', [
                'public_id_hash' => hash('sha256', $publicId),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
