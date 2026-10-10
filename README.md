# Persian Fake Contents

Generate Persian/Farsi dummy posts, pages and custom post types for WordPress theme and plugin development.

[WordPress.org plugin page](https://wordpress.org/plugins/persian-fake-contents/) · wordpress.org readme: [`readme.txt`](readme.txt)

## Features

- Admin screen with a progress bar (up to 500 items per run)
- WooCommerce products: price, sale price, SKU, stock, product categories/tags and rated reviews
- "Persian Lorem" editor block that inserts Persian paragraphs as normal blocks
- Block-editor content (headings, paragraphs, lists, quotes) or classic HTML
- Persian punctuation and frequency-weighted words for natural-looking text
- Random authors, categories, tags, comments and publish dates
- Featured images drawn locally with GD, or downloaded from picsum.photos
- WP-CLI commands for bulk generation
- One-click removal of everything the plugin created (including content from 1.x)

## Usage

**Admin:** Tools → Persian Fake Contents (up to 500 items per run).

**Editor:** add the "Persian Lorem" block.

**WP-CLI:**

```sh
wp persian-fake generate --count=50 --category=random --tags=3 --comments=5 --image=placeholder --days=90
wp persian-fake list
wp persian-fake purge --yes
wp help persian-fake generate
```

## REST API

All routes are under `/wp-json/persian-fake/v1` and need `manage_options` (except `/text`, which needs `edit_posts`):

| Route | Method | Purpose |
|---|---|---|
| `/config` | GET | Form options and the user's last-used settings |
| `/generate` | POST | Create one batch (`options`, `count` ≤ 5) |
| `/items` | GET | Paged list of fake content |
| `/items` | DELETE | Delete one batch; returns `remaining` |
| `/text` | GET | Persian paragraphs for the editor block |

## Hooks

- `gfpc_words` (filter): replace or extend the Persian word list (most frequent words first).

## Development

```sh
composer install
composer lint     # PHP syntax
composer phpcs    # WordPress Coding Standards

npm install
npm run build     # build/ (admin app + editor block)
npm run start     # rebuild on change
npm run lint:js
```

`build/` is not committed; the deploy workflow builds it. If you install the plugin straight from this repository, run `npm install && npm run build` first. JavaScript sources are in `js/`.

Publishing a GitHub release deploys the tagged version to wordpress.org SVN (see `.github/workflows/deploy.yml`). Use the version number without a `v` as the tag, e.g. `2.0.0`.

## License

GPL-3.0-or-later
