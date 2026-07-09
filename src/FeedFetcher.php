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
        $firstPass = self::runBatch($sources, $connectTimeout, $timeout);

        $permanentFailures = array_values(array_filter($firstPass['failures'], static fn (array $f): bool => !$f['transient']));
        $transientFailures = array_values(array_filter($firstPass['failures'], static fn (array $f): bool => $f['transient']));

        $articles = $firstPass['articles'];
        $finalFailures = $permanentFailures;

        // A 2xx status with an empty body (e.g. HTTP 202 "accepted") usually
        // means a CDN edge was mid-refresh when we hit it, not a real block —
        // unlike a persistent 403/404, it's often gone on the very next
        // request. Give those a single immediate retry before giving up;
        // whatever the retry reports (success or failure) is final.
        if ($transientFailures !== []) {
            $retrySources = array_map(static fn (array $f): array => $f['source'], $transientFailures);
            $retryPass = self::runBatch($retrySources, $connectTimeout, $timeout);
            array_push($articles, ...$retryPass['articles']);
            array_push($finalFailures, ...$retryPass['failures']);
        }

        $failed = array_map(static fn (array $f): string => "{$f['source']['name']} ({$f['reason']})", $finalFailures);

        return ['articles' => $articles, 'failed' => $failed];
    }

    /**
     * Runs one parallel fetch+parse pass over the given sources.
     *
     * @param array<int, array{name: string, homepage: string, feed: string}> $sources
     * @return array{articles: array<int, array<string, mixed>>, failures: array<int, array{source: array{name: string, homepage: string, feed: string}, reason: string, transient: bool}>}
     */
    private static function runBatch(array $sources, int $connectTimeout, int $timeout): array
    {
        $multiHandle = curl_multi_init();

        // Cap how many transfers run concurrently. Firing every configured
        // source at once (one connection each) is easy to trip up on a
        // typical desktop/XAMPP box — Windows Firewall, antivirus, or just
        // the local network stack can throttle a burst of simultaneous
        // outbound HTTPS connections, which shows up as otherwise-healthy
        // feeds failing with an empty curl error and HTTP code 0. Curl
        // queues the rest internally and starts them as slots free up.
        curl_multi_setopt($multiHandle, CURLMOPT_MAXCONNECTS, 6);

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
                // A custom bot user-agent gets blanket-rejected by some
                // outlets' bot detection (seen with Al Arabiya returning
                // HTTP 403) even though we're only fetching a public RSS
                // feed. A standard browser UA avoids that false-positive.
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
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
        $failures = [];

        foreach ($handles as $entry) {
            $ch = $entry['handle'];
            $source = $entry['source'];
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $body = curl_multi_getcontent($ch);

            if ($body === null || $body === '' || $error !== '' || $httpCode >= 400) {
                $isEmptyBodyWith2xx = $error === '' && $httpCode >= 200 && $httpCode < 300;
                $reason = match (true) {
                    $error !== '' => $error,
                    $httpCode > 0 => "HTTP {$httpCode}",
                    // curl_error() text is sometimes empty on Windows even
                    // when curl_errno() has a real code (seen with the
                    // Schannel SSL backend some XAMPP/Windows PHP builds
                    // use) — surface the numeric code so a recurring failure
                    // is diagnosable instead of a bare "timed out".
                    $errno !== 0 => "connection failed, curl error {$errno}",
                    default => 'connection failed or timed out',
                };
                $failures[] = ['source' => $source, 'reason' => $reason, 'transient' => $isEmptyBodyWith2xx];
                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
                continue;
            }

            $parsed = self::parseFeed($body, $source);
            if ($parsed === []) {
                $failures[] = ['source' => $source, 'reason' => 'unparsable feed', 'transient' => false];
            } else {
                array_push($articles, ...$parsed);
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        return ['articles' => $articles, 'failures' => $failures];
    }

    /**
     * @param array{name: string, homepage: string, feed: string} $source
     * @return array<int, array<string, mixed>>
     */
    private static function parseFeed(string $body, array $source): array
    {
        $previous = libxml_use_internal_errors(true);

        // Real-world feeds are frequently not quite well-formed (unescaped
        // "&", stray control characters, mismatched encoding declarations).
        // Try a strict parse first, then fall back to libxml's recovery mode
        // rather than discarding the whole feed over a minor validity issue.
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA);
        if ($xml === false) {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_RECOVER | LIBXML_PARSEHUGE);
        }

        libxml_clear_errors();
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
