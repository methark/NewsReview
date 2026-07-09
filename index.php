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
require __DIR__ . '/src/ArticleSearch.php';
require __DIR__ . '/src/StoryClusterer.php';
require __DIR__ . '/src/FactChecker.php';

$config = require __DIR__ . '/config.php';

$runStart = microtime(true);

// Search narrows which of *this run's* fetched articles are considered —
// there's no stored archive to query, only whatever is live right now.
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$searchQuery = mb_substr($searchQuery, 0, 120);

$fetchResult = FeedFetcher::fetchAll(
    $config['sources'],
    $config['fetch_connect_timeout_seconds'],
    $config['fetch_timeout_seconds']
);

$candidateArticles = $searchQuery !== ''
    ? ArticleSearch::filter($fetchResult['articles'], $searchQuery)
    : $fetchResult['articles'];

$rawClusters = StoryClusterer::cluster(
    $candidateArticles,
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

        <form class="search-form" method="get" action="">
            <label for="q" class="sr-only">Search news</label>
            <input type="text" id="q" name="q" placeholder="Search this run's fetched news&hellip;" value="<?= h($searchQuery) ?>" maxlength="120" autocomplete="off">
            <button type="submit">Search</button>
            <?php if ($searchQuery !== ''): ?>
            <a class="clear-search" href="?">Clear</a>
            <?php endif; ?>
        </form>

        <p class="run-meta">
            Checked just now &middot; <?= count($fetchResult['articles']) ?> articles scanned across <?= count($config['sources']) ?> outlets
            <?php if ($searchQuery !== ''): ?>
            &middot; <?= count($candidateArticles) ?> match &ldquo;<?= h($searchQuery) ?>&rdquo;
            <?php endif; ?>
            &middot; <?= count($stories) ?> stories passed validation &middot; generated in <?= number_format($runDuration, 2) ?>s
        </p>
        <?php if ($fetchResult['failed'] !== []): ?>
        <p class="fetch-warning">Unreachable this run: <?= h(implode(', ', $fetchResult['failed'])) ?></p>
        <?php endif; ?>
    </div>
</header>

<main class="story-list" id="storyList" data-batch-size="<?= (int) $config['stories_per_batch'] ?>">
    <?php if ($stories === []): ?>
    <div class="empty-state">
        <?php if ($searchQuery !== ''): ?>
        <p>No stories matching &ldquo;<?= h($searchQuery) ?>&rdquo; cleared cross-validation on this run. Search only covers articles fetched just now, not a historical archive, so a narrow search term can easily fall under the <?= (int) $config['min_sources_required'] ?>-source bar even if the story itself is real.</p>
        <p><a href="?">Clear the search</a> to see all validated stories, or try a broader term.</p>
        <?php else: ?>
        <p>No stories cleared cross-validation on this run. This can happen if too few source feeds were reachable, or if no story was independently confirmed by <?= (int) $config['min_sources_required'] ?>+ outlets in the last <?= (int) $config['max_article_age_hours'] ?> hours.</p>
        <p>Reload the page to run the check again.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($stories as $storyIndex => $story): ?>
    <article class="story-card" data-story-index="<?= (int) $storyIndex ?>" <?= $storyIndex >= $config['stories_per_batch'] ? 'hidden' : '' ?>>
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

    <?php if (count($stories) > (int) $config['stories_per_batch']): ?>
    <div id="scrollSentinel" class="scroll-sentinel" aria-hidden="true"></div>
    <p id="scrollStatus" class="scroll-status"></p>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>Sources polled this run: <?= h(implode(', ', array_column($config['sources'], 'name'))) ?></p>
    <p>This page re-runs the entire fetch-and-verify pipeline on every visit — nothing is cached or stored.</p>
</footer>

<script>
(function () {
    var list = document.getElementById('storyList');
    var sentinel = document.getElementById('scrollSentinel');
    if (!list || !sentinel) {
        return;
    }

    var batchSize = parseInt(list.dataset.batchSize, 10) || 10;
    var status = document.getElementById('scrollStatus');
    var hiddenCards = Array.prototype.slice.call(list.querySelectorAll('.story-card[hidden]'));

    function revealNextBatch() {
        var toReveal = hiddenCards.splice(0, batchSize);
        toReveal.forEach(function (card) {
            card.removeAttribute('hidden');
        });

        if (hiddenCards.length === 0) {
            observer.disconnect();
            sentinel.remove();
            if (status) {
                status.textContent = 'All validated stories for this run are shown.';
            }
        }
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                revealNextBatch();
            }
        });
    }, { rootMargin: '400px 0px' });

    observer.observe(sentinel);
})();
</script>
</body>
</html>
