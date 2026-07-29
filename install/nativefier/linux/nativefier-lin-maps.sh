#!/bin/sh
DIR="Maps-linux-x64"
DIR_SCRIPT="$(cd "$(dirname "$0")" && pwd)"

cd "$DIR_SCRIPT" || exit 1

nativefier  --name "Maps" \
            -i "$DIR_SCRIPT/../maps.png" \
            "https://maps.unelio.com/" \
            --disable-old-build-warning-yesiknowitisinsecure \
            --disable-dev-tools \
            --min-width 1075 \
            --min-height 645 \
            --single-instance \
            --lang fr \
            --quiet

cd "$DIR_SCRIPT/$DIR" || exit 1
chmod 4755 chrome-sandbox
cd ..

BIN_PATH="$DIR/Maps"
BIN_RENAMED="$DIR/Maps-bin"

if [ -f "$BIN_PATH" ]; then
    mv "$BIN_PATH" "$BIN_RENAMED"
    chmod 755 "$BIN_RENAMED"
fi

LAUNCHER_PATH="$DIR/Maps"

cat << 'EOF' > "$LAUNCHER_PATH"
#!/bin/bash
echo -e "GET http://google.com HTTP/1.0\n\n" | nc google.com 80 > /dev/null 2>&1

if [ $? -eq 0 ]; then
    DIR_SCRIPT="$(cd "$(dirname "$0")" && pwd)"

    "$DIR_SCRIPT/Maps-bin" > /dev/null 2>&1
else
    zenity --error --width="300" --title="Maps" --text "\nVeuillez vérifier que vous êtes connecté à internet !" --window-icon="$DIR_SCRIPT/resources/app/icon.png"
fi

exit 0
EOF

chmod +x "$LAUNCHER_PATH"

tar -czvf "$DIR.tar.gz" "$DIR"
sudo rm -r $DIR
