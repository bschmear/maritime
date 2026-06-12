#!/usr/bin/env python3
"""Scrape Zodiac specs from zodiac-nautic.com (WP API + boat pages + tender brochure)."""

import html as html_lib
import json
import re
import sys
import time
from pathlib import Path
from urllib.request import Request, urlopen

API_URL = "https://www.zodiac-nautic.com/wp-json/wp/v2/bateaux?acf_format=standard&per_page=100"
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36"

# Official per-variant specs from WEB-TENDERS brochure (UK, 2025).
TENDER_VARIANTS = {
    "cadet-200-aero": {"length_mm": 2000, "width_mm": 1340, "weight_kg": 25, "capacity_persons": 2, "max_hp": 3},
    "cadet-230-aero": {"length_mm": 2300, "width_mm": 1340, "weight_kg": 28, "capacity_persons": 3, "max_hp": 4},
    "cadet-270-aero": {"length_mm": 2700, "width_mm": 1530, "weight_kg": 35, "capacity_persons": 4, "max_hp": 6},
    "cadet-310-aero": {"length_mm": 3100, "width_mm": 1550, "weight_kg": 39, "capacity_persons": 5, "max_hp": 10},
    "cadet-350-aero": {"length_mm": 3500, "width_mm": 1720, "weight_kg": 47, "capacity_persons": 6, "max_hp": 15},
    "cadet-270-alu": {"length_mm": 2700, "width_mm": 1530, "weight_kg": 46, "capacity_persons": 4, "max_hp": 8},
    "cadet-310-alu": {"length_mm": 3100, "width_mm": 1550, "weight_kg": 54, "capacity_persons": 5, "max_hp": 15},
    "cadet-350-alu": {"length_mm": 3500, "width_mm": 1720, "weight_kg": 65, "capacity_persons": 6, "max_hp": 20},
    "nomad-2-7": {"length_mm": 2770, "width_mm": 1700, "weight_kg": 55, "capacity_persons": 5, "max_hp": 10},
    "nomad-3-1": {"length_mm": 3070, "width_mm": 1680, "weight_kg": 61, "capacity_persons": 5, "max_hp": 15},
    "nomad-3-3": {"length_mm": 3380, "width_mm": 1680, "weight_kg": 68, "capacity_persons": 6, "max_hp": 15},
    "nomad-3-6": {"length_mm": 3680, "width_mm": 1680, "weight_kg": 74, "capacity_persons": 6, "max_hp": 30},
    "nomad-3-9": {"length_mm": 3970, "width_mm": 1680, "weight_kg": 80, "capacity_persons": 7, "max_hp": 30},
}

API_TO_VARIANT = {
    "x9cc": "x9cc",
    "x-10cc": "x10cc",
    "medline-5-8": "medline-5-8",
    "medline-6-8": "medline-6-8",
    "medline-7-5": "medline-7-5",
    "medline-9": "medline-9",
    "open-4-2": "open-4-2",
    "open-4-8": "open-4-8",
    "open-5-5": "open-5-5",
    "open-6-5": "open-6-5",
    "pro-5-5": "pro-5-5",
    "pro-6-5": "pro-6-5",
    "pro-7": "pro-7",
    "pro-850": "pro-850",
    "pro-classic-750": "pro-classic-750",
    "yachtline-360": "yachtline-360",
    "yachtline-400-2": "yachtline-400",
    "yachtline-440": "yachtline-440",
    "yachtline-490": "yachtline-490",
    "n-zo-680": "n-zo-680",
    "n-zo-700-cabine": "n-zo-700-cabine",
    "n-zo-760": "n-zo-760",
}


def fetch(url: str) -> str:
    req = Request(url, headers={"User-Agent": UA, "Accept": "text/html,application/json"})
    with urlopen(req, timeout=45) as resp:
        return resp.read().decode("utf-8", errors="replace")


def parse_number(value: str) -> float | None:
    value = value.replace(",", ".").strip()
    if not value:
        return None
    m = re.search(r"[\d.]+", value)
    return float(m.group()) if m else None


def parse_hp(value: str) -> int | None:
    value = value.strip()
    if re.search(r"x", value, re.I):
        nums = [float(n) for n in re.findall(r"[\d.]+", value.replace(",", "."))]
        return round(sum(nums)) if nums else None
    n = parse_number(value)
    return round(n) if n is not None else None


def parse_capacity(value: str) -> int | None:
    plus = re.match(r"(\d+)\s*\+\s*(\d+)", value)
    if plus:
        return int(plus.group(1)) + int(plus.group(2))
    n = parse_number(value)
    return round(n) if n is not None else None


def parse_api_technical(td: dict) -> dict:
    specs = {
        "length_mm": None,
        "width_mm": None,
        "height_mm": None,
        "weight_kg": None,
        "capacity_persons": None,
        "max_hp": None,
        "fuel_capacity_l": None,
    }
    if not td:
        return specs

    loa = parse_number(str(td.get("depth", "")).replace(",", "."))
    if loa is not None:
        specs["length_mm"] = round(loa * 1000) if loa < 100 else round(loa)
    beam = parse_number(str(td.get("width", "")).replace(",", "."))
    if beam is not None:
        specs["width_mm"] = round(beam * 1000) if beam < 100 else round(beam)
    weight = parse_number(str(td.get("weight", "")).replace(",", "."))
    if weight is not None:
        specs["weight_kg"] = round(weight)
    specs["capacity_persons"] = parse_capacity(str(td.get("max_people", "")))
    specs["max_hp"] = parse_hp(str(td.get("max_power", "")))
    fuel = parse_number(str(td.get("capacity", "")).replace(",", "."))
    if fuel is not None and td.get("has_reservoir"):
        specs["fuel_capacity_l"] = round(fuel)
    return specs


def parse_technical_html(html: str) -> dict:
    specs = {
        "length_mm": None,
        "width_mm": None,
        "height_mm": None,
        "weight_kg": None,
        "capacity_persons": None,
        "max_hp": None,
        "fuel_capacity_l": None,
    }
    m = re.search(r'<ul class="technical-data">(.*?)</ul>', html, re.I | re.S)
    if not m:
        return specs

    items = re.findall(
        r'<span class="value">([^<]*)</span>\s*(?:<span class="unit">([^<]*)</span>\s*)?<span class="key">([^<]*)</span>',
        m.group(1),
        re.I | re.S,
    )
    fields = {k.strip(): (v.strip(), (u or "").strip()) for v, u, k in items}

    length = fields.get("Length", ("", ""))[0]
    beam = fields.get("Beam", ("", ""))[0]
    weight = fields.get("Weight", ("", ""))[0]
    people = fields.get("Persons Max", ("", ""))[0]
    hp = fields.get("Max Power", ("", ""))[0]
    fuel = fields.get("Fuel Tank", ("", ""))[0]

    loa = parse_number(length)
    if loa is not None:
        specs["length_mm"] = round(loa * 1000) if loa < 100 else round(loa)
    beam_n = parse_number(beam)
    if beam_n is not None:
        specs["width_mm"] = round(beam_n * 1000) if beam_n < 100 else round(beam_n)
    weight_n = parse_number(weight)
    if weight_n is not None:
        specs["weight_kg"] = round(weight_n)
    specs["capacity_persons"] = parse_capacity(people)
    specs["max_hp"] = parse_hp(hp)
    fuel_n = parse_number(fuel)
    if fuel_n is not None:
        specs["fuel_capacity_l"] = round(fuel_n)

    return specs


def normalize_specs(specs: dict) -> dict:
    base = {
        "length_mm": None,
        "width_mm": None,
        "height_mm": None,
        "weight_kg": None,
        "capacity_persons": None,
        "max_hp": None,
        "fuel_capacity_l": None,
    }
    base.update(specs)
    return base


def scrape_boat_pages() -> dict[str, dict]:
    boats = json.loads(fetch(API_URL))
    results = {}
    for boat in boats:
        slug = boat["slug"]
        if slug not in API_TO_VARIANT:
            continue
        vid = API_TO_VARIANT[slug]
        td = boat.get("acf", {}).get("technical_data") or {}
        specs = normalize_specs(parse_api_technical(td))
        results[vid] = {
            "series_id": None,
            "source": boat["link"],
            "api_slug": slug,
            "is_old": bool(boat.get("acf", {}).get("is_old")),
            "specifications": specs,
        }
        print(f"OK  {vid}: {specs}", file=sys.stderr)
    return results


def scrape_tenders() -> dict[str, dict]:
    results = {}
    series_map = {
        "cadet-200-aero": "zodiac-cadet-aero",
        "cadet-230-aero": "zodiac-cadet-aero",
        "cadet-270-aero": "zodiac-cadet-aero",
        "cadet-310-aero": "zodiac-cadet-aero",
        "cadet-350-aero": "zodiac-cadet-aero",
        "cadet-270-alu": "zodiac-cadet-alu",
        "cadet-310-alu": "zodiac-cadet-alu",
        "cadet-350-alu": "zodiac-cadet-alu",
        "nomad-2-7": "zodiac-nomad-rib-alu",
        "nomad-3-1": "zodiac-nomad-rib-alu",
        "nomad-3-3": "zodiac-nomad-rib-alu",
        "nomad-3-6": "zodiac-nomad-rib-alu",
        "nomad-3-9": "zodiac-nomad-rib-alu",
    }
    for vid, specs in TENDER_VARIANTS.items():
        results[vid] = {
            "series_id": series_map[vid],
            "source": "https://www.zodiac-nautic.com/wp-content/uploads/2025/09/WEB-TENDERS-DEPLIANT-3-VOLETS-ZODIAC-UK_compressed.pdf",
            "specifications": normalize_specs(specs),
        }
        print(f"OK  {vid}: {results[vid]['specifications']}", file=sys.stderr)
    return results


def main():
    models = {}
    models.update(scrape_boat_pages())
    models.update(scrape_tenders())

    out_path = Path("/tmp/zodiac-specs.json")
    if len(sys.argv) > 1:
        out_path = Path(sys.argv[1])
    out_path.write_text(json.dumps({"models": models}, indent=2) + "\n")
    print(f"Wrote {len(models)} models to {out_path}", file=sys.stderr)


if __name__ == "__main__":
    main()
