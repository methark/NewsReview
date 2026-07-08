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

There is no caching or storage: the whole fetch → cross-check → filter
pipeline runs fresh on each request, straight from `index.php`.

## Requirements

- PHP 8.1+ with the `curl` and `SimpleXML` extensions

## Run locally

```
php -S localhost:8000
```

Then open `http://localhost:8000/index.php`.

## Configuration

Edit `config.php` to change the source outlets, the minimum number of
outlets required to validate a story, the clustering similarity
threshold, or the article age window.

## How it works

- `src/FeedFetcher.php` — pulls each configured RSS/Atom feed in parallel
  and normalizes entries into a flat article list.
- `src/StoryClusterer.php` — groups articles from different outlets that
  are covering the same story (headline/summary token overlap), then
  discards any cluster that isn't confirmed by enough distinct outlets.
- `src/FactChecker.php` — for each validated story, strips sentences
  containing loaded/editorializing language or unattributed opinion,
  de-duplicates near-identical claims, flags numeric discrepancies
  between sources, and builds the considerations and resources lists.
- `src/TextUtils.php` — shared tokenizing, similarity, and bias/opinion
  lexicon helpers used by the above.
