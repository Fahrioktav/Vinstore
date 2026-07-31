from copy import deepcopy
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from lxml import etree


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DOCX = ROOT / "docs" / "skripsi" / "1152200016 - TUGAS AKHIR FAHRI OCTAVIAN FINAL - BAB 4 Penjelasan Gambar Kode V3.docx"
OUTPUT_DOCX = ROOT / "docs" / "skripsi" / "1152200016 - TUGAS AKHIR FAHRI OCTAVIAN FINAL - BAB 4 Penjelasan Gambar Kode V4.docx"

NS = {
    "w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main",
}


def qn(tag: str) -> str:
    prefix, name = tag.split(":")
    return f"{{{NS[prefix]}}}{name}"


def paragraph_text(p):
    return "".join(p.xpath(".//w:t/text()", namespaces=NS)).strip()


def make_paragraph(text: str, style: str | None = None, justify: str | None = "both"):
    p = etree.Element(qn("w:p"))
    p_pr = etree.SubElement(p, qn("w:pPr"))
    if style:
        p_style = etree.SubElement(p_pr, qn("w:pStyle"))
        p_style.set(qn("w:val"), style)
    if justify:
        jc = etree.SubElement(p_pr, qn("w:jc"))
        jc.set(qn("w:val"), justify)
    spacing = etree.SubElement(p_pr, qn("w:spacing"))
    spacing.set(qn("w:after"), "120")
    spacing.set(qn("w:line"), "276")
    spacing.set(qn("w:lineRule"), "auto")
    r = etree.SubElement(p, qn("w:r"))
    t = etree.SubElement(r, qn("w:t"))
    t.text = text
    return p


def cell(text: str, width: int, bold: bool = False, fill: str | None = None):
    tc = etree.Element(qn("w:tc"))
    tc_pr = etree.SubElement(tc, qn("w:tcPr"))
    tc_w = etree.SubElement(tc_pr, qn("w:tcW"))
    tc_w.set(qn("w:w"), str(width))
    tc_w.set(qn("w:type"), "dxa")
    tc_mar = etree.SubElement(tc_pr, qn("w:tcMar"))
    for side in ("top", "left", "bottom", "right"):
        mar = etree.SubElement(tc_mar, qn(f"w:{side}"))
        mar.set(qn("w:w"), "90")
        mar.set(qn("w:type"), "dxa")
    if fill:
        shd = etree.SubElement(tc_pr, qn("w:shd"))
        shd.set(qn("w:fill"), fill)
    p = etree.SubElement(tc, qn("w:p"))
    p_pr = etree.SubElement(p, qn("w:pPr"))
    spacing = etree.SubElement(p_pr, qn("w:spacing"))
    spacing.set(qn("w:after"), "0")
    r = etree.SubElement(p, qn("w:r"))
    r_pr = etree.SubElement(r, qn("w:rPr"))
    sz = etree.SubElement(r_pr, qn("w:sz"))
    sz.set(qn("w:val"), "20")
    sz_cs = etree.SubElement(r_pr, qn("w:szCs"))
    sz_cs.set(qn("w:val"), "20")
    if bold:
        etree.SubElement(r_pr, qn("w:b"))
    t = etree.SubElement(r, qn("w:t"))
    t.text = text
    return tc


def make_table(headers, rows):
    widths = [1900, 1900, 2600, 2600, 900]
    tbl = etree.Element(qn("w:tbl"))
    tbl_pr = etree.SubElement(tbl, qn("w:tblPr"))
    tbl_w = etree.SubElement(tbl_pr, qn("w:tblW"))
    tbl_w.set(qn("w:w"), str(sum(widths)))
    tbl_w.set(qn("w:type"), "dxa")
    borders = etree.SubElement(tbl_pr, qn("w:tblBorders"))
    for side in ("top", "left", "bottom", "right", "insideH", "insideV"):
        border = etree.SubElement(borders, qn(f"w:{side}"))
        border.set(qn("w:val"), "single")
        border.set(qn("w:sz"), "6")
        border.set(qn("w:space"), "0")
        border.set(qn("w:color"), "000000")
    tbl_grid = etree.SubElement(tbl, qn("w:tblGrid"))
    for width in widths:
        grid_col = etree.SubElement(tbl_grid, qn("w:gridCol"))
        grid_col.set(qn("w:w"), str(width))

    header_row = etree.SubElement(tbl, qn("w:tr"))
    tr_pr = etree.SubElement(header_row, qn("w:trPr"))
    etree.SubElement(tr_pr, qn("w:tblHeader"))
    for header, width in zip(headers, widths):
        header_row.append(cell(header, width, bold=True, fill="D9E2F3"))

    for row in rows:
        tr = etree.SubElement(tbl, qn("w:tr"))
        for value, width in zip(row, widths):
            tr.append(cell(value, width))
    return tbl


def main():
    rows = [
        [
            "Registrasi user",
            "Equivalence Partitioning",
            "Sistem menerima data registrasi valid dan membuat akun baru.",
            "Akun berhasil dibuat dan pengguna diarahkan ke halaman login dengan pesan berhasil.",
            "Passed",
        ],
        [
            "Pengajuan produk seller",
            "Equivalence Partitioning",
            "Produk dengan data valid, kategori tersedia, gambar, dan sertifikat dapat dikirim untuk validasi.",
            "Produk berhasil tersimpan dan tampil pada dashboard seller dengan status menunggu validasi.",
            "Passed",
        ],
        [
            "Checkout produk normal",
            "Use Case Testing",
            "Buyer dapat membuat order dan diarahkan ke pembayaran Midtrans sandbox.",
            "Order berhasil dibuat dan sistem menjalankan redirect menuju payment gateway Midtrans.",
            "Passed",
        ],
        [
            "Pengajuan barter",
            "Use Case Testing",
            "Seller A dapat mengajukan barter terhadap produk milik Seller B dengan produk miliknya sendiri.",
            "Pengajuan barter berhasil tersimpan dan tampil pada daftar pengajuan keluar seller.",
            "Passed",
        ],
        [
            "Persetujuan barter",
            "State Transition Testing",
            "Status barter berubah dari pending menjadi accepted ketika Seller B menyetujui pengajuan.",
            "Barter berhasil disetujui dan sistem memproses pertukaran kepemilikan produk.",
            "Passed",
        ],
        [
            "Pengajuan lelang",
            "Equivalence Partitioning",
            "Seller dapat mengajukan barang lelang dengan harga awal, minimal kenaikan, dan jadwal yang valid.",
            "Barang lelang berhasil diajukan dan masuk ke proses persetujuan admin.",
            "Passed",
        ],
        [
            "Bid lelang valid",
            "Boundary Value Analysis",
            "Nominal bid yang lebih tinggi dari harga terakhir ditambah minimal kenaikan dapat diterima.",
            "Bid berhasil tersimpan dan harga tertinggi lelang diperbarui sesuai nominal bid.",
            "Passed",
        ],
        [
            "Bid lelang tidak valid",
            "Boundary Value Analysis",
            "Nominal bid di bawah batas minimal kenaikan ditolak oleh sistem.",
            "Sistem menolak bid, harga tertinggi tidak berubah, dan user tidak masuk ke riwayat bid.",
            "Passed",
        ],
        [
            "Tebak harga",
            "State Transition Testing",
            "Saat periode aktif, harga asli produk disembunyikan dan buyer dapat mengirim satu tebakan.",
            "Harga asli tidak tampil, tebakan berhasil disimpan, dan form berubah menjadi status terkunci.",
            "Passed",
        ],
    ]

    with ZipFile(SOURCE_DOCX, "r") as zin:
        entries = {name: zin.read(name) for name in zin.namelist()}

    document = etree.fromstring(entries["word/document.xml"])
    body = document.xpath(".//w:body", namespaces=NS)[0]
    insert_idx = None
    for idx, child in enumerate(body):
        if child.tag != qn("w:p"):
            continue
        text = paragraph_text(child).replace(" ", "")
        if text == "BAB5KESIMPULANDANSARAN":
            insert_idx = idx
            break
    if insert_idx is None:
        raise RuntimeError("Tidak menemukan posisi BAB 5 untuk menyisipkan subbab 4.8.")

    elements = [
        make_paragraph("4.8. Hasil Pengujian", style="Heading2"),
        make_paragraph(
            "Pengujian fungsional pada sistem Vinstore dilakukan menggunakan metode black box testing. Pengujian ini berfokus pada kesesuaian fungsi sistem berdasarkan input dan output yang terlihat oleh pengguna, tanpa menganalisis struktur internal kode program. Skenario pengujian dijalankan menggunakan Playwright pada browser Chromium untuk mensimulasikan interaksi pengguna terhadap fitur utama marketplace.",
        ),
        make_paragraph(
            "Teknik black box yang digunakan meliputi equivalence partitioning untuk menguji data masukan valid, boundary value analysis untuk menguji batas minimal nominal bid, state transition testing untuk menguji perubahan status proses, serta use case testing untuk menguji alur penggunaan sistem dari awal sampai akhir. Hasil pengujian fungsional ditampilkan pada Tabel 4.1.",
        ),
        make_paragraph("Tabel 4.1 Hasil Pengujian Fungsional Sistem", justify="center"),
        make_table(
            ["Modul", "Teknik Blackbox", "Expected Result", "Actual Result", "Status"],
            rows,
        ),
        make_paragraph(
            "Berdasarkan hasil pengujian pada Tabel 4.1, seluruh modul yang diuji memperoleh status Passed. Hal ini menunjukkan bahwa fitur utama sistem, yaitu registrasi, pengajuan produk, checkout, barter, lelang, dan tebak harga telah berjalan sesuai dengan kebutuhan fungsional yang dirancang.",
        ),
    ]

    for offset, element in enumerate(elements):
        body.insert(insert_idx + offset, element)

    entries["word/document.xml"] = etree.tostring(
        document, xml_declaration=True, encoding="UTF-8", standalone="yes"
    )
    OUTPUT_DOCX.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(OUTPUT_DOCX, "w", ZIP_DEFLATED) as zout:
        for name, data in entries.items():
            zout.writestr(name, data)

    print(OUTPUT_DOCX)
    print(f"Inserted rows: {len(rows)}")


if __name__ == "__main__":
    main()
