#!/usr/bin/env python3
"""Generate debug.pot + per-locale .po files from PHP source + translations_data.py.

Canonical msgids are extracted from the plugin PHP files so the POT never
drifts from the code. The single _n() plural pair is emitted in proper
msgid/msgid_plural form.
"""
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)

import translations_data as t

PLURAL_PAIR = ("Old backup pruned (kept the latest %d).",
               "%d old backups pruned (kept the latest set).")

LOCALES = [
    ("es_ES", "_ES", "Español"),
    ("de_DE", "_DE", "Deutsch"),
    ("fr_FR", "_FR", "Français"),
    ("it_IT", "_IT", "Italiano"),
    ("pt_BR", "_PT", "Português do Brasil"),
    ("ja",     "_JA", "日本語"),
    ("nl_NL", "_NL", "Nederlands"),
    ("zh_CN", "_ZH", "简体中文"),
    ("ru_RU", "_RU", "Русский"),
    ("tr_TR", "_TR", "Türkçe"),
    ("pl_PL", "_PL", "Polski"),
    ("ko_KR", "_KO", "한국어"),
    ("id_ID", "_ID", "Bahasa Indonesia"),
    ("hi_IN", "_HI", "हिन्दी"),
]

HEADER = """# Copyright (C) 2026 SoniNow Team
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: Debug 1.14\\n"
"Report-Msgid-Bugs-To: https://soninow.com/contact\\n"
"POT-Creation-Date: 2026-09-09 00:00:00+0000\\n"
"PO-Revision-Date: 2026-09-09 00:00:00+0000\\n"
"Last-Translator: SoniNow Team <support@soninow.com>\\n"
"Language-Team: {lang} <{code}@li.org>\\n"
"Language: {code}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Generator: OpenClaw\\n"
"X-Domain: debug\\n"

"""


def extract_msgids():
    """Canonical msgid list from PHP + plugin header description."""
    seen, seen_set = [], set()

    def add(s):
        s = s.replace("\\'", "'").replace('\\"', '"')
        if s not in seen_set and s != "":
            seen_set.add(s)
            seen.append(s)

    for path in ["debug.php", "functions/function.php", "functions/plugin.php",
                 "admin/setting.php", "admin/debuglog.php"]:
        src = open(os.path.join(HERE, "..", path), encoding="utf-8").read()
        for m in re.finditer(r"(?:__|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\s*\(\s*'([^']*)'\s*,\s*'debug'", src):
            add(m.group(1))
        for m in re.finditer(r"_n\s*\(\s*'([^']*)'\s*,\s*'([^']*)'\s*,\s*\$?\w+\s*,\s*'debug'", src):
            add(m.group(1))
            add(m.group(2))

    add("Debug your WordPress site, multisite and plugins. Debug is a development/production tool that helps you remove bugs from your WordPress website.")
    return seen


def esc(s):
    return s.replace("\\", "\\\\").replace('"', '\\"')


def write_plain(f, msgid, msgstr):
    f.write(f'msgid "{esc(msgid)}"\n')
    f.write(f'msgstr "{esc(msgstr)}"\n\n')


def write_plural(f, singular, plural, s_trans, p_trans):
    f.write(f'msgid "{esc(singular)}"\n')
    f.write(f'msgid_plural "{esc(plural)}"\n')
    f.write(f'msgstr[0] "{esc(s_trans)}"\n')
    f.write(f'msgstr[1] "{esc(p_trans)}"\n\n')


def main():
    msgids = extract_msgids()
    # Remove the plural variant from plain list (pair handled specially)
    if PLURAL_PAIR[1] in msgids:
        msgids.remove(PLURAL_PAIR[1])
    print(f"Canonical msgids: {len(msgids) + 1} (1 plural pair)")

    # POT
    pot_path = os.path.join(HERE, "debug.pot")
    with open(pot_path, "w", encoding="utf-8") as f:
        f.write(HEADER.format(code="xx", lang=""))
        for msgid in msgids:
            if msgid == PLURAL_PAIR[0]:
                write_plural(f, PLURAL_PAIR[0], PLURAL_PAIR[1], "", "")
            else:
                write_plain(f, msgid, "")
    print(f"wrote debug.pot")

    # Per-locale .po
    for code, dict_name, lang in LOCALES:
        data = getattr(t, dict_name)
        po_path = os.path.join(HERE, f"debug-{code}.po")
        with open(po_path, "w", encoding="utf-8") as f:
            f.write(HEADER.format(code=code, lang=lang))
            n = 0
            for msgid in msgids:
                if msgid == PLURAL_PAIR[0]:
                    write_plural(f, PLURAL_PAIR[0], PLURAL_PAIR[1],
                                 data.get(PLURAL_PAIR[0], PLURAL_PAIR[0]),
                                 data.get(PLURAL_PAIR[1], PLURAL_PAIR[1]))
                else:
                    write_plain(f, msgid, data.get(msgid, msgid))
                n += 1
        print(f"wrote debug-{code}.po ({n} entries)")


if __name__ == "__main__":
    main()
