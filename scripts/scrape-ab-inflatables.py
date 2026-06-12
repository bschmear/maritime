#!/usr/bin/env python3
"""Scrape AB Inflatables model specs from abinflatables.com."""

import json
import re
import sys
import time
from urllib.request import Request, urlopen
from urllib.error import HTTPError

BASE = "https://www.abinflatables.com"

MODEL_URLS = [
    # Nautilus
    ("nautilus", "nautilus-11-dlx", f"{BASE}/ribs-dinghies/nautilus/nautilus-11-dlx/"),
    ("nautilus", "nautilus-12-dlx", f"{BASE}/ribs-dinghies/nautilus/nautilus-12-dlx/"),
    ("nautilus", "nautilus-13-dlx", f"{BASE}/ribs-dinghies/nautilus/nautilus-13-dlx/"),
    ("nautilus", "nautilus-14-dlx", f"{BASE}/ribs-dinghies/nautilus/nautilus-14-dlx/"),
    ("nautilus", "nautilus-15-dlx", f"{BASE}/ribs-dinghies/nautilus/nautilus-15-dlx/"),
    ("nautilus", "nautilus-17-dlx", f"{BASE}/ribs-dinghies/nautilus/nautilus-17-dlx/"),
    ("nautilus", "nautilus-19-dlx", f"{BASE}/ribs-dinghies/nautilus/nautilus-19-dlx/"),
    ("nautilus", "nautilus-19-dlx-i-o", f"{BASE}/ribs-dinghies/nautilus/nautilus-19-dlx-i-o/"),
    # Oceanus
    ("oceanus", "oceanus-12-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-12-vst/"),
    ("oceanus", "oceanus-13-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-13-vst/"),
    ("oceanus", "oceanus-14-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-14-vst/"),
    ("oceanus", "oceanus-15-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-15-vst/"),
    ("oceanus", "oceanus-17-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-17-vst/"),
    ("oceanus", "oceanus-19-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-19-vst/"),
    ("oceanus", "oceanus-21-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-21-vst/"),
    ("oceanus", "oceanus-24-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-24-vst/"),
    ("oceanus", "oceanus-28-vst", f"{BASE}/ribs-dinghies/oceanus/oceanus-28-vst/"),
    # Mares
    ("mares", "mares-10-vsx", f"{BASE}/ribs-dinghies/mares/mares-10-vsx/"),
    ("mares", "mares-11-vsx", f"{BASE}/ribs-dinghies/mares/mares-11-vsx/"),
    ("mares", "mares-12-vsx", f"{BASE}/ribs-dinghies/mares/mares-12-vsx/"),
    # Navigo
    ("navigo", "navigo-8-vs", f"{BASE}/ribs-dinghies/navigo/navigo-8-vs/"),
    ("navigo", "navigo-9-vs", f"{BASE}/ribs-dinghies/navigo/navigo-9-vs/"),
    ("navigo", "navigo-10-vs", f"{BASE}/ribs-dinghies/navigo/navigo-10-vs/"),
    ("navigo", "navigo-12-vs", f"{BASE}/ribs-dinghies/navigo/navigo-12-vs/"),
    ("navigo", "navigo-13-vs", f"{BASE}/ribs-dinghies/navigo/navigo-13-vs/"),
    ("navigo", "navigo-14-vs", f"{BASE}/ribs-dinghies/navigo/navigo-14-vs/"),
    ("navigo", "navigo-15-vs", f"{BASE}/ribs-dinghies/navigo/navigo-15-vs/"),
    ("navigo", "navigo-17-vs", f"{BASE}/ribs-dinghies/navigo/navigo-17-vs/"),
    ("navigo", "navigo-19-vs", f"{BASE}/ribs-dinghies/navigo/navigo-19-vs/"),
    # Ventus
    ("ventus", "ventus-8-vl", f"{BASE}/ribs-dinghies/ventus/ventus-8-vl/"),
    ("ventus", "ventus-9-vl", f"{BASE}/ribs-dinghies/ventus/ventus-9-vl/"),
    ("ventus", "ventus-10-vl", f"{BASE}/ribs-dinghies/ventus/ventus-10-vl/"),
    ("ventus", "ventus-12-vl", f"{BASE}/ribs-dinghies/ventus/ventus-12-vl/"),
    # Alumina
    ("alumina", "alumina-9.5-alx", f"{BASE}/ribs-dinghies/alumina/alumina-9-5-alx/"),
    ("alumina", "alumina-10-alx", f"{BASE}/ribs-dinghies/alumina/alumina-10-alx/"),
    ("alumina", "alumina-11-alx", f"{BASE}/ribs-dinghies/alumina/alumina-11-alx/"),
    ("alumina", "alumina-12-alx", f"{BASE}/ribs-dinghies/alumina/alumina-12-alx/"),
    ("alumina", "alumina-13-alx", f"{BASE}/ribs-dinghies/alumina/alumina-13-alx/"),
    ("alumina", "alumina-14-alx", f"{BASE}/ribs-dinghies/alumina/alumina-14-alx/"),
    ("alumina", "alumina-15-alx", f"{BASE}/ribs-dinghies/alumina/alumina-15-alx/"),
    ("alumina", "alumina-16-alx", f"{BASE}/ribs-dinghies/alumina/alumina-16-alx/"),
    ("alumina", "alumina-16-alx-br", f"{BASE}/ribs-dinghies/alumina/alumina-16-alx-br/"),
    ("alumina", "alumina-18-alx", f"{BASE}/ribs-dinghies/alumina/alumina-18-alx/"),
    # Lammina AL
    ("lammina-al", "lammina-8-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-8-al/"),
    ("lammina-al", "lammina-9-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-9-al/"),
    ("lammina-al", "lammina-9.5-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-9-5-al/"),
    ("lammina-al", "lammina-10-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-10-al/"),
    ("lammina-al", "lammina-11-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-11-al/"),
    ("lammina-al", "lammina-12-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-12-al/"),
    ("lammina-al", "lammina-13-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-13-al/"),
    ("lammina-al", "lammina-14-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-14-al/"),
    ("lammina-al", "lammina-15-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-15-al/"),
    ("lammina-al", "lammina-16-al", f"{BASE}/ribs-dinghies/lammina-al/lammina-16-al/"),
    # Lammina UL
    ("lammina-ul", "lammina-7.5-ul", f"{BASE}/ribs-dinghies/lammina-ul/lammina-7-5-ul/"),
    ("lammina-ul", "lammina-8-ul", f"{BASE}/ribs-dinghies/lammina-ul/lammina-8-ul/"),
    ("lammina-ul", "lammina-9-ul", f"{BASE}/ribs-dinghies/lammina-ul/lammina-9-ul/"),
    ("lammina-ul", "lammina-10-ul", f"{BASE}/ribs-dinghies/lammina-ul/lammina-10-ul/"),
    # AB Rider
    ("ab-rider", "ab-rider", f"{BASE}/ribs-dinghies/ab-rider/ab-rider/"),
    # ABJET S
    ("abjet-s", "abjet-290", f"{BASE}/jet-tenders/s-series/abjet-290/"),
    ("abjet-s", "abjet-330", f"{BASE}/jet-tenders/s-series/abjet-330/"),
    ("abjet-s", "abjet-380", f"{BASE}/jet-tenders/s-series/abjet-380/"),
    # ABJET XP
    ("abjet-xp", "abjet-350-xp", f"{BASE}/jet-tenders/xp-series/abjet-350-xp/"),
    ("abjet-xp", "abjet-390-xp", f"{BASE}/jet-tenders/xp-series/abjet-390-xp/"),
    ("abjet-xp", "abjet-430-xp", f"{BASE}/jet-tenders/xp-series/abjet-430xp/"),
    ("abjet-xp", "abjet-465-xp", f"{BASE}/jet-tenders/xp-series/abjet-465xp/"),
    # ABJET Diesel
    ("abjet-diesel", "abjet-450-diesel", f"{BASE}/jet-tenders/diesel/abjet-450-diesel/"),
    # Profile A (short slugs like /a11/ serve PNGs; use profile-a* HTML pages)
    ("profile-a", "profile-a11", f"{BASE}/professional/profile-a11/"),
    ("profile-a", "profile-a12", f"{BASE}/professional/profile-a12/"),
    ("profile-a", "profile-a13", f"{BASE}/professional/profile-a13/"),
    ("profile-a", "profile-a14", f"{BASE}/professional/profile-a14/"),
    ("profile-a", "profile-a15", f"{BASE}/professional/profile-a15/"),
    ("profile-a", "profile-a16", f"{BASE}/professional/profile-a16/"),
    ("profile-a", "profile-a18", f"{BASE}/professional/profile-a18/"),
    # Profile A-XHD
    ("profile-a-xhd", "profile-a21-xhd", f"{BASE}/professional/profile-a21-xhd/"),
    ("profile-a-xhd", "profile-a24-xhd", f"{BASE}/professional/profile-a24-xhd/"),
    # Profile AS
    ("profile-as", "profile-a11-s", f"{BASE}/professional/profile-a11-s/"),
    ("profile-as", "profile-a12-s", f"{BASE}/professional/profile-a12-s/"),
    ("profile-as", "profile-a13-s", f"{BASE}/professional/profile-a13-s/"),
    ("profile-as", "profile-a14-s", f"{BASE}/professional/profile-a14-s/"),
    ("profile-as", "profile-a16-s", f"{BASE}/professional/profile-a16-s/"),
    # Profile F
    ("profile-f", "profile-f14", f"{BASE}/professional/profile-f14/"),
    ("profile-f", "profile-f15", f"{BASE}/professional/profile-f15/"),
    ("profile-f", "profile-f17", f"{BASE}/professional/profile-f17/"),
    ("profile-f", "profile-f19", f"{BASE}/professional/profile-f19/"),
    ("profile-f", "profile-f21", f"{BASE}/professional/profile-f21/"),
    ("profile-f", "profile-f24", f"{BASE}/professional/profile-f24/"),
    ("profile-f", "profile-f28", f"{BASE}/professional/profile-f28/"),
]


def fetch(url: str) -> str:
    req = Request(
        url,
        headers={
            "User-Agent": "Mozilla/5.0 (compatible; MaritimeBot/1.0)",
            "Accept": "text/html",
        },
    )
    with urlopen(req, timeout=30) as resp:
        content_type = resp.headers.get("Content-Type", "")
        data = resp.read()
        if "html" not in content_type.lower():
            raise ValueError(f"Expected HTML, got {content_type} from {url}")
        return data.decode("utf-8", errors="replace")


def m_to_mm(value: str) -> int | None:
  m = re.search(r"([\d.]+)\s*m(?:ts)?\b", value, re.I)
  if m:
    return round(float(m.group(1)) * 1000)
  return None


def parse_length(value: str) -> int | None:
  mm = m_to_mm(value)
  if mm:
    return mm
  ft = re.search(r"(\d+(?:\.\d+)?)\s*'", value)
  if ft:
    return round(float(ft.group(1)) * 304.8)
  return None


def parse_beam(value: str) -> int | None:
  mm = m_to_mm(value)
  if mm:
    return mm
  # e.g. 6'5" or 6'1"
  m = re.search(r"(\d+)'\s*(\d+)?", value)
  if m:
    feet = int(m.group(1))
    inches = int(m.group(2) or 0)
    return round((feet * 12 + inches) * 25.4)
  return None


def parse_number(value: str) -> float | None:
  m = re.search(r"([\d,]+(?:\.\d+)?)", value)
  if not m:
    return None
  return float(m.group(1).replace(",", ""))


def parse_weight_kg(value: str) -> int | None:
  m = re.search(r"([\d,]+(?:\.\d+)?)\s*kg", value, re.I)
  if m:
    return round(float(m.group(1).replace(",", "")))
  m = re.search(r"([\d,]+(?:\.\d+)?)\s*lb", value, re.I)
  if m:
    return round(float(m.group(1).replace(",", "")) * 0.453592)
  return None


def parse_hp(value: str) -> int | None:
  m = re.search(r"([\d.]+)\s*hp", value, re.I)
  if m:
    return round(float(m.group(1)))
  return None


def parse_fuel_l(value: str) -> int | None:
  m = re.search(r"([\d.]+)\s*lt", value, re.I)
  if m:
    return round(float(m.group(1)))
  m = re.search(r"([\d.]+)\s*lts?", value, re.I)
  if m:
    return round(float(m.group(1)))
  m = re.search(r"([\d.]+)\s*gal", value, re.I)
  if m:
    return round(float(m.group(1)) * 3.78541)
  return None


def extract_spec_block(html: str, label: str) -> str | None:
  # Match label followed by value in adjacent tags
  patterns = [
    rf">{re.escape(label)}<[^>]*>\s*<[^>]*>([^<]+)<",
    rf">{re.escape(label)}[^<]*</[^>]+>\s*<[^>]+>([^<]+)<",
    rf"{re.escape(label)}\s*</[^>]+>\s*<[^>]+>\s*([^<]+)<",
  ]
  for pat in patterns:
    m = re.search(pat, html, re.I | re.S)
    if m:
      return m.group(1).strip()
  # Fallback: text after label in stripped content
  text = re.sub(r"<[^>]+>", "\n", html)
  for line in text.split("\n"):
    if label.lower() in line.lower():
      continue
  m = re.search(rf"{re.escape(label)}\s*\n\s*([^\n]+)", text, re.I)
  if m:
    return m.group(1).strip()
  return None


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

  text = re.sub(r"<[^>]+>", "\n", html)
  text = re.sub(r"\n+", "\n", text)

  def next_value(label: str) -> str | None:
    m = re.search(rf"{re.escape(label)}\s*\n\s*([^\n]+)", text, re.I)
    return m.group(1).strip() if m else None

  length = next_value("Overall Length")
  if length:
    specs["length_mm"] = parse_length(length)

  beam = next_value("Overall Beam")
  if beam:
    specs["width_mm"] = parse_beam(beam)

  height = next_value("Height") or next_value("Height (With Removable Wheel)") or next_value("Height (with Helm Console Folded)")
  if height:
    specs["height_mm"] = parse_beam(height) or m_to_mm(height)

  capacity = next_value("Person Capacity")
  if capacity:
    m = re.search(r"(\d+)", capacity)
    if m:
      specs["capacity_persons"] = int(m.group(1))

  weight = next_value("Weight")
  if weight:
    specs["weight_kg"] = parse_weight_kg(weight)

  max_hp = next_value("Maximum HP") or next_value("Installed HP")
  if max_hp:
    specs["max_hp"] = parse_hp(max_hp)

  fuel = (
    next_value("Fuel Built-in Tanks")
    or next_value("Fuel Built-In Tanks")
    or next_value("Fuel Capacity")
  )
  if fuel:
    specs["fuel_capacity_l"] = parse_fuel_l(fuel)

  return specs


def extract_title(html: str) -> str | None:
  m = re.search(r"<h1[^>]*>([^<]+)</h1>", html, re.I)
  if m:
    return re.sub(r"\s+", " ", m.group(1)).strip()
  m = re.search(r"#\s+([A-Z0-9][^\n]+)", html)
  if m:
    return m.group(1).strip()
  return None


def main():
  results = {}
  errors = []

  for series_id, variant_id, url in MODEL_URLS:
    try:
      html = fetch(url)
      specs = parse_specs(html)
      title = extract_title(html)
      results[variant_id] = {
        "series_id": series_id,
        "url": url,
        "name": title,
        "specifications": specs,
      }
      print(f"OK  {variant_id}: {specs}", file=sys.stderr)
      time.sleep(0.3)
    except HTTPError as e:
      errors.append((variant_id, url, str(e)))
      print(f"ERR {variant_id}: {e}", file=sys.stderr)
    except Exception as e:
      errors.append((variant_id, url, str(e)))
      print(f"ERR {variant_id}: {e}", file=sys.stderr)

  output = {"models": results, "errors": [{"id": v, "url": u, "error": e} for v, u, e in errors]}
  print(json.dumps(output, indent=2))


if __name__ == "__main__":
  main()
