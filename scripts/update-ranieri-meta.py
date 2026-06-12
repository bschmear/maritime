#!/usr/bin/env python3
"""Merge scraped Ranieri specs into meta.json."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
META_PATH = ROOT / "app/AssetInformation/ranieri/meta.json"
SPECS_PATH = Path("/tmp/ranieri-specs.json")

SERIES_VARIANT_ORDER = {
    "cayman-one-luxury-tender": [],
    "cayman-sport": [
        "cayman-19-sport", "cayman-21-sport", "cayman-23-sport", "cayman-26-sport",
    ],
    "cayman-sport-touring": [
        "cayman-23-sport-touring", "cayman-26-sport-touring", "cayman-27-sport-touring",
    ],
    "cayman-executive": [
        "cayman-28-executive", "cayman-33-executive", "cayman-35-executive", "cayman-38-executive",
    ],
    "cayman-cruiser": ["cayman-45-cruiser", "cayman-50-hard-top"],
}

DISPLAY_NAMES = {
    "cayman-one-luxury-tender": "Cayman ONE Luxury Tender",
    "cayman-19-sport": "Cayman 19 Sport",
    "cayman-21-sport": "Cayman 21.0 Sport",
    "cayman-23-sport": "Cayman 23.0 Sport",
    "cayman-26-sport": "Cayman 26.0 Sport",
    "cayman-23-sport-touring": "Cayman 23.0 Sport Touring",
    "cayman-26-sport-touring": "Cayman 26.0 Sport Touring",
    "cayman-27-sport-touring": "Cayman 27.0 Sport Touring",
    "cayman-28-executive": "Cayman 28.0 Executive",
    "cayman-33-executive": "Cayman 33.0 Executive",
    "cayman-35-executive": "Cayman 35.0 Executive",
    "cayman-38-executive": "Cayman 38.0 Executive",
    "cayman-45-cruiser": "Cayman 45.0 Cruiser",
    "cayman-50-hard-top": "Cayman 50.0 Hard Top",
}

NEW_SERIES = {
    "cayman-cruiser": {
        "id": "cayman-cruiser",
        "brand": "Ranieri International",
        "name": "Cayman Cruiser Line",
        "type_display": "Luxury Maxi-RIB Cruiser",
        "boat_type_key": "power-rib",
        "hull_type_key": "rib",
        "hull_material_key": "fiberglass",
        "description": "The Cayman Cruiser line extends Ranieri International's maxi-RIB platform into true long-range luxury cruising, pairing Generation II H.I.S. stepped fiberglass hulls with expansive hard-top or open cruiser layouts. These flagship vessels integrate multi-chambered Hypalon tubes, high-capacity fuel systems, cabin berths, and twin- or triple-outboard configurations for extended offshore hospitality and yacht escort operations.",
        "features": [
            "Generation II H.I.S. multi-stepped deep-V fiberglass hull",
            "Premium multi-chambered Hypalon/ORCA buoyancy tubes",
            "Integrated hard-top or cruiser deck architecture",
            "Enclosed cabin with berths and head accommodations",
            "High-capacity integrated fuel tanks for long-range cruising",
            "Twin or triple outboard engine configurations",
            "Electric anchor windlass with stainless bow roller",
            "SeaDeck synthetic non-skid cockpit decking",
            "Telescopic stainless steel swim ladders",
            "Premium UV-resistant closed-cell upholstery"
        ],
        "has_variants": True,
        "construction": {
            "material": "Fiberglass Multi-Stepped Hull / Hypalon Tubes"
        },
    },
}


def load_meta(path: Path) -> list:
    text = path.read_text()
    text = re.sub(r",\s*//[^\n]*", ",", text)
    text = re.sub(r"\s*//[^\n]*", "", text)
    return json.loads(text)


def default_description(variant_id: str) -> str:
    return f"Official Ranieri {DISPLAY_NAMES.get(variant_id, variant_id)} per current manufacturer specifications."


def update_length_range(series: dict) -> None:
    if not series.get("has_variants"):
        return
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
        if not s.get("has_variants"):
            old_desc[s["id"]] = s.get("description")

    existing_ids = {s["id"] for s in meta}
    for sid, template in NEW_SERIES.items():
        if sid not in existing_ids:
            meta.append(dict(template))

    updated = []
    for series in meta:
        sid = series["id"]
        series = dict(series)

        if sid not in SERIES_VARIANT_ORDER:
            updated.append(series)
            continue

        variant_ids = SERIES_VARIANT_ORDER[sid]
        if not variant_ids:
            if sid not in scraped:
                raise SystemExit(f"Missing scraped data for standalone series {sid}")
            series["specifications"] = scraped[sid]["specifications"]
            if not series.get("description"):
                series["description"] = default_description(sid)
            updated.append(series)
            continue

        variants = []
        for vid in variant_ids:
            if vid not in scraped:
                raise SystemExit(f"Missing scraped data for {vid}")
            variants.append({
                "id": vid,
                "name": DISPLAY_NAMES.get(vid, vid),
                "description": old_desc.get(vid) or default_description(vid),
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
