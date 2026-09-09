#!/usr/bin/env python3
"""Compile .po files to GNU .mo (CPython-compatible) using pure Python.

Usage: python3 msgfmt.py input.po -o output.mo

Handles plain entries and plural pairs (msgid_plural + msgstr[0]/[1]).
Plural entries are encoded msgid\\x00msgid_plural -> msgstr[0]\\x00msgstr[1].
"""
import ast
import struct
import sys
from collections import OrderedDict

LE_MAGIC = 0x950412DE
VERSION = 0


def build_mo(items, out_f):
    """items: list of (key, value) byte strings, header ('', header) first."""
    items = sorted(items, key=lambda kv: kv[0].encode('utf-8'))

    n = len(items)
    o_orig = 28
    o_trans = o_orig + 8 * n + sum(len(k.encode('utf-8')) + 1 for k, _ in items)

    orig_table = b''
    trans_table = b''
    orig_data = b''
    trans_data = b''

    for msgid, msgstr in items:
        kb = msgid.encode('utf-8')
        vb = msgstr.encode('utf-8')
        orig_table += struct.pack('<II', len(kb), o_orig + 8 * n + len(orig_data))
        orig_data += kb + b'\x00'
        trans_table += struct.pack('<II', len(vb), o_trans + 8 * n + len(trans_data))
        trans_data += vb + b'\x00'

    out_f.write(struct.pack('<7I', LE_MAGIC, VERSION, n, o_orig, o_trans, 0, 0))
    out_f.write(orig_table)
    out_f.write(orig_data)
    out_f.write(trans_table)
    out_f.write(trans_data)


HEADER = (
    'Project-Id-Version: Debug 1.14\n'
    'Report-Msgid-Bugs-To: https://soninow.com/contact\n'
    'POT-Creation-Date: 2026-09-09 00:00:00+0000\n'
    'PO-Revision-Date: 2026-09-09 00:00:00+0000\n'
    'Last-Translator: SoniNow Team <support@soninow.com>\n'
    'MIME-Version: 1.0\n'
    'Content-Type: text/plain; charset=UTF-8\n'
    'Content-Transfer-Encoding: 8bit\n'
    'Plural-Forms: nplurals=2; plural=(n != 1);\n'
    'X-Domain: debug\n'
)


def parse_po(path):
    """Parse .po into OrderedDict { msgid: (msgstr, msgid_plural_or_None, [plurals]) }."""
    catalog = OrderedDict()
    with open(path, encoding='utf-8') as f:
        lines = f.read().splitlines()

    cur_id = None
    cur_id_plural = None
    cur_str = None
    cur_plurals = {}

    def unquote(s):
        try:
            return ast.literal_eval(s)
        except (ValueError, SyntaxError):
            return s.strip().strip('"')

    def commit():
        nonlocal cur_id, cur_id_plural, cur_str, cur_plurals
        if cur_id is not None:
            catalog[cur_id] = (cur_str or '', cur_id_plural, list(cur_plurals.values()))
        cur_id, cur_id_plural, cur_str, cur_plurals = None, None, None, {}

    for line in lines:
        line = line.strip()
        if line.startswith('msgid '):
            commit()
            cur_id = unquote(line[len('msgid '):])
        elif line.startswith('msgid_plural '):
            cur_id_plural = unquote(line[len('msgid_plural '):])
        elif line.startswith('msgstr['):
            try:
                idx = int(line.split(']')[0].split('[')[1])
                cur_plurals[idx] = unquote(line.split(' ', 1)[1])
            except (ValueError, IndexError):
                pass
        elif line.startswith('msgstr '):
            cur_str = unquote(line[len('msgstr '):])
    commit()
    return catalog


def main():
    if len(sys.argv) < 4 or '-o' not in sys.argv:
        print('Usage: msgfmt.py input.po -o output.mo')
        sys.exit(1)
    idx = sys.argv.index('-o')
    inpo, outmo = sys.argv[1], sys.argv[idx + 1]

    catalog = parse_po(inpo)
    if not catalog.get('', ('', None, []))[0]:
        catalog[''] = (HEADER, None, [])

    items = []
    for msgid, (msgstr, id_plural, plurals) in catalog.items():
        if msgid == '':
            items.append(('', msgstr))
        elif id_plural is not None:
            key = msgid + '\x00' + id_plural
            val = msgstr
            if plurals:
                val = msgstr + '\x00' + plurals[0]
            items.append((key, val))
        else:
            items.append((msgid, msgstr))

    with open(outmo, 'wb') as f:
        build_mo(items, f)
    print(f'Wrote {outmo}: {len(items) - 1} entries')


if __name__ == '__main__':
    main()
