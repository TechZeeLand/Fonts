#!/usr/bin/env python3
"""TZL Fonts helper: inspect fonts and convert to WOFF2 (needs fonttools + brotli).

  fontkit.py check                 -> {"fonttools": bool, "brotli": bool}
  fontkit.py info  <font>          -> JSON metadata
  fontkit.py woff2 <src> <dest>    -> converts to WOFF2
"""
import json
import sys

# script -> (codepoint ranges, minimum share of those codepoints the font must cover)
SCRIPTS = {
    "latin": ([(0x41, 0x5A), (0x61, 0x7A)], 0.98),
    "latin-ext": ([(0x100, 0x17F)], 0.60),
    "vietnamese": ([(0x1EA0, 0x1EF9)], 0.70),
    "cyrillic": ([(0x410, 0x44F)], 0.90),
    "greek": ([(0x391, 0x3C9)], 0.85),
    "hebrew": ([(0x5D0, 0x5EA)], 0.90),
    "arabic": ([(0x627, 0x64A)], 0.90),
    "devanagari": ([(0x905, 0x939)], 0.80),
    "bengali": ([(0x985, 0x9B9)], 0.70),
    "thai": ([(0xE01, 0xE2E)], 0.90),
    "japanese": ([(0x3041, 0x3096)], 0.90),
}


def imports():
    try:
        import fontTools  # noqa: F401
        ft = True
    except Exception:
        ft = False
    try:
        import brotli  # noqa: F401
        br = True
    except Exception:
        br = False
    return ft, br


def name_of(table, ids):
    for nid in ids:
        for pref in ((3, 1, 0x409), (1, 0, 0), None):
            for rec in table.names:
                if rec.nameID != nid:
                    continue
                if pref and (rec.platformID, rec.platEncID, rec.langID) != pref:
                    continue
                try:
                    s = rec.toUnicode().strip()
                except Exception:
                    continue
                if s:
                    return s
    return ""


def info(path):
    from fontTools.ttLib import TTFont
    f = TTFont(path, lazy=True)
    name = f["name"]
    os2 = f["OS/2"] if "OS/2" in f else None
    weight = int(getattr(os2, "usWeightClass", 400) or 400)
    italic = False
    if os2 is not None and (os2.fsSelection & 1):
        italic = True
    if "head" in f and (f["head"].macStyle & 2):
        italic = True
    variable = "fvar" in f
    axes, wmin, wmax = [], None, None
    if variable:
        for a in f["fvar"].axes:
            axes.append({"tag": a.axisTag, "min": a.minValue, "default": a.defaultValue, "max": a.maxValue})
            if a.axisTag == "wght":
                wmin, wmax = int(a.minValue), int(a.maxValue)
    cmap = {}
    try:
        cmap = f.getBestCmap() or {}
    except Exception:
        pass
    subsets = []
    for key, (ranges, need) in SCRIPTS.items():
        total = sum(b - a + 1 for a, b in ranges)
        have = sum(1 for a, b in ranges for c in range(a, b + 1) if c in cmap)
        if total and have / total >= need:
            subsets.append(key)
    return {
        "family": name_of(name, (16, 1)),
        "subfamily": name_of(name, (17, 2)),
        "full_name": name_of(name, (4,)),
        "ps_name": name_of(name, (6,)),
        "weight": weight,
        "italic": italic,
        "version": name_of(name, (5,)),
        "copyright": name_of(name, (0,)),
        "license_desc": name_of(name, (13,)),
        "license_url": name_of(name, (14,)),
        "designer": name_of(name, (9, 8)),
        "glyphs": int(f["maxp"].numGlyphs),
        "variable": variable,
        "axes": axes,
        "wmin": wmin,
        "wmax": wmax,
        "subsets": subsets,
        "flavor": f.flavor or ("otf" if f.sfntVersion == "OTTO" else "ttf"),
    }


def to_woff2(src, dest):
    from fontTools.ttLib import TTFont
    f = TTFont(src)
    f.flavor = "woff2"
    f.save(dest)


def main(argv):
    if len(argv) < 2:
        print(__doc__)
        return 2
    cmd = argv[1]
    if cmd == "check":
        ft, br = imports()
        print(json.dumps({"fonttools": ft, "brotli": br}))
        return 0
    try:
        if cmd == "info" and len(argv) >= 3:
            print(json.dumps(info(argv[2])))
            return 0
        if cmd == "woff2" and len(argv) >= 4:
            to_woff2(argv[2], argv[3])
            print(json.dumps({"ok": True}))
            return 0
    except Exception as exc:  # report to PHP via stderr + exit code
        sys.stderr.write("%s: %s\n" % (type(exc).__name__, exc))
        return 1
    print(__doc__)
    return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv))
