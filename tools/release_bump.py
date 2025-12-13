#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Release helper for GESTNAV
- Bumps version and build date in config.php
- Prepends a new section in CHANGELOG.md (Keep a Changelog style)

Usage examples:
  python3 tools/release_bump.py --version 1.0.1
  python3 tools/release_bump.py --version 1.0.1 --date 2025-12-15 \
      --added "Nouvelle page d'aide" --changed "Amélioration performance stats" --fixed "Correction suppression sorties"

Notes:
- The script is idempotent per version: it won't prevent duplicates in CHANGELOG if run twice with same version.
- It doesn't deploy; use your existing FTP snippet after running it.
"""

import argparse
import datetime as dt
import os
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONFIG = ROOT / 'config.php'
CHANGELOG = ROOT / 'CHANGELOG.md'

RE_VERSION = re.compile(r"define\(\s*'GESTNAV_VERSION'\s*,\s*'([^']*)'\s*\)\s*;", re.IGNORECASE)
RE_BUILD = re.compile(r"define\(\s*'GESTNAV_BUILD_DATE'\s*,\s*'([^']*)'\s*\)\s*;", re.IGNORECASE)


def bump_config(version: str, build_date: str) -> None:
    if not CONFIG.exists():
        print(f"ERR: {CONFIG} introuvable", file=sys.stderr)
        sys.exit(1)
    content = CONFIG.read_text(encoding='utf-8')

    # Replace version
    if not RE_VERSION.search(content):
        print("ERR: GESTNAV_VERSION introuvable dans config.php", file=sys.stderr)
        sys.exit(1)
    content = RE_VERSION.sub(lambda m: m.group(0).replace(m.group(1), version), content, count=1)

    # Replace build date
    if not RE_BUILD.search(content):
        # If missing, append define just after version define
        content = RE_VERSION.sub(lambda m: m.group(0) + "\nif (!defined('GESTNAV_BUILD_DATE')) { define('GESTNAV_BUILD_DATE', '%s'); }" % build_date, content, count=1)
    else:
        content = RE_BUILD.sub(lambda m: m.group(0).replace(m.group(1), build_date), content, count=1)

    CONFIG.write_text(content, encoding='utf-8')
    print(f"✓ config.php mis à jour: version={version}, date={build_date}")


def build_changelog_block(version: str, date_str: str, added: list[str], changed: list[str], fixed: list[str]) -> str:
    lines = []
    lines.append(f"## [{version}] - {date_str}")
    if added:
        lines.append("### Added")
        lines += [f"- {x}" for x in added]
    if changed:
        lines.append("### Changed")
        lines += [f"- {x}" for x in changed]
    if fixed:
        lines.append("### Fixed")
        lines += [f"- {x}" for x in fixed]
    lines.append("")
    return "\n".join(lines)


def prepend_changelog(block: str) -> None:
    if not CHANGELOG.exists():
        header = "# Changelog\n\nAll notable changes to this project will be documented in this file.\n\n"
        CHANGELOG.write_text(header + block + "\n", encoding='utf-8')
        print("✓ CHANGELOG.md créé")
        return
    existing = CHANGELOG.read_text(encoding='utf-8')
    # Insert after first header line
    if existing.startswith('# Changelog'):
        parts = existing.split('\n', 2)
        if len(parts) >= 3:
            new_content = parts[0] + "\n" + parts[1] + "\n" + block + parts[2]
        else:
            new_content = existing + "\n" + block
    else:
        new_content = block + "\n" + existing
    CHANGELOG.write_text(new_content, encoding='utf-8')
    print("✓ CHANGELOG.md mis à jour")


def main():
    parser = argparse.ArgumentParser(description='Bump GESTNAV version and update CHANGELOG.')
    parser.add_argument('--version', required=True, help='New semantic version, e.g., 1.0.1')
    parser.add_argument('--date', help='Build/Release date YYYY-MM-DD (default: today)')
    parser.add_argument('--added', action='append', default=[], help='Added entry (repeatable)')
    parser.add_argument('--changed', action='append', default=[], help='Changed entry (repeatable)')
    parser.add_argument('--fixed', action='append', default=[], help='Fixed entry (repeatable)')
    args = parser.parse_args()

    try:
        date_iso = args.date or dt.date.today().strftime('%Y-%m-%d')
        # Validate date format
        dt.datetime.strptime(date_iso, '%Y-%m-%d')
    except Exception:
        print('ERR: date invalide, attendu YYYY-MM-DD', file=sys.stderr)
        sys.exit(1)

    bump_config(args.version, date_iso)

    block = build_changelog_block(
        version=args.version,
        date_str=date_iso,
        added=args.added,
        changed=args.changed,
        fixed=args.fixed,
    )
    prepend_changelog(block)

    print('\nTerminé. Pensez à déployer config.php et CHANGELOG.md.')


if __name__ == '__main__':
    main()
