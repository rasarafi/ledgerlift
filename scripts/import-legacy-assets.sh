#!/usr/bin/env bash
# Copies the built InvoicePlane v1.7.2 assets and English strings out of the
# `legacy` compose image into this app. Idempotent: reruns overwrite in place.
set -euo pipefail

command -v php >/dev/null || { echo "php required" >&2; exit 1; }

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
image="${LEGACY_IMAGE:-ledgerlift-legacy:latest}"
www=/var/www/html

work="$(mktemp -d)"
container="ledgerlift-asset-import-$$"
cleanup() {
    docker rm -f "$container" >/dev/null 2>&1 || true
    rm -rf "$work"
}
trap cleanup EXIT

docker create --name "$container" "$image" >/dev/null
docker cp "$container:$www/assets/." "$work/assets"
docker cp "$container:$www/application/language/english/ip_lang.php" "$work/ip_lang.php"

mkdir -p "$root/public/assets" "$root/lang/en"
cp -R "$work/assets/." "$root/public/assets/"
# Upstream's per-directory .gitignore files exclude its build output (fonts, *.min.js,
# style.css) from the InvoicePlane repo; here that output is the deliverable, so drop them.
find "$root/public/assets" -name .gitignore -delete

# ip_lang.php is a single `$lang = [ ... ];` literal; only the wrapper changes.
if [ "$(grep -c '^\$lang = \[$' "$work/ip_lang.php")" != 1 ] || [ "$(tail -n 1 "$work/ip_lang.php")" != '];' ]; then
    echo "ip_lang.php no longer has the expected \$lang = [ ... ]; wrapper" >&2
    exit 1
fi
sed 's/^\$lang = \[$/return [/' "$work/ip_lang.php" > "$root/lang/en/ip.php"

# Prove the converted array is value-identical to the legacy one.
php -r '
    $lang = null;
    include $argv[1];
    exit($lang === (require $argv[2]) ? 0 : 1);
' "$work/ip_lang.php" "$root/lang/en/ip.php" || { echo "converted lang/en/ip.php differs from legacy" >&2; exit 1; }

echo "Imported assets to public/assets and strings to lang/en/ip.php"
