# LOCAL real HTTPS. With the help of Cloudflare

You want a local Https certificate that is real. Accepted by every Browser?
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
    environment:
      - DHPARAM_GENERATION=false
    labels:
      - com.github.kanti.local_https.nginx_proxy=true

  companion:
    build: .
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./.docker/data/letsencrypt:/etc/letsencrypt
      - ./.docker/data/nginx/certs:/etc/nginx/certs
    environment:
      - DNS_CLOUDFLARE_EMAIL=${DNS_CLOUDFLARE_EMAIL:?must be set}
      - DNS_CLOUDFLARE_API_KEY=${DNS_CLOUDFLARE_API_KEY:?must be set}
      - HTTPS_MAIN_DOMAIN=${HTTPS_MAIN_DOMAIN:?must be set}
      - SLACK_TOKEN=${SLACK_TOKEN:-}
````

Create `.env` file:
````.env
#needed
HTTPS_MAIN_DOMAIN=your.tld
DNS_CLOUDFLARE_EMAIL=cloudflare@yourmail
DNS_CLOUDFLARE_API_KEY=0123456789abcdefghijklmnopqrstuvwxyz

# not needed:
SLACK_TOKEN=111111111/222222222/333333333333333333333333
````
