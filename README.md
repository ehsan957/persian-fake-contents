# Persian Fake Contents

Generate Persian/Farsi dummy posts, pages and custom post types for WordPress theme and plugin development.

[WordPress.org plugin page](https://wordpress.org/plugins/persian-fake-contents/) · wordpress.org readme: [`readme.txt`](readme.txt)

## Features

- Block-editor content (headings, paragraphs, lists, quotes) or classic HTML
- Persian punctuation and frequency-weighted words for natural-looking text
- Random authors, categories, tags, comments and publish dates
- Featured images drawn locally with GD, or downloaded from picsum.photos
- WP-CLI commands for bulk generation
- One-click removal of everything the plugin created (including content from 1.x)

## Usage

**Admin:** Tools → Persian Fake Contents (up to 50 items per run).

**WP-CLI:**

```sh
wp persian-fake generate --count=50 --category=random --tags=3 --comments=5 --image=placeholder --days=90
wp persian-fake list
wp persian-fake purge --yes
wp help persian-fake generate
```

## Hooks

- `gfpc_words` (filter): replace or extend the Persian word list (most frequent words first).

## Development

```sh
composer install
composer lint   # PHP syntax
composer phpcs  # WordPress Coding Standards
```

Publishing a GitHub release deploys the tagged version to wordpress.org SVN (see `.github/workflows/deploy.yml`). Use the version number without a `v` as the tag, e.g. `2.0.0`.

## License

GPL-3.0-or-later
