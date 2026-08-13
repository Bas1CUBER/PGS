#!/usr/bin/env bash
# Phase 2 grep-gate: fail CI if banned patterns appear in app code.
# Banned: mysqli usage, raw SQL string interpolation, schema DDL outside
# migrations, CDN assets, debug output. See docs/Backend.md and docs/Consistency.md.
set -euo pipefail

cd "$(dirname "$0")/.."
FAIL=0

check() {
  local label="$1" pattern="$2" dir="$3"
  if grep -rInE --include='*.php' "$pattern" "$dir" | grep -v '/legacy/' > /dev/null 2>&1; then
    echo "FAIL [$label]:"
    grep -rInE --include='*.php' "$pattern" "$dir" | grep -v '/legacy/' || true
    FAIL=1
  fi
}

# 1. mysqli driver is banned entirely (PDO/Eloquent only)
check "mysqli" 'mysqli_' app routes database tests

# 2. CREATE/ALTER TABLE must never appear in app code (migrations only)
check "schema DDL in code" 'CREATE[[:space:]]+TABLE|ALTER[[:space:]]+TABLE' app routes tests

# 3. No legacy global DB connection references
check "global conn/pdo" '\$GLOBALS\[.(conn|pdo).\]' app routes tests

# 4. No debug output
check "debug output" '\b(dd|dump|var_dump|print_r|console\.log)\(' app routes

# 5. No CDN asset references
check "CDN assets" 'https?://[^"'"'"' ]*cdn|unpkg\.com|jsdelivr\.net|cdnjs\.cloudflare\.com' resources

exit $FAIL
