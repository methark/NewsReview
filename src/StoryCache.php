<?php

declare(strict_types=1);

/**
 * Caches the raw fetched-article pool (all sources, all categories) to a
 * local JSON file so an expensive live fetch across 30-40 outlets doesn't
 * have to run on every single page visit — only once per cache_ttl_seconds.
 * Category filtering, search, clustering, and fact-checking still run
 * fresh on every request against whichever pool (cached or freshly
 * fetched) is available; only the network fetch itself is cached.
 */
final class StoryCache
{
    /**
     * @return array{articles: array<int, array<string, mixed>>, failed: string[], fetched_at: int}|null
     *         null if there's no cache file, it's stale, or it's unreadable/corrupt.
     */
    public static function read(string $path, int $ttlSeconds): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $mtime = filemtime($path);
        if ($mtime === false || (time() - $mtime) > $ttlSeconds) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['articles'], $data['failed'], $data['fetched_at'])) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @param string[] $failed
     */
    public static function write(string $path, array $articles, array $failed, int $fetchedAt): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $json = json_encode(
            ['articles' => $articles, 'failed' => $failed, 'fetched_at' => $fetchedAt],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if ($json === false) {
            return false;
        }

        return @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}
