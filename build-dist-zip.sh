#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
DISTIGNORE="$ROOT_DIR/.distignore"
OUTPUT_ZIP="${1:-flosc-with-perfect-css.zip}"
OUTPUT_PATH="$ROOT_DIR/$OUTPUT_ZIP"

if [[ ! -f "$DISTIGNORE" ]]; then
  echo "Missing .distignore at: $DISTIGNORE" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
STAGE_DIR="$TMP_DIR/flosc"
mkdir -p "$STAGE_DIR"

cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

# Stage plugin content while honoring .distignore patterns.
rsync -a \
  --exclude-from="$DISTIGNORE" \
  "$ROOT_DIR/" "$STAGE_DIR/"

# Avoid including the output archive itself from previous runs.
rm -f "$STAGE_DIR/$OUTPUT_ZIP"

(
  cd "$TMP_DIR"
  rm -f "$OUTPUT_PATH"
  zip -qr "$OUTPUT_PATH" "flosc"
)

echo "Created: $OUTPUT_PATH"
