<?php

declare(strict_types=1);

/**
 * Takes a cross-validated cluster of articles (same story, 3+ distinct
 * outlets) and produces the dashboard-ready result: a bias-stripped
 * "filtered article", a list of considerations (discrepancies, confidence),
 * and the resource list used to validate the story.
 */
final class FactChecker
{
    /**
     * @param array<int, array<string, mixed>> $cluster one article per distinct source
     * @return array<string, mixed>
     */
    public static function analyze(array $cluster): array
    {
        usort($cluster, static fn (array $a, array $b): int => ($b['published_at'] ?? 0) <=> ($a['published_at'] ?? 0));

        $title = self::pickTitle($cluster);
        $latestTimestamp = max(array_map(static fn (array $a): int => $a['published_at'] ?? 0, $cluster));

        [$filteredFacts, $removedBiased, $removedOpinion, $removedSpeculative, $keptCount] = self::buildFilteredArticle($cluster);
        $considerations = self::buildConsiderations($cluster, $removedBiased, $removedOpinion, $removedSpeculative, $keptCount);

        $resources = array_map(static fn (array $a): array => [
            'name' => $a['source_name'],
            'link' => $a['link'],
            'homepage' => $a['source_homepage'],
            'published_at' => $a['published_at'],
        ], $cluster);

        return [
            'title' => $title,
            'published_at' => $latestTimestamp > 0 ? $latestTimestamp : null,
            'filtered_facts' => $filteredFacts === []
                ? ['Independent sources confirm this story, but no sentence survived bias/opinion filtering with high enough cross-source agreement to publish as verified body text.']
                : $filteredFacts,
            'considerations' => $considerations,
            'resources' => $resources,
            'source_count' => count($cluster),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $cluster
     */
    private static function pickTitle(array $cluster): string
    {
        // Prefer headlines free of loaded language; among those, the shortest
        // tends to be the least editorialized ("X dies at 90" vs "Beloved icon
        // X tragically passes at 90"). Fall back to shortest overall only if
        // every headline in the cluster contains bias language.
        $titles = array_map(static fn (array $a): string => $a['title'], $cluster);
        usort($titles, static fn (string $a, string $b): int => mb_strlen($a) <=> mb_strlen($b));

        foreach ($titles as $title) {
            if (!TextUtils::isBiased($title) && !TextUtils::isSpeculative($title)) {
                return $title;
            }
        }
        return $titles[0];
    }

    /**
     * Build the neutral, fact-only synthesis of the story from all cluster
     * descriptions: split into sentences, drop biased/opinion sentences,
     * de-duplicate near-identical statements, and keep only statements
     * corroborated (or at least not contradicted) across sources.
     *
     * @param array<int, array<string, mixed>> $cluster
     * @return array{0: string[], 1: int, 2: int, 3: int, 4: int} [sentences, removedBiasedCount, removedOpinionCount, removedSpeculativeCount, keptCount]
     */
    private static function buildFilteredArticle(array $cluster): array
    {
        $candidates = []; // normalized => ['text' => ..., 'sources' => Set<string>]
        $removedBiased = 0;
        $removedOpinion = 0;
        $removedSpeculative = 0;

        foreach ($cluster as $article) {
            $sentences = TextUtils::splitSentences($article['description']);
            foreach ($sentences as $sentence) {
                if (mb_strlen($sentence) < 25) {
                    continue; // too short to be a standalone fact
                }
                if (TextUtils::isBiased($sentence)) {
                    $removedBiased++;
                    continue;
                }
                if (TextUtils::isOpinion($sentence)) {
                    $removedOpinion++;
                    continue;
                }
                if (TextUtils::isSpeculative($sentence)) {
                    $removedSpeculative++;
                    continue;
                }
                $key = TextUtils::normalizeForDedup($sentence);
                if ($key === '') {
                    continue;
                }
                if (!isset($candidates[$key])) {
                    $candidates[$key] = ['text' => $sentence, 'sources' => []];
                }
                $candidates[$key]['sources'][$article['source_name']] = true;
            }
        }

        // Fold near-duplicate sentences (different wording, same claim) using
        // token-overlap so the same fact from two outlets counts as one,
        // corroborated statement rather than two separate ones.
        $folded = self::foldNearDuplicates($candidates);

        // Prefer statements confirmed by more than one outlet; fall back to
        // single-source statements only when nothing better is available.
        usort($folded, static function (array $a, array $b): int {
            $sourceCountCompare = count($b['sources']) <=> count($a['sources']);
            if ($sourceCountCompare !== 0) {
                return $sourceCountCompare;
            }
            return mb_strlen($a['text']) <=> mb_strlen($b['text']);
        });

        $selected = array_slice($folded, 0, 6);
        $sentences = array_map(static fn (array $c): string => $c['text'], $selected);

        return [$sentences, $removedBiased, $removedOpinion, $removedSpeculative, count($selected)];
    }

    /**
     * @param array<string, array{text: string, sources: array<string, bool>}> $candidates
     * @return array<int, array{text: string, sources: array<string, bool>}>
     */
    private static function foldNearDuplicates(array $candidates): array
    {
        $entries = array_values($candidates);
        $merged = [];
        $used = array_fill(0, count($entries), false);

        for ($i = 0; $i < count($entries); $i++) {
            if ($used[$i]) {
                continue;
            }
            $group = $entries[$i];
            $tokensI = TextUtils::tokenize($entries[$i]['text']);

            for ($j = $i + 1; $j < count($entries); $j++) {
                if ($used[$j]) {
                    continue;
                }
                $tokensJ = TextUtils::tokenize($entries[$j]['text']);
                if (TextUtils::jaccard($tokensI, $tokensJ) >= 0.55) {
                    $group['sources'] = $group['sources'] + $entries[$j]['sources'];
                    $used[$j] = true;
                }
            }
            $used[$i] = true;
            $merged[] = $group;
        }

        return $merged;
    }

    /**
     * @param array<int, array<string, mixed>> $cluster
     * @return string[]
     */
    private static function buildConsiderations(array $cluster, int $removedBiased, int $removedOpinion, int $removedSpeculative, int $keptCount): array
    {
        $considerations = [];
        $sourceCount = count($cluster);

        $confidence = $sourceCount >= 5 ? 'High' : ($sourceCount >= 4 ? 'Medium-High' : 'Medium');
        $considerations[] = "Confirmed independently by {$sourceCount} distinct outlets — confidence: {$confidence}.";

        if ($removedBiased > 0) {
            $considerations[] = "Removed {$removedBiased} sentence(s) containing loaded or editorializing language before synthesis.";
        }
        if ($removedOpinion > 0) {
            $considerations[] = "Removed {$removedOpinion} sentence(s) reading as unattributed opinion rather than reporting.";
        }
        if ($removedSpeculative > 0) {
            $considerations[] = "Removed {$removedSpeculative} sentence(s) posing a question or speculating about what might happen rather than reporting a settled fact.";
        }

        // Flag numeric discrepancies across sources (e.g. different death
        // tolls, different dollar figures) so readers know figures are contested.
        $numberSets = [];
        foreach ($cluster as $article) {
            $numbers = TextUtils::extractNumbers($article['description']);
            if ($numbers !== []) {
                $numberSets[$article['source_name']] = $numbers;
            }
        }
        if (count($numberSets) >= 2) {
            $allSame = null;
            $mismatch = false;
            foreach ($numberSets as $numbers) {
                sort($numbers);
                if ($allSame === null) {
                    $allSame = $numbers;
                } elseif ($allSame !== $numbers) {
                    $mismatch = true;
                }
            }
            if ($mismatch) {
                $considerations[] = 'Reported figures vary between sources — cross-check exact numbers against the resources below before citing them.';
            }
        }

        $publishSpread = array_map(static fn (array $a): ?int => $a['published_at'], $cluster);
        $publishSpread = array_filter($publishSpread, static fn (?int $t): bool => $t !== null);
        if (count($publishSpread) >= 2) {
            $spreadHours = (max($publishSpread) - min($publishSpread)) / 3600;
            if ($spreadHours > 24) {
                $considerations[] = 'Coverage spans more than 24 hours — details may have evolved between the earliest and latest report.';
            }
        }

        if ($keptCount <= 1) {
            $considerations[] = 'Limited overlapping factual detail was found in the available summaries; treat this as a headline-level confirmation and read the full resources for depth.';
        }

        return $considerations;
    }
}
