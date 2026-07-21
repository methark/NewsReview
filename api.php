<?php

declare(strict_types=1);

/**
 * JSON endpoint for the standalone "News Revue" frontend (revue.html).
 * Runs the exact same fetch/cache/filter/cluster/fact-check pipeline as
 * index.php (see NewsPipeline) — this is a different presentation of the
 * same verification logic, not a separate implementation of it.
 *
 * GET params:
 *   q       search phrase (optional)
 *   cat[]   categories to include: world, science, finance (optional,
 *           defaults to config's default_categories if omitted entirely)
 */

require __DIR__ . '/src/TextUtils.php';
require __DIR__ . '/src/TopicFilter.php';
require __DIR__ . '/src/FeedFetcher.php';
require __DIR__ . '/src/StoryCache.php';
require __DIR__ . '/src/ArticleSearch.php';
require __DIR__ . '/src/StoryClusterer.php';
require __DIR__ . '/src/FactChecker.php';
require __DIR__ . '/src/NewsPipeline.php';

set_time_limit(120);

header('Content-Type: application/json; charset=utf-8');
// Same-origin by default (revue.html is served alongside this file), but
// this is public, read-only news data with no auth — permissive CORS costs
// nothing and lets the frontend be opened from a different port/origin too.
header('Access-Control-Allow-Origin: *');

function jsonError(int $httpStatus, string $message): never
{
    http_response_code($httpStatus);
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $config = require __DIR__ . '/config.php';
} catch (\Throwable $e) {
    jsonError(500, 'Server configuration error.');
}

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$searchQuery = mb_substr($searchQuery, 0, 120);

$validCategories = ['world', 'science', 'finance'];
$selectedCategories = isset($_GET['cat'])
    ? array_values(array_intersect((array) $_GET['cat'], $validCategories))
    : $config['default_categories'];

try {
    $result = NewsPipeline::run($config, $searchQuery, $selectedCategories);
} catch (\Throwable $e) {
    jsonError(500, 'Failed to run the fact-checking pipeline.');
}

function formatDateTimeIso(?int $timestamp): ?string
{
    return $timestamp !== null ? gmdate('c', $timestamp) : null;
}

function formatDateTimeDisplay(?int $timestamp): string
{
    return $timestamp !== null ? gmdate('D, d M Y H:i', $timestamp) . ' UTC' : 'Date unknown';
}

$stories = array_map(static function (array $story): array {
    return [
        'title' => $story['title'],
        'published_at' => formatDateTimeIso($story['published_at']),
        'published_at_display' => formatDateTimeDisplay($story['published_at']),
        'filtered_facts' => $story['filtered_facts'],
        'considerations' => $story['considerations'],
        'resources' => array_map(static fn (array $r): array => [
            'name' => $r['name'],
            'link' => $r['link'] ?: $r['homepage'],
            'published_at' => formatDateTimeIso($r['published_at']),
            'published_at_display' => formatDateTimeDisplay($r['published_at']),
        ], $story['resources']),
        'source_count' => $story['source_count'],
    ];
}, $result['stories']);

echo json_encode([
    'meta' => [
        'used_cache' => $result['used_cache'],
        'fetched_at' => formatDateTimeIso($result['fetched_at']),
        'fetched_at_display' => formatDateTimeDisplay($result['fetched_at']),
        'categories' => $selectedCategories,
        'search_query' => $searchQuery,
        'outlets_considered' => count($result['filtered_sources']),
        'articles_scanned' => $result['category_article_count'],
        'articles_matched' => $result['candidate_article_count'],
        'stories_found' => count($stories),
        'min_sources_required' => $config['min_sources_required'],
        'failed_sources' => $result['failed_sources'],
        'generated_in_seconds' => round($result['run_duration'], 3),
    ],
    'stories' => $stories,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
