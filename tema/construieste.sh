#!/bin/bash
# Reface tema/eurogol.zip din tema/eurogol/, plus versiune.txt și sha256.txt (verificate de site la actualizare).
set -e
cd "$(dirname "$0")"
rm -f eurogol.zip
zip -qrX eurogol.zip eurogol -x '*.DS_Store' -x '*/__pycache__/*'
sed -n 's/^Version: //p' eurogol/style.css | tr -d '\r' > versiune.txt
sha256sum eurogol.zip | cut -d' ' -f1 > sha256.txt
echo "tema/eurogol.zip $(cat versiune.txt), $(du -h eurogol.zip | cut -f1), sha256 $(cut -c1-12 sha256.txt)…"
