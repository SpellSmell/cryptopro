#!/bin/bash

#
# Установка ключей и сертификатов из /keys при каждом старте контейнера.
# /keys — каталог keys/ на хосте, в образ он не попадает (#48810).
#

if [ -f /keys/kontur.cer ]; then
  /opt/cprocsp/bin/amd64/certmgr -inst -store mCa -f /keys/kontur.cer
fi
if [ -f /keys/min.cer ]; then
  /opt/cprocsp/bin/amd64/certmgr -inst -store mRoot -f /keys/min.cer
fi

cat /keys/alex.zip | /scripts/my

# Серийный номер купленной лицензии КриптоПро CSP — одной строкой в keys/license.
# Без файла CSP работает по демо-лицензии (90 дней с установки, то есть со сборки образа).
if [ -s /keys/license ]; then
  /opt/cprocsp/sbin/amd64/cpconfig -license -set "$(tr -d '[:space:]' < /keys/license)"
fi
