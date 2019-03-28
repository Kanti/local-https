FROM certbot/dns-cloudflare

RUN wget https://github.com/jwilder/docker-gen/releases/download/0.7.3/docker-gen-linux-amd64-0.7.3.tar.gz && \
  tar xvzf docker-gen-linux-amd64-0.7.3.tar.gz && \
  rm docker-gen-linux-amd64-0.7.3.tar.gz && \
  mv docker-gen /usr/bin/ && \
  chmod +x /usr/bin/docker-gen

RUN apk add --update curl \
    && mkdir -p /tmp/download \
    && curl -L "https://download.docker.com/linux/static/stable/x86_64/docker-18.09.4.tgz" | tar -xz -C /tmp/download \
    && mv /tmp/download/docker/docker /usr/local/bin/ \
    && rm -rf /tmp/download \
    && apk del curl \
    && rm -rf /var/cache/apk/*

RUN apk add --update \
    php7 \
    php7-json \
    php7-pecl-yaml \
    php7-curl \
    composer \
  && rm -rf /var/cache/apk/*

COPY src /app/src
COPY scripts /app/scripts
COPY templates /app/templates
COPY composer.json /app/
COPY composer.lock /app/

WORKDIR /app

RUN composer install --no-dev -n

ENTRYPOINT ["php", "/app/scripts/entrypoint.php"]

CMD ["docker-gen --watch --interval 3600 --wait 15s --notify-output --notify '/app/scripts/notify.php' templates/data.tmpl var/data.json"]


