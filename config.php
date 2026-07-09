<?php

declare(strict_types=1);

/**
 * Independent news sources used for cross-checking.
 * Deliberately spans different owners/countries so that agreement between
 * them is a meaningful signal rather than several outlets echoing one wire
 * feed. Every source is tagged with a 'category' — one of 'world',
 * 'science', or 'finance' — matching the topics this dashboard covers.
 * Sports and celebrity/gossip outlets are never added here; a keyword
 * filter (see TopicFilter) additionally strips that content out even when
 * it slips into an otherwise on-topic "World" feed.
 */
return [
    'sources' => [
        // --- World ---
        ['name' => 'BBC News',      'homepage' => 'https://www.bbc.com/news',        'feed' => 'https://feeds.bbci.co.uk/news/world/rss.xml', 'category' => 'world'],
        ['name' => 'The Guardian',  'homepage' => 'https://www.theguardian.com',      'feed' => 'https://www.theguardian.com/world/rss', 'category' => 'world'],
        ['name' => 'Al Jazeera',    'homepage' => 'https://www.aljazeera.com',        'feed' => 'https://www.aljazeera.com/xml/rss/all.xml', 'category' => 'world'],
        ['name' => 'NPR',           'homepage' => 'https://www.npr.org',              'feed' => 'https://feeds.npr.org/1004/rss.xml', 'category' => 'world'],
        ['name' => 'Sky News',      'homepage' => 'https://news.sky.com',             'feed' => 'https://feeds.skynews.com/feeds/rss/world.xml', 'category' => 'world'],
        ['name' => 'Deutsche Welle','homepage' => 'https://www.dw.com',               'feed' => 'https://rss.dw.com/rdf/rss-en-all', 'category' => 'world'],
        ['name' => 'CBS News',      'homepage' => 'https://www.cbsnews.com',          'feed' => 'https://www.cbsnews.com/latest/rss/world', 'category' => 'world'],
        ['name' => 'ABC News (AU)', 'homepage' => 'https://www.abc.net.au/news',      'feed' => 'https://www.abc.net.au/news/feed/51120/rss.xml', 'category' => 'world'],
        ['name' => 'Le Monde (EN)', 'homepage' => 'https://www.lemonde.fr/en',        'feed' => 'https://www.lemonde.fr/en/rss/une.xml', 'category' => 'world'],
        ['name' => 'CNN',           'homepage' => 'https://www.cnn.com',              'feed' => 'http://rss.cnn.com/rss/edition_world.rss', 'category' => 'world'],
        ['name' => 'The New York Times', 'homepage' => 'https://www.nytimes.com',    'feed' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml', 'category' => 'world'],
        ['name' => 'Fox News',      'homepage' => 'https://www.foxnews.com',         'feed' => 'https://moxie.foxnews.com/google-publisher/world.xml', 'category' => 'world'],
        ['name' => 'PBS NewsHour',  'homepage' => 'https://www.pbs.org/newshour',    'feed' => 'https://www.pbs.org/newshour/feeds/rss/headlines', 'category' => 'world'],
        ['name' => 'The Independent', 'homepage' => 'https://www.independent.co.uk', 'feed' => 'https://www.independent.co.uk/news/world/rss', 'category' => 'world'],
        ['name' => 'Euronews',      'homepage' => 'https://www.euronews.com',        'feed' => 'https://www.euronews.com/rss?level=theme&name=news', 'category' => 'world'],
        ['name' => 'France24 (EN)', 'homepage' => 'https://www.france24.com/en',     'feed' => 'https://www.france24.com/en/rss', 'category' => 'world'],
        ['name' => 'Der Spiegel Intl', 'homepage' => 'https://www.spiegel.de/international', 'feed' => 'https://www.spiegel.de/international/index.rss', 'category' => 'world'],
        ['name' => 'CBC News',      'homepage' => 'https://www.cbc.ca/news',         'feed' => 'https://www.cbc.ca/cmlink/rss-world', 'category' => 'world'],
        ['name' => 'Global News',   'homepage' => 'https://globalnews.ca',           'feed' => 'https://globalnews.ca/world/feed/', 'category' => 'world'],
        ['name' => 'The Japan Times', 'homepage' => 'https://www.japantimes.co.jp',  'feed' => 'https://www.japantimes.co.jp/feed/', 'category' => 'world'],
        ['name' => 'Straits Times', 'homepage' => 'https://www.straitstimes.com',    'feed' => 'https://www.straitstimes.com/news/world/rss.xml', 'category' => 'world'],
        ['name' => 'Times of India', 'homepage' => 'https://timesofindia.indiatimes.com', 'feed' => 'https://timesofindia.indiatimes.com/rssfeedstopstories.cms', 'category' => 'world'],
        ['name' => 'NBC News',       'homepage' => 'https://www.nbcnews.com',          'feed' => 'https://feeds.nbcnews.com/nbcnews/public/news', 'category' => 'world'],
        ['name' => 'The Hill',       'homepage' => 'https://thehill.com',              'feed' => 'https://thehill.com/homenews/feed', 'category' => 'world'],
        ['name' => 'Politico',       'homepage' => 'https://www.politico.com',         'feed' => 'https://www.politico.com/rss/politicopicks.xml', 'category' => 'world'],
        ['name' => 'The Atlantic',   'homepage' => 'https://www.theatlantic.com',      'feed' => 'https://www.theatlantic.com/feed/all/', 'category' => 'world'],
        ['name' => 'South China Morning Post', 'homepage' => 'https://www.scmp.com',   'feed' => 'https://www.scmp.com/rss/91/feed', 'category' => 'world'],
        ['name' => 'The Times of Israel', 'homepage' => 'https://www.timesofisrael.com', 'feed' => 'https://www.timesofisrael.com/feed/', 'category' => 'world'],

        // --- Science ---
        ['name' => 'BBC Science',   'homepage' => 'https://www.bbc.com/news/science_and_environment', 'feed' => 'http://feeds.bbci.co.uk/news/science_and_environment/rss.xml', 'category' => 'science'],
        ['name' => 'The Guardian Science', 'homepage' => 'https://www.theguardian.com/science', 'feed' => 'https://www.theguardian.com/science/rss', 'category' => 'science'],
        ['name' => 'NYT Science',   'homepage' => 'https://www.nytimes.com/section/science', 'feed' => 'https://rss.nytimes.com/services/xml/rss/nyt/Science.xml', 'category' => 'science'],
        ['name' => 'Fox News Science', 'homepage' => 'https://www.foxnews.com/science', 'feed' => 'https://moxie.foxnews.com/google-publisher/science.xml', 'category' => 'science'],
        ['name' => 'Scientific American', 'homepage' => 'https://www.scientificamerican.com', 'feed' => 'https://www.scientificamerican.com/platform/syndication/rss/', 'category' => 'science'],
        ['name' => 'NBC News Science', 'homepage' => 'https://www.nbcnews.com/science', 'feed' => 'https://feeds.nbcnews.com/nbcnews/public/science', 'category' => 'science'],

        // --- Finance ---
        ['name' => 'BBC Business',  'homepage' => 'https://www.bbc.com/news/business', 'feed' => 'http://feeds.bbci.co.uk/news/business/rss.xml', 'category' => 'finance'],
        ['name' => 'The Guardian Business', 'homepage' => 'https://www.theguardian.com/business', 'feed' => 'https://www.theguardian.com/business/rss', 'category' => 'finance'],
        ['name' => 'NYT Business',  'homepage' => 'https://www.nytimes.com/section/business', 'feed' => 'https://rss.nytimes.com/services/xml/rss/nyt/Business.xml', 'category' => 'finance'],
        ['name' => 'CNBC',          'homepage' => 'https://www.cnbc.com',             'feed' => 'https://www.cnbc.com/id/100727362/device/rss/rss.html', 'category' => 'finance'],
        ['name' => 'Business Insider', 'homepage' => 'https://www.businessinsider.com', 'feed' => 'https://www.businessinsider.com/rss', 'category' => 'finance'],
        ['name' => 'Fox Business',  'homepage' => 'https://www.foxbusiness.com',      'feed' => 'https://moxie.foxnews.com/google-publisher/business.xml', 'category' => 'finance'],

        // Al Arabiya (EN) deliberately omitted: its bot detection keeps
        // returning HTTP 403 even with a standard browser user-agent,
        // which points to something beyond simple UA filtering (a JS
        // challenge or TLS fingerprinting) that a plain HTTP client can't
        // satisfy — not worth the added complexity to chase.
        //
        // Feeds above haven't been live-verified from this dev environment
        // (its network policy blocks outbound requests to arbitrary news
        // domains) — if any show up under "Unreachable this run"
        // consistently, treat that the same as Reuters/USA Today earlier:
        // check the URL still exists, or just remove the entry.
    ],

    // Which categories are fetched when the page loads with no ?cat[]
    // selection at all (a fresh visit, not a filtered one).
    'default_categories' => ['world', 'science', 'finance'],

    // Network behaviour: page must render fresh every visit, so keep fetches
    // reasonably short. Connections are capped at 6 concurrent (see
    // FeedFetcher), so with 40 sources most feeds queue behind others and
    // don't even start until an earlier one finishes — give real (if slow)
    // connections enough room to complete once their turn comes, rather
    // than timing out just from being queued behind 30+ other sources.
    'fetch_timeout_seconds'       => 15,
    'fetch_connect_timeout_seconds' => 8,

    // Only consider articles published within this window, so "today's" cross-checks
    // aren't polluted by an old story resurfacing on one outlet.
    'max_article_age_hours' => 48,

    // When a search query is active, look back further than the default
    // dashboard window — a deliberate search should be able to reach a
    // slightly older story that outlets have moved off their front page,
    // not just what's freshest right now.
    'search_max_article_age_hours' => 168,

    // Clustering: minimum blended token-overlap score (Jaccard + overlap
    // coefficient, averaged) for two articles from different outlets to be
    // treated as the same story.
    'similarity_threshold' => 0.30,

    // A "fact-checked" story requires independent confirmation from at least
    // this many distinct outlets.
    'min_sources_required' => 3,

    // Cap on how many validated stories to compute/render per run (all are
    // rendered server-side; the page reveals them progressively as the user
    // scrolls — see 'stories_per_batch').
    'max_stories_shown' => 40,

    // How many story cards are visible initially, and how many more are
    // revealed each time the user scrolls near the bottom of the list.
    'stories_per_batch' => 10,
];
