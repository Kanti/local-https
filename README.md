# LOCAL real HTTPS. With the help of Cloudflare DNS

You want a local Https certificate that is real? Accepted by every Browser?
With your domain and a little help of Docker you can do it.

````yml
version: '3.5'
services:

  proxy:
    restart: always
    image: jwilder/nginx-proxy
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/tmp/docker.sock:ro
      - ./.docker/data/nginx/certs:/etc/nginx/certs
      - ./.docker/data/nginx/dhparam:/etc/nginx/dhparam
    labels:
      - com.github.kanti.local_https.nginx_proxy=true

  companion:
    restart: always
    image: kanti/local-https
    network_mode: host
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./.docker/data/letsencrypt:/etc/letsencrypt
      - ./.docker/data/nginx/certs:/etc/nginx/certs
      - /etc/hosts:/wsl-hosts-file/hosts
      - /.docker/data/windows-hosts-file/:/windows-hosts-file/
    environment:
      - DNS_CLOUDFLARE_API_TOKEN=${DNS_CLOUDFLARE_API_TOKEN:?must be set}
      - HTTPS_MAIN_DOMAIN=${HTTPS_MAIN_DOMAIN:?must be set}
      - DDNS_INTERFACE=${DDNS_INTERFACE:-eth0}
      - SLACK_TOKEN=${SLACK_TOKEN:-}
      - SENTRY_DSN=${SENTRY_DSN:-}
````

## get an Cloudflare api token

visit: https://dash.cloudflare.com/profile/api-tokens  
use the Template `Edit zone DNS` and select you zones.

## hosts file feature

if you don't want to automatically add the domains into your hosts file.

- Ether do not mount your hosts file into the container.
- or set the env `DDNS_INTERFACE` to `off`

if you do not use this Feature, you don't need the `network_mode: host`

## .env file:

Create a `.env` file:
````.env
# required:
HTTPS_MAIN_DOMAIN=your.tld
DNS_CLOUDFLARE_API_TOKEN=_aA_AaBbCc12GgHh34_HX77AbCdEf9_23FgHtZax

# optional:
SLACK_TOKEN=111111111/222222222/333333333333333333333333
SENTRY_DSN=https://0123456789abcdefghijklmnopqrstuvwxyz@sentry.com/123456
````


## local testing:

``docker build -t kanti/local-https --build-arg RELEASE_TAG=$(git log --format="%H" -n 1) .``
