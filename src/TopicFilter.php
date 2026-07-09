<?php

declare(strict_types=1);

/**
 * Keyword-based content exclusion for sports and celebrity/gossip material.
 * The source list is deliberately Science/World/Finance outlets and
 * sections, but a general "World" or "top stories" feed occasionally mixes
 * in a viral sports or celebrity story anyway — this is the safety net
 * that catches those regardless of which feed they came from.
 */
final class TopicFilter
{
    /** @var string[] */
    private static array $sportsTerms = [
        'championship', 'tournament', 'playoffs', 'quarterfinal', 'semifinal', 'clinches',
        'touchdown', 'home run', 'penalty kick', 'red card', 'yellow card', 'relegation',
        'premier league', 'champions league', 'world cup', 'super bowl', 'world series',
        'stanley cup', 'grand slam', 'olympics', 'olympic', 'medal count', 'nba', 'nfl', 'nhl',
        'mlb', 'fifa', 'uefa', 'coach says', 'head coach', 'transfer window', 'draft pick',
        'scored twice', 'hat-trick', 'hat trick', 'clean sheet', 'batting average', 'goal in the',
        'wins the match', 'wins the game', 'beat them', 'defeated them', 'season opener',
    ];

    /** @var string[] */
    private static array $gossipTerms = [
        'red carpet', 'reality star', 'reality tv', 'engaged to', 'got engaged', 'announces divorce',
        'files for divorce', 'spotted with', 'spotted together', 'dating rumors', 'dating rumours',
        'breakup', 'broke up with', 'split from', 'romance rumors', 'love life', 'baby bump',
        'celebrity couple', 'a-lister', 'paparazzi', 'instagram post', 'social media post sparks',
        'fans react', 'went viral after', 'royal wedding', 'tabloid', 'gossip', 'love island',
        'kardashian', 'bachelor nation', 'season finale of', 'reality show contestant',
    ];

    public static function isSportsOrGossip(string $text): bool
    {
        $lower = mb_strtolower($text);
        // Word-boundary matching, not plain substring: short acronyms like
        // "nfl" or "nba" otherwise false-positive inside ordinary words
        // ("inflation" contains "nfl").
        foreach (array_merge(self::$sportsTerms, self::$gossipTerms) as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', $lower) === 1) {
                return true;
            }
        }
        return false;
    }
}
