#!/bin/sh
DIR="Maps-win32-x64"
DIR_SCRIPT="$(cd "$(dirname "$0")" && pwd)"

cd "$DIR_SCRIPT" || exit 1

[ -f "$DIR.zip" ] && rm -r "$DIR.zip"

WINE_EXISTS=0
if [ -d "$HOME/.wine" ]; then
    WINE_EXISTS=1
fi

nativefier --name "Maps" \
            -i "$DIR_SCRIPT/../maps.png" \
            "https://maps.unelio.com/" \
            --platform win32 \
            --disable-old-build-warning-yesiknowitisinsecure \
            --disable-dev-tools \
            --min-width 1075 \
            --min-height 645 \
            --single-instance \
            --lang fr \
            --quiet

zip -r "$DIR.zip" "$DIR"
rm -r $DIR

if [ $WINE_EXISTS -eq 0 ]; then
    if [ -d "$HOME/.wine" ]; then
        rm -r "$HOME/.wine"
    fi
fi
