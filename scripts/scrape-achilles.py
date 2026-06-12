#!/usr/bin/env python3
"""Scrape Achilles model specs from achillesboats.com series pages."""

import json
import re
import sys
import time
from pathlib import Path
from urllib.request import Request, urlopen

BASE = "https://achillesboats.com"
UA = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

SERIES_URLS = {
    "lex-series": f"{BASE}/boatmodels/tendersandsportboats/lex/",
    "lsr-e-series": f"{BASE}/boatmodels/tendersandsportboats/lsr/",
    "spd-e-series": f"{BASE}/boatmodels/tendersandsportboats/spd/",
    "lsi-e-series": f"{BASE}/boatmodels/tendersandsportboats/lsi/",
    "lt-series": f"{BASE}/boatmodels/dinghies/lt/",
    "ls-ru-series": f"{BASE}/boatmodels/dinghies/ls/",
    "hb-al-series": f"{BASE}/boatmodels/rigidhulls/hb_al/",
    "hb-ax-series": f"{BASE}/boatmodels/rigidhulls/hb_ax/",
    "hb-fx-series": f"{BASE}/boatmodels/rigidhulls/hb_fx/",
    "hb-lx-series": f"{BASE}/boatmodels/rigidhulls/hb_lx/",
    "hb-dx-series": f"{BASE}/boatmodels/rigidhulls/hb_dx/",
    "frb-series": f"{BASE}/boatmodels/sportutility/frb_sport_utility/",
    "sgx-series": f"{BASE}/boatmodels/sportutility/sgx/",
    "sg-series": f"{BASE}/boatmodels/sportutility/sg/",
    "su-series": f"{BASE}/boatmodels/sportutility/su/",
    "ksb-series": f"{BASE}/boatmodels/river/ksb/",
    "rv-series": f"{BASE}/boatmodels/river/rv/",
    "rv-sb-series": f"{BASE}/boatmodels/river/rv-sb/",
    "rv-sb-3t-series": f"{BASE}/boatmodels/river/rv-sb-3t/",
    "fr-series": f"{BASE}/boatmodels/river/fr/",
}


def fetch(url: str) -> str:
    req = Request(url, headers={"User-Agent": UA, "Accept": "text/html,*/*"})
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
        .replace("\ufffd", '"')
        .replace("ï¿½", '"')
    )


def cm_to_mm(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*cm\b", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 10)
    return None


def ft_in_to_mm(value: str) -> int | None:
    value = normalize_quotes(value)
    m = re.search(r"(\d+)'\s*(\d+)?\"?", value)
    if m:
        feet = int(m.group(1))
        inches = int(m.group(2) or 0)
        return round((feet * 12 + inches) * 25.4)
    return None


def parse_dimension(value: str) -> int | None:
    value = normalize_quotes(value)
    metric = cm_to_mm(value)
    if metric:
        return metric
    return ft_in_to_mm(value)


def parse_weight_kg(value: str) -> int | None:
    m = re.search(r"([\d,]+(?:\.\d+)?)\s*kg", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d,]+(?:\.\d+)?)\s*lbs?", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 0.453592)
    return None


def parse_hp(value: str) -> int | None:
    nums = [float(n.replace(",", "")) for n in re.findall(r"([\d.,]+)\s*hp", value, re.I)]
    if not nums:
        nums = [float(n.replace(",", "")) for n in re.findall(r"\b(\d+)\b", value)]
    return round(max(nums)) if nums else None


def parse_capacity(value: str) -> int | None:
    value = value.strip().rstrip("*")
    m = re.search(r"(\d+)", value)
    return int(m.group(1)) if m else None


def parse_raw_specs(raw: dict[str, str]) -> dict:
    specs = {
        "length_mm": None,
        "width_mm": None,
        "height_mm": None,
        "weight_kg": None,
        "capacity_persons": None,
        "max_hp": None,
        "fuel_capacity_l": None,
    }

    length = raw.get("Length")
    if length:
        specs["length_mm"] = parse_dimension(normalize_quotes(length))

    beam = raw.get("Beam")
    if beam:
        specs["width_mm"] = parse_dimension(normalize_quotes(beam))

    weight = raw.get("Weight")
    if weight:
        specs["weight_kg"] = parse_weight_kg(normalize_quotes(weight))

    capacity = raw.get("Person Capacity")
    if capacity:
        specs["capacity_persons"] = parse_capacity(capacity)

    hp = raw.get("Maximum H.P.") or raw.get("Recommended H.P.")
    if hp:
        specs["max_hp"] = parse_hp(normalize_quotes(hp))

    return specs


def parse_series_page(html: str) -> list[dict]:
    models = []
    wrappers = list(re.finditer(r'id="([^"]+)"\s+class="prod-wrapper"', html))
    for i, m in enumerate(wrappers):
        vid = m.group(1)
        end = wrappers[i + 1].start() if i + 1 < len(wrappers) else m.end() + 8000
        chunk = html[m.start() : end]
        title_m = re.search(r'class="prod-title[^"]*">([^<]+)<', chunk)
        name = title_m.group(1).strip() if title_m else vid.upper()
        raw_specs = {}
        for sm in re.finditer(
            r'class="prod-spec-name[^"]*">([^<]+)</div>\s*<div[^>]*>([^<]+)</div>',
            chunk,
        ):
            raw_specs[sm.group(1).strip()] = normalize_quotes(sm.group(2).strip())
        models.append(
            {
                "id": vid,
                "name": name,
                "raw_specs": raw_specs,
                "specifications": parse_raw_specs(raw_specs),
            }
        )
    return models


def main():
    results = {}
    series_variants = {}
    errors = []

    for series_id, url in SERIES_URLS.items():
        try:
            html = fetch(url)
            models = parse_series_page(html)
            series_variants[series_id] = [m["id"] for m in models]
            for model in models:
                vid = model["id"]
                results[vid] = {
                    "series_id": series_id,
                    "url": url,
                    "name": model["name"],
                    "specifications": model["specifications"],
                    "raw_specs": model["raw_specs"],
                }
                print(f"OK  {vid}: {model['specifications']}", file=sys.stderr)
            time.sleep(0.35)
        except Exception as e:
            errors.append((series_id, url, str(e)))
            print(f"ERR {series_id}: {e}", file=sys.stderr)

    out_path = Path("/tmp/achilles-specs.json")
    if len(sys.argv) > 1:
        out_path = Path(sys.argv[1])
    out_path.write_text(
        json.dumps(
            {"models": results, "series_variants": series_variants, "errors": errors},
            indent=2,
        )
        + "\n"
    )
    print(f"Wrote {len(results)} models to {out_path}", file=sys.stderr)


if __name__ == "__main__":
    main()
