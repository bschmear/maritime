#!/usr/bin/env python3
"""Merge scraped Northstar specs into meta.json."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
META_PATH = ROOT / "app/AssetInformation/northstar/meta.json"
SPECS_PATH = Path("/tmp/northstar-specs.json")

SERIES_VARIANT_ORDER = {
    "northstar-axis": [
        "axis-3-1", "axis-3-4", "axis-3-8", "axis-4-2", "axis-4-8", "axis-5-3",
    ],
    "northstar-vega": [
        "vega-2-9", "vega-3-2", "vega-3-5", "vega-3-8", "vega-4-2", "vega-4-8",
        "vega-5-4", "vega-5-8", "vega-6-4",
    ],
    "northstar-orion": ["orion-6", "orion-7", "orion-8", "orion-10"],
    "northstar-ion": ["ion-10-5", "ion-12"],
    "northstar-core": ["core-4-8", "core-5-4", "core-5-9"],
}


def load_meta(path: Path) -> list:
    text = path.read_text()
    text = re.sub(r",\s*//[^\n]*", ",", text)
    text = re.sub(r"\s*//[^\n]*", "", text)
    return json.loads(text)


def update_length_range(series: dict) -> None:
    lengths = [
        v["specifications"]["length_mm"]
        for v in series["variants"]
        if v["specifications"].get("length_mm")
    ]
    if lengths:
        series["length_range_mm"] = {"min": min(lengths), "max": max(lengths)}


def main():
    meta = load_meta(META_PATH)
    scraped = json.loads(SPECS_PATH.read_text())["models"]

    old_desc = {v["id"]: v.get("description") for s in meta for v in s.get("variants", [])}

    updated = []
    for series in meta:
        sid = series["id"]
        series = dict(series)
        if sid not in SERIES_VARIANT_ORDER:
            updated.append(series)
            continue

        variants = []
        for vid in SERIES_VARIANT_ORDER[sid]:
            if vid not in scraped:
                raise SystemExit(f"Missing scraped data for {vid}")
            data = scraped[vid]
            variants.append({
                "id": vid,
                "name": data["name"],
                "description": old_desc.get(vid) or data.get("description") or f"Official Northstar {data['name']}.",
                "specifications": data["specifications"],
            })

        series["variants"] = variants
        series["has_variants"] = True
        update_length_range(series)
        updated.append(series)

    META_PATH.write_text(json.dumps(updated, indent=2) + "\n")
    print(f"Updated {META_PATH}")
    print(f"Series: {len(updated)}")
    print(f"Variants: {sum(len(s.get('variants', [])) for s in updated)}")


if __name__ == "__main__":
    main()
