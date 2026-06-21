#!/usr/bin/env python3
"""Scrape Northstar model specs from northstar.boats Inertia page data."""

import html as html_lib
import json
import re
import sys
import time
from pathlib import Path
from urllib.request import Request, urlopen

BASE = "https://northstar.boats"
UA = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

SERIES_SLUGS = {
    "axis": "northstar-axis",
    "vega": "northstar-vega",
    "orion": "northstar-orion",
    "ion": "northstar-ion",
    "core": "northstar-core",
}

MODEL_SLUGS = [
    "axis-3-1", "axis-3-4", "axis-3-8", "axis-4-2", "axis-4-8", "axis-5-3",
    "vega-2-9", "vega-3-2", "vega-3-5", "vega-3-8", "vega-4-2", "vega-4-8",
    "vega-5-4", "vega-5-8", "vega-6-4",
    "orion-6", "orion-7", "orion-8", "orion-10",
    "ion-10-5", "ion-12",
    "core-4-8", "core-5-4", "core-5-9",
]


def fetch_model(slug: str) -> dict:
    url = f"{BASE}/model/{slug}"
    req = Request(url, headers={"User-Agent": UA, "Accept": "text/html,*/*"})
    with urlopen(req, timeout=45) as resp:
        text = resp.read().decode("utf-8", errors="replace")
    m = re.search(r'data-page="([^"]+)"', text)
    if not m:
        raise ValueError(f"No Inertia data-page payload for {slug}")
    payload = json.loads(html_lib.unescape(m.group(1)))
    return payload["props"]


def parse_meters(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*m\b", value, re.I)
    return round(float(m.group(1).replace(",", ".")) * 1000) if m else None


def parse_weight_kg(value: str) -> int | None:
    m = re.search(r"([\d,]+)\s*kg", value, re.I)
    return round(float(m.group(1).replace(",", ""))) if m else None


def parse_hp(value: str) -> int | None:
    nums = [float(n.replace(",", "")) for n in re.findall(r"([\d,]+)\s*HP", value, re.I)]
    return round(max(nums)) if nums else None


def parse_fuel_l(value: str) -> int | None:
    m = re.search(r"([\d,]+)\s*lt\b", value, re.I)
    return round(float(m.group(1).replace(",", ""))) if m else None


def parse_specs(product: dict) -> dict:
    length_raw = product.get("lenghtt") or product.get("length") or ""
    beam_raw = product.get("beam") or ""
    weight_raw = product.get("weight") or ""
    hp_raw = product.get("maximum_hp") or product.get("max_power") or ""
    fuel_raw = product.get("fuel_tank_capacity") or ""

    persons = product.get("max_person")
    capacity = int(persons) if persons and str(persons).isdigit() else None

    return {
        "length_mm": parse_meters(length_raw),
        "width_mm": parse_meters(beam_raw),
        "height_mm": None,
        "weight_kg": parse_weight_kg(weight_raw),
        "capacity_persons": capacity,
        "max_hp": parse_hp(hp_raw),
        "fuel_capacity_l": parse_fuel_l(fuel_raw),
    }


def strip_html(value: str) -> str:
    text = html_lib.unescape(re.sub(r"<[^>]+>", " ", value or ""))
    return re.sub(r"\s+", " ", text).strip()


def html_list_items(value: str) -> list[str]:
    items = re.findall(r"<li[^>]*>(.*?)</li>", value or "", re.I | re.S)
    out = []
    for item in items:
        text = strip_html(item)
        if text and not text[0].isdigit():
            out.append(text)
        elif re.match(r"^\d+\.", text):
            out.append(re.sub(r"^\d+\.\s*", "", text))
        elif text:
            out.append(text)
    return out


def main():
    categories: dict[str, dict] = {}
    models: dict[str, dict] = {}
    series_variants: dict[str, list[str]] = {}
    errors = []

    for slug in MODEL_SLUGS:
        try:
            props = fetch_model(slug)
            product = props["product"]
            cat = props.get("cat") or {}
            series_key = slug.split("-")[0]
            if series_key == "ion" and slug.startswith("ion-"):
                series_key = "ion"
            series_id = SERIES_SLUGS.get(series_key, series_key)
            categories.setdefault(series_key, cat)
            specs = parse_specs(product)
            models[slug] = {
                "series_id": series_id,
                "series_key": series_key,
                "url": f"{BASE}/model/{slug}",
                "name": product.get("title") or slug.upper(),
                "description": strip_html(product.get("excerpt") or ""),
                "specifications": specs,
                "raw": {
                    "lenghtt": product.get("lenghtt"),
                    "beam": product.get("beam"),
                    "weight": product.get("weight"),
                    "max_person": product.get("max_person"),
                    "max_power": product.get("max_power"),
                    "fuel_tank_capacity": product.get("fuel_tank_capacity"),
                    "tube_material": product.get("tube_material"),
                    "design_category": product.get("design_category"),
                },
            }
            series_variants.setdefault(series_id, []).append(slug)
            print(f"OK  {slug}: {specs}", file=sys.stderr)
            time.sleep(0.25)
        except Exception as e:
            errors.append((slug, str(e)))
            print(f"ERR {slug}: {e}", file=sys.stderr)

    out_path = Path("/tmp/northstar-specs.json")
    if len(sys.argv) > 1:
        out_path = Path(sys.argv[1])
    out_path.write_text(
        json.dumps(
            {
                "models": models,
                "categories": categories,
                "series_variants": series_variants,
                "errors": errors,
            },
            indent=2,
        )
        + "\n"
    )
    print(f"Wrote {len(models)} models to {out_path}", file=sys.stderr)


if __name__ == "__main__":
    main()
