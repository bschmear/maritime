#!/usr/bin/env python3
"""Merge scraped Walker Bay specs into meta.json."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
META_PATH = ROOT / "app/AssetInformation/walker-bay/meta.json"
SPECS_PATH = Path("/tmp/wb-specs.json")

SERIES_VARIANT_ORDER = {
    "generation-dlx": [
        "generation-340-dlx",
        "generation-360-dlx",
        "generation-400-dlx",
        "generation-450-dlx",
        "generation-525-dlx",
    ],
    "generation-lte": [
        "generation-10-lte",
        "generation-11-lte",
        "generation-12-lte",
        "generation-13-lte",
        "generation-14-lte",
    ],
    "venture": ["venture-13", "venture-14", "venture-16"],
    "stx": ["stx-325", "stx-365"],
}

STANDALONE_SERIES = ["walker-bay-22", "generation-12", "generation-15"]

DISPLAY_NAMES = {
    "stx-325": "325 STX Deluxe Console",
    "stx-365": "365 STX Deluxe Console",
    "generation-13-lte": "Generation 13 LTE",
    "generation-14-lte": "Generation 14 LTE",
}


def load_meta_with_comments(path: Path) -> list:
    text = path.read_text()
    text = re.sub(r",\s*//[^\n]*", ",", text)
    text = re.sub(r"\s*//[^\n]*", "", text)
    return json.loads(text)


def display_name(variant_id: str, series: dict) -> str:
    if variant_id in DISPLAY_NAMES:
        return DISPLAY_NAMES[variant_id]
    if variant_id.startswith("generation-"):
        suffix = variant_id.replace("generation-", "").replace("-", " ").upper()
        suffix = suffix.replace("DLX", "DLX").replace("LTE", "LTE")
        parts = suffix.split()
        titled = [p if p in {"DLX", "LTE"} else p.title() if not p.isdigit() else p for p in parts]
        return f"Generation {' '.join(titled)}"
    if variant_id.startswith("venture-"):
        return f"Venture {variant_id.split('-')[1]}"
    return variant_id


def default_description(variant_id: str, series: dict) -> str:
    return f"Official Walker Bay {display_name(variant_id, series)} per current manufacturer specifications."


def update_length_range(series: dict) -> None:
    lengths = []
    if series.get("has_variants"):
        for v in series["variants"]:
            l = v["specifications"].get("length_mm")
            if l:
                lengths.append(l)
    else:
        l = series["specifications"].get("length_mm")
        if l:
            lengths.append(l)
    if lengths:
        series["length_range_mm"] = {"min": min(lengths), "max": max(lengths)}


def main():
    meta = load_meta_with_comments(META_PATH)
    scraped = json.loads(SPECS_PATH.read_text())["models"]

    old_variant_desc = {}
    for s in meta:
        for v in s.get("variants", []):
            old_variant_desc[v["id"]] = v.get("description")

    updated = []
    for series in meta:
        sid = series["id"]
        series = dict(series)

        if sid in STANDALONE_SERIES:
            key = sid
            if key in scraped:
                series["specifications"] = scraped[key]["specifications"]
                series["variants"] = []
                series["has_variants"] = False
                update_length_range(series)
            updated.append(series)
            continue

        if sid not in SERIES_VARIANT_ORDER:
            updated.append(series)
            continue

        variants = []
        for vid in SERIES_VARIANT_ORDER[sid]:
            data = scraped[vid]
            variants.append({
                "id": vid,
                "name": display_name(vid, series),
                "description": old_variant_desc.get(vid) or default_description(vid, series),
                "specifications": data["specifications"],
            })

        series["variants"] = variants
        series["has_variants"] = True
        update_length_range(series)
        updated.append(series)

    META_PATH.write_text(json.dumps(updated, indent=2) + "\n")
    print(f"Updated {META_PATH}")


if __name__ == "__main__":
    main()
