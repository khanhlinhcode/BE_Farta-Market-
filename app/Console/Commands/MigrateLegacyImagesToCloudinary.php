<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CloudinaryImageService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateLegacyImagesToCloudinary extends Command
{
    protected $signature = 'images:migrate-to-cloudinary
        {--source= : Absolute path to the storefront public directory}
        {--dry-run : Report legacy images without uploading or updating the database}';

    protected $description = 'Upload legacy local images to Cloudinary and replace their database references';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private string $sourceRoot;

    public function __construct(private readonly CloudinaryImageService $cloudinary)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->sourceRoot = $this->resolveSourceRoot();

        $this->migrateProductImages();
        $this->migrateCategories();
        $this->migrateBanners();
        $this->migrateAvatars();

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                [$this->option('dry-run') ? 'Ready' : 'Migrated', $this->migrated],
                ['Skipped', $this->skipped],
                ['Failed', $this->failed],
            ]
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run completed. No files were uploaded and the database was not changed.');
        }

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function migrateProductImages(): void
    {
        Product::query()->with('images')->orderBy('id')->each(function (Product $product) {
            foreach ($product->images as $image) {
                if ($this->isManagedCloudinaryImage($image->url, $image->public_id, $image->provider)) {
                    $this->skipped++;

                    continue;
                }

                $path = $this->resolveLocalPath($image->url, $image->path);

                $this->migrateFile(
                    'product image',
                    $image->id,
                    $path,
                    'farta/products/'.$product->id,
                    function (array $uploaded) use ($image, $product) {
                        DB::transaction(function () use ($uploaded, $image, $product) {
                            $image->update([
                                'provider' => 'cloudinary',
                                'path' => null,
                                'public_id' => $uploaded['public_id'],
                                'url' => $uploaded['url'],
                            ]);

                            if ($image->is_primary) {
                                $product->update(['img' => $uploaded['url']]);
                            }
                        });
                    }
                );
            }

            if ($this->option('dry-run')) {
                if ($product->images->isEmpty() && $product->img !== '') {
                    $this->migrateFile(
                        'product cover',
                        $product->id,
                        $this->resolveLocalPath($product->img),
                        'farta/products/'.$product->id,
                        fn () => null
                    );
                }

                return;
            }

            $product->refresh()->load('images');
            $primary = $product->images->firstWhere('is_primary', true);

            if ($primary && $this->isManagedCloudinaryImage($primary->url, $primary->public_id, $primary->provider)) {
                if ($product->img !== $primary->url) {
                    $product->update(['img' => $primary->url]);
                }

                return;
            }

            if ($product->images->isNotEmpty()) {
                $first = $product->images->first();
                $first->update(['is_primary' => true]);
                $product->update(['img' => $first->url]);

                return;
            }

            if ($product->img === '') {
                $this->skipped++;

                return;
            }

            $path = $this->resolveLocalPath($product->img);

            $this->migrateFile(
                'product cover',
                $product->id,
                $path,
                'farta/products/'.$product->id,
                function (array $uploaded) use ($product) {
                    DB::transaction(function () use ($uploaded, $product) {
                        $product->images()->create([
                            'provider' => 'cloudinary',
                            'path' => null,
                            'public_id' => $uploaded['public_id'],
                            'url' => $uploaded['url'],
                            'sort_order' => 0,
                            'is_primary' => true,
                        ]);
                        $product->update(['img' => $uploaded['url']]);
                    });
                }
            );
        });
    }

    private function migrateCategories(): void
    {
        Category::query()->orderBy('id')->each(function (Category $category) {
            $this->migrateImageFields(
                'category image',
                $category->id,
                $category->image_url,
                $category->image_public_id,
                'farta/categories/'.$category->id,
                fn (array $uploaded) => $category->update([
                    'image_url' => $uploaded['url'],
                    'image_public_id' => $uploaded['public_id'],
                ])
            );
        });
    }

    private function migrateBanners(): void
    {
        Banner::query()->orderBy('id')->each(function (Banner $banner) {
            $this->migrateImageFields(
                'banner image',
                $banner->id,
                $banner->image_url,
                $banner->image_public_id,
                'farta/banners/'.$banner->id,
                fn (array $uploaded) => $banner->update([
                    'image_url' => $uploaded['url'],
                    'image_public_id' => $uploaded['public_id'],
                ])
            );
        });
    }

    private function migrateAvatars(): void
    {
        User::query()
            ->whereNotNull('avatar_url')
            ->where('avatar_url', '!=', '')
            ->orderBy('id')
            ->each(function (User $user) {
                $this->migrateImageFields(
                    'avatar',
                    $user->id,
                    $user->avatar_url,
                    $user->avatar_public_id,
                    'farta/avatars/'.$user->id,
                    fn (array $uploaded) => $user->update([
                        'avatar_url' => $uploaded['url'],
                        'avatar_public_id' => $uploaded['public_id'],
                    ])
                );
            });
    }

    private function migrateImageFields(
        string $type,
        int $id,
        ?string $url,
        ?string $publicId,
        string $folder,
        callable $persist
    ): void {
        if (! is_string($url) || $url === '') {
            $this->skipped++;

            return;
        }

        if ($this->isManagedCloudinaryImage($url, $publicId)) {
            $this->skipped++;

            return;
        }

        $this->migrateFile($type, $id, $this->resolveLocalPath($url), $folder, $persist);
    }

    private function migrateFile(
        string $type,
        int $id,
        ?string $path,
        string $folder,
        callable $persist
    ): void {
        if ($path === null) {
            $this->failed++;
            $this->error("Missing local file for {$type} #{$id}.");

            return;
        }

        if ($this->option('dry-run')) {
            $this->migrated++;
            $this->line("Ready: {$type} #{$id}");

            return;
        }

        $uploaded = null;

        try {
            $uploaded = $this->cloudinary->uploadToFolder(
                new UploadedFile(
                    $path,
                    basename($path),
                    mime_content_type($path) ?: null,
                    UPLOAD_ERR_OK,
                    true
                ),
                $folder
            );

            $persist($uploaded);
            $this->migrated++;
            $this->info("Migrated: {$type} #{$id}");
        } catch (Throwable $exception) {
            if (is_array($uploaded) && isset($uploaded['public_id'])) {
                try {
                    $this->cloudinary->destroy($uploaded['public_id']);
                } catch (Throwable $cleanupException) {
                    Log::warning('Could not roll back a migrated Cloudinary image.', [
                        'public_id_hash' => hash('sha256', $uploaded['public_id']),
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            report($exception);
            $this->failed++;
            $this->error("Failed: {$type} #{$id}. {$exception->getMessage()}");
        }
    }

    private function resolveLocalPath(?string $url, ?string $storagePath = null): ?string
    {
        $candidates = [];

        if (is_string($storagePath) && $storagePath !== '') {
            $candidates[] = Storage::disk('public')->path($storagePath);
        }

        if (is_string($url) && $url !== '' && ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $path = (string) parse_url($url, PHP_URL_PATH);

            if (str_starts_with($path, '/storage/')) {
                $candidates[] = Storage::disk('public')->path(substr($path, strlen('/storage/')));
            }

            $candidates[] = $this->sourceRoot.DIRECTORY_SEPARATOR.ltrim($path, '/');
            $candidates[] = public_path(ltrim($path, '/'));
        }

        foreach ($candidates as $candidate) {
            $realPath = realpath($candidate);

            if ($realPath !== false && is_file($realPath) && $this->isApprovedPath($realPath)) {
                return $realPath;
            }
        }

        return null;
    }

    private function resolveSourceRoot(): string
    {
        $source = trim((string) $this->option('source'));

        if ($source === '') {
            $source = base_path('../../websivi/public');
        }

        $realPath = realpath($source);

        return $realPath !== false ? rtrim($realPath, DIRECTORY_SEPARATOR) : rtrim($source, DIRECTORY_SEPARATOR);
    }

    private function isApprovedPath(string $path): bool
    {
        $roots = [
            realpath($this->sourceRoot),
            realpath(public_path()),
            realpath(Storage::disk('public')->path('')),
        ];

        foreach (array_filter($roots) as $root) {
            $root = rtrim($root, DIRECTORY_SEPARATOR);

            if ($path === $root || str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function isManagedCloudinaryImage(?string $url, ?string $publicId, ?string $provider = 'cloudinary'): bool
    {
        return $provider === 'cloudinary'
            && is_string($publicId)
            && $publicId !== ''
            && is_string($url)
            && str_starts_with($url, 'https://res.cloudinary.com/');
    }
}
