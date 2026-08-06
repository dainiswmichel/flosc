#!/usr/bin/env bash
# Build a WordPress.org-safe FLOSC zip from this plugin directory.
# Fail closed: refuses to write a zip if any forbidden path is present.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ ! -f flosc.php || ! -f readme.txt ]]; then
  echo "FATAL: run from the flosc plugin root (missing flosc.php/readme.txt)" >&2
  exit 1
fi

if [[ ! -f .distignore ]]; then
  echo "FATAL: .distignore missing — will not package without exclude list" >&2
  exit 1
fi

# Refuse to build if review/dev junk is sitting in the ship tree.
# Silent exclude is not enough — presence means a hand zip -r will ship it.
SOURCE_DENY=(
  flosc_development_worknotes
  flosc_development_archives
  zip-files
  tmp
  node_modules
)
source_fail=0
for d in "${SOURCE_DENY[@]}"; do
  if [[ -e "$ROOT/$d" ]]; then
    echo "FATAL: remove $d from the ship tree before building a zip" >&2
    source_fail=1
  fi
done
if [[ "$source_fail" -ne 0 ]]; then
  echo "FATAL: clean the ship tree, then re-run ./build-dist-zip.sh" >&2
  exit 1
fi
# vendor/ may exist for local PHPCS; it must never enter the zip (scanned below).
if [[ -d "$ROOT/vendor" ]]; then
  echo "[build-dist-zip] note: vendor/ present locally — excluded from zip (must stay excluded)"
fi

STAMP="${1:-$(date +%Y%m%d-%H%M%S)}"
OUT_DIR="${FLOSC_ZIP_OUT_DIR:-$(cd .. && pwd)/zip-files}"
mkdir -p "$OUT_DIR"
STAGE="$(mktemp -d "${TMPDIR:-/tmp}/flosc-dist.XXXXXX")"
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

# Hard deny list — never ship these, even if .distignore is edited badly.
# Paths are relative to plugin root (no leading flosc/).
DENY_PATTERNS=(
  'flosc_development_worknotes'
  'flosc_development_archives'
  'sample-data'
  'vendor'
  'composer.json'
  'composer.lock'
  'zip-files'
  '.git'
  '.github'
  '.githooks'
  '.venv'
  'node_modules'
  'tmp'
  'mvp_sprint'
  'quiz_development'
  'da1ni5_personal_profitability'
  'build-dist-zip.sh'
  '.distignore'
)

rsync_excludes=()
while IFS= read -r line || [[ -n "$line" ]]; do
  # strip comments / blanks
  line="${line%%#*}"
  line="$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  [[ -z "$line" ]] && continue
  rsync_excludes+=(--exclude "$line")
done < .distignore

# Always exclude deny list (belt + suspenders over .distignore)
for p in "${DENY_PATTERNS[@]}"; do
  rsync_excludes+=(--exclude "$p" --exclude "${p}/" --exclude "${p}/**")
done

echo "[build-dist-zip] staging runtime tree -> $STAGE/flosc"
mkdir -p "$STAGE/flosc"
rsync -a \
  --delete \
  "${rsync_excludes[@]}" \
  --exclude '.DS_Store' \
  --exclude '*.zip' \
  --exclude '*.log' \
  --exclude '*.bundle' \
  ./ "$STAGE/flosc/"

# --- Fail closed: scan staged tree for forbidden content ---
fail=0
while IFS= read -r -d '' found; do
  rel="${found#"$STAGE/flosc/"}"
  echo "FATAL: forbidden path staged for zip: flosc/${rel}" >&2
  fail=1
done < <(find "$STAGE/flosc" \( \
  -path '*/flosc_development_worknotes' -o \
  -path '*/flosc_development_worknotes/*' -o \
  -path '*/flosc_development_archives' -o \
  -path '*/flosc_development_archives/*' -o \
  -path '*/sample-data' -o \
  -path '*/sample-data/*' -o \
  -path '*/vendor' -o \
  -path '*/vendor/*' -o \
  -path '*/.git' -o \
  -path '*/.git/*' -o \
  -path '*/.github' -o \
  -path '*/.github/*' -o \
  -path '*/zip-files' -o \
  -path '*/zip-files/*' -o \
  -name 'composer.json' -o \
  -name 'composer.lock' -o \
  -name 'build-dist-zip.sh' -o \
  -name '.distignore' -o \
  -name '*.zip' -o \
  -name '*.bundle' \
\) -print0 2>/dev/null)

# Filename/content red flags inside staged PHP/md/txt
while IFS= read -r -d '' found; do
  rel="${found#"$STAGE/flosc/"}"
  base="$(basename "$found")"
  case "$base" in
    *worknote*|*WORKNOTE*|*wporg-review-reply*|*SESSION-SUMMARY*|*agent-review*)
      echo "FATAL: worknotes-like file staged: flosc/${rel}" >&2
      fail=1
      ;;
  esac
done < <(find "$STAGE/flosc" -type f -print0)

if [[ ! -f "$STAGE/flosc/flosc.php" || ! -f "$STAGE/flosc/readme.txt" ]]; then
  echo "FATAL: staged zip missing flosc.php or readme.txt" >&2
  fail=1
fi

if [[ "$fail" -ne 0 ]]; then
  echo "FATAL: refusing to write zip (see above)." >&2
  exit 1
fi

ZIP_PATH="${OUT_DIR}/flosc-${STAMP}.zip"
# Relative path inside zip must be flosc/...
(
  cd "$STAGE"
  # -X strip extra attrs; -9 compress
  zip -X -r -9 "$ZIP_PATH" flosc \
    -x '*.DS_Store' \
    -x '*__MACOSX*'
)

# Second pass: inspect the zip itself
if unzip -l "$ZIP_PATH" | grep -Eiq 'worknote|flosc_development_|/vendor/|composer\.(json|lock)|sample-data/|\.git/'; then
  echo "FATAL: zip contents still contain forbidden paths:" >&2
  unzip -l "$ZIP_PATH" | grep -Ei 'worknote|flosc_development_|/vendor/|composer\.(json|lock)|sample-data/|\.git/' >&2 || true
  rm -f "$ZIP_PATH"
  exit 1
fi

COUNT="$(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}')"
SIZE="$(ls -lh "$ZIP_PATH" | awk '{print $5}')"
echo "[build-dist-zip] OK  $ZIP_PATH"
echo "[build-dist-zip] files=$COUNT size=$SIZE"
echo "[build-dist-zip] top-level entries:"
unzip -l "$ZIP_PATH" | awk 'NR>3 && /\/$/ && $NF ~ /^flosc\/[^\/]+\/$/ {print $NF}' | head -40
