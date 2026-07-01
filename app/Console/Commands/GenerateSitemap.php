<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the public sitemap for Farta Market.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $sitemap = Sitemap::create()
            ->add(Url::create($baseUrl.'/')->setPriority(1.0))
            ->add(Url::create($baseUrl.'/san-pham')->setPriority(0.9));

        Product::query()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->chunk(100, function ($products) use ($sitemap, $baseUrl) {
                foreach ($products as $product) {
                    $sitemap->add(
                        Url::create($baseUrl.'/san-pham/chi-tiet/'.$product->id)
                            ->setLastModificationDate($product->updated_at)
                            ->setPriority(0.8)
                    );
                }
            });

        $sitemap->writeToFile(public_path('sitemap.xml'));
        $this->info('Sitemap generated at public/sitemap.xml');

        return self::SUCCESS;
    }
}
