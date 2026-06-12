#!/usr/bin/env python3
"""Merge scraped Highfield specs into meta.json."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
META_PATH = ROOT / "app/AssetInformation/highfield/meta.json"
SPECS_PATH = Path("/tmp/highfield-specs.json")

SERIES_VARIANT_ORDER = {
    "highfield-roll-up": [
        "ru-200", "ru-230", "ru-250", "ru-280", "ru-320",
        "ru-easygo-250", "ru-easygo-300",
    ],
    "highfield-ultralite": ["ul-240", "ul-260", "ul-290", "ul-310", "ul-340"],
    "highfield-classic": [
        "cl-260", "cl-290", "cl-310", "cl-340", "cl-360", "cl-380",
        "cl-400", "cl-420", "cl-460",
    ],
    "highfield-sport": [
        "sp300", "sp330", "sp360", "sp390", "sp420", "sp460", "sp520", "sp560",
        "sp660", "sp660-flux-electric", "sp700", "sp760", "sp800", "sp900",
    ],
    "highfield-patrol": [
        "pa420", "pa460", "pa500", "pa540", "pa540-coaster", "pa600",
        "pa660", "pa700", "pa760", "pa860",
    ],
    "highfield-escape": ["escape-650", "escape-750"],
    "highfield-velox": ["velox-420", "velox-560", "velox-660"],
    "highfield-adventure": ["adv7", "adv9"],
}

DISPLAY_NAMES = {
    "ru-easygo-250": "Roll-Up EasyGo 250",
    "ru-easygo-300": "Roll-Up EasyGo 300",
    "sp660-flux-electric": "Sport 660 Flux Electric",
    "pa540-coaster": "Patrol 540 Coaster",
    "adv7": "ADV7",
    "adv9": "ADV9",
}


def load_meta(path: Path) -> list:
    text = path.read_text()
    text = re.sub(r",\s*//[^\n]*", ",", text)
    text = re.sub(r"\s*//[^\n]*", "", text)
    return json.loads(text)


def display_name(variant_id: str, series: dict) -> str:
    if variant_id in DISPLAY_NAMES:
        return DISPLAY_NAMES[variant_id]
    if variant_id.startswith("ru-"):
        suffix = variant_id.replace("ru-", "").replace("easygo-", "EasyGo ")
        return f"Roll-Up {suffix.upper() if suffix.isdigit() else suffix.title()}"
    if variant_id.startswith("ul-"):
        return f"UL {variant_id.replace('ul-', '')}"
    if variant_id.startswith("cl-"):
        return f"CL {variant_id.replace('cl-', '')}"
    if variant_id.startswith("sp"):
        num = variant_id.replace("sp", "").replace("-flux-electric", " Flux Electric")
        return f"Sport {num.upper() if num.isdigit() else num}"
    if variant_id.startswith("pa"):
        name = variant_id.replace("pa", "Patrol ").replace("-coaster", " Coaster")
        return name
    if variant_id.startswith("escape-"):
        return f"Escape {variant_id.replace('escape-', '')}"
    if variant_id.startswith("velox-"):
        return f"Velox {variant_id.replace('velox-', '')}"
    return variant_id


def default_description(variant_id: str, series: dict) -> str:
    return f"Official Highfield {display_name(variant_id, series)} per current manufacturer specifications."


def update_length_range(series: dict) -> None:
    lengths = [v["specifications"]["length_mm"] for v in series["variants"] if v["specifications"].get("length_mm")]
    if lengths:
        series["length_range_mm"] = {"min": min(lengths), "max": max(lengths)}


def main():
    meta = load_meta(META_PATH)
    scraped = json.loads(SPECS_PATH.read_text())["models"]

    old_desc = {}
    for s in meta:
        for v in s.get("variants", []):
            old_desc[v["id"]] = v.get("description")
    if "sp650" in old_desc and "sp660" not in old_desc:
        old_desc["sp660"] = old_desc["sp650"]

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
            variants.append({
                "id": vid,
                "name": display_name(vid, series),
                "description": old_desc.get(vid) or default_description(vid, series),
                "specifications": scraped[vid]["specifications"],
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
