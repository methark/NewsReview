<?php

declare(strict_types=1);

/**
 * Small text-processing helpers shared by the clusterer and fact filter.
 * No external NLP dependency — everything here is a plain heuristic.
 */
final class TextUtils
{
    /** @var string[] */
    private static array $stopwords = [
        'a','an','the','and','or','but','if','then','than','so','of','in','on','at','to','for',
        'with','from','by','as','is','are','was','were','be','been','being','it','its','this',
        'that','these','those','has','have','had','will','would','could','should','can','may',
        'might','must','not','no','do','does','did','doing','about','into','over','after','before',
        'between','out','up','down','off','again','further','once','here','there','when','while',
        'both','each','few','more','most','other','some','such','only','own','same','too','very',
        'just','also','said','says','say','told','new','amid','amidst','per','via', 'we', 'he',
        'she', 'they', 'you', 'i', 'his', 'her', 'their', 'our', 'your', 'who', 'what', 'which',
    ];

    /** @var string[] words/phrases that signal editorializing rather than reporting */
    public static array $biasLexicon = [
        'slams', 'slammed', 'blasts', 'blasted', 'shocking', 'shockingly', 'outrageous',
        'outraged', 'brutal', 'brutally', 'devastating', 'explosive', 'bombshell', 'insane',
        'terrifying', 'heartbreaking', 'furious', 'savage', 'epic', 'unbelievable', 'stunning',
        'jaw-dropping', 'destroys', 'annihilates', 'obliterates', 'humiliates', 'humiliating',
        'erupts', 'meltdown', 'chaos', 'disaster', 'catastrophic', 'brilliant', 'genius',
        'disgraceful', 'disgusting', 'shameful', 'shameless', 'evil', 'monstrous', 'unhinged',
        'you won\'t believe', 'brave', 'heroic', 'cowardly', 'radical left', 'radical right',
        'fake news', 'mainstream media', 'deep state', 'crooked', 'corrupt regime',
        'so-called', 'notorious', 'infamous', 'slap in the face', 'slaps', 'blast at',
    ];

    /** @var string[] hedging verbs that, unattributed, signal opinion rather than fact */
    public static array $opinionMarkers = [
        'i think', 'i believe', 'many believe', 'some say', 'critics say', 'it seems',
        'arguably', 'one could argue', 'in my view', 'clearly', 'obviously', 'undoubtedly',
        'everyone knows', 'it is obvious', 'without a doubt',
    ];

    public static function stripHtml(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * @return string[] lowercase, stopword-free tokens
     */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower(self::stripHtml($text));
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text) ?? $text;
        $tokens = preg_split('/\s+/u', trim($text)) ?: [];
        $tokens = array_filter($tokens, static fn (string $t): bool => mb_strlen($t) >= 3 && !in_array($t, self::$stopwords, true));
        return array_values($tokens);
    }

    /**
     * Jaccard similarity between two token sets (0..1).
     *
     * @param string[] $a
     * @param string[] $b
     */
    public static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $setA = array_unique($a);
        $setB = array_unique($b);
        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));
        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Split free text into trimmed, non-empty sentences.
     *
     * @return string[]
     */
    public static function splitSentences(string $text): array
    {
        $text = self::stripHtml($text);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"\'])/u', $text) ?: [$text];
        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, static fn (string $s): bool => $s !== ''));
    }

    /** True if the sentence contains loaded/editorializing language. */
    public static function isBiased(string $sentence): bool
    {
        $lower = mb_strtolower($sentence);
        foreach (self::$biasLexicon as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }
        return false;
    }

    /** True if the sentence reads as unattributed opinion rather than reporting. */
    public static function isOpinion(string $sentence): bool
    {
        $lower = mb_strtolower($sentence);
        foreach (self::$opinionMarkers as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pull out standalone numbers (figures, percentages, counts) for
     * cross-source consistency checks.
     *
     * @return string[]
     */
    public static function extractNumbers(string $text): array
    {
        preg_match_all('/\b\d[\d,\.]*%?\b/u', self::stripHtml($text), $matches);
        return $matches[0] ?? [];
    }

    /** Normalize a sentence for de-duplication comparisons. */
    public static function normalizeForDedup(string $sentence): string
    {
        $s = mb_strtolower($sentence);
        $s = preg_replace('/[^a-z0-9\s]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }
}
