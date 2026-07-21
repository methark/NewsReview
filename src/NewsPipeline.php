<?php

declare(strict_types=1);

/**
 * The shared fetch → cache → filter → cluster → fact-check pipeline used by
 * both index.php (HTML dashboard) and api.php (JSON endpoint for the
 * standalone "News Revue" frontend), so the two presentations can never
 * drift into running different logic against the same data.
 */
final class NewsPipeline
{
    /**
     * @param array<string, mixed> $config
     * @param string[] $selectedCategories
     * @return array{
     *   stories: array<int, array<string, mixed>>,
     *   used_cache: bool,
     *   fetched_at: int,
     *   failed_sources: string[],
     *   filtered_sources: array<int, array<string, mixed>>,
     *   category_article_count: int,
     *   candidate_article_count: int,
     *   age_window_hours: int,
     *   run_duration: float
     * }
     */
    public static function run(array $config, string $searchQuery, array $selectedCategories): array
    {
        $runStart = microtime(true);

        $filteredSources = array_values(array_filter(
            $config['sources'],
            static fn (array $source): bool => in_array($source['category'], $selectedCategories, true)
        ));

        // The cache holds ALL sources' articles (every category), fetched
        // together in one pass, so it can serve any category combination a
        // visitor picks without needing a separate cache per combination.
        $cached = $config['cache_enabled'] ? StoryCache::read($config['cache_file'], $config['cache_ttl_seconds']) : null;
        $usedCache = $cached !== null;

        if ($usedCache) {
            $allArticles = $cached['articles'];
            $failedSources = $cached['failed'];
            $fetchedAt = $cached['fetched_at'];
        } else {
            $fetchResult = FeedFetcher::fetchAll(
                $config['sources'],
                $config['fetch_connect_timeout_seconds'],
                $config['fetch_timeout_seconds']
            );
            $allArticles = $fetchResult['articles'];
            $failedSources = $fetchResult['failed'];
            $fetchedAt = time();
            if ($config['cache_enabled']) {
                StoryCache::write($config['cache_file'], $allArticles, $failedSources, $fetchedAt);
            }
        }

        $categoryArticles = array_values(array_filter(
            $allArticles,
            static fn (array $article): bool => in_array($article['source_category'], $selectedCategories, true)
        ));

        $candidateArticles = $searchQuery !== ''
            ? ArticleSearch::filter($categoryArticles, $searchQuery)
            : $categoryArticles;

        $ageWindowHours = $searchQuery !== '' ? $config['search_max_article_age_hours'] : $config['max_article_age_hours'];

        $rawClusters = StoryClusterer::cluster(
            $candidateArticles,
            $config['similarity_threshold'],
            $ageWindowHours
        );

        $stories = [];
        foreach ($rawClusters as $cluster) {
            $deduped = StoryClusterer::dedupeBySource($cluster);
            if (count($deduped) >= $config['min_sources_required']) {
                $stories[] = FactChecker::analyze($deduped);
            }
        }

        usort($stories, static fn (array $a, array $b): int => ($b['published_at'] ?? 0) <=> ($a['published_at'] ?? 0));
        $stories = array_slice($stories, 0, $config['max_stories_shown']);

        return [
            'stories' => $stories,
            'used_cache' => $usedCache,
            'fetched_at' => $fetchedAt,
            'failed_sources' => $failedSources,
            'filtered_sources' => $filteredSources,
            'category_article_count' => count($categoryArticles),
            'candidate_article_count' => count($candidateArticles),
            'age_window_hours' => $ageWindowHours,
            'run_duration' => microtime(true) - $runStart,
        ];
    }
}
