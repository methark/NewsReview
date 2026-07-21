# NewsReview

A fact-checked news dashboard. On every page visit it fetches live from
several independent news outlets, groups articles that report the same
story, keeps only the stories confirmed by at least 3 distinct outlets,
strips biased/opinion language, and shows each surviving story as a card
with:

- **Title**
- **Date/Time**
- **The Filtered Article** — a bias-stripped, fact-only synthesis
- **Considerations** — confidence level, discrepancies between sources, what was filtered out
- **Resources** — the outlets used to cross-check the story, with links

Cross-checking, filtering, and search run fresh on every request. The
underlying live fetch across 30-40 outlets is the one expensive step, so
it's cached to a local file for up to an hour (`cache_ttl_seconds`) rather
than re-run on every single page visit — a search or a category-checkbox
change reads from that cache instead of re-fetching everything live. Set
`cache_enabled` to `false` in `config.php` to go back to a live fetch on
every visit. Run `refresh.php` (CLI, browser, or a scheduled task — see
that file for a Windows Task Scheduler example) to force or schedule a
refresh independent of any page visit, so the cache stays warm and no
visitor has to wait through a live fetch themselves.

A search box at the top filters the current article pool (cached or
freshly fetched) by query before clustering runs, so results still go
through the same 3-source validation — there's no historical archive to
search beyond whatever's currently cached (searching looks back further
than the default dashboard view within that pool, since a deliberate
search should reach slightly older stories outlets have moved off their
front page — see `search_max_article_age_hours`). Matched terms are
highlighted in results. Only the first 10 validated stories are shown
initially; a "Load more" button (and auto-reveal on scroll) shows more —
already computed server-side, just progressively unhidden.

Cross-checking isn't limited to headlines using near-identical wording:
articles are also matched when they share a specific proper-noun phrase
(a full org or person name) even if the rest of the wording is
unrelated, since news outlets often describe the same story very
differently while still naming the same people, places, or organizations.

Coverage is scoped to World, Science, and Finance — checkboxes at the top
let you narrow to just one or two. Sports and celebrity/gossip content is
never included: no such outlets are configured, and a keyword filter
additionally strips that content out even if it slips into an otherwise
on-topic "World" feed. Articles phrased as a question or speculation
("Will NATO get involved in securing the Strait of Hormuz?") are excluded
entirely — a question about what might happen isn't a fact, even when it
accurately summarizes what a real news piece discusses — and the same
check strips individual speculative sentences out of an otherwise-factual
article's body.

## Two frontends, one backend

- **`index.php`** — the full server-rendered dashboard described above.
- **`revue.html`** ("The News Revue") — a single static HTML/CSS/JS file
  implementing the same Trinary Method (a story only ever shows once at
  least 3 high-fidelity sources confirm it) with a "Search the Web" field,
  talking to a JSON endpoint (**`api.php`**) instead of rendering HTML
  server-side. Both frontends and the JSON endpoint run the *exact same*
  pipeline (`src/NewsPipeline.php`) — same sources, same cache, same
  3-source validation, same bias/speculation filtering — just different
  presentations of it, so results can never drift between them.

  A browser can't fetch arbitrary news sites directly (cross-origin
  restrictions block it), and a real web-search API needs a paid/keyed
  service — so `revue.html` isn't a fully standalone file with no backend;
  it needs `api.php` served alongside it on the same PHP server. Opening
  `revue.html` directly as a local file (or hosting it somewhere without
  the PHP backend) will show a clear "couldn't reach the backend" message
  rather than silently failing.

- **`revue-standalone.html`** — an experimental, genuinely no-backend
  edition: the entire fetch/cluster/fact-check pipeline above is
  reimplemented in plain JavaScript, running only in the browser (no PHP
  at all). Since browsers can't fetch cross-origin RSS feeds any more
  than they can fetch arbitrary websites, it routes each feed through a
  public CORS-proxy service (with a second proxy as fallback), then
  parses, clusters, and fact-checks client-side, caching results in
  `localStorage` for an hour. A "Start auto-refresh" toggle polls again
  every few minutes until you click "Stop" — deliberately not a tight
  non-stop loop, since hammering a free proxy as fast as possible gets it
  to block the page within seconds — and stops itself automatically after
  15 minutes even if nobody clicks "Stop", so a left-open tab doesn't poll
  indefinitely; this cap only applies to auto-refresh, never to a manual
  "Search the Web" click. This is a parallel reimplementation,
  not a call into the PHP pipeline, so it's independently maintained and
  can drift from it over time; it's also meaningfully less reliable,
  since it depends entirely on third-party proxy services outside this
  project's control (rate limits, downtime, or a policy change can break
  it with no warning). Keep using `index.php`/`revue.html` if you want
  the same guarantees as the rest of this project.

## Requirements

- PHP 8.1+ with the `curl` and `SimpleXML` extensions

## Run locally

```
php -S localhost:8000
```

Then open `http://localhost:8000/index.php` or `http://localhost:8000/revue.html`.

## Configuration

Edit `config.php` to change the source outlets (each tagged with a
`category` of `world`, `science`, or `finance`), the minimum number of
outlets required to validate a story, the clustering similarity
threshold, the article age window, how many validated stories are
computed per run (`max_stories_shown`), how many are shown per scroll
batch (`stories_per_batch`), or the cache behavior
(`cache_enabled`, `cache_ttl_seconds`, `cache_file`).

## How it works

- `src/FeedFetcher.php` — pulls each configured RSS/Atom feed in parallel
  and normalizes entries into a flat article list.
- `src/StoryClusterer.php` — groups articles from different outlets that
  are covering the same story, using either headline/summary token
  overlap or shared proper-noun phrases (see
  `TextUtils::extractEntityPhrases`), then discards any cluster that
  isn't confirmed by enough distinct outlets.
- `src/FactChecker.php` — for each validated story, strips sentences
  containing loaded/editorializing language or unattributed opinion,
  de-duplicates near-identical claims, flags numeric discrepancies
  between sources, and builds the considerations and resources lists.
- `src/TextUtils.php` — shared tokenizing, similarity, and bias/opinion
  lexicon helpers used by the above.
- `src/ArticleSearch.php` — filters fetched articles by a search query
  before clustering, so search results are cross-checked the same way
  as the unfiltered view.
- `src/TopicFilter.php` — keyword-based exclusion of sports and
  celebrity/gossip content, applied to every fetched article regardless
  of which feed it came from.
- `src/StoryCache.php` — reads/writes the cached article pool that
  `index.php` uses when it's fresh, and `refresh.php` writes to on a
  forced or scheduled run.
- `src/NewsPipeline.php` — the shared fetch/cache/filter/cluster/fact-check
  pipeline both `index.php` and `api.php` call, so the two frontends can't
  run different logic against the same data.
- `refresh.php` — standalone script that fetches every configured
  source and writes the cache, independent of any page visit.
- `api.php` — JSON version of the pipeline's output, consumed by
  `revue.html`.
- `revue.html` — the standalone frontend described above.

Speculative or question-framed articles (`TextUtils::isSpeculative`) are
rejected entirely at the fetch stage in `FeedFetcher`, so they never
enter clustering, search, or the fact-checker — and the same check also
strips individual speculative sentences out of an otherwise-admitted
article's body in `FactChecker`.
