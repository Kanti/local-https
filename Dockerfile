ARG BUILD_STAGE=prod

FROM certbot/dns-cloudflare:v5.1.0 AS base
# find version here: https://releasealert.dev/dockerhub/_/docker
ARG DOCKER_VERSION=29.0.4
# find version here: https://github.com/nginx-proxy/docker-gen/releases
ARG DOCKER_GEN_VERSION=0.16.1

RUN wget https://github.com/nginx-proxy/docker-gen/releases/download/${DOCKER_GEN_VERSION}/docker-gen-alpine-linux-amd64-${DOCKER_GEN_VERSION}.tar.gz && \
  tar xvzf docker-gen-alpine-linux-amd64-${DOCKER_GEN_VERSION}.tar.gz && \
  rm docker-gen-alpine-linux-amd64-${DOCKER_GEN_VERSION}.tar.gz && \
  mv docker-gen /usr/bin/ && \
  chmod +x /usr/bin/docker-gen

RUN apk add --update curl \
    && mkdir -p /tmp/download \
    && curl -L "https://download.docker.com/linux/static/stable/x86_64/docker-${DOCKER_VERSION}.tgz" | tar -xz -C /tmp/download \
    && mv /tmp/download/docker/docker /usr/local/bin/ \
    && rm -rf /tmp/download \
    && apk del curl \
    && rm -rf /var/cache/apk/*

#
# dev
#
FROM base AS dev

RUN apk add --update \
    php83 \
    php83-json \
    php83-pecl-yaml \
    php83-curl \
    php83-dom \
    php83-pdo \
    php83-simplexml \
    php83-tokenizer \
    php83-xml \
    php83-xmlwriter \
    php83-posix \
    php83-ctype \
    file \
    composer \
    git \
    git-perl \
  && rm -rf /var/cache/apk/*

ENV PATH="$PATH:./vendor/bin/"

RUN git config --global --add safe.directory /app

#
# prod
#
FROM base AS prod

RUN apk add --update \
    php83 \
    php83-json \
    php83-pecl-yaml \
    php83-curl \
    composer \
  && rm -rf /var/cache/apk/*

#
# finish
#

FROM ${BUILD_STAGE} AS finish

COPY src /app/src
COPY templates /app/templates
COPY config /app/config
COPY s composer.json composer.lock /app/

WORKDIR /app

RUN composer install --no-dev -n && chmod +x /app/s

ARG RELEASE_TAG
ENV RELEASE_TAG=${RELEASE_TAG}

ENTRYPOINT ["/app/s", "entrypoint"]

CMD ["docker-gen --watch --interval 60 --notify-output --notify './s' templates/data.tmpl var/data.json"]
