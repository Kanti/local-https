ARG BUILD_STAGE=prod

FROM certbot/dns-cloudflare:v2.5.0 as base

RUN wget https://github.com/nginx-proxy/docker-gen/releases/download/0.10.4/docker-gen-alpine-linux-amd64-0.10.4.tar.gz && \
  tar xvzf docker-gen-alpine-linux-amd64-0.10.4.tar.gz && \
  rm docker-gen-alpine-linux-amd64-0.10.4.tar.gz && \
  mv docker-gen /usr/bin/ && \
  chmod +x /usr/bin/docker-gen

RUN apk add --update curl \
    && mkdir -p /tmp/download \
    && curl -L "https://download.docker.com/linux/static/stable/x86_64/docker-23.0.4.tgz" | tar -xz -C /tmp/download \
    && mv /tmp/download/docker/docker /usr/local/bin/ \
    && rm -rf /tmp/download \
    && apk del curl \
    && rm -rf /var/cache/apk/*

#
# dev
#
FROM base as dev

RUN apk add --update \
    php8 \
    php8-json \
    php8-pecl-yaml \
    php8-curl \
    php8-dom \
    php8-pdo \
    php8-simplexml \
    php8-tokenizer \
    php8-xml \
    php8-xmlwriter \
    php8-posix \
    php8-ctype \
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
FROM base as prod

RUN apk add --update \
    php8 \
    php8-json \
    php8-pecl-yaml \
    php8-curl \
    composer \
  && rm -rf /var/cache/apk/*

#
# finish
#

FROM ${BUILD_STAGE} as finish

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


