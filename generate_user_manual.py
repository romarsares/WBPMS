"""
generate_user_manual.py
Generates docs/User_Manual_Template.docx — a blank, professionally formatted
User Manual template. All content is placeholder-only; the client fills in
the actual system details later.

Requires: python-docx  (pip install python-docx)
"""

import os
from docx import Document
from docx.shared import Pt, Inches, RGBColor, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
import copy

# ---------------------------------------------------------------------------
# Output path
# ---------------------------------------------------------------------------
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "docs")
OUTPUT_FILE = os.path.join(OUTPUT_DIR, "User_Manual_Template.docx")
os.makedirs(OUTPUT_DIR, exist_ok=True)

# ---------------------------------------------------------------------------
# Helper utilities
# ---------------------------------------------------------------------------

def set_cell_bg(cell, hex_color: str):
    """Fill a table cell with a background color (hex, no #)."""
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_color)
    tcPr.append(shd)


def set_cell_borders(cell, top=None, bottom=None, left=None, right=None):
    """Apply border settings to a single cell."""
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    tcBorders = OxmlElement("w:tcBorders")
    for side, val in [("top", top), ("bottom", bottom), ("left", left), ("right", right)]:
        if val is not None:
            el = OxmlElement(f"w:{side}")
            el.set(qn("w:val"), val.get("val", "single"))
            el.set(qn("w:sz"), str(val.get("sz", 4)))
            el.set(qn("w:space"), "0")
            el.set(qn("w:color"), val.get("color", "000000"))
            tcBorders.append(el)
    tcPr.append(tcBorders)


def set_table_borders(table, color="CCCCCC", sz=4):
    """Apply uniform single borders to every cell in a table."""
    border = {"val": "single", "sz": sz, "color": color}
    for row in table.rows:
        for cell in row.cells:
            set_cell_borders(cell, top=border, bottom=border, left=border, right=border)


def add_page_break(doc):
    doc.add_page_break()


def placeholder_run(para, text: str, italic=True, color="7F7F7F"):
    """Add a grey italic placeholder run to a paragraph."""
    run = para.add_run(text)
    run.italic = italic
    run.font.color.rgb = RGBColor.from_string(color)
    return run


def add_placeholder_para(doc, text: str, style="Normal"):
    """Add a standalone placeholder paragraph."""
    para = doc.add_paragraph(style=style)
    placeholder_run(para, text)
    return para


def add_screenshot_box(doc, label="[Insert Screenshot Here]"):
    """Add a visually distinct screenshot placeholder."""
    table = doc.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.cell(0, 0)
    cell.width = Inches(5.5)
    set_cell_bg(cell, "F2F2F2")
    set_cell_borders(
        cell,
        top={"val": "dashed", "sz": 6, "color": "AAAAAA"},
        bottom={"val": "dashed", "sz": 6, "color": "AAAAAA"},
        left={"val": "dashed", "sz": 6, "color": "AAAAAA"},
        right={"val": "dashed", "sz": 6, "color": "AAAAAA"},
    )
    para = cell.paragraphs[0]
    para.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = para.add_run(label)
    run.italic = True
    run.font.color.rgb = RGBColor(0x99, 0x99, 0x99)
    run.font.size = Pt(10)
    # Add some vertical padding via spacing
    pPr = para._p.get_or_add_pPr()
    spacing = OxmlElement("w:spacing")
    spacing.set(qn("w:before"), "120")
    spacing.set(qn("w:after"), "120")
    pPr.append(spacing)
    doc.add_paragraph()  # breathing space after the box
    return table


def heading(doc, text: str, level: int):
    """Add a heading and return the paragraph."""
    return doc.add_heading(text, level=level)


def add_toc_field(doc):
    """Insert a Word TOC field that auto-updates on open/print."""
    para = doc.add_paragraph()
    run = para.add_run()
    fld_char_begin = OxmlElement("w:fldChar")
    fld_char_begin.set(qn("w:fldCharType"), "begin")
    instr_text = OxmlElement("w:instrText")
    instr_text.set(qn("xml:space"), "preserve")
    instr_text.text = ' TOC \\o "1-3" \\h \\z \\u '
    fld_char_separate = OxmlElement("w:fldChar")
    fld_char_separate.set(qn("w:fldCharType"), "separate")
    fld_char_end = OxmlElement("w:fldChar")
    fld_char_end.set(qn("w:fldCharType"), "end")

    run._r.append(fld_char_begin)
    run._r.append(instr_text)
    run._r.append(fld_char_separate)

    # Placeholder text inside the TOC field
    toc_placeholder = para.add_run(
        "[Right-click here and select 'Update Field' to generate the Table of Contents]"
    )
    toc_placeholder.italic = True
    toc_placeholder.font.color.rgb = RGBColor(0x99, 0x99, 0x99)
    toc_placeholder.font.size = Pt(10)

    run2 = para.add_run()
    run2._r.append(fld_char_end)


def add_procedure_table(doc, steps=3):
    """Add a numbered step table for procedures."""
    table = doc.add_table(rows=1, cols=3)
    table.style = "Table Grid"
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    # Header row
    hdr = table.rows[0].cells
    for cell, label in zip(hdr, ["Step", "Action / Description", "Expected Result"]):
        cell.paragraphs[0].clear()
        run = cell.paragraphs[0].add_run(label)
        run.bold = True
        run.font.size = Pt(10)
        set_cell_bg(cell, "1F3864")
        cell.paragraphs[0].runs[0].font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
    # Data rows
    for i in range(1, steps + 1):
        row = table.add_row().cells
        row[0].text = str(i)
        row[0].paragraphs[0].runs[0].font.size = Pt(10)
        row[0].width = Inches(0.5)
        p = row[1].paragraphs[0]
        placeholder_run(p, "[Step Description]")
        p.runs[0].font.size = Pt(10)
        p2 = row[2].paragraphs[0]
        placeholder_run(p2, "[Expected Result]")
        p2.runs[0].font.size = Pt(10)
    set_table_borders(table)
    doc.add_paragraph()


def add_faq_item(doc, q_num: int):
    """Add a single FAQ question-answer block."""
    q = doc.add_paragraph(style="Normal")
    qr = q.add_run(f"Q{q_num}: ")
    qr.bold = True
    qr.font.size = Pt(11)
    placeholder_run(q, "[Enter Frequently Asked Question Here]")
    a = doc.add_paragraph(style="Normal")
    ar = a.add_run("A: ")
    ar.bold = True
    ar.font.size = Pt(11)
    placeholder_run(a, "[Enter Answer Here]")
    doc.add_paragraph()


# ---------------------------------------------------------------------------
# Style tweaks applied after document is created
# ---------------------------------------------------------------------------

def configure_styles(doc):
    """Adjust built-in styles to match a clean professional look."""
    styles = doc.styles

    # Normal
    normal = styles["Normal"]
    normal.font.name = "Calibri"
    normal.font.size = Pt(11)
    normal.paragraph_format.space_after = Pt(6)

    # Heading 1
    h1 = styles["Heading 1"]
    h1.font.name = "Calibri"
    h1.font.size = Pt(18)
    h1.font.bold = True
    h1.font.color.rgb = RGBColor(0x1F, 0x38, 0x64)
    h1.paragraph_format.space_before = Pt(18)
    h1.paragraph_format.space_after = Pt(6)
    h1.paragraph_format.keep_with_next = True

    # Heading 2
    h2 = styles["Heading 2"]
    h2.font.name = "Calibri"
    h2.font.size = Pt(14)
    h2.font.bold = True
    h2.font.color.rgb = RGBColor(0x2E, 0x74, 0xB5)
    h2.paragraph_format.space_before = Pt(12)
    h2.paragraph_format.space_after = Pt(4)
    h2.paragraph_format.keep_with_next = True

    # Heading 3
    h3 = styles["Heading 3"]
    h3.font.name = "Calibri"
    h3.font.size = Pt(12)
    h3.font.bold = True
    h3.font.color.rgb = RGBColor(0x1F, 0x61, 0x69)  # teal accent
    h3.paragraph_format.space_before = Pt(8)
    h3.paragraph_format.space_after = Pt(3)
    h3.paragraph_format.keep_with_next = True


def set_page_margins(doc, top=1.0, bottom=1.0, left=1.25, right=1.25):
    """Set page margins (inches) on all sections."""
    for section in doc.sections:
        section.top_margin = Inches(top)
        section.bottom_margin = Inches(bottom)
        section.left_margin = Inches(left)
        section.right_margin = Inches(right)


def add_header_footer(doc, system_name="[System Name]", version="[Version]"):
    """Add a simple header and page-number footer to the default section."""
    section = doc.sections[0]

    # Header
    header = section.header
    hp = header.paragraphs[0] if header.paragraphs else header.add_paragraph()
    hp.clear()
    hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = hp.add_run(f"{system_name}  |  User Manual  |  {version}")
    run.font.size = Pt(9)
    run.font.color.rgb = RGBColor(0x7F, 0x7F, 0x7F)

    # Footer — centred page number
    footer = section.footer
    fp = footer.paragraphs[0] if footer.paragraphs else footer.add_paragraph()
    fp.clear()
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = fp.add_run("Page ")
    run.font.size = Pt(9)
    run.font.color.rgb = RGBColor(0x7F, 0x7F, 0x7F)

    fld_char1 = OxmlElement("w:fldChar")
    fld_char1.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = "PAGE"
    fld_char2 = OxmlElement("w:fldChar")
    fld_char2.set(qn("w:fldCharType"), "end")
    run._r.append(fld_char1)
    run._r.append(instr)
    run._r.append(fld_char2)

    run2 = fp.add_run(" of ")
    run2.font.size = Pt(9)
    run2.font.color.rgb = RGBColor(0x7F, 0x7F, 0x7F)

    fld_char3 = OxmlElement("w:fldChar")
    fld_char3.set(qn("w:fldCharType"), "begin")
    instr2 = OxmlElement("w:instrText")
    instr2.set(qn("xml:space"), "preserve")
    instr2.text = "NUMPAGES"
    fld_char4 = OxmlElement("w:fldChar")
    fld_char4.set(qn("w:fldCharType"), "end")
    run2._r.append(fld_char3)
    run2._r.append(instr2)
    run2._r.append(fld_char4)


# ---------------------------------------------------------------------------
# Section builders
# ---------------------------------------------------------------------------

def build_cover_page(doc):
    # Large top padding
    for _ in range(6):
        doc.add_paragraph()

    # System name
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run("[System / Project Name]")
    run.font.name = "Calibri"
    run.font.size = Pt(28)
    run.bold = True
    run.font.color.rgb = RGBColor(0x1F, 0x38, 0x64)

    # Document type
    p2 = doc.add_paragraph()
    p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r2 = p2.add_run("User Manual")
    r2.font.name = "Calibri"
    r2.font.size = Pt(20)
    r2.bold = False
    r2.font.color.rgb = RGBColor(0x2E, 0x74, 0xB5)

    doc.add_paragraph()

    # Meta table (version / date / prepared by / org)
    meta = doc.add_table(rows=4, cols=2)
    meta.alignment = WD_TABLE_ALIGNMENT.CENTER
    meta.style = "Table Grid"
    labels = ["Version", "Date", "Prepared By", "Organization / Company"]
    placeholders = ["[Version]", "[MM/DD/YYYY]", "[Prepared By]", "[Company / Organization Name]"]
    for i, (lbl, ph) in enumerate(zip(labels, placeholders)):
        lc = meta.rows[i].cells[0]
        rc = meta.rows[i].cells[1]
        lc.width = Inches(1.8)
        rc.width = Inches(3.0)
        set_cell_bg(lc, "1F3864")
        r = lc.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(11)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        placeholder_run(rc.paragraphs[0], ph)
        rc.paragraphs[0].runs[0].font.size = Pt(11)
    set_table_borders(meta, color="CCCCCC")

    # Push meta block toward center
    for _ in range(8):
        doc.add_paragraph()

    # Confidentiality notice at bottom
    note = doc.add_paragraph()
    note.alignment = WD_ALIGN_PARAGRAPH.CENTER
    nr = note.add_run(
        "CONFIDENTIAL — For authorized use only. "
        "Do not distribute without permission."
    )
    nr.font.size = Pt(9)
    nr.italic = True
    nr.font.color.rgb = RGBColor(0x99, 0x99, 0x99)

    add_page_break(doc)


def build_revision_history(doc):
    heading(doc, "Document Information", 1)
    heading(doc, "Revision History", 2)
    add_placeholder_para(
        doc,
        "Update this table each time the document is revised. "
        "List all changes in chronological order."
    )
    doc.add_paragraph()

    table = doc.add_table(rows=5, cols=4)
    table.style = "Table Grid"
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    headers = ["Version", "Date", "Description of Changes", "Updated By"]
    widths = [Inches(0.8), Inches(1.2), Inches(3.2), Inches(1.4)]
    hdr_row = table.rows[0]
    for i, (lbl, w) in enumerate(zip(headers, widths)):
        cell = hdr_row.cells[i]
        cell.width = w
        cell.paragraphs[0].clear()
        r = cell.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        set_cell_bg(cell, "1F3864")

    data_rows = [
        ["1.0", "[MM/DD/YYYY]", "Initial release", "[Author Name]"],
        ["", "[MM/DD/YYYY]", "[Enter Description Here]", "[Author Name]"],
        ["", "[MM/DD/YYYY]", "[Enter Description Here]", "[Author Name]"],
        ["", "[MM/DD/YYYY]", "[Enter Description Here]", "[Author Name]"],
    ]
    for r_idx, row_data in enumerate(data_rows, start=1):
        row = table.rows[r_idx]
        for c_idx, val in enumerate(row_data):
            cell = row.cells[c_idx]
            cell.width = widths[c_idx]
            cell.paragraphs[0].clear()
            if val.startswith("[") or val == "":
                placeholder_run(cell.paragraphs[0], val if val else "[Enter Value Here]")
                cell.paragraphs[0].runs[0].font.size = Pt(10)
            else:
                run = cell.paragraphs[0].add_run(val)
                run.font.size = Pt(10)
            if r_idx % 2 == 0:
                set_cell_bg(cell, "EEF4FB")
    set_table_borders(table)
    doc.add_paragraph()


def build_toc(doc):
    heading(doc, "Table of Contents", 1)
    add_placeholder_para(
        doc,
        "[Right-click inside the field below and select 'Update Field' "
        "to regenerate the Table of Contents after editing the document.]"
    )
    doc.add_paragraph()
    add_toc_field(doc)
    add_page_break(doc)


def build_introduction(doc):
    heading(doc, "Introduction", 1)

    heading(doc, "Purpose", 2)
    add_placeholder_para(
        doc,
        "[Enter the purpose of this document. Describe what the manual covers "
        "and what the reader will be able to do after reading it.]"
    )

    heading(doc, "Scope", 2)
    add_placeholder_para(
        doc,
        "[Describe the scope of this manual — which parts of the system are "
        "covered, which roles it applies to, and any limitations or exclusions.]"
    )

    heading(doc, "Intended Users", 2)
    add_placeholder_para(
        doc,
        "[List the target audience for this manual. Example: "
        "Business Owner, HR Head, and Employees.]"
    )
    # Placeholder bullet list
    for role in ["[Role 1 — brief description]", "[Role 2 — brief description]", "[Role 3 — brief description]"]:
        p = doc.add_paragraph(style="List Bullet")
        placeholder_run(p, role)

    heading(doc, "System Overview", 2)
    add_placeholder_para(
        doc,
        "[Provide a brief high-level overview of the system — what it does, "
        "its main purpose, and the primary benefit to the organization.]"
    )
    doc.add_paragraph()


def build_getting_started(doc):
    heading(doc, "Getting Started", 1)

    heading(doc, "System Requirements", 2)
    add_placeholder_para(
        doc,
        "[List the minimum hardware and software requirements needed to use the system.]"
    )
    table = doc.add_table(rows=1, cols=2)
    table.style = "Table Grid"
    for cell, lbl in zip(table.rows[0].cells, ["Requirement", "Details"]):
        r = cell.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        set_cell_bg(cell, "1F3864")
    for req in [
        ("Web Browser", "[Recommended: Chrome, Firefox, or Edge — latest version]"),
        ("Internet Connection", "[Enter Minimum Requirements Here]"),
        ("Screen Resolution", "[Enter Minimum Requirements Here]"),
        ("Operating System", "[Enter Requirements Here]"),
        ("Other", "[Enter Requirements Here]"),
    ]:
        row = table.add_row().cells
        row[0].paragraphs[0].add_run(req[0]).font.size = Pt(10)
        placeholder_run(row[1].paragraphs[0], req[1])
        row[1].paragraphs[0].runs[0].font.size = Pt(10)
    set_table_borders(table)
    doc.add_paragraph()

    heading(doc, "Accessing the System", 2)
    add_placeholder_para(
        doc,
        "[Describe how users access the system — URL, network path, or application launcher.]"
    )
    p = doc.add_paragraph(style="Normal")
    p.add_run("System URL / Access Point:  ")
    placeholder_run(p, "[Enter System URL or Access Method Here]")
    doc.add_paragraph()

    heading(doc, "Logging In", 2)
    add_placeholder_para(
        doc,
        "[Describe the login process step by step.]"
    )
    add_procedure_table(doc, steps=3)
    add_screenshot_box(doc, "[Insert Login Screen Screenshot Here]")

    heading(doc, "Logging Out", 2)
    add_placeholder_para(
        doc,
        "[Describe how to properly log out of the system.]"
    )
    add_procedure_table(doc, steps=2)
    doc.add_paragraph()


def build_navigation(doc):
    heading(doc, "System Navigation", 1)

    heading(doc, "Main Dashboard", 2)
    add_placeholder_para(
        doc,
        "[Describe the main dashboard — what information is displayed, "
        "what widgets or summary panels are available, and how to interpret them.]"
    )
    add_screenshot_box(doc, "[Insert Main Dashboard Screenshot Here]")

    heading(doc, "Navigation Menu Structure", 2)
    add_placeholder_para(
        doc,
        "[Describe the main navigation menu. List each top-level menu item "
        "and its sub-items below.]"
    )
    for item in [
        "[Menu Item 1]",
        "[Menu Item 2]",
        "[Menu Item 3]",
        "[Menu Item 4]",
        "[Menu Item 5]",
    ]:
        p = doc.add_paragraph(style="List Bullet")
        placeholder_run(p, item)
        sub = doc.add_paragraph(style="List Bullet 2")
        placeholder_run(sub, "[Sub-menu item — if applicable]")

    doc.add_paragraph()

    heading(doc, "General Interface Guide", 2)
    add_placeholder_para(
        doc,
        "[Describe common UI elements — buttons, icons, status indicators, "
        "pagination, search bars, filters, and other recurring interface components.]"
    )
    table = doc.add_table(rows=1, cols=2)
    table.style = "Table Grid"
    for cell, lbl in zip(table.rows[0].cells, ["Element / Icon", "Description"]):
        r = cell.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        set_cell_bg(cell, "1F3864")
    for _ in range(5):
        row = table.add_row().cells
        placeholder_run(row[0].paragraphs[0], "[Element Name / Icon]")
        placeholder_run(row[1].paragraphs[0], "[Enter Description Here]")
        for c in row:
            c.paragraphs[0].runs[0].font.size = Pt(10)
    set_table_borders(table)
    doc.add_paragraph()


def build_module_template(doc, module_number: int, module_name: str = None):
    """Build one reusable module documentation block."""
    name = module_name or f"[Module {module_number} Name]"

    heading(doc, f"{module_number}. {name}", 2)

    heading(doc, "Purpose / Description", 3)
    add_placeholder_para(
        doc,
        f"[Describe the purpose and functionality of {name}. "
        "Explain what this module does and why it is used.]"
    )

    heading(doc, "How to Access", 3)
    add_placeholder_para(
        doc,
        "[Describe how to navigate to this module — e.g., "
        "Main Menu > [Section] > [Module Name]]"
    )

    heading(doc, "Prerequisites", 3)
    add_placeholder_para(
        doc,
        "[List any prerequisites required before using this module. "
        "Leave blank or write 'None' if not applicable.]"
    )
    for _ in range(2):
        p = doc.add_paragraph(style="List Bullet")
        placeholder_run(p, "[Prerequisite — e.g., user must be logged in as HR Head]")

    heading(doc, "Step-by-Step Procedure", 3)
    add_placeholder_para(
        doc,
        "[Provide a numbered step-by-step guide for the most common task in this module.]"
    )
    add_procedure_table(doc, steps=5)

    heading(doc, "Expected Result", 3)
    add_placeholder_para(
        doc,
        "[Describe what the user should see or what happens after completing the procedure.]"
    )

    heading(doc, "Notes / Important Information", 3)
    add_placeholder_para(
        doc,
        "[List any important notes, warnings, or reminders related to this module.]"
    )
    for _ in range(2):
        p = doc.add_paragraph(style="List Bullet")
        placeholder_run(p, "[Note — e.g., This action cannot be undone.]")

    heading(doc, "Screenshot", 3)
    add_screenshot_box(doc, f"[Insert {name} Screenshot Here]")


def build_modules_section(doc):
    heading(doc, "System Modules / Features", 1)
    add_placeholder_para(
        doc,
        "[This section documents each module of the system. "
        "Duplicate the module template below for each additional module you need to document.]"
    )
    doc.add_paragraph()

    # Three sample module templates
    for i in range(1, 4):
        build_module_template(doc, i)
        if i < 3:
            doc.add_paragraph()

    # Instruction to client
    note = doc.add_paragraph()
    nr = note.add_run(
        "TIP: To add a new module, copy the section above (from the module heading "
        "down to the screenshot placeholder) and paste it below. "
        "Then replace all placeholders with the actual module content."
    )
    nr.italic = True
    nr.font.size = Pt(10)
    nr.font.color.rgb = RGBColor(0x1F, 0x61, 0x69)


def build_reports(doc):
    heading(doc, "Reports", 1)

    heading(doc, "Available Reports", 2)
    add_placeholder_para(
        doc,
        "[List all reports available in the system and provide a brief description of each.]"
    )
    table = doc.add_table(rows=1, cols=2)
    table.style = "Table Grid"
    for cell, lbl in zip(table.rows[0].cells, ["Report Name", "Description"]):
        r = cell.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        set_cell_bg(cell, "1F3864")
    for _ in range(5):
        row = table.add_row().cells
        placeholder_run(row[0].paragraphs[0], "[Report Name]")
        placeholder_run(row[1].paragraphs[0], "[Enter Report Description Here]")
        for c in row:
            c.paragraphs[0].runs[0].font.size = Pt(10)
    set_table_borders(table)
    doc.add_paragraph()

    heading(doc, "How to Generate a Report", 2)
    add_placeholder_para(
        doc,
        "[Describe the steps for generating a report.]"
    )
    add_procedure_table(doc, steps=4)
    add_screenshot_box(doc, "[Insert Report Generation Screenshot Here]")

    heading(doc, "Filtering and Searching", 2)
    add_placeholder_para(
        doc,
        "[Describe the available filter and search options for reports — "
        "e.g., date range, branch, employee, report type.]"
    )

    heading(doc, "Printing and Exporting", 2)
    add_placeholder_para(
        doc,
        "[Describe how to print or export reports — "
        "e.g., PDF, Excel, print preview.]"
    )
    add_procedure_table(doc, steps=3)
    add_screenshot_box(doc, "[Insert Print / Export Screenshot Here]")


def build_common_procedures(doc):
    heading(doc, "Common Procedures", 1)
    add_placeholder_para(
        doc,
        "[Document frequently performed tasks in this section. "
        "Add as many procedures as needed using the template below.]"
    )
    doc.add_paragraph()

    for i in range(1, 4):
        heading(doc, f"Procedure {i}: [Procedure Name]", 2)
        add_placeholder_para(
            doc,
            "[Brief description of this procedure and when it is typically performed.]"
        )
        add_procedure_table(doc, steps=4)
        add_screenshot_box(doc, f"[Insert Screenshot for Procedure {i} Here]")
        doc.add_paragraph()


def build_troubleshooting(doc):
    heading(doc, "Error Messages and Troubleshooting", 1)
    add_placeholder_para(
        doc,
        "[Document known error messages and their solutions in the table below. "
        "Add rows as needed.]"
    )
    doc.add_paragraph()

    table = doc.add_table(rows=1, cols=3)
    table.style = "Table Grid"
    headers = ["Problem / Error Message", "Possible Cause", "Recommended Solution"]
    widths = [Inches(2.0), Inches(2.0), Inches(2.5)]
    for i, (lbl, w) in enumerate(zip(headers, widths)):
        cell = table.rows[0].cells[i]
        cell.width = w
        cell.paragraphs[0].clear()
        r = cell.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        set_cell_bg(cell, "1F3864")

    for _ in range(6):
        row = table.add_row().cells
        placeholder_run(row[0].paragraphs[0], "[Error or Problem Description]")
        placeholder_run(row[1].paragraphs[0], "[Possible Cause]")
        placeholder_run(row[2].paragraphs[0], "[Recommended Solution or Action]")
        for c in row:
            c.paragraphs[0].runs[0].font.size = Pt(10)
    set_table_borders(table)
    doc.add_paragraph()


def build_faq(doc):
    heading(doc, "Frequently Asked Questions (FAQ)", 1)
    add_placeholder_para(
        doc,
        "[Add commonly asked questions and their answers below. "
        "Duplicate the Q&A block for each additional question.]"
    )
    doc.add_paragraph()

    for i in range(1, 6):
        add_faq_item(doc, i)


def build_tips(doc):
    heading(doc, "Tips and Important Notes", 1)
    add_placeholder_para(
        doc,
        "[Add useful tips, best practices, reminders, and important notes for system users. "
        "Use the bullet list below and add or remove items as needed.]"
    )
    doc.add_paragraph()

    heading(doc, "General Tips", 2)
    for _ in range(4):
        p = doc.add_paragraph(style="List Bullet")
        placeholder_run(p, "[Enter Tip or Reminder Here]")

    heading(doc, "Important Reminders", 2)
    for _ in range(4):
        p = doc.add_paragraph(style="List Bullet")
        placeholder_run(p, "[Enter Important Reminder Here]")

    heading(doc, "Security and Data Privacy Notes", 2)
    for _ in range(3):
        p = doc.add_paragraph(style="List Bullet")
        placeholder_run(p, "[Enter Security or Data Privacy Note Here]")

    doc.add_paragraph()


def build_appendix(doc):
    heading(doc, "Appendix", 1)

    heading(doc, "Glossary", 2)
    add_placeholder_para(
        doc,
        "[Define technical terms, abbreviations, and acronyms used in this manual.]"
    )
    table = doc.add_table(rows=1, cols=2)
    table.style = "Table Grid"
    for cell, lbl in zip(table.rows[0].cells, ["Term / Abbreviation", "Definition"]):
        r = cell.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        set_cell_bg(cell, "1F3864")
    for _ in range(6):
        row = table.add_row().cells
        placeholder_run(row[0].paragraphs[0], "[Term]")
        placeholder_run(row[1].paragraphs[0], "[Enter Definition Here]")
        for c in row:
            c.paragraphs[0].runs[0].font.size = Pt(10)
    set_table_borders(table)
    doc.add_paragraph()

    heading(doc, "References", 2)
    add_placeholder_para(
        doc,
        "[List any reference documents, standards, or external resources cited in or related to this manual.]"
    )
    for _ in range(3):
        p = doc.add_paragraph(style="List Number")
        placeholder_run(p, "[Reference Title — Author, Date, Version, or URL]")

    heading(doc, "Additional Information", 2)
    add_placeholder_para(
        doc,
        "[Include any additional supporting information, attachments, or supplementary material here.]"
    )

    heading(doc, "Document Approvals", 2)
    table2 = doc.add_table(rows=1, cols=3)
    table2.style = "Table Grid"
    for cell, lbl in zip(table2.rows[0].cells, ["Name", "Role / Title", "Signature and Date"]):
        r = cell.paragraphs[0].add_run(lbl)
        r.bold = True
        r.font.size = Pt(10)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        set_cell_bg(cell, "1F3864")
    for _ in range(3):
        row = table2.add_row().cells
        placeholder_run(row[0].paragraphs[0], "[Full Name]")
        placeholder_run(row[1].paragraphs[0], "[Role / Title]")
        placeholder_run(row[2].paragraphs[0], "[Signature                    Date]")
        for c in row:
            c.paragraphs[0].runs[0].font.size = Pt(10)
            # Add extra row height via spacing
            sp = OxmlElement("w:spacing")
            sp.set(qn("w:before"), "200")
            sp.set(qn("w:after"), "200")
            c.paragraphs[0]._p.get_or_add_pPr().append(sp)
    set_table_borders(table2)
    doc.add_paragraph()


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    doc = Document()
    configure_styles(doc)
    set_page_margins(doc)
    add_header_footer(doc)

    # 1. Cover Page
    build_cover_page(doc)

    # 2. Document Information / Revision History
    build_revision_history(doc)
    add_page_break(doc)

    # 3. Table of Contents
    build_toc(doc)

    # 4. Introduction
    build_introduction(doc)
    add_page_break(doc)

    # 5. Getting Started
    build_getting_started(doc)
    add_page_break(doc)

    # 6. System Navigation
    build_navigation(doc)
    add_page_break(doc)

    # 7. System Modules / Features
    build_modules_section(doc)
    add_page_break(doc)

    # 8. Reports
    build_reports(doc)
    add_page_break(doc)

    # 9. Common Procedures
    build_common_procedures(doc)
    add_page_break(doc)

    # 10. Error Messages / Troubleshooting
    build_troubleshooting(doc)
    add_page_break(doc)

    # 11. FAQ
    build_faq(doc)
    add_page_break(doc)

    # 12. Tips and Important Notes
    build_tips(doc)
    add_page_break(doc)

    # 13. Appendix
    build_appendix(doc)

    doc.save(OUTPUT_FILE)
    print(f"SUCCESS: {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
