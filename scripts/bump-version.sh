#!/usr/bin/env bash
#
# Bump the plugin version across all files.
#
# Usage:
#   ./scripts/bump-version.sh 2.0.4
#
# This script updates:
#   - chip-for-woocommerce.php  (Version header + CHIP_WOOCOMMERCE_MODULE_VERSION)
#   - readme.txt                (Stable tag)
#   - package.json              (version field)
#   - changelog.txt             (prepends a new version entry)
#
# Then runs npm run build and stages changes.
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "$PROJECT_ROOT"

# ─── Validate input ───

if [ $# -ne 1 ]; then
    echo "Usage: $0 <version>"
    echo "Example: $0 2.0.4"
    exit 1
fi

NEW_VERSION="$1"

# Validate semver-ish format: X.Y.Z
if ! [[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: Version must be in X.Y.Z format (e.g., 2.0.4)"
    exit 1
fi

# Detect current version from the plugin header
CURRENT_VERSION=$(grep -oP '^\s*Version:\s*\K[0-9.]+' chip-for-woocommerce.php)

echo "🔢 Current version: $CURRENT_VERSION"
echo "🔢 New version:     $NEW_VERSION"
read -r -p "Continue? [y/N] " CONFIRM
if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

# ─── Update version strings ───

echo "📝 Updating version strings..."

# chip-for-woocommerce.php — Version header
sed -i.bak "s/^ \* Version: ${CURRENT_VERSION}/ * Version: ${NEW_VERSION}/" chip-for-woocommerce.php
rm -f chip-for-woocommerce.php.bak

# chip-for-woocommerce.php — CHIP_WOOCOMMERCE_MODULE_VERSION constant
sed -i.bak "s/CHIP_WOOCOMMERCE_MODULE_VERSION', 'v${CURRENT_VERSION}'/CHIP_WOOCOMMERCE_MODULE_VERSION', 'v${NEW_VERSION}'/" chip-for-woocommerce.php
rm -f chip-for-woocommerce.php.bak

# readme.txt — Stable tag
sed -i.bak "s/^Stable tag: ${CURRENT_VERSION}/Stable tag: ${NEW_VERSION}/" readme.txt
rm -f readme.txt.bak

# package.json — version field
sed -i.bak "s/\"version\": \"${CURRENT_VERSION}\"/\"version\": \"${NEW_VERSION}\"/" package.json
rm -f package.json.bak

# ─── Update changelog.txt ───

TODAY=$(date +%Y-%m-%d)
CHANGELOG_ENTRY="= ${NEW_VERSION} ${TODAY} =
* [Add your changelog entry here]
"

# Prepend the new entry after the "== Changelog ==" header
if grep -q "^= ${NEW_VERSION} ${TODAY} =" changelog.txt; then
    echo "⚠️  Changelog entry for ${NEW_VERSION} already exists. Skipping."
else
    echo "📝 Adding changelog entry..."
    # Use a temp file to prepend after the header line
    awk -v entry="$CHANGELOG_ENTRY" '
        NR==1 { print; next }
        NR==2 { print; print entry; next }
        { print }
    ' changelog.txt > changelog.txt.tmp
    mv changelog.txt.tmp changelog.txt
fi

# ─── Build assets ───

echo "🔨 Running npm run build..."
npm run build

# ─── Stage changes ───

echo "📦 Staging changes..."
git add -A

echo ""
echo "✅ Version bumped to ${NEW_VERSION}"
echo ""
echo "Next steps:"
echo "  1. Review the changelog entry in changelog.txt"
echo "  2. git commit -m \"Bump version to ${NEW_VERSION}\""
echo "  3. git tag v${NEW_VERSION}"
echo "  4. git push origin main --tags"
echo ""
echo "The deploy workflow will then release to WordPress.org automatically."
