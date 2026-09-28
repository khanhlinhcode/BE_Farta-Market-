<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ChatFixtureAudit
{
    /** @param array<int, string> $relativePaths @return array<int, array{query: string, fixture: string}> */
    public static function queries(array $relativePaths): array
    {
        $queries = [];
        foreach ($relativePaths as $relativePath) {
            $fixture = require base_path($relativePath);
            self::collect($fixture, $relativePath, $queries);
        }

        return $queries;
    }

    /**
     * @param  array<int, array{query: string, fixture: string}>  $candidate
     * @param  array<int, array{query: string, fixture: string}>  $previous
     * @return array{exact: array<int, array<string, string>>, near: array<int, array<string, mixed>>}
     */
    public static function overlaps(array $candidate, array $previous, float $nearThreshold = 0.82): array
    {
        $exact = [];
        $near = [];
        foreach ($candidate as $new) {
            $normalizedNew = self::normalize($new['query']);
            $newTokens = self::tokens($normalizedNew);
            foreach ($previous as $old) {
                $normalizedOld = self::normalize($old['query']);
                if ($normalizedNew === $normalizedOld) {
                    $exact[] = [
                        'candidate' => $new['query'],
                        'previous' => $old['query'],
                        'fixture' => $old['fixture'],
                    ];

                    continue;
                }
                $oldTokens = self::tokens($normalizedOld);
                if (count($newTokens) < 4 || count($oldTokens) < 4) {
                    continue;
                }
                $union = array_unique([...$newTokens, ...$oldTokens]);
                $score = $union === [] ? 0.0 : count(array_intersect($newTokens, $oldTokens)) / count($union);
                if ($score >= $nearThreshold) {
                    $near[] = [
                        'candidate' => $new['query'],
                        'previous' => $old['query'],
                        'fixture' => $old['fixture'],
                        'jaccard' => round($score, 4),
                    ];
                }
            }
        }

        usort($near, fn (array $left, array $right): int => $right['jaccard'] <=> $left['jaccard']);

        return ['exact' => $exact, 'near' => $near];
    }

    public static function runtimeHash(): string
    {
        $files = [];
        foreach (['app', 'config', 'routes', 'resources/chat', 'database/migrations'] as $relativeDirectory) {
            $directory = base_path($relativeDirectory);
            if (! is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        $hash = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($hash, Str::after($file, base_path().DIRECTORY_SEPARATOR)."\0");
            hash_update($hash, (string) file_get_contents($file)."\0");
        }

        return hash_final($hash);
    }

    private static function normalize(string $query): string
    {
        return Str::of($query)->ascii()->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
    }

    /** @return array<int, string> */
    private static function tokens(string $normalized): array
    {
        return array_values(array_unique(array_filter(explode(' ', $normalized))));
    }

    /**
     * @param  array<int, array{query: string, fixture: string}>  $queries
     */
    private static function collect(mixed $value, string $fixture, array &$queries): void
    {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (in_array($key, ['query', 'initial'], true) && is_string($item)) {
                $queries[] = ['query' => $item, 'fixture' => $fixture];
            } elseif (is_array($item)) {
                self::collect($item, $fixture, $queries);
            }
        }
    }
}
