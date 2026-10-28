#!/usr/bin/env bash
# Wasmer CLI for the API, run in Docker (no darwin-amd64 build of Wasmer exists).
#
#   deploy/wasmer/wasmer.sh login                 # once; paste a token from wasmer.io/settings/access-tokens
#   deploy/wasmer/wasmer.sh run . --net --env-file /local.env   # try the bundle locally on :8080
#   WASMER_OWNER=<namespace> deploy/wasmer/wasmer.sh deploy   # build the bundle and deploy to Wasmer Edge
#   deploy/wasmer/wasmer.sh app secrets create APP_KEY base64:...
#   deploy/wasmer/wasmer.sh test [phpunit args]   # tests on the Wasmer PHP runtime (32-bit); default: Unit suite
#
# `run` and `deploy` first build a clean bundle inside the container: tracked and
# untracked-not-ignored files only (so never .env), `composer install --no-dev`.
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
api="$(cd "$here/../.." && pwd)"
repo="$(git -C "$api" rev-parse --show-toplevel)"
image=cranewatch-wasmer

docker image inspect "$image" >/dev/null 2>&1 || docker build --platform linux/amd64 -t "$image" "$here"

opts=(); [ -t 0 ] && opts+=(-it)
# Local-only env for `run`, mounted outside the bundle so it is never deployed.
[ -f "$here/local.env" ] && opts+=(-v "$here/local.env:/local.env:ro")

# The Wasmer runtime has no pcntl/sockets/redis; only the workers that don't run there need them.
build() {
  echo "git config --global --add safe.directory '*' \
    && git -C /src/api ls-files -co --exclude-standard -z . | rsync -a --from0 --files-from=- /src/api/ /build/ \
    && composer config platform-check false \
    && composer install $1 --optimize-autoloader --no-interaction --no-progress -q"
}
php_pkg="php/php@$(sed -n 's/^"php\/php" = "=\([^"]*\)".*/\1/p' "$api/wasmer.toml")"

case "${1:-}" in
  run) build=$(build --no-dev) ;;
  deploy)
    build=$(build --no-dev)
    : "${WASMER_OWNER:?set WASMER_OWNER to your Wasmer username or namespace}"
    set -- "$@" --owner "$WASMER_OWNER"
    ;;
  test)
    build=$(build "")
    shift
    [ $# -eq 0 ] && set -- --testsuite Unit
    set -- run "$php_pkg" --volume /build:/app -- /app/vendor/bin/phpunit -c /app/phpunit.xml --do-not-cache-result "$@"
    ;;
  *) build=true ;;
esac

exec docker run --rm ${opts[@]+"${opts[@]}"} --platform linux/amd64 \
  -v "$HOME/.wasmer-docker:/root/.wasmer" -v "$repo:/src:ro" \
  -p 8080:8080 --add-host=host.docker.internal:host-gateway \
  --entrypoint sh "$image" -c "$build && exec wasmer \"\$@\"" wasmer "$@"
