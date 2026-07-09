<?php

declare(strict_types=1);

/**
 * Fact-checked news dashboard.
 *
 * The expensive step — live-fetching 30-40 outlets — is cached to a local
 * JSON file for up to cache_ttl_seconds (see StoryCache), since running
 * that fetch on every single page visit was hitting real-world timeouts
 * under normal network conditions and made every visit (including a
 * search or a category-checkbox change, neither of which needs a fresh
 * fetch) pay that cost. Category filtering, search, clustering, and
 * fact-checking still run fresh on every request against whichever pool
 * — cached or freshly fetched — is available; see refresh.php to force
 * or schedule a fetch independent of any page visit.
 */

require __DIR__ . '/src/TextUtils.php';
require __DIR__ . '/src/TopicFilter.php';
require __DIR__ . '/src/FeedFetcher.php';
require __DIR__ . '/src/StoryCache.php';
require __DIR__ . '/src/ArticleSearch.php';
require __DIR__ . '/src/StoryClusterer.php';
require __DIR__ . '/src/FactChecker.php';

// Worst case, a full run fetches (source count / 6 concurrent connections)
// batches, each bounded by fetch_timeout_seconds — with 40 sources that's
// up to ~100s. PHP's default max_execution_time (often 30s) would silently
// kill the script mid-fetch before curl's own timeouts ever get a chance
// to resolve things cleanly. This only stretches the ceiling; a normal run
// where most feeds respond quickly finishes in a couple of seconds either way.
set_time_limit(120);

$config = require __DIR__ . '/config.php';

$runStart = microtime(true);

// Search narrows which of *this run's* fetched articles are considered —
// there's no stored archive to query, only whatever is live right now.
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$searchQuery = mb_substr($searchQuery, 0, 120);

// Category checkboxes: unchecked boxes simply aren't submitted by HTML
// forms, so an empty $_GET['cat'] is indistinguishable from "no filter
// touched yet" unless a fresh page load (no 'filtered' marker) is told
// apart from a real submission where the user unchecked everything.
$validCategories = ['world', 'science', 'finance'];
if (isset($_GET['filtered'])) {
    $selectedCategories = array_values(array_intersect((array) ($_GET['cat'] ?? []), $validCategories));
} else {
    $selectedCategories = $config['default_categories'];
}

$filteredSources = array_values(array_filter(
    $config['sources'],
    static fn (array $source): bool => in_array($source['category'], $selectedCategories, true)
));

// The cache holds ALL sources' articles (every category), fetched together
// in one pass, so it can serve any category combination a visitor picks
// without needing a separate cache per combination.
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

function formatMinutesAgo(int $timestamp): string
{
    $minutes = (int) round((time() - $timestamp) / 60);
    if ($minutes <= 0) {
        return 'less than a minute ago';
    }
    if ($minutes === 1) {
        return '1 minute ago';
    }
    return "{$minutes} minutes ago";
}

/**
 * Escapes $text for HTML, then wraps case-insensitive matches of the
 * search query's terms in <mark>. Operates on already-escaped text and
 * only ever inserts a fixed, attribute-free tag, so it can't reintroduce
 * markup from user input.
 */
function highlightQuery(string $text, string $query): string
{
    $escaped = h($text);
    if ($query === '') {
        return $escaped;
    }

    $terms = TextUtils::tokenize($query);
    if ($terms === []) {
        $terms = [mb_strtolower($query)];
    }

    $patterns = array_map(
        static fn (string $term): string => '/' . preg_quote(h($term), '/') . '/iu',
        array_filter($terms, static fn (string $t): bool => $t !== '')
    );

    if ($patterns === []) {
        return $escaped;
    }

    return preg_replace($patterns, '<mark>$0</mark>', $escaped) ?? $escaped;
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

            <input type="hidden" name="filtered" value="1">
            <fieldset class="category-filter">
                <legend class="sr-only">Filter by category</legend>
                <?php foreach ($validCategories as $category): ?>
                <label class="category-checkbox">
                    <input type="checkbox" name="cat[]" value="<?= h($category) ?>" <?= in_array($category, $selectedCategories, true) ? 'checked' : '' ?>>
                    <?= h(ucfirst($category)) ?>
                </label>
                <?php endforeach; ?>
            </fieldset>
        </form>

        <p class="run-meta">
            <?= $usedCache ? 'Data refreshed ' . h(formatMinutesAgo($fetchedAt)) : 'Checked just now' ?>
            &middot; <?= count($categoryArticles) ?> articles scanned across <?= count($filteredSources) ?> outlets
            <?php if ($selectedCategories !== $validCategories): ?>
            (<?= h(implode(', ', array_map('ucfirst', $selectedCategories)) ?: 'none selected') ?>)
            <?php endif; ?>
            <?php if ($searchQuery !== ''): ?>
            &middot; <?= count($candidateArticles) ?> match &ldquo;<?= h($searchQuery) ?>&rdquo;
            <?php endif; ?>
            &middot; <?= count($stories) ?> stories passed validation &middot; generated in <?= number_format($runDuration, 2) ?>s
        </p>
        <?php if ($failedSources !== []): ?>
        <p class="fetch-warning"><?= $usedCache ? 'Unreachable at last refresh' : 'Unreachable this run' ?> (all categories): <?= h(implode(', ', $failedSources)) ?></p>
        <?php endif; ?>
    </div>
</header>

<main class="story-list" id="storyList" data-batch-size="<?= (int) $config['stories_per_batch'] ?>">
    <?php if ($stories === []): ?>
    <div class="empty-state">
        <?php if ($selectedCategories === []): ?>
        <p>No category is selected, so there's nothing to fetch. Check at least one of World, Science, or Finance above.</p>
        <?php elseif ($searchQuery !== ''): ?>
        <p>No stories matching &ldquo;<?= h($searchQuery) ?>&rdquo; cleared cross-validation. Search only covers <?= $usedCache ? 'the currently cached articles' : 'articles fetched just now' ?>, not a full historical archive, so a narrow search term can easily fall under the <?= (int) $config['min_sources_required'] ?>-source bar even if the story itself is real.</p>
        <p><a href="?">Clear the search</a> to see all validated stories, or try a broader term.</p>
        <?php else: ?>
        <p>No stories cleared cross-validation. This can happen if too few source feeds were reachable, or if no story was independently confirmed by <?= (int) $config['min_sources_required'] ?>+ outlets in the last <?= (int) $ageWindowHours ?> hours.</p>
        <p>Reload the page to run the check again<?= $usedCache ? ', or wait for the next scheduled refresh' : '' ?>.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($stories as $storyIndex => $story): ?>
    <article class="story-card" data-story-index="<?= (int) $storyIndex ?>" <?= $storyIndex >= $config['stories_per_batch'] ? 'hidden' : '' ?>>
        <h2 class="story-title"><?= highlightQuery($story['title'], $searchQuery) ?></h2>
        <p class="story-datetime"><?= h(formatDateTime($story['published_at'])) ?></p>

        <section class="story-section">
            <h3>The Filtered Article</h3>
            <ul class="filtered-article">
                <?php foreach ($story['filtered_facts'] as $fact): ?>
                <li><?= highlightQuery($fact, $searchQuery) ?></li>
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
    <div class="load-more-row">
        <button type="button" id="loadMoreButton" class="load-more-button"></button>
    </div>
    <p id="scrollStatus" class="scroll-status" hidden>All validated stories for this run are shown.</p>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>Sources considered this run: <?= h(implode(', ', array_column($filteredSources, 'name')) ?: 'none') ?></p>
    <?php if ($config['cache_enabled']): ?>
    <p>Cross-checking, filtering, and search run fresh on every visit; the underlying article fetch across all outlets is cached for up to <?= (int) round($config['cache_ttl_seconds'] / 60) ?> minutes so a search or category change doesn't re-trigger a live fetch of 30+ outlets. See <code>refresh.php</code> to force or schedule a refresh independent of any page visit.</p>
    <?php else: ?>
    <p>This page re-runs the entire fetch-and-verify pipeline on every visit — nothing is cached or stored.</p>
    <?php endif; ?>
</footer>

<script>
(function () {
    var list = document.getElementById('storyList');
    var sentinel = document.getElementById('scrollSentinel');
    var button = document.getElementById('loadMoreButton');
    var status = document.getElementById('scrollStatus');
    if (!list || !sentinel || !button) {
        return;
    }

    var batchSize = parseInt(list.dataset.batchSize, 10) || 10;
    var hiddenCards = Array.prototype.slice.call(list.querySelectorAll('.story-card[hidden]'));

    function updateButtonLabel() {
        button.textContent = 'Load ' + Math.min(batchSize, hiddenCards.length) + ' more stories (' + hiddenCards.length + ' remaining)';
    }

    function revealNextBatch() {
        var toReveal = hiddenCards.splice(0, batchSize);
        toReveal.forEach(function (card) {
            card.removeAttribute('hidden');
        });

        if (hiddenCards.length === 0) {
            if (observer) {
                observer.disconnect();
            }
            sentinel.remove();
            button.remove();
            if (status) {
                status.hidden = false;
            }
        } else {
            updateButtonLabel();
        }
    }

    button.addEventListener('click', revealNextBatch);
    updateButtonLabel();

    // Auto-reveal on scroll is a bonus on top of the button, not a
    // replacement — IntersectionObserver support is assumed but the button
    // above works regardless of whether this fires.
    var observer = null;
    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    revealNextBatch();
                }
            });
        }, { rootMargin: '400px 0px' });
        observer.observe(sentinel);
    }
})();
</script>
</body>
</html>
