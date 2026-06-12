#!/usr/bin/env python3
"""Merge scraped AB Inflatables specs into meta.json."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
META_PATH = ROOT / "app/AssetInformation/ab-inflatables/meta.json"
SPECS_PATH = Path("/tmp/ab-specs.json")

SERIES_VARIANT_ORDER = {
    "nautilus": [
        "nautilus-11-dlx", "nautilus-12-dlx", "nautilus-13-dlx", "nautilus-14-dlx",
        "nautilus-15-dlx", "nautilus-17-dlx", "nautilus-19-dlx", "nautilus-19-dlx-i-o",
    ],
    "oceanus": [
        "oceanus-12-vst", "oceanus-13-vst", "oceanus-14-vst", "oceanus-15-vst",
        "oceanus-17-vst", "oceanus-19-vst", "oceanus-21-vst", "oceanus-24-vst", "oceanus-28-vst",
    ],
    "mares": ["mares-10-vsx", "mares-11-vsx", "mares-12-vsx"],
    "navigo": [
        "navigo-8-vs", "navigo-9-vs", "navigo-10-vs", "navigo-12-vs", "navigo-13-vs",
        "navigo-14-vs", "navigo-15-vs", "navigo-17-vs", "navigo-19-vs",
    ],
    "ventus": ["ventus-8-vl", "ventus-9-vl", "ventus-10-vl", "ventus-12-vl"],
    "alumina": [
        "alumina-9.5-alx", "alumina-10-alx", "alumina-11-alx", "alumina-12-alx", "alumina-13-alx",
        "alumina-14-alx", "alumina-15-alx", "alumina-16-alx", "alumina-16-alx-br", "alumina-18-alx",
    ],
    "lammina-al": [
        "lammina-8-al", "lammina-9-al", "lammina-9.5-al", "lammina-10-al", "lammina-11-al",
        "lammina-12-al", "lammina-13-al", "lammina-14-al", "lammina-15-al", "lammina-16-al",
    ],
    "lammina-ul": ["lammina-7.5-ul", "lammina-8-ul", "lammina-9-ul", "lammina-10-ul"],
    "abjet-s": ["abjet-290", "abjet-330", "abjet-380"],
    "abjet-xp": ["abjet-350-xp", "abjet-390-xp", "abjet-430-xp", "abjet-465-xp"],
    "abjet-diesel": ["abjet-450-diesel"],
    "profile-a": [
        "profile-a11", "profile-a12", "profile-a13", "profile-a14", "profile-a15",
        "profile-a16", "profile-a18",
    ],
    "profile-a-xhd": ["profile-a21-xhd", "profile-a24-xhd"],
    "profile-as": [
        "profile-a11-s", "profile-a12-s", "profile-a13-s", "profile-a14-s", "profile-a16-s",
    ],
    "profile-f": [
        "profile-f14", "profile-f15", "profile-f17", "profile-f19", "profile-f21", "profile-f24", "profile-f28",
    ],
}

LAMMINA_NAMES = {
    "lammina-8-al": "Lammina 8 AL",
    "lammina-9-al": "Lammina 9 AL",
    "lammina-9.5-al": "Lammina 9.5 AL",
    "lammina-10-al": "Lammina 10 AL",
    "lammina-11-al": "Lammina 11 AL",
    "lammina-12-al": "Lammina 12 AL",
    "lammina-13-al": "Lammina 13 AL",
    "lammina-14-al": "Lammina 14 AL",
    "lammina-15-al": "Lammina 15 AL",
    "lammina-16-al": "Lammina 16 AL",
    "lammina-7.5-ul": "Lammina 7.5 UL",
    "lammina-8-ul": "Lammina 8 UL",
    "lammina-9-ul": "Lammina 9 UL",
    "lammina-10-ul": "Lammina 10 UL",
}

DISPLAY_NAMES = {
    "nautilus-19-dlx-i-o": "Nautilus 19 DLX I-O",
    "alumina-9.5-alx": "Alumina 9.5 ALX",
    "alumina-16-alx-br": "Alumina 16 ALX BR",
    "lammina-9.5-al": "Lammina 9.5 AL",
    "lammina-7.5-ul": "Lammina 7.5 UL",
    "abjet-350-xp": "ABJET 350 XP",
    "abjet-390-xp": "ABJET 390 XP",
    "abjet-430-xp": "ABJET 430 XP",
    "abjet-465-xp": "ABJET 465 XP",
    "abjet-450-diesel": "ABJET 450 Diesel",
    "profile-a11": "Profile A11",
    "profile-a12": "Profile A12",
    "profile-a13": "Profile A13",
    "profile-a14": "Profile A14",
    "profile-a15": "Profile A15",
    "profile-a16": "Profile A16",
    "profile-a18": "Profile A18",
    "profile-a21-xhd": "Profile A21-XHD",
    "profile-a24-xhd": "Profile A24-XHD",
    "profile-a11-s": "Profile A11-S",
    "profile-a12-s": "Profile A12-S",
    "profile-a13-s": "Profile A13-S",
    "profile-a14-s": "Profile A14-S",
    "profile-a16-s": "Profile A16-S",
    "profile-f14": "Profile F14",
    "profile-f15": "Profile F15",
    "profile-f17": "Profile F17",
    "profile-f19": "Profile F19",
    "profile-f21": "Profile F21",
    "profile-f24": "Profile F24",
    "profile-f28": "Profile F28",
}


def display_name(variant_id: str, series: dict) -> str:
    if variant_id in LAMMINA_NAMES:
        return LAMMINA_NAMES[variant_id]
    if variant_id in DISPLAY_NAMES:
        return DISPLAY_NAMES[variant_id]
    if variant_id.startswith("abjet-"):
        num = variant_id.replace("abjet-", "").replace("-xp", " XP").replace("-diesel", " Diesel").upper()
        return f"ABJET {num}"
    parts = variant_id.split("-", 1)
    if len(parts) == 2:
        series_name = series["name"].replace(" Series", "")
        model = parts[1].replace("-", " ").upper()
        model = re.sub(r"\bDLX\b", "DLX", model)
        model = re.sub(r"\bVST\b", "VST", model)
        model = re.sub(r"\bVSX\b", "VSX", model)
        model = re.sub(r"\bVS\b", "VS", model)
        model = re.sub(r"\bVL\b", "VL", model)
        model = re.sub(r"\bALX\b", "ALX", model)
        model = re.sub(r"\bAL\b", "AL", model)
        model = re.sub(r"\bUL\b", "UL", model)
        # Title case with preserved acronyms
        tokens = model.split()
        titled = []
        for t in tokens:
            if t in {"DLX", "VST", "VSX", "VS", "VL", "ALX", "AL", "UL", "I", "O"}:
                titled.append(t if t != "I" else "I")
            elif t == "I-O":
                titled.append("I-O")
            else:
                titled.append(t.title() if not re.match(r"^\d", t) else t)
        return f"{series_name} {' '.join(titled)}"
    return variant_id


def default_description(variant_id: str, series: dict) -> str:
    name = display_name(variant_id, series)
    return f"Official AB Inflatables {name} model per current manufacturer specifications."


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


def load_meta_with_comments(path: Path) -> list:
    text = path.read_text()
    text = re.sub(r",\s*//[^\n]*", ",", text)
    text = re.sub(r"\s*//[^\n]*", "", text)
    return json.loads(text)


def main():
    meta = load_meta_with_comments(META_PATH)
    scraped = json.loads(SPECS_PATH.read_text())["models"]

    old_by_series = {s["id"]: s for s in meta}
    old_variant_desc = {}
    for s in meta:
        for v in s.get("variants", []):
            old_variant_desc[v["id"]] = v.get("description")

    updated = []
    for series in meta:
        sid = series["id"]
        series = dict(series)

        if sid == "ab-rider":
            specs = scraped["ab-rider"]["specifications"]
            series["specifications"] = specs
            series["variants"] = []
            update_length_range(series)
            updated.append(series)
            continue

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
    print(f"Series: {len(updated)}")
    total_variants = sum(len(s.get('variants', [])) for s in updated)
    print(f"Variants: {total_variants}")


if __name__ == "__main__":
    main()
