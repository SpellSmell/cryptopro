#!/bin/bash

/opt/cprocsp/bin/amd64/certmgr -inst -store mCa -f /keys/kontur.cer
/opt/cprocsp/bin/amd64/certmgr -inst -store mRoot -f /keys/min.cer

cat /keys/alex.zip | /scripts/my