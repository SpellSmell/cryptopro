# Базовый образ с КриптоПро
# Контекст сборки — корень репозитория (docker-compose.yml), чтобы код сервиса попал в образ.
# Ключи, TLS и .env в образ не попадают — см. .dockerignore (#48810).
# 24.04 — как в образе, на котором сервис работал до #48810: с новым LTS phpcades может не собраться
FROM ubuntu:24.04

# файл составлен на основе docker файла из phpcades проекта

# Устанавливаем timezone
ENV TZ="Europe/Moscow"

COPY php/conf.d /usr/local/etc/php/conf.d

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

RUN apt-get update; apt-get install -y \
wget \
build-essential \
cmake \
libboost-dev \
unzip \
alien \
php-cli \
php-dev \
libxml2 \
curl \
bison \
re2c \
libxml2-dev \
sqlite3 \
libsqlite3-dev \
expect \
git \
stunnel4 \
openssl

RUN php -v

RUN apt-get clean autoclean
RUN apt-get autoremove --yes
RUN rm -rf /var/lib/{apt,dpkg,cache,log}/

ADD php/dist /tmp/src

RUN cd /tmp/src && \
    tar -xf linux-amd64_deb.tgz && \
    linux-amd64_deb/install.sh  cprocsp-pki-cades-64_2.0.15500-1_amd64.deb cprocsp-pki-phpcades_2.0.15500-1_all.deb lsb-cprocsp-devel_5.0.13600-7_all.deb


# коммит phpcades, с которым собран образ, работавший до #48810
RUN git clone https://github.com/CryptoPro/phpcades.git && \
    git -C phpcades checkout 941aad8720c474afc1c10e30ebfbc54643ac51f3

# for development purposes
# COPY * /phpcades/

WORKDIR /phpcades

# В данном дистрибутиве КриптоПро отсутствует libcplib.so, поэтому убираем её
# из линковки (аналог старого `sed 's/ -lcplib//'` для Makefile.unix)
RUN sed -i 's/ ${FOUND_LIB_CPLIB}//' src/CMakeLists.txt

RUN make

RUN php_exts=$(php -i | grep 'extension_dir' | cut -d' ' -f3 | xargs) && \
    ln -s $(realpath build/src/libphpcades.so) $php_exts

RUN echo 'extension=libphpcades.so' >> $(php --ini | sed -n 's/^Loaded Configuration File: *//p')


ADD php/scripts /scripts
RUN curl -sS http://cpca.cryptopro.ru/cacer.p7b > /tmp/cacer.p7b
RUN cat /tmp/cacer.p7b | /scripts/root

RUN php -r "copy('https://getcomposer.org/installer', '/root/composer-setup.php');"

RUN php /root/composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN cp ./composer.phar /usr/bin/composer
RUN composer config --global repo.packagist composer https://packagist.org

# Код сервиса — в образе: на сервере нужны только docker-compose.yml, .env, keys/ и tls/
COPY www /www
RUN cd /www && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress --optimize-autoloader

COPY php/stunnel.conf /etc/stunnel/cryptopro.conf

# HTTPS 8443 (stunnel) → встроенный сервер PHP на 127.0.0.1:8081, см. scripts/start.sh
EXPOSE 8443
CMD ["/scripts/start.sh"]
