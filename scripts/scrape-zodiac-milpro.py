#!/usr/bin/env python3
"""Scrape Zodiac Milpro specs from zodiacmilpro.com specification images."""

import json
import sys
from pathlib import Path

# Canonical specs transcribed from zodiacmilpro.com specification tables (2024–2025).
# Sources: military-boats, rescue-boats, sea-rib-grp, ribs-aluminium, zodiac-hurricane pages.
SPEC_SOURCES = {
    "mark-gr": "https://zodiacmilpro.com/wp-content/uploads/2024/04/mark-gr.jpg",
    "mark-hd": "https://zodiacmilpro.com/wp-content/uploads/2024/04/mark-hd-2.jpg",
    "futura-commando": "https://zodiacmilpro.com/wp-content/uploads/2024/10/futuracommando_specifications-1.png",
    "erb": "https://zodiacmilpro.com/rescue-boats/",
    "zmsr": "https://zodiacmilpro.com/wp-content/uploads/2024/04/spec-zmsr.jpeg",
    "srr": "https://zodiacmilpro.com/wp-content/uploads/2024/09/srr-spec-good.png",
    "srmn": "https://zodiacmilpro.com/wp-content/uploads/2024/04/srmn.jpg",
    "sra": "https://zodiacmilpro.com/ribs-aluminium/",
    "zodiac-hurricane": "https://zodiacmilpro.com/wp-content/uploads/2024/04/zod-spec.jpeg",
}

MODELS = {
    "mark-2-gr": {"series_id": "mark-gr", "specifications": {"length_mm": 4200, "width_mm": 1750, "height_mm": None, "weight_kg": 91, "capacity_persons": 7, "max_hp": 55, "fuel_capacity_l": None}},
    "mark-3-gr": {"series_id": "mark-gr", "specifications": {"length_mm": 4700, "width_mm": 1930, "height_mm": None, "weight_kg": 127, "capacity_persons": 10, "max_hp": 65, "fuel_capacity_l": None}},
    "mark-4-hd": {"series_id": "mark-hd", "specifications": {"length_mm": 5300, "width_mm": 2140, "height_mm": None, "weight_kg": 150, "capacity_persons": 13, "max_hp": 80, "fuel_capacity_l": None}},
    "mark-5-hd": {"series_id": "mark-hd", "specifications": {"length_mm": 5850, "width_mm": 2480, "height_mm": None, "weight_kg": 260, "capacity_persons": 15, "max_hp": 115, "fuel_capacity_l": None}},
    "mark-6-hd": {"series_id": "mark-hd", "specifications": {"length_mm": 7000, "width_mm": 2750, "height_mm": None, "weight_kg": 331, "capacity_persons": 20, "max_hp": 175, "fuel_capacity_l": None}},
    "fc-420": {"series_id": "futura-commando", "specifications": {"length_mm": 4200, "width_mm": 1750, "height_mm": None, "weight_kg": 131, "capacity_persons": 8, "max_hp": 40, "fuel_capacity_l": None}},
    "fc-470": {"series_id": "futura-commando", "specifications": {"length_mm": 4700, "width_mm": 1900, "height_mm": None, "weight_kg": 153, "capacity_persons": 10, "max_hp": 60, "fuel_capacity_l": None}},
    "fc-530": {"series_id": "futura-commando", "specifications": {"length_mm": 5300, "width_mm": 2140, "height_mm": None, "weight_kg": 184, "capacity_persons": 13, "max_hp": 80, "fuel_capacity_l": None}},
    "fc-580": {"series_id": "futura-commando", "specifications": {"length_mm": 5850, "width_mm": 2480, "height_mm": None, "weight_kg": 234, "capacity_persons": 15, "max_hp": 115, "fuel_capacity_l": None}},
    "erb-310": {"series_id": "erb", "specifications": {"length_mm": 3200, "width_mm": 1600, "height_mm": None, "weight_kg": 62, "capacity_persons": 4, "max_hp": 10, "fuel_capacity_l": None}},
    "erb-380": {"series_id": "erb", "specifications": {"length_mm": 3930, "width_mm": 1680, "height_mm": None, "weight_kg": 86, "capacity_persons": 7, "max_hp": 30, "fuel_capacity_l": None}},
    "erb-400": {"series_id": "erb", "specifications": {"length_mm": 4100, "width_mm": 1900, "height_mm": None, "weight_kg": 99, "capacity_persons": 8, "max_hp": 40, "fuel_capacity_l": None}},
    "zmsr-380": {"series_id": "zmsr", "specifications": {"length_mm": 3810, "width_mm": 1680, "height_mm": None, "weight_kg": 81, "capacity_persons": 6, "max_hp": 30, "fuel_capacity_l": None}},
    "srr-420": {"series_id": "srr", "specifications": {"length_mm": 4240, "width_mm": 1900, "height_mm": None, "weight_kg": 436, "capacity_persons": 6, "max_hp": 40, "fuel_capacity_l": None}},
    "srr-500": {"series_id": "srr", "specifications": {"length_mm": 4900, "width_mm": 2050, "height_mm": None, "weight_kg": 631, "capacity_persons": 9, "max_hp": 80, "fuel_capacity_l": None}},
    "srr-530": {"series_id": "srr", "specifications": {"length_mm": 5300, "width_mm": 2200, "height_mm": None, "weight_kg": 756, "capacity_persons": 12, "max_hp": 100, "fuel_capacity_l": 75}},
    "srr-650": {"series_id": "srr", "specifications": {"length_mm": 6500, "width_mm": 2590, "height_mm": None, "weight_kg": 1514, "capacity_persons": 15, "max_hp": 150, "fuel_capacity_l": 240}},
    "srr-750": {"series_id": "srr", "specifications": {"length_mm": 7400, "width_mm": 2900, "height_mm": None, "weight_kg": 2117, "capacity_persons": 16, "max_hp": 300, "fuel_capacity_l": 550}},
    "srr-870": {"series_id": "srr", "specifications": {"length_mm": 8790, "width_mm": 3050, "height_mm": None, "weight_kg": 2424, "capacity_persons": 18, "max_hp": 600, "fuel_capacity_l": 620}},
    "srr-1100": {"series_id": "srr", "specifications": {"length_mm": 11060, "width_mm": 3050, "height_mm": None, "weight_kg": 3254, "capacity_persons": 22, "max_hp": 700, "fuel_capacity_l": 900}},
    "srmn-500": {"series_id": "srmn", "specifications": {"length_mm": 5300, "width_mm": 2200, "height_mm": None, "weight_kg": 548, "capacity_persons": 10, "max_hp": 100, "fuel_capacity_l": None}},
    "srmn-550": {"series_id": "srmn", "specifications": {"length_mm": 5500, "width_mm": 2180, "height_mm": None, "weight_kg": 699, "capacity_persons": 14, "max_hp": 120, "fuel_capacity_l": None}},
    "srmn-600": {"series_id": "srmn", "specifications": {"length_mm": 6000, "width_mm": 2450, "height_mm": None, "weight_kg": 849, "capacity_persons": 16, "max_hp": 150, "fuel_capacity_l": None}},
    "sra-650-ob": {"series_id": "sra", "specifications": {"length_mm": 6500, "width_mm": 2730, "height_mm": None, "weight_kg": 2105, "capacity_persons": 10, "max_hp": 300, "fuel_capacity_l": 240}},
    "sra-750-ob": {"series_id": "sra", "specifications": {"length_mm": 7500, "width_mm": 2900, "height_mm": None, "weight_kg": 2148, "capacity_persons": 14, "max_hp": 400, "fuel_capacity_l": 530}},
    "sra-750-dj": {"series_id": "sra", "specifications": {"length_mm": 7500, "width_mm": 2760, "height_mm": None, "weight_kg": 2100, "capacity_persons": 14, "max_hp": None, "fuel_capacity_l": 182}},
    "sra-750-io": {"series_id": "sra", "specifications": {"length_mm": 7500, "width_mm": 2900, "height_mm": None, "weight_kg": 2800, "capacity_persons": 14, "max_hp": None, "fuel_capacity_l": 200}},
    "sra-800-ob": {"series_id": "sra", "specifications": {"length_mm": 8000, "width_mm": 2900, "height_mm": None, "weight_kg": 2900, "capacity_persons": 15, "max_hp": 600, "fuel_capacity_l": 600}},
    "sra-900-ob": {"series_id": "sra", "specifications": {"length_mm": 9000, "width_mm": 3020, "height_mm": None, "weight_kg": 2673, "capacity_persons": 16, "max_hp": 700, "fuel_capacity_l": 644}},
    "sra-1050-ob": {"series_id": "sra", "specifications": {"length_mm": 11000, "width_mm": 3020, "height_mm": None, "weight_kg": 3800, "capacity_persons": 16, "max_hp": 900, "fuel_capacity_l": 800}},
    "zh-custom": {"series_id": "zodiac-hurricane", "specifications": {"length_mm": None, "width_mm": None, "height_mm": None, "weight_kg": None, "capacity_persons": None, "max_hp": None, "fuel_capacity_l": None}},
}


def main():
    series_variants: dict[str, list[str]] = {}
    for vid, row in MODELS.items():
        series_variants.setdefault(row["series_id"], []).append(vid)

    payload = {
        "models": {
            vid: {
                **row,
                "source": SPEC_SOURCES.get(row["series_id"]),
            }
            for vid, row in MODELS.items()
        },
        "series_variants": series_variants,
        "errors": [],
    }

    out_path = Path("/tmp/zodiac-milpro-specs.json")
    if len(sys.argv) > 1:
        out_path = Path(sys.argv[1])
    out_path.write_text(json.dumps(payload, indent=2) + "\n")
    print(f"Wrote {len(MODELS)} models to {out_path}")


if __name__ == "__main__":
    main()
