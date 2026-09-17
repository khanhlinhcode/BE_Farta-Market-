<?php

namespace App\Services;

use App\Exceptions\CloudinaryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class CloudinaryImageService
{
    /**
     * @return array{url: string, public_id: string}
     */
    public function upload(UploadedFile $file, int $productId): array
    {
        return $this->uploadToFolder($file, 'farta/products/'.$productId);
    }

    /**
     * @return array{url: string, public_id: string}
     */
    public function uploadToFolder(UploadedFile $file, string $folder): array
    {
        $credentials = $this->credentials();
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new CloudinaryException('Could not read the uploaded image.');
        }

        if (! preg_match('#^farta/(products|categories|banners|avatars)/[1-9][0-9]*$#', $folder)) {
            throw new CloudinaryException('The Cloudinary upload folder is invalid.');
        }

        $parameters = [
            'public_id' => $folder.'/'.Str::uuid(),
            'timestamp' => (string) now()->timestamp,
        ];

        try {
            $response = Http::timeout(15)
                ->attach('file', $contents, $file->getClientOriginalName(), [
                    'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                ])
                ->post($this->endpoint($credentials['cloud_name'], 'upload'), [
                    ...$parameters,
                    'api_key' => $credentials['api_key'],
                    'signature' => $this->signature($parameters, $credentials['api_secret']),
                ]);
        } catch (ConnectionException $exception) {
            throw new CloudinaryException('Could not connect to Cloudinary.', previous: $exception);
        }

        if ($response->failed()) {
            throw new CloudinaryException("Cloudinary upload failed with HTTP {$response->status()}.");
        }

        $url = $response->json('secure_url');
        $publicId = $response->json('public_id');

        $isAllowedUrl = is_string($url) && (str_starts_with($url, 'https://')
            || (app()->environment('testing') && str_starts_with($url, 'http://127.0.0.1:')));

        if (! $isAllowedUrl || ! is_string($publicId) || $publicId === '') {
            throw new CloudinaryException('Cloudinary returned an invalid upload response.');
        }

        return ['url' => $url, 'public_id' => $publicId];
    }

    public function destroy(string $publicId): void
    {
        if ($publicId === '') {
            throw new CloudinaryException('The Cloudinary public ID is missing.');
        }

        $credentials = $this->credentials();
        $parameters = [
            'invalidate' => 'true',
            'public_id' => $publicId,
            'timestamp' => (string) now()->timestamp,
        ];

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($this->endpoint($credentials['cloud_name'], 'destroy'), [
                    ...$parameters,
                    'api_key' => $credentials['api_key'],
                    'signature' => $this->signature($parameters, $credentials['api_secret']),
                ]);
        } catch (ConnectionException $exception) {
            throw new CloudinaryException('Could not connect to Cloudinary.', previous: $exception);
        }

        if ($response->failed() || ! in_array($response->json('result'), ['ok', 'not found'], true)) {
            throw new CloudinaryException("Cloudinary delete failed with HTTP {$response->status()}.");
        }
    }

    /**
     * @return array{cloud_name: string, api_key: string, api_secret: string}
     */
    private function credentials(): array
    {
        $credentials = [
            'cloud_name' => (string) config('services.cloudinary.cloud_name'),
            'api_key' => (string) config('services.cloudinary.api_key'),
            'api_secret' => (string) config('services.cloudinary.api_secret'),
        ];

        if (in_array('', $credentials, true)) {
            throw new CloudinaryException('Cloudinary is not configured.');
        }

        return $credentials;
    }

    private function endpoint(string $cloudName, string $action): string
    {
        $baseUrl = rtrim((string) config('services.cloudinary.base_url'), '/');

        $isLocalTestEndpoint = app()->environment('testing')
            && str_starts_with($baseUrl, 'http://127.0.0.1:');

        if (! str_starts_with($baseUrl, 'https://') && ! $isLocalTestEndpoint) {
            throw new CloudinaryException('Cloudinary requires an HTTPS API endpoint.');
        }

        return $baseUrl
            .'/v1_1/'.rawurlencode($cloudName).'/image/'.$action;
    }

    private function signature(array $parameters, string $apiSecret): string
    {
        ksort($parameters);
        $payload = collect($parameters)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode('&');

        return sha1($payload.$apiSecret);
    }
}
