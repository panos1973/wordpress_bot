#!/bin/bash
#
# Build script for Medical Website Chatbot WordPress plugin.
# Creates a versioned zip file ready for WordPress plugin installation.
#
# Usage:
#   ./build.sh          - Build zip with current version from medical-chatbot.php
#   ./build.sh 1.2.0    - Bump version to 1.2.0, then build zip
#

set -e

PLUGIN_SLUG="medical-chatbot"
MAIN_FILE="medical-chatbot.php"
BUILD_DIR="build"

# Get the script's directory
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# If a version argument is provided, bump the version first
if [ -n "$1" ]; then
    NEW_VERSION="$1"

    # Validate semver format
    if ! echo "$NEW_VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
        echo "Error: Version must be in semver format (e.g., 1.2.0)"
        exit 1
    fi

    echo "Bumping version to $NEW_VERSION..."

    # Update the plugin header version
    sed -i "s/^ \* Version: .*/ * Version: $NEW_VERSION/" "$MAIN_FILE"

    # Update the MCB_VERSION constant
    sed -i "s/define( 'MCB_VERSION', '.*' );/define( 'MCB_VERSION', '$NEW_VERSION' );/" "$MAIN_FILE"

    # Update the MCB_DB_VERSION constant (same as plugin version by default)
    sed -i "s/define( 'MCB_DB_VERSION', '.*' );/define( 'MCB_DB_VERSION', '$NEW_VERSION' );/" "$MAIN_FILE"

    echo "Version bumped to $NEW_VERSION"
fi

# Read the current version from the main file
VERSION=$(grep "define( 'MCB_VERSION'" "$MAIN_FILE" | sed "s/.*'\([0-9.]*\)'.*/\1/")

if [ -z "$VERSION" ]; then
    echo "Error: Could not detect plugin version from $MAIN_FILE"
    exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

# Remove any previous version zip files so only the latest remains.
rm -f ${PLUGIN_SLUG}-*.zip 2>/dev/null || true

echo "Building $PLUGIN_SLUG v$VERSION..."
echo ""

# Clean previous build
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

# Copy plugin files (exclude dev/build files)
cp -r \
    medical-chatbot.php \
    uninstall.php \
    includes/ \
    templates/ \
    assets/ \
    "$BUILD_DIR/$PLUGIN_SLUG/"

# Remove any hidden/dev files that snuck in
find "$BUILD_DIR/$PLUGIN_SLUG" -name '.git*' -exec rm -rf {} + 2>/dev/null || true
find "$BUILD_DIR/$PLUGIN_SLUG" -name '.DS_Store' -exec rm -f {} + 2>/dev/null || true
find "$BUILD_DIR/$PLUGIN_SLUG" -name 'Thumbs.db' -exec rm -f {} + 2>/dev/null || true

# Create the zip
cd "$BUILD_DIR"
zip -r "../$ZIP_NAME" "$PLUGIN_SLUG/" -x "*.git*"
cd ..

# Clean up build dir
rm -rf "$BUILD_DIR"

echo ""
echo "========================================="
echo "  Built: $ZIP_NAME"
echo "  Version: $VERSION"
echo "========================================="
echo ""
echo "Install via WordPress Admin > Plugins > Add New > Upload Plugin"
echo "Upgrading: Upload the same zip — WordPress will detect the existing"
echo "plugin and offer to replace it with the new version."
