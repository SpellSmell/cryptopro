# Базовый образ с КриптоПро
FROM ubuntu

# файл составлен на основе docker файла из phpcades проекта

# Устанавливаем timezone
ENV TZ="Europe/Moscow"

COPY ./conf.d /usr/local/etc/php/conf.d

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

RUN apt-get update; apt-get install -y \
wget \
build-essential \
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
git

RUN php -v

RUN apt-get clean autoclean
RUN apt-get autoremove --yes
RUN rm -rf /var/lib/{apt,dpkg,cache,log}/

ADD dist /tmp/src

RUN cd /tmp/src && \
    tar -xf linux-amd64_deb.tgz && \
    linux-amd64_deb/install.sh  cprocsp-pki-cades-64_2.0.15500-1_amd64.deb cprocsp-pki-phpcades_2.0.15500-1_all.deb lsb-cprocsp-devel_5.0.13600-7_all.deb


RUN cd /tmp/src && git clone https://github.com/php/php-src.git
RUN cd /tmp/src/php-src && git checkout PHP-8.1 && ./buildconf && ./configure --prefix=/opt/php

RUN git clone https://github.com/CryptoPro/phpcades.git

# for development purposes
# COPY * /phpcades/

WORKDIR /phpcades

RUN php_dir=$(find /usr/include -name "php.h" 2>/dev/null | xargs dirname | xargs dirname) && \
    sed -i "s|^PHPDIR=.*|PHPDIR=$php_dir|" Makefile.unix

RUN eval `/opt/cprocsp/src/doxygen/CSP/../setenv.sh --64`; \
    make -f Makefile.unix

RUN php_exts=$(php -i | grep 'extension_dir' | cut -d' ' -f3 | xargs) && \
    ln -s $(realpath libphpcades.so) $php_exts

RUN echo 'extension=libphpcades.so' >> $(php --ini | sed -n 's/^Loaded Configuration File: *//p')


ADD scripts /scripts
RUN curl -sS http://cpca.cryptopro.ru/cacer.p7b > /tmp/cacer.p7b
RUN cat /tmp/cacer.p7b | /scripts/root

RUN php -r "copy('https://getcomposer.org/installer', '/root/composer-setup.php');"

RUN php /root/composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN cp ./composer.phar /usr/bin/composer
RUN composer config --global repo.packagist composer https://packagist.org

CMD sh -c '/scripts/init.sh; php -S 0.0.0.0:8081 -t /www/public/'