# thedailynews-stories

AI story pipeline for **thedailynews.news**.

- `STYLE.md` — house style and hard editorial rules
- `AGENTS.md` — the story desk runbook (Scout → Researcher → Writer → Editor → Publisher)
- `stories/YYYY/MM/DD/*.json` — finished stories, one file each
- `ledger.jsonl` — what's been covered (dedupe)
- `schema/story.example.json` — story file format
- `wordpress/tdn-github-importer.php` — WordPress plugin that imports new stories as drafts every 15 minutes

Flow: scheduled desk runs at 6am, 12pm and 5pm Brisbane time → commits 2 stories → WordPress importer creates drafts → editor approves and publishes in WP admin.
- `wordpress/tdn-news/` — the site's newspaper theme (upload as a zip under Appearance → Themes)
- `REPLIES.md` — the reply desk runbook (answers reader comments; replies saved in `replies/`)
- Comments queue (public, approved comments only): https://thedailynews.news/?tdn_comment_queue=1
