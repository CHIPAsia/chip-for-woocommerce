# Contributing to CHIP for WooCommerce

Thank you for your interest in contributing! This document outlines how to set up the development environment, our coding standards, and the release process.

## Development Setup

### Prerequisites

- PHP 7.4 or higher (8.0+ recommended)
- Node.js 20+ and npm 10+
- Composer (for PHPCS/WPCS)
- A local WordPress installation with WooCommerce

### Installation

1. Clone the repository into your WordPress `plugins/` directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/CHIPAsia/chip-for-woocommerce.git
   cd chip-for-woocommerce
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node.js dependencies and build assets:
   ```bash
   npm install
   npm run build
   ```

### Build Assets

JavaScript assets for WooCommerce Blocks checkout are built with Webpack:

```bash
npm run build      # Production build
npm run start      # Development watch mode
```

Built files are output to `assets/js/frontend/` and are **gitignored**. They must be built before releasing.

## Coding Standards

We follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). Run the linters before submitting a PR:

```bash
# PHP CodeSniffer (WordPress standards)
phpcs --standard=phpcs.xml .

# PHP Compatibility check
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4 --extensions=php --ignore=vendor,node_modules,assets/js/frontend .
```

### Key Rules

- Use tabs for indentation (not spaces)
- Maximum line length: 120 characters
- All functions/classes must be prefixed with `chip_` or `Chip_Woocommerce`
- Always sanitize and escape output
- Include `ABSPATH` guard at the top of every PHP file

## Submitting Changes

1. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes and write clear, concise commit messages

3. Ensure tests and linters pass:
   ```bash
   phpcs --standard=phpcs.xml .
   npm run build
   ```

4. Push your branch and open a Pull Request against `main`

5. The PR summary workflow will auto-generate a description. Fill in any missing sections manually

## Release Process

### Automated Version Bump (Recommended)

Use the provided script to bump the version across all files:

```bash
./scripts/bump-version.sh 2.0.4
```

This will:
- Update version strings in all files
- Add a changelog entry template
- Run `npm run build`
- Stage changes for commit

After running the script, review the changes, write the changelog entry, commit, and push the tag:

```bash
git add -A
git commit -m "Bump version to 2.0.4"
git tag v2.0.4
git push origin main --tags
```

The `deploy.yml` GitHub Actions workflow will then:
- Build production assets
- Deploy to WordPress.org SVN (`trunk/` + `tags/2.0.4/` + `assets/`)
- Attach the release zip to the GitHub release

### Manual Version Bump

If you prefer not to use the script, follow this checklist:

- [ ] `chip-for-woocommerce.php` — `Version: X.Y.Z` header
- [ ] `chip-for-woocommerce.php` — `CHIP_WOOCOMMERCE_MODULE_VERSION` constant
- [ ] `readme.txt` — `Stable tag: X.Y.Z`
- [ ] `package.json` — `version` field
- [ ] `changelog.txt` — Add new version entry with date
- [ ] `readme.txt` — Add new version entry in `== Changelog ==` section
- [ ] Run `npm run build` and verify built assets are fresh
- [ ] Run `phpcs --standard=phpcs.xml .` and fix any issues
- [ ] Run `git add -A && git commit -m "Bump version to X.Y.Z"`
- [ ] Push tag: `git tag vX.Y.Z && git push origin vX.Y.Z`
- [ ] Verify GitHub Actions `deploy.yml` workflow succeeds
- [ ] Verify [wordpress.org plugin page](https://wordpress.org/plugins/chip-for-woocommerce/) shows the new version

### Version Numbering

We follow [Semantic Versioning](https://semver.org/) adapted for WordPress plugins:

| Level | When to Bump | Example |
|---|---|---|
| **Major (X)** | Breaking changes, dropped PHP/WP support, major refactors | `2.0.0` |
| **Minor (Y)** | New features, new payment methods, new hooks | `2.1.0` |
| **Patch (Z)** | Bug fixes, security patches, compatibility bumps | `2.0.4` |

### WordPress.org SVN Notes

The deploy workflow manages three SVN directories:

- `trunk/` — Always contains the latest development code
- `tags/X.Y.Z/` — Immutable release snapshots
- `assets/` — Plugin page banners, icons, and screenshots

**Important:** The `Stable tag` in `readme.txt` must match an existing tag directory. Never update `Stable tag` before the tag exists in SVN.

## Questions?

Open an issue on GitHub or reach out to the CHIP developer community.
