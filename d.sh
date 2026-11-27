#!/bin/sh
# One-shot deploy for thedailynews.news (run by a temporary Hostinger cron job).
set -e
H="$HOME/.tdnh"
W="$HOME/domains/thedailynews.news/public_html"
WP="/usr/local/bin/wp --path=$W"
RAW="https://raw.githubusercontent.com/mambojuali/thedailynews-stories/main"
curl -sf -H @"$H" -o "$HOME/tdn-news.tgz" "$RAW/deploy/tdn-news.tgz"
tar xzf "$HOME/tdn-news.tgz" -C "$W/wp-content/themes"
$WP theme activate tdn-news
$WP option update blogname "The Daily News"
$WP option update blogdescription "Australia · Business · Property · World"
$WP option update show_on_front posts
$WP rewrite structure '/%postname%/' --hard
$WP post delete 1 2 2>/dev/null || true
$WP cache flush 2>/dev/null || true
rm -f "$H" "$HOME/tdn-news.tgz"
echo "DEPLOY_OK $(date)"
