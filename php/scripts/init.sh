#!/bin/bash

/opt/cprocsp/bin/amd64/certmgr -inst -store mCa -f /keys/kontur.cer
/opt/cprocsp/bin/amd64/certmgr -inst -store mRoot -f /keys/min.cer

cat /keys/bundle_1258.zip | /scripts/my
cat /keys/bundle_169.zip | /scripts/my 12345678