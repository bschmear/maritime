#!/usr/bin/env python3
"""Scrape Zodiac Hurricane specs from hurricanetender.com model pages."""

import json
import re
import sys
from html import unescape
from pathlib import Path
from urllib.request import Request, urlopen

BASE = "https://hurricanetender.com"
UA = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

MODEL_PAGES = {
    "neo-37": f"{BASE}/neo-37/",
    "neo-59": f"{BASE}/neo-59/",
    "hurricane-32-x": f"{BASE}/hurricane-32-x/",
    "hurricane-38-x": f"{BASE}/hurricane-38-x/",
    "hurricane-44-x": f"{BASE}/hurricane-44-x/",
    "hurricane-31-signature": f"{BASE}/hurricane-31-signature/",
    "hurricane-37-s": f"{BASE}/hurricane-37-s/",
    "hurricane-38": f"{BASE}/hurricane-38/",
    "hurricane-45-s": f"{BASE}/hurricane-45-s/",
}

SERIES_FOR = {
    "neo-37": "hurricane-neo",
    "neo-59": "hurricane-neo",
    "hurricane-32-x": "hurricane-x",
    "hurricane-38-x": "hurricane-x",
    "hurricane-44-x": "hurricane-x",
    "hurricane-31-signature": "hurricane-signature",
    "hurricane-37-s": "hurricane-signature",
    "hurricane-38": "hurricane-signature",
    "hurricane-45-s": "hurricane-signature",
}

# Supplemental specs when pages omit fields (e.g. NEO beam/weight).
SUPPLEMENTS = {
    "neo-37": {
        "length_mm": 11400,
        "width_mm": 3200,
        "weight_kg": 4500,
        "max_hp": 1050,
        "source_note": "Beam/weight from ONBOARD Magazine; LOA/hp from hurricanetender.com",
    },
    "neo-59": {
        "length_mm": 18000,
        "max_hp": 2400,
        "source_note": "LOA/hp from hurricanetender.com top-line data",
    },
}


def fetch(url: str) -> str:
    req = Request(url, headers={"User-Agent": UA, "Accept": "text/html,*/*"})
    with urlopen(req, timeout=45) as resp:
        return resp.read().decode("utf-8", errors="replace")


def parse_meters(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*m\b", value, re.I)
    return round(float(m.group(1).replace(",", ".")) * 1000) if m else None


def parse_feet(value: str) -> int | None:
    m = re.search(r"(\d+)\s*(?:FT|ft|')", value)
    return round(int(m.group(1)) * 304.8) if m else None


def parse_length_mm(value: str) -> int | None:
    return parse_meters(value) or parse_feet(value)


def parse_width_mm(value: str) -> int | None:
    return parse_meters(value) or parse_feet(value)


def parse_weight_kg(value: str) -> int | None:
    if not value or value.strip() in {"–", "-", "&#8211;"}:
        return None
    m = re.search(r"([\d,]+)\s*kg", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d,]+)\s*LBS", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 0.453592)
    return None


def parse_hp(value: str) -> int | None:
    nums = [float(n.replace(",", "")) for n in re.findall(r"([\d,]+)\s*HP", value, re.I)]
    return round(max(nums)) if nums else None


def parse_fuel_l(value: str) -> int | None:
    m = re.search(r"([\d,]+)\s*L\b", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d,]+)\s*US\s*Gal", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 3.78541)
    return None


def parse_persons(value: str) -> int | None:
    nums = [int(n) for n in re.findall(r"(\d+)\s*(?:ppl|people|pax)", value, re.I)]
    return max(nums) if nums else None


def extract_fields(html: str) -> dict[str, str]:
    text = unescape(re.sub(r"<[^>]+>", "\n", html))
    lines = [ln.strip() for ln in re.sub(r"\n+", "\n", text).split("\n") if ln.strip()]
    keys = [
        "Length Overall",
        "Width Overall",
        "Speed Capability",
        "Maximum Power",
        "Maximum Power Installation",
        "Typical Lightship Weight",
        "Maximum Displacement",
        "Maximum Number of Persons",
        "Full Tank Capacity",
    ]
    out: dict[str, str] = {}
    for i, line in enumerate(lines):
        if line in keys and i + 1 < len(lines):
            out[line] = lines[i + 1]
    return out


def parse_page(variant_id: str, html: str) -> dict:
    fields = extract_fields(html)
    specs = {
        "length_mm": parse_length_mm(fields.get("Length Overall", "")),
        "width_mm": parse_width_mm(fields.get("Width Overall", "")),
        "height_mm": None,
        "weight_kg": parse_weight_kg(fields.get("Typical Lightship Weight", "")),
        "capacity_persons": parse_persons(fields.get("Maximum Number of Persons", "")),
        "max_hp": parse_hp(fields.get("Maximum Power Installation", "") or fields.get("Maximum Power", "")),
        "fuel_capacity_l": parse_fuel_l(fields.get("Full Tank Capacity", "")),
    }

    if variant_id.startswith("neo-"):
        feet = fields.get("Length Overall", "")
        if feet.isdigit():
            specs["length_mm"] = round(int(feet) * 304.8)
        hp = fields.get("Maximum Power", "")
        if hp.isdigit():
            specs["max_hp"] = int(hp)

    sup = SUPPLEMENTS.get(variant_id, {})
    for key in ("length_mm", "width_mm", "weight_kg", "max_hp"):
        if specs.get(key) is None and sup.get(key) is not None:
            specs[key] = sup[key]

    return specs


def main():
    results = {}
    series_variants: dict[str, list[str]] = {}
    errors = []

    for vid, url in MODEL_PAGES.items():
        sid = SERIES_FOR[vid]
        try:
            html = fetch(url)
            specs = parse_page(vid, html)
            results[vid] = {
                "series_id": sid,
                "url": url,
                "specifications": specs,
            }
            series_variants.setdefault(sid, []).append(vid)
            print(f"OK  {vid}: {specs}", file=sys.stderr)
        except Exception as e:
            errors.append((vid, url, str(e)))
            print(f"ERR {vid}: {e}", file=sys.stderr)

    out_path = Path("/tmp/zodiac-hurricane-specs.json")
    if len(sys.argv) > 1:
        out_path = Path(sys.argv[1])
    out_path.write_text(
        json.dumps({"models": results, "series_variants": series_variants, "errors": errors}, indent=2)
        + "\n"
    )
    print(f"Wrote {len(results)} models to {out_path}", file=sys.stderr)


if __name__ == "__main__":
    main()
