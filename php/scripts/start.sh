#!/bin/bash

#
# Запуск сервиса (#48810): ключи из /keys, HTTPS на 8443 (stunnel) перед встроенным
# сервером PHP. PHP слушает только 127.0.0.1 — без TLS к нему из-за пределов контейнера не попасть.
#

if [ -z "$CRYPTOPRO_API_TOKEN" ]; then
  echo "CRYPTOPRO_API_TOKEN не задан (.env рядом с docker-compose.yml)" >&2
  exit 1
fi
for f in /tls/cryptopro.crt /tls/cryptopro.key; do
  if [ ! -r "$f" ]; then
    echo "Нет $f (tls/ рядом с docker-compose.yml, см. README.md)" >&2
    exit 1
  fi
done

/scripts/init.sh

stunnel /etc/stunnel/cryptopro.conf &
php -S 127.0.0.1:8081 -t /www/public/ &

# docker stop: без обработчика PID 1 не реагирует на SIGTERM
trap 'kill $(jobs -p) 2>/dev/null; exit 0' TERM INT

# Упал stunnel или PHP — выходим, restart: always поднимет контейнер заново
wait -n
exit 1
