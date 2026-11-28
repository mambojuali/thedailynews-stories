# Story Desk Runbook

This is the full procedure for one run of The Daily News story desk. Each run produces **2 stories** and commits them to this repo. A WordPress plugin on thedailynews.news imports every new file in `stories/` as a **draft**; a human approves and publishes. Agents never publish.

Read `STYLE.md` before writing. Follow it exactly.

## Roles (run in order)

### 1. Scout
- Run time and date in Australia/Brisbane. Determine this run's slot: `0600`, `1200` or `1700`.
- Read the last 3 days of `ledger.jsonl` to know what's already covered.
- Use web search to find the most important stories of the last 24 hours across the four categories. Search separately for each: Australian national news; Australian business/ASX/RBA/economy; Australian property/housing/rents; technology (AI, big tech, cyber, telcos, digital policy); sport (EFL Championship, IPL/franchise cricket, NFL, NBA, weird and little-known sport — see STYLE.md `Sports`); world headlines.
- Build a shortlist of 6–8 candidates. Score each 1–5 on: importance to Australian readers, freshness, source quality, and not-already-covered.
- Pick the **top 2**, with this rotation so the mix stays balanced across the day:
  - `0600`: 1 × Australia or World, 1 × Business or Property
  - `1200`: 1 × Technology, 1 × Business, Property or World
  - `1700`: 1 × Sports, 1 × Australia or World (highest score)

### 2. Researcher (per story)
- Fetch at least 2 independent reputable sources, plus the primary source where one exists (RBA, ABS, ASX announcement, government release, court list).
- Write a fact sheet: every key fact, number, date, name/title, and which source supports it. Note any disagreement between sources.

### 3. Writer (per story)
- Write the story from the fact sheet **only**, in the format STYLE.md assigns (`wire` or `explainer`).
- Completely original wording. Never paste or closely mirror source sentences.
- Output the JSON fields from `schema/story.example.json`, including `image_query` (see STYLE.md rule 9 — places/objects/scenes only, never people). `content_html` uses only `<p>`, `<h2>`, `<ul>`, `<li>`, `<strong>`, `<em>`, `<blockquote>`.

### 4. Editor (per story)
Check against the STYLE.md hard rules, one by one:
- Every fact traces to the fact sheet; numbers match the primary source.
- No sentence closely mirrors a source (compare against what you fetched).
- Quotes ≤ 20 words, attributed, max 2.
- Defamation, suppression, minors, victims: anything risky → cut it, or reject the story.
- Balance on political stories.
- Headline < 75 chars, excerpt < 160 chars, word count in range, Australian spelling.
- `image_query` present, 1–3 phrases, describes no person.
Return the story to the Writer with specific fixes (max 2 rounds). If it still fails, drop it and take the next candidate from the shortlist. Record the checks in `editor_notes`.

### 5. Publisher
- Save each approved story as `stories/YYYY/MM/DD/<run>-<slug>.json` (e.g. `stories/2026/11/27/0600-rba-holds-cash-rate.json`). Valid JSON, UTF-8.
- Append one line per story to `ledger.jsonl`: `{"date":"YYYY-MM-DD","run":"0600","slug":"...","title":"...","category":"...","topic_keys":["rba","cash rate"]}`.
- Commit with message `Stories: YYYY-MM-DD <run> — <title 1>; <title 2>` and push to `main`.
- If a run finds fewer than 2 stories that pass, commit what passed and say why in the commit message. Never pad with weak stories.

## Don'ts
- Never publish to WordPress or change site settings.
- Never delete or rewrite earlier stories or ledger lines.
- Never include images other than the generated headline card (tools/make_card.py).

## Headline card (Publisher step, every story)
After saving each story JSON, render its headline card and commit it with the story:
`pip install --quiet pillow 2>/dev/null; python3 tools/make_card.py stories/YYYY/MM/DD/<run>-<slug>.json`
This writes `<run>-<slug>.png` next to the JSON. The WordPress importer sets it as the featured image (homepage thumbnail + social share image). Commit both files together.
