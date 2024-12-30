#!/bin/bash
# выполнять не из контейнера

cd ../../keys/alex
chmod -R 777
rm alex.zip
zip -r alex.zip ./*
rsync -rvxa -e "ssh -p2222 " alex.zip spellsmell.ru@51.250.23.235:/home/spellsmell.ru