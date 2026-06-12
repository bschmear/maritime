#!/usr/bin/env python3
"""Merge scraped Achilles specs into meta.json."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
META_PATH = ROOT / "app/AssetInformation/achilles/meta.json"
SPECS_PATH = Path("/tmp/achilles-specs.json")

DESCRIPTION_ALIASES = {
    "ls2-ru": "ls-2ru",
    "ls4-ru": "ls-4ru",
    "hb-270fx": "hb-240fx",
    "hb-300fx": "hb-310fx",
    "hb-280lx": "hb-270lx",
    "hb-315lx": "hb-310lx",
    "hb-350lx": None,
    "hb-335ax": None,
    "hb-335ax-pro": None,
    "ksb-94": "ksb-9",
    "ksb-116": "ksb-14",
}


def load_meta(path: Path) -> list:
    text = path.read_text()
    text = re.sub(r",\s*//[^\n]*", ",", text)
    text = re.sub(r"\s*//[^\n]*", "", text)
    return json.loads(text)


def default_description(name: str) -> str:
    return f"Official Achilles {name} per current manufacturer specifications."


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
    payload = json.loads(SPECS_PATH.read_text())
    scraped = payload["models"]
    series_variants = payload["series_variants"]

    old_desc = {}
    for s in meta:
        for v in s.get("variants", []):
            old_desc[v["id"]] = v.get("description")

    updated = []
    for series in meta:
        sid = series["id"]
        series = dict(series)
        if sid not in series_variants:
            updated.append(series)
            continue

        variants = []
        for vid in series_variants[sid]:
            if vid not in scraped:
                raise SystemExit(f"Missing scraped data for {vid} in {sid}")

            data = scraped[vid]
            alias = DESCRIPTION_ALIASES.get(vid)
            desc = (
                old_desc.get(vid)
                or (old_desc.get(alias) if alias else None)
                or default_description(data["name"])
            )
            variants.append(
                {
                    "id": vid,
                    "name": data["name"],
                    "description": desc,
                    "specifications": data["specifications"],
                }
            )

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
