#!/usr/bin/env python3
"""Scrape Ranieri Cayman specs from ranieri-international.com."""

import json
import re
import sys
import time
from pathlib import Path
from urllib.request import Request, urlopen

BASE = "https://ranieri-international.com"
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36"

MODEL_URLS = [
    ("cayman-one-luxury-tender", "cayman-one-luxury-tender", f"{BASE}/cayman-one-luxury-tender/"),
    ("cayman-sport", "cayman-19-sport", f"{BASE}/cayman-19-sport/"),
    ("cayman-sport", "cayman-21-sport", f"{BASE}/cayman-21-sport/"),
    ("cayman-sport", "cayman-23-sport", f"{BASE}/cayman-23-sport/"),
    ("cayman-sport", "cayman-26-sport", f"{BASE}/cayman-26-sport/"),
    ("cayman-sport-touring", "cayman-23-sport-touring", f"{BASE}/cayman-23-sport-touring/"),
    ("cayman-sport-touring", "cayman-26-sport-touring", f"{BASE}/cayman-26-sport-touring/"),
    ("cayman-sport-touring", "cayman-27-sport-touring", f"{BASE}/cayman-27-sport-touring/"),
    ("cayman-executive", "cayman-28-executive", f"{BASE}/cayman-28-0-executive/"),
    ("cayman-executive", "cayman-33-executive", f"{BASE}/cayman-33-0-executive/"),
    ("cayman-executive", "cayman-35-executive", f"{BASE}/cayman-35-0-executive/"),
    ("cayman-executive", "cayman-38-executive", f"{BASE}/cayman-38-0-executive/"),
    ("cayman-cruiser", "cayman-45-cruiser", f"{BASE}/cayman-45-0-cruiser/"),
    ("cayman-cruiser", "cayman-50-hard-top", f"{BASE}/cayman-50-0-hard-top/"),
]


def fetch(url: str) -> str:
    req = Request(url, headers={"User-Agent": UA, "Accept": "text/html"})
    with urlopen(req, timeout=45) as resp:
        return resp.read().decode("utf-8", errors="replace")


def parse_fields(html: str) -> dict[str, str]:
    items = re.findall(
        r'elementor-price-list-title">\s*([^<]+?)\s*</span>\s*<span class="elementor-price-list-price">\s*([^<]+?)\s*</span>',
        html,
        re.S,
    )
    return {k.strip(): v.strip() for k, v in items}


def metric_meters(value: str) -> float | None:
    value = value.split("/")[0].strip()
    value = re.sub(r"\s*m\s*$", "", value, flags=re.I)
    value = value.replace(" ", "").replace(",", ".")
    m = re.search(r"([\d.]+)", value)
    return float(m.group(1)) if m else None


def parse_kg(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*kg", value, re.I)
    if not m:
        return None
    num = m.group(1).replace(".", "").replace(",", ".")
    return round(float(num))


def parse_liters(fields: dict[str, str]) -> int | None:
    for key, val in fields.items():
        if "serbatoio carburante" in key.lower():
            m = re.search(r"([\d.,]+)\s*L\b", val, re.I)
            if m:
                num = m.group(1).replace(".", "").replace(",", ".")
                return round(float(num))
    return None


def parse_hp(value: str) -> int | None:
    nums = [float(n.replace(",", ".")) for n in re.findall(r"([\d.,]+)\s*Hp", value, re.I)]
    if not nums:
        parts = re.findall(r"([\d.,]+)", value)
        nums = [float(p.replace(",", ".")) for p in parts]
    return round(max(nums)) if nums else None


def parse_specs(html: str) -> dict:
    fields = parse_fields(html)
    specs = {
        "length_mm": None,
        "width_mm": None,
        "height_mm": None,
        "weight_kg": None,
        "capacity_persons": None,
        "max_hp": None,
        "fuel_capacity_l": None,
    }

    loa = metric_meters(fields.get("Lunghezza F.T.", ""))
    beam = metric_meters(fields.get("Larghezza F.T.", ""))
    if loa is not None:
        specs["length_mm"] = round(loa * 1000)
    if beam is not None:
        specs["width_mm"] = round(beam * 1000)
    specs["weight_kg"] = parse_kg(fields.get("Peso", ""))
    people = fields.get("Persone a bordo", "")
    if people.isdigit():
        specs["capacity_persons"] = int(people)
    specs["max_hp"] = parse_hp(fields.get("Potenza Min/Max", ""))
    specs["fuel_capacity_l"] = parse_liters(fields)
    return specs


def main():
    results = {}
    errors = []

    for series_id, variant_id, url in MODEL_URLS:
        try:
            html = fetch(url)
            specs = parse_specs(html)
            results[variant_id] = {
                "series_id": series_id,
                "url": url,
                "specifications": specs,
            }
            print(f"OK  {variant_id}: {specs}", file=sys.stderr)
            time.sleep(0.3)
        except Exception as e:
            errors.append((variant_id, url, str(e)))
            print(f"ERR {variant_id}: {e}", file=sys.stderr)

    out_path = Path("/tmp/ranieri-specs.json")
    if len(sys.argv) > 1:
        out_path = Path(sys.argv[1])
    out_path.write_text(json.dumps({"models": results, "errors": errors}, indent=2) + "\n")
    print(f"Wrote {len(results)} models to {out_path}", file=sys.stderr)


if __name__ == "__main__":
    main()
