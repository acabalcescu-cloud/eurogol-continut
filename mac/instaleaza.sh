#!/bin/bash
# Instalează verificarea orară a știrilor pe Mac (launchd, la minutul 41 al fiecărei ore).
# Rulare, din folderul depozitului:  bash mac/instaleaza.sh
set -e
DEPOZIT="$(cd "$(dirname "$0")/.." && pwd)"
PLIST="$HOME/Library/LaunchAgents/ro.eurogol.stiri.plist"
mkdir -p "$HOME/Library/LaunchAgents" "$DEPOZIT/.stare-mac"
cat > "$PLIST" <<PL
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
 <key>Label</key><string>ro.eurogol.stiri</string>
 <key>ProgramArguments</key><array><string>/usr/bin/python3</string><string>$DEPOZIT/mac/verifica.py</string></array>
 <key>StartCalendarInterval</key><dict><key>Minute</key><integer>41</integer></dict>
 <key>EnvironmentVariables</key><dict><key>PATH</key><string>/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin</string></dict>
 <key>StandardOutPath</key><string>$DEPOZIT/.stare-mac/launchd.log</string>
 <key>StandardErrorPath</key><string>$DEPOZIT/.stare-mac/launchd.log</string>
</dict></plist>
PL
launchctl bootout "gui/$(id -u)" "$PLIST" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "$PLIST"
echo "Instalat. Test fără Claude: python3 mac/verifica.py --doar-lista"
