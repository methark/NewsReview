<?php

declare(strict_types=1);

/**
 * Fact-checked news dashboard.
 *
 * On every visit: fetches live from several independent outlets, clusters
 * articles that report the same story, keeps only stories confirmed by
 * min_sources_required distinct outlets, strips biased/opinion language,
 * and renders the result. No caching — this pipeline runs fresh each time
 * the page loads, per requirement.
 */

require __DIR__ . '/src/TextUtils.php';
require __DIR__ . '/src/FeedFetcher.php';
require __DIR__ . '/src/StoryClusterer.php';
require __DIR__ . '/src/FactChecker.php';

$config = require __DIR__ . '/config.php';

$runStart = microtime(true);

$fetchResult = FeedFetcher::fetchAll(
    $config['sources'],
    $config['fetch_connect_timeout_seconds'],
    $config['fetch_timeout_seconds']
);

$rawClusters = StoryClusterer::cluster(
    $fetchResult['articles'],
    $config['similarity_threshold'],
    $config['max_article_age_hours']
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

$runDuration = microtime(true) - $runStart;

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatDateTime(?int $timestamp): string
{
    if ($timestamp === null) {
        return 'Date unknown';
    }
    return gmdate('D, d M Y H:i', $timestamp) . ' UTC';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NewsReview — Fact-Checked News Dashboard</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <h1>NewsReview</h1>
        <p class="tagline">Only stories independently confirmed by at least <?= (int) $config['min_sources_required'] ?> separate outlets. Biased and unattributed-opinion language is filtered out.</p>
        <p class="run-meta">
            Checked just now &middot; <?= count($fetchResult['articles']) ?> articles scanned across <?= count($config['sources']) ?> outlets
            &middot; <?= count($stories) ?> stories passed validation &middot; generated in <?= number_format($runDuration, 2) ?>s
        </p>
        <?php if ($fetchResult['failed'] !== []): ?>
        <p class="fetch-warning">Unreachable this run: <?= h(implode(', ', $fetchResult['failed'])) ?></p>
        <?php endif; ?>
    </div>
</header>

<main class="story-list">
    <?php if ($stories === []): ?>
    <div class="empty-state">
        <p>No stories cleared cross-validation on this run. This can happen if too few source feeds were reachable, or if no story was independently confirmed by <?= (int) $config['min_sources_required'] ?>+ outlets in the last <?= (int) $config['max_article_age_hours'] ?> hours.</p>
        <p>Reload the page to run the check again.</p>
    </div>
    <?php endif; ?>

    <?php foreach ($stories as $story): ?>
    <article class="story-card">
        <h2 class="story-title"><?= h($story['title']) ?></h2>
        <p class="story-datetime"><?= h(formatDateTime($story['published_at'])) ?></p>

        <section class="story-section">
            <h3>The Filtered Article</h3>
            <ul class="filtered-article">
                <?php foreach ($story['filtered_facts'] as $fact): ?>
                <li><?= h($fact) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="story-section">
            <h3>Considerations</h3>
            <ul class="considerations">
                <?php foreach ($story['considerations'] as $note): ?>
                <li><?= h($note) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="story-section">
            <h3>Resources (<?= count($story['resources']) ?>)</h3>
            <ul class="resources">
                <?php foreach ($story['resources'] as $resource): ?>
                <li>
                    <a href="<?= h($resource['link'] ?: $resource['homepage']) ?>" target="_blank" rel="noopener noreferrer nofollow">
                        <?= h($resource['name']) ?>
                    </a>
                    <span class="resource-date"><?= h(formatDateTime($resource['published_at'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </article>
    <?php endforeach; ?>
</main>

<footer class="site-footer">
    <p>Sources polled this run: <?= h(implode(', ', array_column($config['sources'], 'name'))) ?></p>
    <p>This page re-runs the entire fetch-and-verify pipeline on every visit — nothing is cached or stored.</p>
</footer>
</body>
</html>
