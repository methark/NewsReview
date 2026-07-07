<?php

declare(strict_types=1);

/**
 * Independent news sources used for cross-checking.
 * Deliberately spans different owners/countries so that agreement between
 * them is a meaningful signal rather than several outlets echoing one wire feed.
 */
return [
    'sources' => [
        ['name' => 'BBC News',      'homepage' => 'https://www.bbc.com/news',        'feed' => 'https://feeds.bbci.co.uk/news/world/rss.xml'],
        ['name' => 'The Guardian',  'homepage' => 'https://www.theguardian.com',      'feed' => 'https://www.theguardian.com/world/rss'],
        ['name' => 'Al Jazeera',    'homepage' => 'https://www.aljazeera.com',        'feed' => 'https://www.aljazeera.com/xml/rss/all.xml'],
        ['name' => 'NPR',           'homepage' => 'https://www.npr.org',              'feed' => 'https://feeds.npr.org/1004/rss.xml'],
        ['name' => 'Sky News',      'homepage' => 'https://news.sky.com',             'feed' => 'https://feeds.skynews.com/feeds/rss/world.xml'],
        ['name' => 'Deutsche Welle','homepage' => 'https://www.dw.com',               'feed' => 'https://rss.dw.com/rdf/rss-en-all'],
        ['name' => 'CBS News',      'homepage' => 'https://www.cbsnews.com',          'feed' => 'https://www.cbsnews.com/latest/rss/world'],
        ['name' => 'ABC News (AU)', 'homepage' => 'https://www.abc.net.au/news',      'feed' => 'https://www.abc.net.au/news/feed/51120/rss.xml'],
        ['name' => 'Reuters (via feed)', 'homepage' => 'https://www.reuters.com',     'feed' => 'https://www.reutersagency.com/feed/?best-topics=top-news&post_type=best'],
        ['name' => 'Le Monde (EN)', 'homepage' => 'https://www.lemonde.fr/en',        'feed' => 'https://www.lemonde.fr/en/rss/une.xml'],
    ],

    // Network behaviour: page must render fresh every visit, so keep fetches short.
    'fetch_timeout_seconds'       => 6,
    'fetch_connect_timeout_seconds' => 4,

    // Only consider articles published within this window, so "today's" cross-checks
    // aren't polluted by an old story resurfacing on one outlet.
    'max_article_age_hours' => 48,

    // Clustering: minimum token-overlap (Jaccard similarity) for two headlines
    // from different outlets to be treated as the same story.
    'similarity_threshold' => 0.30,

    // A "fact-checked" story requires independent confirmation from at least
    // this many distinct outlets.
    'min_sources_required' => 3,

    // Cap on how many validated stories to display.
    'max_stories_shown' => 20,
];
