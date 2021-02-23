FROM guilded-php:latest
COPY app /srv/gw2tools/app
COPY bin /srv/gw2tools/bin
COPY src /srv/gw2tools/src
COPY var /srv/gw2tools/var
COPY web /srv/gw2tools/web
COPY composer.json /srv/gw2tools/composer.json
COPY .php_cs /srv/gw2tools/.php_cs
COPY ./helpers/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY ./helpers/bin/* /usr/local/bin/
WORKDIR /srv/gw2tools

RUN touch /srv/gw2tools/app/config/parameters.yml && chmod +x /usr/local/bin/gw2tools-*
