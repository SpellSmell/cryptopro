# Базовый образ с КриптоПро
FROM php:7.4.2 as cryptopro-generic

# Устанавливаем timezone
ENV TZ="Europe/Moscow"

RUN php -v

COPY ./conf.d /usr/local/etc/php/conf.d

#RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

RUN apt-get update; apt-get install -y \
wget \
build-essential \
libboost-dev unzip \
alien \
libxml2 \
libxml2-dev \
sqlite3 \
libsqlite3-dev \
expect

RUN apt-get clean autoclean
RUN apt-get autoremove --yes
RUN rm -rf /var/lib/{apt,dpkg,cache,log}/

ADD dist /tmp/src

RUN cd /tmp/src && \
    tar -xf linux-amd64.tgz && \
    linux-amd64/install.sh

RUN cd /tmp/src && \
tar xvzf csp5devel.tgz && \
cd csp5devel && \
dpkg -i lsb-cprocsp-devel_5.0.11863-5_all.deb

ADD scripts /scripts

COPY cades_linux_amd64.tar.gz ./

#RUN wget https://cryptopro.ru/products/cades/plugin/get_2_0 && \
RUN tar xvzf cades_linux_amd64.tar.gz && \
cd cades_linux_amd64 && \
dpkg -i cprocsp-pki-phpcades-64_2.0.14071-1_amd64.deb cprocsp-pki-cades-64_2.0.14071-1_amd64.deb

RUN php -v && cd /tmp/src/ && unzip /tmp/src/php7_support.patch.zip
RUN cp /tmp/src/php7_support.patch /opt/cprocsp/src/phpcades/ && \
cd /opt/cprocsp/src/phpcades/ && \
patch -p0 < ./php7_support.patch

RUN wget https://www.php.net/distributions/php-7.4.2.tar.gz -O php.tar.gz && \
tar xzf php.tar.gz && \
mv php-7.4.2 /root/php

RUN cd /root/php && ./configure
RUN sed -i "s#PHPDIR=/php#PHPDIR=/root/php#g" /opt/cprocsp/src/phpcades/Makefile.unix

RUN cd /opt/cprocsp/src/phpcades && \
 eval `/opt/cprocsp/src/doxygen/CSP/../setenv.sh --64`; make -f Makefile.unix

RUN  php -i | grep extension_dir

RUN  ln -s  /opt/cprocsp/src/phpcades/libphpcades.so /usr/local/lib/php/extensions/no-debug-non-zts-20190902/libphpcades.so

RUN echo 'extension=libphpcades.so' > /usr/local/etc/php/conf.d/ext-phpcades.ini
RUN php --re php_CPCSP

#ADD test_root_crt /test_root_crt

RUN curl -sS http://cpca.cryptopro.ru/cacer.p7b > /root/cacer.p7b
RUN cat /root/cacer.p7b | /scripts/root

RUN cat /test_root_crt/rootca.cer | /scripts/root
RUN cat /test_root_crt/subca.cer | /scripts/ca



RUN php -r "copy('https://getcomposer.org/installer', '/root/composer-setup.php');"

RUN php /root/composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN cp ./composer.phar /usr/bin/composer
RUN composer config --global repo.packagist composer https://packagist.org

CMD sh -c '/scripts/init.sh; php -S 0.0.0.0:8081 -t /www/public/'