#!/bin/sh
set -eu

# npm 12.2.0 bundles vulnerable dependencies even on current Node images.
# Replace only these packages, using reviewed exact versions and lock integrity.
script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
npm_root=$(npm root --global)
test "$(npm --version)" = '12.2.0'
test -f "$npm_root/npm/package.json"
patch_dir=$(mktemp -d)
trap 'rm -rf "$patch_dir"' EXIT
cp "$script_dir/npm-security/package.json" "$script_dir/npm-security/package-lock.json" "$patch_dir/"
npm ci --prefix "$patch_dir" --ignore-scripts --no-audit --no-fund
for package in undici brace-expansion balanced-match; do
    test -f "$patch_dir/node_modules/$package/package.json"
    test -d "$npm_root/npm/node_modules/$package"
    rm -rf "$npm_root/npm/node_modules/$package"
    cp -R "$patch_dir/node_modules/$package" "$npm_root/npm/node_modules/$package"
done
npm audit --prefix "$patch_dir" --audit-level=moderate
node -e 'const root=process.argv[1]; for (const [name,version] of [["undici","6.28.1"],["brace-expansion","5.0.12"]]) { if(require(root+"/npm/node_modules/"+name+"/package.json").version!==version) process.exit(1); }' "$npm_root"
test "$(npm --version)" = '12.2.0'
