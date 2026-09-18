#!/usr/bin/env bash
#
# Build an installable Moodle plugin zip for mod_accredible from the current
# working tree, without modifying the repo.
#
# It stages a copy of the repo (so version.php and everything else stay
# untouched), bumps the staged version.php to the requested release, and zips
# it with the top-level "accredible/" folder Moodle expects.
#
# It does NOT touch git (no commits, tags, or pushes), so it cannot trigger any
# GitHub workflow. Only pushing a "v*" tag would do that.
#
# Usage:
#   scripts/build-plugin-zip.sh <release> [versionnum] [outdir]
#
#     <release>     release string for version.php, e.g. v2.5.0   (required)
#     [versionnum]  Moodle version number YYYYMMDDXX (default: today + "00")
#     [outdir]      output directory (default: ~/Downloads)
#
# Examples:
#   scripts/build-plugin-zip.sh v2.5.0
#   scripts/build-plugin-zip.sh v2.5.0 2026061000 ~/Desktop

set -euo pipefail

PLUGIN_DIR="accredible"   # Top-level folder Moodle expects (last part of mod_accredible).

RELEASE="${1:-}"
VERSIONNUM="${2:-$(date +%Y%m%d)00}"
OUTDIR="${3:-$HOME/Downloads}"

if [[ -z "$RELEASE" ]]; then
    echo "Usage: $0 <release> [versionnum YYYYMMDDXX] [outdir]" >&2
    echo "  e.g. $0 v2.5.0" >&2
    exit 1
fi

if ! [[ "$VERSIONNUM" =~ ^[0-9]{10}$ ]]; then
    echo "Error: versionnum must be 10 digits (YYYYMMDDXX); got '$VERSIONNUM'." >&2
    exit 1
fi

# The plugin root is the directory the script is run from (the plugin repo root),
# overridable via PLUGIN_ROOT. Keeping it location-independent means the same file
# works both in the repo and as a bundled copy in the Claude skill directory.
REPO_ROOT="${PLUGIN_ROOT:-$PWD}"

if [[ ! -f "$REPO_ROOT/version.php" ]] || ! grep -q "mod_accredible" "$REPO_ROOT/version.php"; then
    echo "Error: run from the moodle-mod_accredible plugin root" >&2
    echo "  (no mod_accredible version.php found at '$REPO_ROOT'; or set PLUGIN_ROOT=/path/to/plugin)." >&2
    exit 1
fi

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/$PLUGIN_DIR"

# Copy the working tree minus VCS / editor / build / dev-tooling files. A leading
# slash anchors the pattern to the repo root, so a nested directory that happens
# to be called scripts/ (or similar) is still packaged.
rsync -a \
    --exclude='/.git' \
    --exclude='/.github' \
    --exclude='/.claude' \
    --exclude='/.vscode' \
    --exclude='/.playwright-mcp' \
    --exclude='/.gitignore' \
    --exclude='/scripts' \
    --exclude='/Dockerfile' \
    --exclude='/docker-compose*.yml' \
    --exclude='/phpunit.xml' \
    --exclude='/tests/phpunit.xml' \
    --exclude='.DS_Store' \
    --exclude='node_modules' \
    --exclude='*.zip' \
    "$REPO_ROOT/" "$STAGE/$PLUGIN_DIR/"

# Bump version.php in the staged copy only (the repo is untouched). The capture
# group preserves the original spacing/comment after the value.
VERSION_FILE="$STAGE/$PLUGIN_DIR/version.php"
VERSIONNUM="$VERSIONNUM" perl -pi -e 's/(\$plugin->version\s*=\s*)\d+;/$1$ENV{VERSIONNUM};/' "$VERSION_FILE"
RELEASE="$RELEASE" perl -pi -e 's/(\$plugin->release\s*=\s*)"[^"]*";/$1"$ENV{RELEASE}";/' "$VERSION_FILE"
php -l "$VERSION_FILE" >/dev/null

mkdir -p "$OUTDIR"
OUT="$OUTDIR/mod_accredible_${RELEASE}_${VERSIONNUM}.zip"
rm -f "$OUT"
( cd "$STAGE" && zip -r -X -q "$OUT" "$PLUGIN_DIR" -x '*/.DS_Store' )

echo "Built: $OUT"
echo "  release:    $RELEASE"
echo "  versionnum: $VERSIONNUM"
echo "  top folder: $PLUGIN_DIR/"
