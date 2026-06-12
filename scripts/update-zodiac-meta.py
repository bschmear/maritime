#!/usr/bin/env python3
"""Merge scraped Zodiac specs into meta.json."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
META_PATH = ROOT / "app/AssetInformation/zodiac/meta.json"
SPECS_PATH = Path("/tmp/zodiac-specs.json")

SERIES_VARIANT_ORDER = {
    "zodiac-xcc": ["x9cc", "x10cc"],
    "zodiac-medline": ["medline-5-8", "medline-6-8", "medline-7-5", "medline-9"],
    "zodiac-open": ["open-4-2", "open-4-8", "open-5-5", "open-6-5"],
    "zodiac-pro": ["pro-5-5", "pro-6-5", "pro-7", "pro-850", "pro-classic-750"],
    "zodiac-cadet-aero": [
        "cadet-200-aero", "cadet-230-aero", "cadet-270-aero",
        "cadet-310-aero", "cadet-350-aero",
    ],
    "zodiac-cadet-alu": ["cadet-270-alu", "cadet-310-alu", "cadet-350-alu"],
    "zodiac-nomad-rib-alu": ["nomad-2-7", "nomad-3-1", "nomad-3-3", "nomad-3-6", "nomad-3-9"],
    "zodiac-yachtline": ["yachtline-360", "yachtline-400", "yachtline-440", "yachtline-490"],
    "zodiac-n-zo": ["n-zo-680", "n-zo-700-cabine", "n-zo-760"],
    "zodiac-milpro-mark": ["mark-2-gr", "mark-3-gr"],
    "zodiac-milpro-futura": ["fc-420", "fc-470", "fc-530"],
    "zodiac-milpro-sr": ["sr-420", "sr-530", "sr-650", "sr-750", "sr-870"],
    "zodiac-milpro-sra": ["sra-750", "sra-900"],
}

DISPLAY_NAMES = {
    "x10cc": "X10CC",
    "x9cc": "X9CC",
    "open-6-5": "Open 6.5",
    "pro-classic-750": "Pro Classic 750",
    "n-zo-700-cabine": "N-ZO 700 Cabine",
    "nomad-2-7": "Nomad 2.7",
    "nomad-3-1": "Nomad 3.1",
    "nomad-3-3": "Nomad 3.3",
    "nomad-3-6": "Nomad 3.6",
    "nomad-3-9": "Nomad 3.9",
}

DESCRIPTION_ALIASES = {
    "open-6-5": "open-6-7",
    "pro-classic-750": None,
    "n-zo-700-cabine": None,
}


def load_meta(path: Path) -> list:
    text = path.read_text()
    text = re.sub(r",\s*//[^\n]*", ",", text)
    text = re.sub(r"\s*//[^\n]*", "", text)
    return json.loads(text)


def display_name(variant_id: str) -> str:
    if variant_id in DISPLAY_NAMES:
        return DISPLAY_NAMES[variant_id]
    if variant_id.startswith("cadet-"):
        parts = variant_id.replace("cadet-", "").replace("-aero", " Aero").replace("-alu", " ALU")
        return "Cadet " + parts.replace("-", " ").title()
    if variant_id.startswith("medline-"):
        return "Medline " + variant_id.replace("medline-", "").replace("-", ".")
    if variant_id.startswith("open-"):
        return "Open " + variant_id.replace("open-", "").replace("-", ".")
    if variant_id.startswith("pro-"):
        return variant_id.replace("pro-", "Pro ").replace("-", " ").title()
    if variant_id.startswith("yachtline-"):
        return "Yachtline " + variant_id.replace("yachtline-", "")
    if variant_id.startswith("n-zo-"):
        return variant_id.replace("n-zo-", "N-ZO ").replace("-", " ").title()
    return variant_id.replace("-", " ").title()


def default_description(variant_id: str) -> str:
    return f"Official Zodiac {display_name(variant_id)} per current manufacturer specifications."


def update_length_range(series: dict) -> None:
    lengths = [v["specifications"]["length_mm"] for v in series["variants"] if v["specifications"].get("length_mm")]
    if lengths:
        series["length_range_mm"] = {"min": min(lengths), "max": max(lengths)}


def main():
    meta = load_meta(META_PATH)
    scraped = json.loads(SPECS_PATH.read_text())["models"]

    old_desc = {}
    old_specs = {}
    for s in meta:
        for v in s.get("variants", []):
            old_desc[v["id"]] = v.get("description")
            old_specs[v["id"]] = v.get("specifications")

    updated = []
    for series in meta:
        sid = series["id"]
        series = dict(series)
        if sid not in SERIES_VARIANT_ORDER:
            updated.append(series)
            continue

        variants = []
        for vid in SERIES_VARIANT_ORDER[sid]:
            if vid in scraped:
                specs = scraped[vid]["specifications"]
            elif vid in old_specs:
                specs = old_specs[vid]
            else:
                raise SystemExit(f"Missing data for {vid}")

            alias = DESCRIPTION_ALIASES.get(vid)
            desc = old_desc.get(vid) or (old_desc.get(alias) if alias else None) or default_description(vid)
            variants.append({
                "id": vid,
                "name": display_name(vid),
                "description": desc,
                "specifications": specs,
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
