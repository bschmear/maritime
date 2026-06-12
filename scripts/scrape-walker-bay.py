#!/usr/bin/env python3
"""Scrape Walker Bay model specs from walkerbay.com."""

import html as html_lib
import json
import re
import sys
import time
from urllib.request import Request, urlopen

BASE = "https://walkerbay.com"
UA = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

MODEL_URLS = [
    ("walker-bay-22", "walker-bay-22", f"{BASE}/model/walker-bay-22/"),
    ("generation-12", "generation-12", f"{BASE}/model/generation-12/"),
    ("generation-15", "generation-15", f"{BASE}/model/generation-15/"),
    ("generation-dlx", "generation-340-dlx", f"{BASE}/model/generation-340/"),
    ("generation-dlx", "generation-360-dlx", f"{BASE}/model/generation-360/"),
    ("generation-dlx", "generation-400-dlx", f"{BASE}/model/generation-400/"),
    ("generation-dlx", "generation-450-dlx", f"{BASE}/model/generation-450/"),
    ("generation-dlx", "generation-525-dlx", f"{BASE}/model/generation-525/"),
    ("generation-lte", "generation-10-lte", f"{BASE}/model/generation-10lte/"),
    ("generation-lte", "generation-11-lte", f"{BASE}/model/generation-11lte/"),
    ("generation-lte", "generation-12-lte", f"{BASE}/model/generation-12lte/"),
    ("generation-lte", "generation-13-lte", f"{BASE}/model/generation-13lte/"),
    ("generation-lte", "generation-14-lte", f"{BASE}/model/generation-14lte/"),
    ("venture", "venture-13", f"{BASE}/model/venture-13/"),
    ("venture", "venture-14", f"{BASE}/model/venture-14/"),
    ("venture", "venture-16", f"{BASE}/model/venture-16/"),
    ("stx", "stx-325", f"{BASE}/model/325-stx-deluxe-console/"),
    ("stx", "stx-365", f"{BASE}/model/365-stx-deluxe-console/"),
]


def fetch(url: str) -> str:
    req = Request(url, headers={"User-Agent": UA, "Accept": "text/html,application/xhtml+xml"})
    with urlopen(req, timeout=30) as resp:
        return resp.read().decode("utf-8", errors="replace")


def cm_to_mm(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*cm\b", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 10)
    return None


def m_to_mm(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*m\b", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 1000)
    return None


def normalize_quotes(value: str) -> str:
    return (
        value.replace("\u2019", "'")
        .replace("\u2018", "'")
        .replace("\u2032", "'")
        .replace("\u201d", '"')
        .replace("\u201c", '"')
        .replace("\u2033", '"')
    )


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


def parse_length(value: str) -> int | None:
    return cm_to_mm(value) or m_to_mm(value) or ft_in_to_mm(value)


def parse_beam(value: str) -> int | None:
    return m_to_mm(value) or cm_to_mm(value) or ft_in_to_mm(value)


def parse_weight_kg(value: str) -> int | None:
    m = re.search(r"([\d,]+(?:\.\d+)?)\s*kg", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d,]+(?:\.\d+)?)\s*lbs?", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 0.453592)
    return None


def parse_hp(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*hp", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d.,]+)\s*kw", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 1.34102)
    return None


def parse_fuel_l(value: str) -> int | None:
    m = re.search(r"([\d.,]+)\s*L\b", value)
    if m:
        return round(float(m.group(1).replace(",", "")))
    m = re.search(r"([\d.,]+)\s*gal", value, re.I)
    if m:
        return round(float(m.group(1).replace(",", "")) * 3.78541)
    return None


def extract_spec_section(html: str) -> str:
    text = html_lib.unescape(re.sub(r"<[^>]+>", "\n", html))
    text = re.sub(r"\n+", "\n", text)
    m = re.search(
        r"Features and Specifications\s*\n\s*Specifications\s*\n(.*?)(?:Standard Features|Optional Features|Manuals and Brochures)\b",
        text,
        re.I | re.S,
    )
    if m:
        return m.group(1)
    m = re.search(
        r"\nSpecifications\s*\n(.*?)(?:Standard Features|Optional Features|Manuals and Brochures)\b",
        text,
        re.I | re.S,
    )
    return m.group(1) if m else text


def parse_dimension(value: str) -> int | None:
    imperial = ft_in_to_mm(value)
    metric = cm_to_mm(value) or m_to_mm(value)
    if imperial and metric:
        return imperial if imperial > metric else metric
    return imperial or metric


def parse_specs(html: str) -> dict:
    specs = {
        "length_mm": None,
        "width_mm": None,
        "height_mm": None,
        "weight_kg": None,
        "capacity_persons": None,
        "max_hp": None,
        "fuel_capacity_l": None,
    }

    text = extract_spec_section(html)

    def field(label: str) -> str | None:
        m = re.search(rf"{re.escape(label)}\*{{0,3}}\s*[–\-—]\s*([^\n]+)", text, re.I)
        return normalize_quotes(m.group(1).strip()) if m else None

    loa = field("LOA")
    if loa:
        specs["length_mm"] = parse_dimension(loa)

    beam = field("Beam")
    if beam:
        specs["width_mm"] = parse_dimension(beam)

    passengers = field("Max Passengers")
    if passengers:
        m = re.search(r"(\d+)", passengers)
        if m:
            specs["capacity_persons"] = int(m.group(1))

    weight = field("Boat Weight")
    if weight:
        specs["weight_kg"] = parse_weight_kg(weight)

    max_power = field("Max Power")
    if max_power:
        specs["max_hp"] = parse_hp(max_power)

    tank = field("Tank Size")
    if tank:
        specs["fuel_capacity_l"] = parse_fuel_l(tank)

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
            time.sleep(0.4)
        except Exception as e:
            errors.append((variant_id, url, str(e)))
            print(f"ERR {variant_id}: {e}", file=sys.stderr)

    print(json.dumps({"models": results, "errors": errors}, indent=2))


if __name__ == "__main__":
    main()
