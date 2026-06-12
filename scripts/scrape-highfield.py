#!/usr/bin/env python3
"""Scrape Highfield model specs from highfieldboats.com."""

import html as html_lib
import json
import re
import sys
import time
from pathlib import Path
from urllib.request import Request, urlopen

BASE = "https://www.highfieldboats.com"
UA = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

MODEL_URLS = [
    # Roll-Up
    ("highfield-roll-up", "ru-200", f"{BASE}/boat/roll-up/roll-up-200/"),
    ("highfield-roll-up", "ru-230", f"{BASE}/boat/roll-up/roll-up-230/"),
    ("highfield-roll-up", "ru-250", f"{BASE}/boat/roll-up/roll-up-250/"),
    ("highfield-roll-up", "ru-280", f"{BASE}/boat/roll-up/roll-up-280/"),
    ("highfield-roll-up", "ru-320", f"{BASE}/boat/roll-up/roll-up-320/"),
    ("highfield-roll-up", "ru-easygo-250", f"{BASE}/boat/roll-up/roll-up-easygo-250/"),
    ("highfield-roll-up", "ru-easygo-300", f"{BASE}/boat/roll-up/roll-up-easygo-300/"),
    # Ultralite
    ("highfield-ultralite", "ul-240", f"{BASE}/boat/the-ultralite-range/ultralite-240/"),
    ("highfield-ultralite", "ul-260", f"{BASE}/boat/the-ultralite-range/ultralite-260/"),
    ("highfield-ultralite", "ul-290", f"{BASE}/boat/the-ultralite-range/ultralite-290/"),
    ("highfield-ultralite", "ul-310", f"{BASE}/boat/the-ultralite-range/ultralite-310/"),
    ("highfield-ultralite", "ul-340", f"{BASE}/boat/the-ultralite-range/ultralite-340/"),
    # Classic
    ("highfield-classic", "cl-260", f"{BASE}/boat/the-classic-range/classic-260/"),
    ("highfield-classic", "cl-290", f"{BASE}/boat/the-classic-range/classic-290/"),
    ("highfield-classic", "cl-310", f"{BASE}/boat/the-classic-range/classic-310/"),
    ("highfield-classic", "cl-340", f"{BASE}/boat/the-classic-range/classic-340/"),
    ("highfield-classic", "cl-360", f"{BASE}/boat/the-classic-range/classic-360/"),
    ("highfield-classic", "cl-380", f"{BASE}/boat/the-classic-range/classic-380/"),
    ("highfield-classic", "cl-400", f"{BASE}/boat/the-classic-range/classic-400/"),
    ("highfield-classic", "cl-420", f"{BASE}/boat/the-classic-range/classic-420-2/"),
    ("highfield-classic", "cl-460", f"{BASE}/boat/the-classic-range/classic-460-2/"),
    # Sport
    ("highfield-sport", "sp300", f"{BASE}/boat/the-sport-range/sport-300/"),
    ("highfield-sport", "sp330", f"{BASE}/boat/the-sport-range/sport-330/"),
    ("highfield-sport", "sp360", f"{BASE}/boat/the-sport-range/sport-360/"),
    ("highfield-sport", "sp390", f"{BASE}/boat/the-sport-range/sport-390/"),
    ("highfield-sport", "sp420", f"{BASE}/boat/the-sport-range/sport-420/"),
    ("highfield-sport", "sp460", f"{BASE}/boat/the-sport-range/sport-460/"),
    ("highfield-sport", "sp520", f"{BASE}/boat/the-sport-range/sport-520/"),
    ("highfield-sport", "sp560", f"{BASE}/boat/the-sport-range/sport-560/"),
    ("highfield-sport", "sp660", f"{BASE}/boat/the-sport-range/sport-660/"),
    ("highfield-sport", "sp660-flux-electric", f"{BASE}/boat/the-sport-range/sport-660-flux-electric/"),
    ("highfield-sport", "sp700", f"{BASE}/boat/the-sport-range/sport-700/"),
    ("highfield-sport", "sp760", f"{BASE}/boat/the-sport-range/sport-760/"),
    ("highfield-sport", "sp800", f"{BASE}/boat/the-sport-range/sport-800/"),
    ("highfield-sport", "sp900", f"{BASE}/boat/the-sport-range/sport-900/"),
    # Patrol
    ("highfield-patrol", "pa420", f"{BASE}/boat/the-patrol-range/patrol-420/"),
    ("highfield-patrol", "pa460", f"{BASE}/boat/the-patrol-range/patrol-460/"),
    ("highfield-patrol", "pa500", f"{BASE}/boat/the-patrol-range/patrol-500/"),
    ("highfield-patrol", "pa540", f"{BASE}/boat/the-patrol-range/patrol-540/"),
    ("highfield-patrol", "pa540-coaster", f"{BASE}/boat/the-patrol-range/patrol-540-coaster/"),
    ("highfield-patrol", "pa600", f"{BASE}/boat/the-patrol-range/patrol-600/"),
    ("highfield-patrol", "pa660", f"{BASE}/boat/the-patrol-range/patrol-660/"),
    ("highfield-patrol", "pa700", f"{BASE}/boat/the-patrol-range/patrol-700/"),
    ("highfield-patrol", "pa760", f"{BASE}/boat/the-patrol-range/patrol-760/"),
    ("highfield-patrol", "pa860", f"{BASE}/boat/the-patrol-range/patrol-860/"),
    # Escape
    ("highfield-escape", "escape-650", f"{BASE}/boat/the-escape-range/escape-650/"),
    ("highfield-escape", "escape-750", f"{BASE}/boat/the-escape-range/escape-750-2/"),
    # Velox
    ("highfield-velox", "velox-420", f"{BASE}/boat/the-velox-range/velox-420/"),
    ("highfield-velox", "velox-560", f"{BASE}/boat/the-velox-range/velox-560/"),
    ("highfield-velox", "velox-660", f"{BASE}/boat/the-velox-range/velox-660/"),
    # ADV (specs on dedicated site pages when available)
    ("highfield-adventure", "adv7", f"{BASE}/boat/the-adv-range/"),
    ("highfield-adventure", "adv9", f"{BASE}/boat/the-adv-range/"),
]


def fetch(url: str) -> str:
    req = Request(url, headers={"User-Agent": UA, "Accept": "text/html,application/xhtml+xml"})
    with urlopen(req, timeout=45) as resp:
        return resp.read().decode("utf-8", errors="replace")


def normalize_quotes(value: str) -> str:
    return (
        value.replace("\u2019", "'")
        .replace("\u2018", "'")
        .replace("\u2032", "'")
        .replace("\u201d", '"')
        .replace("\u201c", '"')
        .replace("\u2033", '"')
    )


def m_to_mm(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*m\b", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 1000)
    return None


def cm_to_mm(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*cm\b", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 10)
    return None


def ft_in_to_mm(value: str) -> int | None:
    value = normalize_quotes(value)
    m = re.search(r"(\d+)'\s*(\d+)?", value)
    if m:
        feet = int(m.group(1))
        inches = int(m.group(2) or 0)
        return round((feet * 12 + inches) * 25.4)
    m = re.search(r"(\d+(?:\.\d+)?)\s*'", value)
    if m:
        return round(float(m.group(1)) * 304.8)
    return None


def parse_dimension(value: str) -> int | None:
    imperial = ft_in_to_mm(value)
    metric = m_to_mm(value) or cm_to_mm(value)
    if imperial and metric:
        return imperial if imperial > metric else metric
    return imperial or metric


def parse_weight_kg(value: str) -> int | None:
    m = re.search(r"([\d,]+(?:\.\d+)?)\s*kg", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d,]+(?:\.\d+)?)\s*lbs?", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 0.453592)
    return None


def parse_hp(value: str) -> int | None:
    nums = []
    for m in re.finditer(r"([\d.,]+)\s*hp", value, re.I):
        nums.append(float(m.group(1).replace(",", "")))
    twin = re.search(r"2\s*\*\s*([\d.,]+)\s*hp", value, re.I)
    if twin:
        nums.append(float(twin.group(1).replace(",", "")) * 2)
    if not nums:
        bare = re.match(r"^([\d.,]+)\s*$", value.strip())
        if bare:
            nums.append(float(bare.group(1).replace(",", "")))
    if not nums:
        kw = re.search(r"([\d.,]+)\s*kw", value, re.I)
        if kw:
            return round(float(kw.group(1).replace(",", "")) * 1.34102)
        return None
    return round(max(nums))


def parse_fuel_l(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*l\b", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d.,]+)\s*gal", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 3.78541)
    return None


def parse_capacity(value: str) -> int | None:
    value = value.strip()
    plus = re.match(r"(\d+)\s*\+\s*(\d+)", value)
    if plus:
        return int(plus.group(1)) + int(plus.group(2))
    m = re.search(r"(\d+)", value)
    return int(m.group(1)) if m else None


def extract_metric_spec_section(text: str) -> str:
    m = re.search(
        r"Specifications\s*(.*?)(?:Metric Imperial|Standard Features|### Standard Features|Key Features)",
        text,
        re.I | re.S,
    )
    if not m:
        return text
    block = m.group(1).strip()
    # Metric specs lead the block; imperial repeats after a second "Overall Length" in feet.
    if block.startswith("Overall Length\n"):
        parts = re.split(r"\nOverall Length\n", block, maxsplit=1)
        return parts[0]
    parts = re.split(r"\nOverall Length\n", block, maxsplit=1)
    if len(parts) >= 2 and re.search(r"[\d.]+\s*m\b", parts[1]):
        return "Overall Length\n" + parts[1]
    return block


def field_value(text: str, label: str) -> str | None:
    pattern = rf"{re.escape(label)}\s*\n\s*([^\n]+)"
    m = re.search(pattern, text, re.I)
    if not m:
        return None
    return normalize_quotes(m.group(1).strip().rstrip("*"))


def field_values(text: str, label: str) -> list[str]:
    pattern = rf"{re.escape(label)}\s*\n\s*([^\n]+)"
    return [
        normalize_quotes(m.group(1).strip().rstrip("*"))
        for m in re.finditer(pattern, text, re.I)
    ]


def parse_specs(html: str, variant_id: str) -> dict:
    specs = {
        "length_mm": None,
        "width_mm": None,
        "height_mm": None,
        "weight_kg": None,
        "capacity_persons": None,
        "max_hp": None,
        "fuel_capacity_l": None,
    }

    text = html_lib.unescape(re.sub(r"<[^>]+>", "\n", html))
    text = re.sub(r"\n+", "\n", text)
    full_block = ""
    block_match = re.search(
        r"Specifications\s*(.*?)(?:Metric Imperial|Standard Features|### Standard Features|Key Features)",
        text,
        re.I | re.S,
    )
    if block_match:
        full_block = block_match.group(1)
    section = extract_metric_spec_section(text)

    loa = field_value(section, "Overall Length")
    if loa:
        specs["length_mm"] = parse_dimension(loa)

    beam = field_value(section, "Overall Width")
    if beam:
        specs["width_mm"] = parse_dimension(beam)

    people_values = []
    for label in (
        "Maximum People",
        "Maximum People - Cat C",
        "Maximum People - Cat B",
        "Max Passengers",
    ):
        val = field_value(section, label)
        if val:
            people_values.append(parse_capacity(val))
    if people_values:
        specs["capacity_persons"] = max(v for v in people_values if v)

    weights = field_values(full_block or section, "Boat only Weight") or field_values(
        full_block or section, "Boat Only Weight"
    )
    if weights:
        metric_kg = parse_weight_kg(weights[0])
        specs["weight_kg"] = metric_kg
        if len(weights) > 1:
            imperial_kg = parse_weight_kg(weights[1])
            if metric_kg and imperial_kg and metric_kg < imperial_kg * 0.5:
                specs["weight_kg"] = imperial_kg

    hp = field_value(section, "Max HP") or field_value(section, "Maximum HP")
    if hp:
        specs["max_hp"] = parse_hp(hp)
    else:
        single = field_value(section, "Max HP Single")
        twin = field_value(section, "Max HP Twin")
        hp_values = [v for v in (single, twin) if v]
        if hp_values:
            specs["max_hp"] = max(parse_hp(v) or 0 for v in hp_values) or None

    fuel = field_value(section, "Fuel Tank")
    if fuel and re.search(r"\d", fuel):
        specs["fuel_capacity_l"] = parse_fuel_l(fuel)

    # ADV range cards on listing page (no per-model spec pages)
    if variant_id == "adv7":
        m = re.search(r"ADV7\s*\n([\d.]+)m\s*\n(\d+)\s*\n([\d.]+)l\s*\n(\d+)hp", text, re.I)
        if m:
            specs["length_mm"] = round(float(m.group(1)) * 1000)
            specs["capacity_persons"] = int(m.group(2))
            specs["fuel_capacity_l"] = round(float(m.group(3)))
            specs["max_hp"] = int(m.group(4))
    if variant_id == "adv9":
        m = re.search(
            r"ADV9\s*\n([\d.]+)m\s*\n(?:([\d.]+)l\s*\n)?(\d+)hp",
            text,
            re.I,
        )
        if m:
            specs["length_mm"] = round(float(m.group(1)) * 1000)
            if m.group(2):
                specs["fuel_capacity_l"] = round(float(m.group(2)))
            specs["max_hp"] = int(m.group(3))
        cap = re.search(r"ADV9[\s\S]{0,400}?(\d+)\s*(?:people|passengers)", text, re.I)
        if cap:
            specs["capacity_persons"] = int(cap.group(1))

    return specs


def main():
    results = {}
    errors = []

    for series_id, variant_id, url in MODEL_URLS:
        try:
            html = fetch(url)
            specs = parse_specs(html, variant_id)
            results[variant_id] = {
                "series_id": series_id,
                "url": url,
                "specifications": specs,
            }
            print(f"OK  {variant_id}: {specs}", file=sys.stderr)
            time.sleep(0.35)
        except Exception as e:
            errors.append((variant_id, url, str(e)))
            print(f"ERR {variant_id}: {e}", file=sys.stderr)

    out_path = Path("/tmp/highfield-specs.json")
    if len(sys.argv) > 1:
        out_path = Path(sys.argv[1])
    out_path.write_text(json.dumps({"models": results, "errors": errors}, indent=2) + "\n")
    print(f"Wrote {len(results)} models to {out_path}", file=sys.stderr)


if __name__ == "__main__":
    main()
