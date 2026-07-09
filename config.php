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
        ['name' => 'Le Monde (EN)', 'homepage' => 'https://www.lemonde.fr/en',        'feed' => 'https://www.lemonde.fr/en/rss/une.xml'],
        ['name' => 'CNN',           'homepage' => 'https://www.cnn.com',              'feed' => 'http://rss.cnn.com/rss/edition_world.rss'],
        ['name' => 'The New York Times', 'homepage' => 'https://www.nytimes.com',    'feed' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml'],
        ['name' => 'Fox News',      'homepage' => 'https://www.foxnews.com',         'feed' => 'https://moxie.foxnews.com/google-publisher/world.xml'],
        ['name' => 'PBS NewsHour',  'homepage' => 'https://www.pbs.org/newshour',    'feed' => 'https://www.pbs.org/newshour/feeds/rss/headlines'],
        ['name' => 'The Independent', 'homepage' => 'https://www.independent.co.uk', 'feed' => 'https://www.independent.co.uk/news/world/rss'],
        ['name' => 'Euronews',      'homepage' => 'https://www.euronews.com',        'feed' => 'https://www.euronews.com/rss?level=theme&name=news'],
        ['name' => 'France24 (EN)', 'homepage' => 'https://www.france24.com/en',     'feed' => 'https://www.france24.com/en/rss'],
        ['name' => 'Der Spiegel Intl', 'homepage' => 'https://www.spiegel.de/international', 'feed' => 'https://www.spiegel.de/international/index.rss'],
        ['name' => 'CBC News',      'homepage' => 'https://www.cbc.ca/news',         'feed' => 'https://www.cbc.ca/cmlink/rss-world'],
        ['name' => 'Global News',   'homepage' => 'https://globalnews.ca',           'feed' => 'https://globalnews.ca/world/feed/'],
        ['name' => 'The Japan Times', 'homepage' => 'https://www.japantimes.co.jp',  'feed' => 'https://www.japantimes.co.jp/feed/'],
        ['name' => 'Straits Times', 'homepage' => 'https://www.straitstimes.com',    'feed' => 'https://www.straitstimes.com/news/world/rss.xml'],
        ['name' => 'Times of India', 'homepage' => 'https://timesofindia.indiatimes.com', 'feed' => 'https://timesofindia.indiatimes.com/rssfeedstopstories.cms'],
        ['name' => 'Al Arabiya (EN)', 'homepage' => 'https://english.alarabiya.net', 'feed' => 'https://english.alarabiya.net/.mrss/en.xml'],
    ],

    // Network behaviour: page must render fresh every visit, so keep fetches
    // reasonably short. Connections are capped at 6 concurrent (see
    // FeedFetcher), so with 20+ sources some feeds queue behind others —
    // give slightly more headroom than a single-burst fetch would need.
    'fetch_timeout_seconds'       => 10,
    'fetch_connect_timeout_seconds' => 6,

    // Only consider articles published within this window, so "today's" cross-checks
    // aren't polluted by an old story resurfacing on one outlet.
    'max_article_age_hours' => 48,

    // Clustering: minimum blended token-overlap score (Jaccard + overlap
    // coefficient, averaged) for two articles from different outlets to be
    // treated as the same story.
    'similarity_threshold' => 0.30,

    // A "fact-checked" story requires independent confirmation from at least
    // this many distinct outlets.
    'min_sources_required' => 3,

    // Cap on how many validated stories to display.
    'max_stories_shown' => 20,
];
