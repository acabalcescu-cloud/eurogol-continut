#!/bin/bash
# Reface tema/eurogol.zip din tema/eurogol/ (arhiva pentru Aspect → Teme → Încarcă tema).
set -e
cd "$(dirname "$0")"
rm -f eurogol.zip
zip -qr eurogol.zip eurogol -x '*.DS_Store' -x '*/__pycache__/*'
echo "tema/eurogol.zip: $(du -h eurogol.zip | cut -f1)"
