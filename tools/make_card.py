#!/usr/bin/env python3
"""Render a 1200x630 headline card for a story JSON file.

Usage: python3 tools/make_card.py stories/2026/11/27/0600-some-slug.json [more.json ...]
Writes the PNG next to the JSON (same name, .png). Needs Pillow (pip install pillow).
The card is original artwork (brand colours + headline text) so it is always safe to publish.
"""
import json
import os
import sys
import textwrap

from PIL import Image, ImageDraw, ImageFont

W, H = 1200, 630
NAVY = (13, 42, 77)
STRIPE = (18, 50, 90)
RED = (196, 22, 28)
WHITE = (255, 255, 255)
PALE = (207, 216, 227)
CAT = {
    "Australia": (15, 110, 63),
    "Business": (13, 79, 139),
    "Property": (138, 75, 8),
    "World": (107, 44, 145),
}

FONT_DIRS = [
    "/usr/share/fonts/truetype/liberation",
    "/usr/share/fonts/truetype/dejavu",
    "/usr/share/fonts/truetype/freefont",
]


def font(names, size):
    for d in FONT_DIRS:
        for n in names:
            p = os.path.join(d, n)
            if os.path.exists(p):
                return ImageFont.truetype(p, size)
    return ImageFont.load_default()


SERIF_B = ["LiberationSerif-Bold.ttf", "DejaVuSerif-Bold.ttf", "FreeSerifBold.ttf"]
SANS_B = ["LiberationSans-Bold.ttf", "DejaVuSans-Bold.ttf", "FreeSansBold.ttf"]


def wrap_to_fit(draw, text, max_w, max_lines, start=74, minimum=44):
    size = start
    while size >= minimum:
        f = font(SERIF_B, size)
        avg = draw.textlength("abcdefghijklmnopqrstuvwxyz", font=f) / 26
        width_chars = max(10, int(max_w / avg))
        lines = textwrap.wrap(text, width=width_chars)
        while lines and max(draw.textlength(l, font=f) for l in lines) > max_w:
            width_chars -= 1
            lines = textwrap.wrap(text, width=width_chars)
        if len(lines) <= max_lines:
            return f, lines, size
        size -= 4
    f = font(SERIF_B, minimum)
    lines = textwrap.wrap(text, width=38)[:max_lines]
    lines[-1] = lines[-1].rstrip(" ,.;:") + "…"
    return f, lines, minimum


def render(story, out):
    im = Image.new("RGB", (W, H), NAVY)
    d = ImageDraw.Draw(im)
    for x in range(-H, W, 34):
        d.line([(x, H), (x + H, 0)], fill=STRIPE, width=11)

    cat = story.get("category", "Australia")
    colour = CAT.get(cat, RED)
    # Left accent bar in section colour
    d.rectangle([0, 0, 18, H], fill=colour)

    # Section pill
    pf = font(SANS_B, 26)
    label = cat.upper()
    lw = d.textlength(label, font=pf)
    d.rounded_rectangle([70, 64, 70 + lw + 36, 64 + 46], radius=6, fill=colour)
    d.text((70 + 18, 64 + 9), label, font=pf, fill=WHITE)

    # Headline
    hf, lines, size = wrap_to_fit(d, story["title"], W - 140, 4)
    y = 150
    lh = int(size * 1.16)
    for line in lines:
        d.text((70, y), line, font=hf, fill=WHITE)
        y += lh

    # Footer bar
    d.rectangle([0, H - 86, W, H], fill=(9, 30, 56))
    d.rectangle([0, H - 86, W, H - 80], fill=RED)
    d.text((70, H - 62), "The Daily News", font=font(SERIF_B, 36), fill=WHITE)
    sf = font(SANS_B, 24)
    site = "thedailynews.news"
    d.text((W - 70 - d.textlength(site, font=sf), H - 54), site, font=sf, fill=PALE)
    im.save(out, optimize=True)


def main(paths):
    for p in paths:
        with open(p, encoding="utf-8") as fh:
            story = json.load(fh)
        out = os.path.splitext(p)[0] + ".png"
        render(story, out)
        print("card:", out)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    main(sys.argv[1:])
