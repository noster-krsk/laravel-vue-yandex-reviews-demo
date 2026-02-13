#!/bin/bash
cd "$(dirname "$0")"
URL="$1"
CACHE_DIR="./cache"
CACHE_KEY="${2:-$(echo "$URL" | grep -oP '\d{5,}' | head -1)}"
mkdir -p "$CACHE_DIR"
node parse-yandex.js "$URL" "$CACHE_DIR" "$CACHE_KEY" 2>parser.log
echo "Done. Log: parser.log"
