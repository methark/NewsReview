<?php

declare(strict_types=1);

/**
 * Groups articles from different outlets that are reporting the same story,
 * using headline/summary token-overlap as a similarity signal. A cluster only
 * counts as "cross-checked" once it contains a minimum number of distinct
 * outlets — that's the "at least 3 validations" requirement.
 */
final class StoryClusterer
{
    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<int, array<string, mixed>>> clusters, each a list of articles
     */
    public static function cluster(array $articles, float $similarityThreshold, int $maxAgeHours): array
    {
        $now = time();
        $cutoff = $now - ($maxAgeHours * 3600);

        // Keep only recent, well-formed articles and pre-compute their token sets.
        $items = [];
        foreach ($articles as $article) {
            $published = $article['published_at'];
            if ($published !== null && $published < $cutoff) {
                continue;
            }
            $article['tokens'] = TextUtils::tokenize($article['title'] . ' ' . $article['description']);
            $article['entities'] = TextUtils::extractEntityPhrases($article['title']);
            $items[] = $article;
        }

        $n = count($items);
        $parent = range(0, $n - 1);

        $find = static function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        $union = static function (int $a, int $b) use (&$parent, $find): void {
            $rootA = $find($a);
            $rootB = $find($b);
            if ($rootA !== $rootB) {
                $parent[$rootA] = $rootB;
            }
        };

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($items[$i]['source_name'] === $items[$j]['source_name']) {
                    continue; // similarity across the *same* outlet isn't cross-validation
                }
                $tokensA = $items[$i]['tokens'];
                $tokensB = $items[$j]['tokens'];

                // Outlets vary hugely in how long their RSS descriptions are
                // (one-line teaser vs full paragraph). Jaccard alone punishes
                // that mismatch even when one text is fully contained in the
                // other, so blend it with the overlap coefficient (normalized
                // by the smaller set) and require a minimum absolute overlap
                // so two very short, coincidentally-similar texts can't match.
                $jaccard = TextUtils::jaccard($tokensA, $tokensB);
                $overlap = TextUtils::overlapCoefficient($tokensA, $tokensB);
                $similarity = ($jaccard + $overlap) / 2;

                $isTokenMatch = $similarity >= $similarityThreshold && TextUtils::sharedTokenCount($tokensA, $tokensB) >= 4;

                // Two headlines can describe the same story in almost
                // unrelated wording ("Fed raises rates" vs "Central bank
                // lifts benchmark") while still sharing the names that
                // actually identify it. A shared multi-word name (a full
                // org/person name) is specific enough to stand on its own;
                // a shared single-word name is weaker and coincidental
                // enough (two unrelated stories both mentioning "Biden")
                // that it also needs at least two of them, plus some
                // topical relevance floor, before counting as a match.
                $entitiesA = $items[$i]['entities'];
                $entitiesB = $items[$j]['entities'];
                $isEntityMatch = TextUtils::hasSharedMultiWordEntity($entitiesA, $entitiesB)
                    || (TextUtils::sharedTokenCount($entitiesA, $entitiesB) >= 2 && $similarity >= ($similarityThreshold * 0.5));

                if ($isTokenMatch || $isEntityMatch) {
                    $union($i, $j);
                }
            }
        }

        $groups = [];
        for ($i = 0; $i < $n; $i++) {
            $root = $find($i);
            $groups[$root][] = $items[$i];
        }

        return array_values($groups);
    }

    /**
     * Reduce a cluster to at most one article per distinct source (the most
     * recent one), so a single outlet republishing can't inflate the count.
     *
     * @param array<int, array<string, mixed>> $cluster
     * @return array<int, array<string, mixed>>
     */
    public static function dedupeBySource(array $cluster): array
    {
        $bySource = [];
        foreach ($cluster as $article) {
            $key = $article['source_name'];
            if (!isset($bySource[$key]) || ($article['published_at'] ?? 0) > ($bySource[$key]['published_at'] ?? 0)) {
                $bySource[$key] = $article;
            }
        }
        return array_values($bySource);
    }
}
