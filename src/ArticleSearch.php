<?php

declare(strict_types=1);

/**
 * Filters the raw fetched-article pool down to what matches a user's search
 * query, before clustering/cross-checking runs. Search only reaches
 * whatever was fetched this run — there's no historical index — so a query
 * narrows which live articles are considered, not a stored archive.
 */
final class ArticleSearch
{
    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<string, mixed>>
     */
    public static function filter(array $articles, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $articles;
        }

        $queryTokens = TextUtils::tokenize($query);

        // Short queries (e.g. "AI", "UN") tokenize to nothing, since
        // TextUtils::tokenize drops anything under 3 characters. Fall back
        // to a plain case-insensitive substring match for those instead of
        // matching everything.
        if ($queryTokens === []) {
            $needle = mb_strtolower($query);
            return array_values(array_filter($articles, static function (array $article) use ($needle): bool {
                $haystack = mb_strtolower($article['title'] . ' ' . $article['description']);
                return str_contains($haystack, $needle);
            }));
        }

        return array_values(array_filter($articles, static function (array $article) use ($queryTokens): bool {
            $articleTokens = TextUtils::tokenize($article['title'] . ' ' . $article['description']);
            foreach ($queryTokens as $token) {
                if (!in_array($token, $articleTokens, true)) {
                    return false;
                }
            }
            return true;
        }));
    }
}
