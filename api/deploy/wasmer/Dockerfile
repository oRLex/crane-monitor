# Wasmer CLI + PHP/Composer for building and deploying the API to Wasmer Edge.
# Wasmer ships no darwin-amd64 binary, so the CLI runs in this linux/amd64 image.
# PHP 8.3 matches the php/php runtime on Wasmer Edge.
FROM php:8.3-cli

ARG WASMER_VERSION=v7.5.0

RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl git unzip rsync libpq-dev libzip-dev \
 && docker-php-ext-install pdo_pgsql pcntl sockets bcmath zip \
 && rm -rf /var/lib/apt/lists/* \
 && curl -fsSL "https://github.com/wasmerio/wasmer/releases/download/${WASMER_VERSION}/wasmer-linux-amd64.tar.gz" \
    | tar -xz -C /usr/local bin/wasmer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /build
ENTRYPOINT ["wasmer"]
