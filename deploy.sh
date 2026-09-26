#!/bin/bash
#
# Deploy plugin to WordPress.org SVN repository.
#
# Usage:
#   ./deploy.sh <svn-checkout-path>
#
# Example:
#   ./deploy.sh ../switchback-carousel-svn
#   ./deploy.sh /Users/bmx269/Sites/wordpress/switchback-carousel
#
# Prerequisites:
#   - Run `npm run build` first
#   - SVN checkout must already exist
#

set -euo pipefail

PLUGIN_SLUG="switchback-carousel"
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"

# Get version from the main plugin file header.
VERSION=$(grep -m1 "Version:" "$PLUGIN_DIR/$PLUGIN_SLUG.php" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')

if [ -z "$VERSION" ]; then
    echo "Error: Could not determine version from $PLUGIN_SLUG.php"
    exit 1
fi

# Determine SVN path.
if [ $# -ge 1 ]; then
    SVN_DIR="$1"
else
    echo "Usage: $0 <svn-checkout-path>"
    echo ""
    echo "Example: $0 /path/to/svn/switchback-carousel"
    exit 1
fi

if [ ! -d "$SVN_DIR/.svn" ]; then
    echo "Error: $SVN_DIR is not an SVN checkout"
    exit 1
fi

echo "Deploying $PLUGIN_SLUG v$VERSION"
echo "  Source:  $PLUGIN_DIR"
echo "  SVN:     $SVN_DIR"
echo ""

# Ensure build is fresh.
if [ ! -d "$PLUGIN_DIR/build" ]; then
    echo "Error: build/ directory not found. Run 'npm run build' first."
    exit 1
fi

# --- Sync trunk ---
echo "Syncing trunk..."
TRUNK_DIR="$SVN_DIR/trunk"
mkdir -p "$TRUNK_DIR"

# Remove old trunk contents (except .svn).
find "$TRUNK_DIR" -mindepth 1 -not -path '*/.svn/*' -not -name '.svn' -delete 2>/dev/null || true

# Copy distributable files to trunk. .distignore decides what ships, the same
# rules the GitHub deploy workflow and Plugin Check CI use.
rsync -a --exclude-from="$PLUGIN_DIR/.distignore" "$PLUGIN_DIR/" "$TRUNK_DIR/"

# --- Sync assets ---
echo "Syncing assets..."
ASSETS_DIR="$SVN_DIR/assets"
mkdir -p "$ASSETS_DIR"

if [ -d "$PLUGIN_DIR/.wordpress-org" ]; then
    cp "$PLUGIN_DIR/.wordpress-org/"* "$ASSETS_DIR/" 2>/dev/null || true
fi

# --- Create tag ---
TAG_DIR="$SVN_DIR/tags/$VERSION"
if [ -d "$TAG_DIR" ]; then
    echo "Warning: Tag $VERSION already exists. Overwriting."
    find "$TAG_DIR" -mindepth 1 -not -path '*/.svn/*' -not -name '.svn' -delete 2>/dev/null || true
else
    mkdir -p "$TAG_DIR"
fi

echo "Creating tag $VERSION..."
cp -r "$TRUNK_DIR/"* "$TAG_DIR/"

# --- SVN operations ---
echo ""
echo "Adding new files to SVN..."
cd "$SVN_DIR"
svn add --force . --auto-props --parents --depth infinity -q 2>/dev/null || true

# Remove deleted files from SVN.
svn status | grep '^!' | awk '{print $2}' | xargs -I {} svn rm --force {} 2>/dev/null || true

echo ""
echo "SVN status:"
svn status

echo ""
echo "Ready to commit. Review the changes above, then run:"
echo "  cd $SVN_DIR"
echo "  svn commit -m \"Release $VERSION\""
