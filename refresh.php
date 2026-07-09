<?php

declare(strict_types=1);

/**
 * Forces a fresh fetch across every configured source and writes it to the
 * cache that index.php reads from (see StoryCache), independent of any
 * page visit. Run this from the command line or point a scheduled task at
 * it to keep the cache warm — e.g. so the first visitor after an hour
 * doesn't have to wait through a live fetch of 30+ outlets themselves.
 *
 * CLI:     php refresh.php
 * Browser: http://localhost/NewsReview/refresh.php
 *
 * Windows Task Scheduler, hourly:
 *   Program/script:  C:\xampp\php\php.exe
 *   Arguments:       C:\xampp\htdocs\NewsReview\refresh.php
 *   Trigger:         Repeat every 1 hour
 *
 * (Or any cron-equivalent on other platforms: `0 * * * * php /path/to/refresh.php`)
 */

require __DIR__ . '/src/TextUtils.php';
require __DIR__ . '/src/TopicFilter.php';
require __DIR__ . '/src/FeedFetcher.php';
require __DIR__ . '/src/StoryCache.php';

set_time_limit(120);

$config = require __DIR__ . '/config.php';

if (!$config['cache_enabled']) {
    $message = "cache_enabled is false in config.php — nothing to refresh.\n";
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message);
        exit(1);
    }
    header('Content-Type: text/plain');
    echo $message;
    exit;
}

$start = microtime(true);
$result = FeedFetcher::fetchAll(
    $config['sources'],
    $config['fetch_connect_timeout_seconds'],
    $config['fetch_timeout_seconds']
);
$fetchedAt = time();

$written = StoryCache::write($config['cache_file'], $result['articles'], $result['failed'], $fetchedAt);
$duration = microtime(true) - $start;

$summary = sprintf(
    "%s — %d articles from %d sources (%d unreachable) in %.2fs -> %s\n",
    gmdate('Y-m-d H:i:s', $fetchedAt) . ' UTC',
    count($result['articles']),
    count($config['sources']),
    count($result['failed']),
    $duration,
    $written ? 'cache written' : 'CACHE WRITE FAILED (check ' . dirname($config['cache_file']) . ' is writable)'
);

if (PHP_SAPI === 'cli') {
    echo $summary;
    if ($result['failed'] !== []) {
        echo "Unreachable: " . implode(', ', $result['failed']) . "\n";
    }
    exit($written ? 0 : 1);
}

header('Content-Type: text/plain');
echo $summary;
if ($result['failed'] !== []) {
    echo "Unreachable: " . implode(', ', $result['failed']) . "\n";
}
