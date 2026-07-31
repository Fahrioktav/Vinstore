from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from lxml import etree


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DOCX = ROOT / "docs" / "skripsi" / "1152200016 - TUGAS AKHIR FAHRI OCTAVIAN FINAL - BAB 4 Penjelasan Gambar Kode V4.docx"
OUTPUT_DOCX = ROOT / "docs" / "skripsi" / "1152200016 - TUGAS AKHIR FAHRI OCTAVIAN FINAL - BAB 3 Deployment dan BAB 4 Pengujian V7.docx"

NS = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}


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
    spacing.set(qn("w:before"), "0")
    spacing.set(qn("w:after"), "120")
    spacing.set(qn("w:line"), "276")
    spacing.set(qn("w:lineRule"), "auto")
    r = etree.SubElement(p, qn("w:r"))
    t = etree.SubElement(r, qn("w:t"))
    t.text = text
    return p


def make_cell(text: str, width: int, bold: bool = False, fill: str | None = None, center: bool = False):
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
    if center:
        jc = etree.SubElement(p_pr, qn("w:jc"))
        jc.set(qn("w:val"), "center")
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


def make_table(headers, rows, widths, center_cols: set[int] | None = None):
    center_cols = center_cols or set()
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
    grid = etree.SubElement(tbl, qn("w:tblGrid"))
    for width in widths:
        grid_col = etree.SubElement(grid, qn("w:gridCol"))
        grid_col.set(qn("w:w"), str(width))

    header_row = etree.SubElement(tbl, qn("w:tr"))
    tr_pr = etree.SubElement(header_row, qn("w:trPr"))
    etree.SubElement(tr_pr, qn("w:tblHeader"))
    for header, width in zip(headers, widths):
        header_row.append(make_cell(header, width, bold=True, fill="D9E2F3", center=True))

    for row in rows:
        tr = etree.SubElement(tbl, qn("w:tr"))
        for col_idx, (value, width) in enumerate(zip(row, widths)):
            tr.append(make_cell(value, width, center=col_idx in center_cols))
    return tbl


def find_first(body, predicate):
    for idx, child in enumerate(body):
        if child.tag == qn("w:p") and predicate(paragraph_text(child)):
            return idx
    return None


def main():
    with ZipFile(SOURCE_DOCX, "r") as zin:
        entries = {name: zin.read(name) for name in zin.namelist()}

    document = etree.fromstring(entries["word/document.xml"])
    body = document.xpath(".//w:body", namespaces=NS)[0]

    bab4_idx = find_first(body, lambda t: t.replace(" ", "") == "BAB4HASILDANPEMBAHASAN")
    if bab4_idx is None:
        raise RuntimeError("Tidak menemukan posisi BAB 4.")

    deployment_elements = [
        make_paragraph("Tahapan Deployment", style="Heading3"),
        make_paragraph(
            "Tahap deployment dilakukan setelah prototype sistem selesai dibangun dan fitur utama telah dapat digunakan. Pada penelitian ini, website Vinstore dideploy menggunakan layanan hosting cPanel agar aplikasi dapat diakses melalui server hosting berbasis web. Deployment dilakukan dengan menyiapkan file aplikasi Laravel, hasil build frontend React Inertia, konfigurasi environment, database MySQL, storage publik, serta konfigurasi domain.",
        ),
        make_paragraph("Table 3. 15. Tahapan Deployment Menggunakan cPanel", justify="center"),
        make_table(
            ["Tahap", "Kegiatan", "Tujuan"],
            [
                ["Persiapan aplikasi", "Menyiapkan source code Laravel, dependency, file build React Inertia, dan konfigurasi .env production.", "Memastikan aplikasi siap dijalankan pada server hosting."],
                ["Upload ke hosting", "Mengunggah file project ke cPanel melalui File Manager atau Git deployment sesuai fasilitas hosting.", "Memindahkan aplikasi dari lingkungan lokal ke server hosting."],
                ["Konfigurasi domain dan public path", "Mengarahkan document root domain ke folder public Laravel atau menyesuaikan struktur public_html.", "Menjamin request pengguna masuk melalui file index.php Laravel."],
                ["Konfigurasi database", "Membuat database MySQL di cPanel, mengatur user database, melakukan migrasi atau import database.", "Menyediakan penyimpanan data aplikasi pada server hosting."],
                ["Konfigurasi storage dan permission", "Menjalankan atau menyesuaikan symbolic link storage serta hak akses folder storage dan bootstrap/cache.", "Memastikan file upload produk, bukti refund, dan dokumen sertifikat dapat diakses sistem."],
                ["Konfigurasi layanan eksternal", "Mengisi server key, client key, dan endpoint notifikasi Midtrans sandbox pada file environment.", "Menghubungkan transaksi sistem dengan payment gateway."],
                ["Verifikasi deployment", "Menguji halaman login, katalog produk, dashboard, checkout, dan fitur utama setelah website aktif.", "Memastikan aplikasi berjalan pada lingkungan hosting."],
            ],
            [1700, 4300, 3000],
        ),
        make_paragraph("Arsitektur Deployment", style="Heading3"),
        make_paragraph(
            "Arsitektur deployment Vinstore pada cPanel menggunakan pola client-server. Pengguna mengakses aplikasi melalui browser, kemudian request dikirim ke domain yang diarahkan ke web server hosting. Web server meneruskan request ke folder public Laravel melalui file index.php. Selanjutnya Laravel memproses route, middleware, controller, model, database, storage, serta integrasi payment gateway Midtrans.",
        ),
        make_paragraph("Table 3. 16. Arsitektur Deployment Sistem", justify="center"),
        make_table(
            ["Lapisan", "Komponen", "Fungsi"],
            [
                ["Client", "Browser pengguna", "Mengakses halaman marketplace, dashboard, transaksi, lelang, barter, dan chat."],
                ["Domain dan SSL", "Domain hosting dan sertifikat SSL", "Menjadi alamat akses website dan mengamankan komunikasi antara pengguna dan server."],
                ["Web server cPanel", "Apache atau LiteSpeed pada hosting", "Menerima request HTTP/HTTPS dan mengarahkannya ke aplikasi Laravel."],
                ["Application layer", "Laravel, React, Inertia.js", "Menjalankan logika backend, merender halaman frontend, memproses autentikasi, validasi, dan transaksi."],
                ["Data layer", "MySQL dan storage public", "Menyimpan data pengguna, produk, order, bid, barter, chat, refund, serta file upload."],
                ["External service", "Midtrans sandbox", "Memproses pembayaran dan mengirim notifikasi status transaksi kepada sistem."],
            ],
            [1900, 2700, 4400],
        ),
        make_paragraph(
            "Secara sederhana, alur deployment dapat digambarkan sebagai berikut: Browser Pengguna -> Domain/SSL -> Web Server cPanel -> public/index.php Laravel -> Route dan Controller -> Model -> MySQL/Storage -> Midtrans Sandbox. Alur tersebut menunjukkan bahwa folder public Laravel menjadi pintu masuk aplikasi, sedangkan proses bisnis dijalankan oleh controller dan model yang terhubung dengan database serta layanan eksternal.",
        ),
        make_paragraph("Mekanisme dan Siklus Feedback", style="Heading3"),
        make_paragraph(
            "Pada metode prototyping, feedback merupakan bagian penting karena prototype tidak dianggap sebagai hasil akhir yang langsung final. Prototype diberikan kepada pengguna atau pihak terkait untuk dicoba, kemudian pengembang mengumpulkan masukan mengenai kesesuaian fitur, kemudahan penggunaan, dan kebutuhan yang belum terpenuhi.",
        ),
        make_paragraph("Table 3. 17. Siklus Feedback Prototype", justify="center"),
        make_table(
            ["Tahap Feedback", "Aktivitas", "Hasil"],
            [
                ["Delivery", "Prototype sistem diberikan untuk dicoba pada fitur utama seperti registrasi, produk, checkout, lelang, barter, refund, dan chat.", "Pengguna dapat menjalankan alur utama sistem."],
                ["Evaluation", "Pengguna dan pengembang mengevaluasi apakah fitur berjalan sesuai kebutuhan dan mudah digunakan.", "Ditemukan catatan perbaikan tampilan, validasi, alur transaksi, atau status proses."],
                ["Feedback collection", "Masukan dikumpulkan dari hasil uji coba, error yang muncul, serta perubahan kebutuhan pengguna.", "Daftar perbaikan dan prioritas pengembangan berikutnya."],
                ["Refinement", "Pengembang memperbaiki prototype berdasarkan feedback yang diperoleh.", "Prototype menjadi lebih sesuai dengan kebutuhan pengguna."],
                ["Iteration", "Prototype yang telah diperbaiki diuji kembali sampai fitur utama dinilai layak.", "Sistem mencapai kondisi yang lebih stabil dan siap digunakan."],
            ],
            [2000, 4200, 2800],
        ),
        make_paragraph("Tahap Pengujian Sistem (Black Box Testing)", style="Heading3"),
        make_paragraph(
            "Tahap pengujian sistem dilakukan untuk memastikan bahwa fungsi utama Vinstore berjalan sesuai kebutuhan yang telah dirancang. Pengujian dilakukan menggunakan metode black box testing, yaitu metode pengujian yang menilai perilaku sistem berdasarkan input dan output tanpa melihat struktur internal kode program.",
        ),
        make_paragraph("Lingkungan Pengujian", style="Heading4"),
        make_paragraph(
            "Pengujian dilakukan pada lingkungan lokal sebelum sistem digunakan secara lebih luas. Lingkungan pengujian disusun agar dapat mewakili kondisi penggunaan aplikasi web, mulai dari browser, frontend, backend, database, hingga alat pengujian otomatis.",
        ),
        make_paragraph("Table 3. 18. Lingkungan Pengujian", justify="center"),
        make_table(
            ["Komponen", "Spesifikasi"],
            [
                ["Sistem Operasi", "Windows"],
                ["Browser", "Chromium (Playwright headless)"],
                ["Frontend", "React.js + Inertia.js yang berjalan melalui aplikasi Laravel"],
                ["Backend", "Laravel dengan server lokal pada http://127.0.0.1:8001"],
                ["Basis Data", "MySQL lokal"],
                ["Tools Pengujian", "Playwright untuk end-to-end testing"],
                ["Tanggal Pengujian", "18 Juli 2026"],
            ],
            [2600, 6400],
        ),
        make_paragraph("Metodologi Pengujian", style="Heading4"),
        make_paragraph(
            "Metodologi pengujian disusun berdasarkan skenario penggunaan utama sistem. Setiap skenario diuji dari sudut pandang pengguna melalui antarmuka aplikasi, seperti mengisi form, memilih produk, mengirim transaksi, melakukan bid, dan melihat perubahan status. Dengan pendekatan ini, pengujian dapat menilai apakah respons sistem sudah sesuai dengan kebutuhan fungsional.",
        ),
        make_paragraph(
            "Teknik equivalence partitioning digunakan untuk membagi data masukan ke dalam beberapa kelompok atau kelas data yang memiliki karakteristik serupa. Setiap kelompok dianggap mewakili kemungkinan input tertentu, misalnya data valid, data kosong, format data salah, atau data yang tidak memenuhi aturan sistem. Dengan teknik ini, pengujian tidak perlu dilakukan terhadap seluruh kemungkinan data satu per satu, tetapi cukup mengambil data perwakilan dari setiap kelas masukan.",
        ),
        make_paragraph(
            "Pada sistem Vinstore, equivalence partitioning diterapkan pada fitur registrasi akun, pengajuan produk, dan pengajuan barang lelang. Pada fitur registrasi, data yang diuji meliputi data akun yang lengkap dan valid sebagai kelas data diterima, serta data yang tidak sesuai aturan sebagai kelas data ditolak. Pada fitur tambah produk, data valid ditunjukkan melalui nama produk, harga, stok, kategori, deskripsi, gambar, dan sertifikat yang sesuai ketentuan. Tujuan penggunaan teknik ini adalah memastikan bahwa sistem mampu menerima input yang benar dan memberikan respons penolakan ketika input tidak memenuhi aturan validasi.",
        ),
        make_paragraph(
            "Teknik boundary value analysis digunakan untuk menguji nilai yang berada di sekitar batas minimum atau maksimum suatu aturan. Teknik ini penting karena kesalahan sistem sering terjadi pada nilai batas, misalnya nilai yang tepat pada batas, sedikit di bawah batas, atau sedikit di atas batas. Dengan melakukan pengujian pada area batas, sistem dapat dipastikan menjalankan aturan numerik secara tepat.",
        ),
        make_paragraph(
            "Pada Vinstore, boundary value analysis diterapkan pada fitur lelang, khususnya saat pengguna mengajukan nominal bid. Sistem memiliki aturan bahwa nominal penawaran harus lebih tinggi dari harga tertinggi saat ini dan memenuhi minimal kenaikan bid yang telah ditentukan oleh seller. Oleh karena itu, pengujian dilakukan dengan membandingkan nominal bid valid dan nominal bid yang berada di bawah batas minimal. Hasil yang diharapkan adalah sistem menerima bid yang memenuhi batas dan menolak bid yang tidak mencapai batas kenaikan minimal.",
        ),
        make_paragraph(
            "Teknik state transition testing digunakan untuk menguji perubahan status sistem dari satu kondisi ke kondisi lainnya. Teknik ini sesuai digunakan pada fitur yang memiliki alur proses bertahap, karena setiap perubahan status harus terjadi berdasarkan aksi atau kondisi tertentu. Apabila perubahan status tidak dikontrol dengan baik, sistem dapat menghasilkan kondisi yang tidak sesuai, misalnya transaksi dianggap selesai sebelum pembayaran berhasil atau lelang berakhir sebelum waktunya.",
        ),
        make_paragraph(
            "Pada sistem Vinstore, state transition testing diterapkan pada modul barter, lelang, tebak harga, refund, dan transaksi pembayaran. Pada fitur barter, status pengajuan dapat berubah dari pending menjadi accepted atau rejected. Pada fitur lelang, status dapat berubah dari pending, active, sampai ended sesuai hasil validasi admin dan waktu lelang. Pada fitur transaksi, status pembayaran dan status pesanan juga berubah berdasarkan aksi pembeli, seller, admin, maupun respons payment gateway. Pengujian ini memastikan bahwa setiap status hanya berubah ketika syarat proses telah terpenuhi.",
        ),
        make_paragraph(
            "Teknik use case testing digunakan untuk menguji sistem berdasarkan skenario penggunaan yang dilakukan oleh aktor. Teknik ini tidak hanya memeriksa satu input tertentu, tetapi mengevaluasi rangkaian aktivitas pengguna dari awal hingga akhir. Dengan demikian, use case testing dapat memperlihatkan apakah fitur yang dibangun sudah mendukung kebutuhan aktor sesuai alur bisnis yang dirancang.",
        ),
        make_paragraph(
            "Pada Vinstore, use case testing diterapkan pada alur checkout produk, pengajuan barter, pengajuan produk oleh seller, bid lelang, dan tebak harga. Contohnya, pada alur checkout, pengujian dimulai dari pembeli memilih produk, membuka halaman checkout, membuat order, sampai sistem mengarahkan pembeli ke Midtrans sandbox. Pada alur barter, pengujian melibatkan dua seller, yaitu seller pengaju dan seller penerima. Penggunaan teknik ini membantu memastikan bahwa interaksi antaraktor dan respons sistem berjalan sesuai kebutuhan fungsional marketplace.",
        ),
        make_paragraph(
            "Pelaksanaan pengujian dilakukan dengan bantuan Playwright sebagai automated end-to-end testing. Playwright digunakan untuk mensimulasikan aktivitas pengguna melalui browser Chromium, seperti membuka halaman, mengisi form, mengunggah file, menekan tombol, dan memeriksa tampilan hasil. Dengan demikian, pengujian dapat dilakukan secara lebih konsisten karena setiap skenario dijalankan berdasarkan langkah uji yang sama.",
        ),
    ]

    for offset, element in enumerate(deployment_elements):
        body.insert(bab4_idx + offset, element)

    # Insert recap before BAB 5 after the detailed functional-test table in section 4.8.
    bab5_idx = find_first(body, lambda t: t.replace(" ", "") == "BAB5KESIMPULANDANSARAN")
    if bab5_idx is None:
        raise RuntimeError("Tidak menemukan posisi BAB 5 untuk rekapitulasi.")

    recap_elements = [
        make_paragraph("4.8.1. Rekapitulasi Hasil Pengujian", style="Heading3"),
        make_paragraph(
            "Rekapitulasi hasil pengujian digunakan untuk merangkum keberhasilan setiap kelompok modul yang diuji. Rekapitulasi ini memudahkan pembaca melihat jumlah skenario pengujian, jumlah skenario yang berhasil, jumlah skenario yang gagal, serta persentase keberhasilan dari keseluruhan proses pengujian.",
        ),
        make_paragraph("Tabel 4.2 Rekapitulasi Hasil Pengujian", justify="center"),
        make_table(
            ["Modul Pengujian", "Jumlah Skenario", "Berhasil", "Gagal", "Persentase Keberhasilan"],
            [
                ["Modul jual beli, registrasi, produk seller, dan checkout", "3", "3", "0", "100%"],
                ["Modul barter antar seller", "2", "2", "0", "100%"],
                ["Modul lelang barang antik", "3", "3", "0", "100%"],
                ["Modul tebak harga", "1", "1", "0", "100%"],
                ["Total", "9", "9", "0", "100%"],
            ],
            [3000, 1500, 1200, 1100, 2200],
            center_cols={1, 2, 3, 4},
        ),
        make_paragraph(
            "Berdasarkan rekapitulasi pada Tabel 4.2, seluruh kelompok modul memperoleh persentase keberhasilan sebesar 100%. Hasil tersebut menunjukkan bahwa skenario fungsional yang diuji telah berjalan sesuai dengan expected result. Meskipun demikian, pengujian masih dapat dikembangkan pada penelitian berikutnya dengan menambahkan skenario negatif yang lebih banyak, pengujian keamanan, dan pengujian performa.",
        ),
    ]

    for offset, element in enumerate(recap_elements):
        body.insert(bab5_idx + offset, element)

    entries["word/document.xml"] = etree.tostring(
        document, xml_declaration=True, encoding="UTF-8", standalone="yes"
    )
    OUTPUT_DOCX.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(OUTPUT_DOCX, "w", ZIP_DEFLATED) as zout:
        for name, data in entries.items():
            zout.writestr(name, data)

    print(OUTPUT_DOCX)
    print(f"Inserted BAB 3 elements: {len(deployment_elements)}")
    print(f"Inserted BAB 4 recap elements: {len(recap_elements)}")


if __name__ == "__main__":
    main()
