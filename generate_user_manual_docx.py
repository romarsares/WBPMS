"""
Generate docs/User_Manual.docx from docs/User_Manual.md
Run: python generate_user_manual_docx.py
"""

import re
from pathlib import Path
from docx import Document
from docx.shared import Pt, RGBColor, Inches, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

MD_PATH   = Path(__file__).parent / "docs" / "User_Manual.md"
DOCX_PATH = Path(__file__).parent / "docs" / "User_Manual.docx"


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def set_cell_bg(cell, hex_color: str):
    tc   = cell._tc
    tcPr = tc.get_or_add_tcPr()
    shd  = OxmlElement('w:shd')
    shd.set(qn('w:val'),   'clear')
    shd.set(qn('w:color'), 'auto')
    shd.set(qn('w:fill'),  hex_color)
    tcPr.append(shd)


def add_horizontal_rule(doc):
    p   = doc.add_paragraph()
    pPr = p._p.get_or_add_pPr()
    pb  = OxmlElement('w:pBdr')
    bot = OxmlElement('w:bottom')
    bot.set(qn('w:val'),   'single')
    bot.set(qn('w:sz'),    '6')
    bot.set(qn('w:space'), '1')
    bot.set(qn('w:color'), 'AAAAAA')
    pb.append(bot)
    pPr.append(pb)
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after  = Pt(2)


def clean_text(text: str) -> str:
    """Strip markdown link syntax and bold/code markers for plain text."""
    text = re.sub(r'\[([^\]]+)\]\([^)]*\)', r'\1', text)
    text = text.replace('**', '')
    text = re.sub(r'`([^`]+)`', r'\1', text)
    return text.strip()


def add_inline_runs(para, text: str):
    """Add runs to a paragraph with **bold** and `code` inline formatting."""
    # Strip markdown link syntax first (keep the label)
    text = re.sub(r'\[([^\]]+)\]\([^)]*\)', r'\1', text)
    pattern = r'(\*\*[^*]+\*\*|`[^`]+`)'
    parts   = re.split(pattern, text)
    for part in parts:
        if part.startswith('**') and part.endswith('**'):
            r = para.add_run(part[2:-2])
            r.bold = True
        elif part.startswith('`') and part.endswith('`'):
            r = para.add_run(part[1:-1])
            r.font.name = 'Courier New'
            r.font.size = Pt(9)
            r.font.color.rgb = RGBColor(0xB0, 0x00, 0x00)
        else:
            para.add_run(part)


def add_heading(doc, text: str, level: int):
    clean = clean_text(text)
    h = doc.add_heading(clean, level=level)
    h.paragraph_format.space_before = Pt(14 if level <= 2 else 8)
    h.paragraph_format.space_after  = Pt(4)
    # Color: dark blue for H1/H2, medium for H3, gray for H4
    colors = {1: (0x1A, 0x3A, 0x6B), 2: (0x1A, 0x3A, 0x6B),
              3: (0x1F, 0x5C, 0x99), 4: (0x44, 0x44, 0x44)}
    rgb = colors.get(level, (0x0, 0x0, 0x0))
    for run in h.runs:
        run.font.color.rgb = RGBColor(*rgb)


def add_blockquote(doc, text: str):
    p = doc.add_paragraph(style='Normal')
    p.paragraph_format.left_indent  = Cm(0.8)
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after  = Pt(4)
    clean = re.sub(r'^>+\s*', '', text).strip()
    run = p.add_run(clean)
    run.italic = True
    run.font.color.rgb = RGBColor(0x55, 0x55, 0x55)


def render_table(doc, rows: list):
    if not rows:
        return
    col_count = max(len(r) for r in rows)
    table = doc.add_table(rows=0, cols=col_count)
    table.style = 'Table Grid'
    table.alignment = WD_TABLE_ALIGNMENT.LEFT

    for ri, row_cells in enumerate(rows):
        tr = table.add_row()
        for ci in range(col_count):
            raw_cell = row_cells[ci].strip() if ci < len(row_cells) else ''
            cell_text = clean_text(raw_cell)
            cell = tr.cells[ci]
            cell.text = ''
            para = cell.paragraphs[0]
            para.paragraph_format.space_before = Pt(2)
            para.paragraph_format.space_after  = Pt(2)
            run = para.add_run(cell_text)
            run.font.size = Pt(9)
            if ri == 0:
                run.bold = True
                set_cell_bg(cell, 'C9DCF0')
            elif ri % 2 == 0:
                set_cell_bg(cell, 'F0F5FB')

    doc.add_paragraph().paragraph_format.space_after = Pt(4)


def add_list_item(doc, text: str, ordered: bool, number: int, depth: int):
    p = doc.add_paragraph(style='Normal')
    p.paragraph_format.left_indent = Cm(0.5 + depth * 0.55)
    p.paragraph_format.space_after = Pt(3)
    if ordered:
        r = p.add_run(f'{number}. ')
        r.bold = True
    else:
        r = p.add_run('\u2022 ')
        r.bold = True
    add_inline_runs(p, text)


def add_normal_para(doc, text: str):
    p = doc.add_paragraph(style='Normal')
    p.paragraph_format.space_after = Pt(4)
    add_inline_runs(p, text)


# ---------------------------------------------------------------------------
# Main builder
# ---------------------------------------------------------------------------

def build_docx(md_path: Path, docx_path: Path):
    lines = md_path.read_text(encoding='utf-8').splitlines()
    doc   = Document()

    # Page layout
    sec = doc.sections[0]
    sec.page_width    = Inches(8.5)
    sec.page_height   = Inches(11)
    sec.left_margin   = Inches(1.25)
    sec.right_margin  = Inches(1.25)
    sec.top_margin    = Inches(1.0)
    sec.bottom_margin = Inches(1.0)

    # Default font
    doc.styles['Normal'].font.name = 'Calibri'
    doc.styles['Normal'].font.size = Pt(10.5)

    # ---- Cover page ----
    cover = doc.add_paragraph()
    cover.alignment = WD_ALIGN_PARAGRAPH.CENTER
    cover.paragraph_format.space_before = Pt(60)
    r = cover.add_run('Web-Based Payroll Management System (WBPMS)')
    r.bold = True
    r.font.size = Pt(20)
    r.font.color.rgb = RGBColor(0x1A, 0x3A, 0x6B)

    sub = doc.add_paragraph()
    sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
    rs = sub.add_run('User Manual')
    rs.bold = True
    rs.font.size = Pt(15)
    rs.font.color.rgb = RGBColor(0x1F, 0x5C, 0x99)

    doc.add_paragraph()
    for ml in [
        'Organization: Light Diamond Enterprises',
        'System: Web-Based Payroll Management System',
        'Audience: Business Owner, HR Head, Employee',
        'Date: September 2026',
    ]:
        mp = doc.add_paragraph(style='Normal')
        mp.alignment = WD_ALIGN_PARAGRAPH.CENTER
        mp.paragraph_format.space_after = Pt(2)
        mp.add_run(ml)

    doc.add_page_break()

    # ---- State ----
    in_code_block = False
    table_rows: list = []
    ol_counters: dict = {}   # depth -> count
    skip_toc = False

    # We skip the markdown cover block (first lines up to and including the
    # metadata block before the first ---), then start from the TOC heading.
    # Strategy: skip lines until we see "## Table of Contents", then process
    # everything from that point forward.
    found_start = False

    i = 0
    while i < len(lines):
        line = lines[i]

        # Find the real start (skip markdown cover)
        if not found_start:
            if line.startswith('## Table of Contents'):
                found_start = True
                # Don't skip this line — fall through to process it
            else:
                i += 1
                continue

        # ---- Code fences ----
        if line.startswith('```'):
            in_code_block = not in_code_block
            i += 1
            continue

        if in_code_block:
            p = doc.add_paragraph(style='Normal')
            p.paragraph_format.left_indent = Cm(1.0)
            r = p.add_run(line)
            r.font.name = 'Courier New'
            r.font.size = Pt(9)
            r.font.color.rgb = RGBColor(0x33, 0x33, 0x33)
            i += 1
            continue

        # ---- Horizontal rules ----
        if re.match(r'^-{3,}\s*$', line):
            add_horizontal_rule(doc)
            i += 1
            continue

        # ---- Table rows ----
        if line.strip().startswith('|') and '|' in line:
            cells = [c.strip() for c in line.strip().strip('|').split('|')]
            # Separator row: all cells are ---/:-:/:--- patterns
            is_sep = all(re.match(r'^:?-+:?$', c.replace(' ', '')) for c in cells if c)
            if not is_sep:
                table_rows.append(cells)
            i += 1
            # If next line is not a table row, flush the table
            next_line = lines[i] if i < len(lines) else ''
            if not (next_line.strip().startswith('|') and '|' in next_line):
                render_table(doc, table_rows)
                table_rows = []
            continue

        # ---- Headings ----
        h4 = re.match(r'^#### (.+)', line)
        h3 = re.match(r'^### (.+)', line)
        h2 = re.match(r'^## (.+)', line)
        h1 = re.match(r'^# (.+)', line)

        if h4:
            add_heading(doc, h4.group(1), 4)
            ol_counters = {}
            i += 1
            continue
        if h3:
            add_heading(doc, h3.group(1), 3)
            ol_counters = {}
            i += 1
            continue
        if h2:
            text = h2.group(1)
            # Skip the TOC heading itself (we'll add a simple label)
            if 'Table of Contents' in text:
                add_heading(doc, text, 2)
                # skip all TOC content lines until the next ---
                i += 1
                while i < len(lines) and not re.match(r'^-{3,}\s*$', lines[i]):
                    i += 1
                continue
            add_heading(doc, text, 2)
            ol_counters = {}
            i += 1
            continue
        if h1:
            add_heading(doc, h1.group(1), 1)
            ol_counters = {}
            i += 1
            continue

        # ---- Blockquotes ----
        if line.startswith('>'):
            add_blockquote(doc, line)
            i += 1
            continue

        # ---- Ordered list ----
        ol = re.match(r'^(\s*)(\d+)\.\s+(.*)', line)
        if ol:
            depth = len(ol.group(1)) // 3
            num   = int(ol.group(2))
            text  = ol.group(3)
            add_list_item(doc, text, True, num, depth)
            i += 1
            continue

        # ---- Unordered list ----
        ul = re.match(r'^(\s*)[-*]\s+(.*)', line)
        if ul:
            depth = len(ul.group(1)) // 2
            text  = ul.group(2)
            add_list_item(doc, text, False, 0, depth)
            i += 1
            continue

        # ---- Empty line ----
        if line.strip() == '':
            sp = doc.add_paragraph()
            sp.paragraph_format.space_before = Pt(0)
            sp.paragraph_format.space_after  = Pt(2)
            i += 1
            continue

        # ---- Italic/end-of-manual marker ----
        if line.strip().startswith('*') and line.strip().endswith('*'):
            p = doc.add_paragraph(style='Normal')
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            r = p.add_run(line.strip().strip('*'))
            r.italic = True
            r.font.color.rgb = RGBColor(0x88, 0x88, 0x88)
            i += 1
            continue

        # ---- Default normal paragraph ----
        add_normal_para(doc, line.strip())
        i += 1

    doc.save(docx_path)
    sz = docx_path.stat().st_size // 1024
    print(f'Saved: {docx_path}  ({sz} KB)')

    # Quick verification
    verify = Document(docx_path)
    headings = [p for p in verify.paragraphs if 'Heading' in p.style.name]
    tables   = verify.tables
    print(f'Paragraphs : {len(verify.paragraphs)}')
    print(f'Tables     : {len(tables)}')
    print(f'Headings   : {len(headings)}')


if __name__ == '__main__':
    build_docx(MD_PATH, DOCX_PATH)
