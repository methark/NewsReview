<?php

declare(strict_types=1);

/**
 * Fetches every configured RSS/Atom feed in parallel (curl_multi) and
 * normalizes entries into a flat article list. Runs fresh on every request —
 * no caching layer, by design.
 */
final class FeedFetcher
{
    /**
     * @param array<int, array{name: string, homepage: string, feed: string}> $sources
     * @return array{articles: array<int, array<string, mixed>>, failed: string[]}
     */
    public static function fetchAll(array $sources, int $connectTimeout, int $timeout): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($sources as $source) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $source['feed'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'NewsReviewFactCheckBot/1.0 (+https://example.local)',
                CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml, */*'],
            ]);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[(int) $ch] = ['handle' => $ch, 'source' => $source];
        }

        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $articles = [];
        $failed = [];

        foreach ($handles as $entry) {
            $ch = $entry['handle'];
            $source = $entry['source'];
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $body = curl_multi_getcontent($ch);

            if ($body === null || $body === '' || $error !== '' || $httpCode >= 400) {
                $failed[] = $source['name'] . ($error !== '' ? " ({$error})" : " (HTTP {$httpCode})");
                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
                continue;
            }

            $parsed = self::parseFeed($body, $source);
            if ($parsed === []) {
                $failed[] = $source['name'] . ' (unparsable feed)';
            } else {
                array_push($articles, ...$parsed);
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        return ['articles' => $articles, 'failed' => $failed];
    }

    /**
     * @param array{name: string, homepage: string, feed: string} $source
     * @return array<int, array<string, mixed>>
     */
    private static function parseFeed(string $body, array $source): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $items = [];

        // RSS 2.0 / RDF: channel/item
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = self::normalizeRssItem($item, $source);
            }
        } elseif (isset($xml->item)) {
            foreach ($xml->item as $item) {
                $items[] = self::normalizeRssItem($item, $source);
            }
        } elseif (isset($xml->entry)) {
            // Atom
            foreach ($xml->entry as $entry) {
                $items[] = self::normalizeAtomEntry($entry, $source);
            }
        }

        return array_values(array_filter($items, static fn (?array $a): bool => $a !== null && $a['title'] !== ''));
    }

    /**
     * @param array{name: string, homepage: string, feed: string} $source
     * @return array<string, mixed>|null
     */
    private static function normalizeRssItem(\SimpleXMLElement $item, array $source): ?array
    {
        $title = TextUtils::stripHtml((string) $item->title);
        $link = trim((string) $item->link);
        $description = TextUtils::stripHtml((string) ($item->description ?? $item->summary ?? ''));

        $dc = $item->children('http://purl.org/dc/elements/1.1/');
        $dateRaw = (string) ($item->pubDate ?? $dc->date ?? '');
        $timestamp = $dateRaw !== '' ? strtotime($dateRaw) : false;

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'link' => $link,
            'description' => $description,
            'source_name' => $source['name'],
            'source_homepage' => $source['homepage'],
            'published_at' => $timestamp !== false ? $timestamp : null,
        ];
    }

    /**
     * @param array{name: string, homepage: string, feed: string} $source
     * @return array<string, mixed>|null
     */
    private static function normalizeAtomEntry(\SimpleXMLElement $entry, array $source): ?array
    {
        $title = TextUtils::stripHtml((string) $entry->title);
        $link = '';
        if (isset($entry->link)) {
            foreach ($entry->link as $l) {
                $attrs = $l->attributes();
                $rel = (string) ($attrs['rel'] ?? '');
                if ($rel === '' || $rel === 'alternate') {
                    $link = (string) ($attrs['href'] ?? '');
                    break;
                }
            }
        }
        $description = TextUtils::stripHtml((string) ($entry->summary ?? $entry->content ?? ''));
        $dateRaw = (string) ($entry->updated ?? $entry->published ?? '');
        $timestamp = $dateRaw !== '' ? strtotime($dateRaw) : false;

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'link' => $link,
            'description' => $description,
            'source_name' => $source['name'],
            'source_homepage' => $source['homepage'],
            'published_at' => $timestamp !== false ? $timestamp : null,
        ];
    }
}
