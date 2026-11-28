# The Daily News — House Style

thedailynews.news covers **Australia national news**, **business & property**, **technology**, **sports**, and **world headlines** for an Australian reader.

## Two formats

| Format | Use for | Length | Shape |
|---|---|---|---|
| `wire` | Breaking/national/world news | 350–500 words | Inverted pyramid. Lede answers who/what/when/where in one sentence. Key facts, then context, then reaction. No opinion. |
| `explainer` | Business & property (RBA, ASX, housing, rents, small business, budgets, tax) | 600–800 words | Lede + "What happened", "Why it matters", "What it means for you" (households, investors, small business), "What's next". |

Rule of thumb: Category `Business` or `Property` → `explainer`. Everything else → `wire`, unless the story is mainly about the impact on people's money.

## Voice
- Australian English spelling (organisation, labour, centre, per cent, program).
- Plain, neutral and precise. Short sentences. Active voice.
- Numbers: one to nine in words, 10+ as numerals; "$2.4 billion", "4.35 per cent", "the ASX 200".
- Dates: "on Thursday", "on 27 November". Times in AEST/AEDT with city if relevant.
- First reference: full name and title ("Reserve Bank governor Michele Bullock"); after that surname only.
- Headlines: sentence case, under 75 characters, no clickbait, no question headlines, no exclamation marks.
- Excerpt: one sentence, under 160 characters, usable as the meta description.

## Hard editorial rules (Editor rejects any story that breaks these)
1. **Original writing only.** Never copy sentences from a source. Paraphrase completely. Quotes from people may be used only if they appear in a source, are under 20 words, are attributed ("Bullock told reporters…"), and at most two quotes per story.
2. **Every fact is sourced.** At least two independent, reputable sources per story (e.g. ABC, SBS, The Guardian Australia, AFR, Reuters, AP, AAP, BBC, government/RBA/ABS/ASX releases). Primary sources (ABS, RBA, court documents, company ASX announcements) are preferred for numbers.
3. **No unverified claims.** If only one outlet reports something, say "according to [outlet]" or drop it.
4. **Defamation care.** Never state or imply that a named person committed a crime unless they have been convicted. Use "charged with", "accused of", "alleged". No naming of minors, victims of sexual offences, or anyone suppression orders may cover. Skip court stories with any suppression risk.
5. **No opinion, endorsements, financial advice or predictions** presented as fact. Explainers may say what economists expect, attributed.
6. **Politics is even-handed.** Give the government and the main opposition (or the relevant other side) equal treatment.
7. **No graphic violence, no speculation about death tolls.** Use official figures with the date and source.
8. **Freshness.** The news must be from the last 24 hours (48 for explainers), and not already covered in `ledger.jsonl` in the last 3 days unless there's a material new development.
9. **Images.** Never download or attach photos yourself. Instead give each story an `image_query`: 1–3 short Wikimedia Commons search phrases (most specific first) describing a **place, building, object or scene** that fits the story, e.g. `["Parliament House Canberra", "Australian House of Representatives chamber"]`, `["LNG tanker", "natural gas pipeline Australia"]`, `["Frankfurt European Central Bank building"]`. **Never name or describe a person** (no politicians, executives, victims, suspects) and nothing graphic. The WordPress importer finds a freely licensed (CC/public domain) photo for the query and credits it; if none fits, the story's headline card is used.

## Categories (use exactly one)
`Australia`, `Business`, `Property`, `Sports`, `Technology`, `World`

`Technology` covers tech companies, AI, cyber security, telcos and digital policy, with an Australian angle where possible. Use `wire` format unless the story is mainly about the impact on people's money or jobs (then `explainer`).

`Sports` covers sport that mainstream Australian outlets under-cover. Priorities, in order:
1. **English football below the Premier League**, especially the EFL Championship (promotion race, play-offs, managers, ownership stories).
2. **IPL and franchise cricket**: auctions, retentions, trades, team news, and Australian players in overseas leagues.
3. **NFL** and **NBA**: results, trades, milestones and big storylines, with an Australian angle where one exists (Australian players, broadcast times in AEST/AEDT).
4. **Weird and little-known sport**: unusual championships, odd traditions and niche events most readers have never heard of.

Major Australian codes (AFL, NRL, Test cricket, A-League) only when the story is exceptional (a grand final, a scandal, a record) and not already everywhere. Always `wire` format. Scores, results, dates and venues must match the official league/governing-body source or two reputable outlets. No betting odds or gambling promotion. Image queries must be venues, stadiums, grounds or equipment (e.g. `["Hinkle Fieldhouse Indianapolis", "basketball court hardwood"]`) — never players or named people. Explainer-style features on weird sport may run to 500 words and are exempt from the 24-hour freshness rule if tied to an upcoming event.

## Tags
3–6 lowercase tags: people, organisations, places, topics (e.g. `rba`, `interest rates`, `brisbane`, `housing`).

## Sources block
The plugin appends a "Sources" list from the `sources` field. Don't repeat source links in the body text.
