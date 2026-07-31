from copy import deepcopy
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import re
import struct

from lxml import etree


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DOCX = Path(
    r"C:\Users\fahri\OneDrive\Documents\JO\Kuliah\Semester 8\Tugas Akhir\1152200016 - TUGAS AKHIR FAHRI OCTAVIAN.docx"
)
OUTPUT_DIR = ROOT / "docs" / "skripsi"
OUTPUT_DOCX = OUTPUT_DIR / "1152200016 - TUGAS AKHIR FAHRI OCTAVIAN - Draft BAB 2-5 Sesuai Referensi.docx"

NS = {
    "w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main",
    "r": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
    "wp": "http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing",
    "a": "http://schemas.openxmlformats.org/drawingml/2006/main",
    "pic": "http://schemas.openxmlformats.org/drawingml/2006/picture",
}
PKG_REL_NS = "http://schemas.openxmlformats.org/package/2006/relationships"
CONTENT_NS = "http://schemas.openxmlformats.org/package/2006/content-types"


def qn(tag):
    prefix, local = tag.split(":")
    return f"{{{NS[prefix]}}}{local}"


def rel_qn(local):
    return f"{{{PKG_REL_NS}}}{local}"


def ct_qn(local):
    return f"{{{CONTENT_NS}}}{local}"


def paragraph(text="", style=None, bold=False, italic=False, align=None, page_break_before=False):
    p = etree.Element(qn("w:p"))
    if style or align or page_break_before:
        ppr = etree.SubElement(p, qn("w:pPr"))
        if style:
            pstyle = etree.SubElement(ppr, qn("w:pStyle"))
            pstyle.set(qn("w:val"), style)
        if align:
            jc = etree.SubElement(ppr, qn("w:jc"))
            jc.set(qn("w:val"), align)
        if page_break_before:
            etree.SubElement(ppr, qn("w:pageBreakBefore"))

    if not text:
        return p

    r = etree.SubElement(p, qn("w:r"))
    if bold or italic:
        rpr = etree.SubElement(r, qn("w:rPr"))
        if bold:
            etree.SubElement(rpr, qn("w:b"))
        if italic:
            etree.SubElement(rpr, qn("w:i"))
    t = etree.SubElement(r, qn("w:t"))
    if text.startswith(" ") or text.endswith(" "):
        t.set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
    t.text = text
    return p


def page_break():
    p = etree.Element(qn("w:p"))
    r = etree.SubElement(p, qn("w:r"))
    br = etree.SubElement(r, qn("w:br"))
    br.set(qn("w:type"), "page")
    return p


def image_size(path):
    data = Path(path).read_bytes()
    if data.startswith(b"\x89PNG\r\n\x1a\n"):
        return struct.unpack(">II", data[16:24])
    if data.startswith(b"\xff\xd8"):
        i = 2
        while i < len(data):
            if data[i] != 0xFF:
                i += 1
                continue
            marker = data[i + 1]
            length = struct.unpack(">H", data[i + 2 : i + 4])[0]
            if marker in (0xC0, 0xC2):
                h, w = struct.unpack(">HH", data[i + 5 : i + 9])
                return w, h
            i += 2 + length
    return 1200, 800


def image_paragraph(rid, image_name, image_path, width_in=5.9):
    width_px, height_px = image_size(image_path)
    ratio = height_px / max(width_px, 1)
    cx = int(width_in * 914400)
    cy = int(width_in * ratio * 914400)
    docpr_id = abs(hash((rid, image_name))) % 100000 + 1
    xml = f"""
    <w:p xmlns:w="{NS['w']}" xmlns:r="{NS['r']}" xmlns:wp="{NS['wp']}" xmlns:a="{NS['a']}" xmlns:pic="{NS['pic']}">
      <w:pPr><w:jc w:val="center"/></w:pPr>
      <w:r>
        <w:drawing>
          <wp:inline distT="0" distB="0" distL="0" distR="0">
            <wp:extent cx="{cx}" cy="{cy}"/>
            <wp:effectExtent l="0" t="0" r="0" b="0"/>
            <wp:docPr id="{docpr_id}" name="{image_name}"/>
            <wp:cNvGraphicFramePr>
              <a:graphicFrameLocks noChangeAspect="1"/>
            </wp:cNvGraphicFramePr>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <pic:pic>
                  <pic:nvPicPr>
                    <pic:cNvPr id="0" name="{image_name}"/>
                    <pic:cNvPicPr/>
                  </pic:nvPicPr>
                  <pic:blipFill>
                    <a:blip r:embed="{rid}"/>
                    <a:stretch><a:fillRect/></a:stretch>
                  </pic:blipFill>
                  <pic:spPr>
                    <a:xfrm>
                      <a:off x="0" y="0"/>
                      <a:ext cx="{cx}" cy="{cy}"/>
                    </a:xfrm>
                    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                  </pic:spPr>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    """
    return etree.fromstring(xml.encode("utf-8"))


def table(rows):
    tbl = etree.Element(qn("w:tbl"))
    tbl_pr = etree.SubElement(tbl, qn("w:tblPr"))
    borders = etree.SubElement(tbl_pr, qn("w:tblBorders"))
    for edge in ["top", "left", "bottom", "right", "insideH", "insideV"]:
        b = etree.SubElement(borders, qn(f"w:{edge}"))
        b.set(qn("w:val"), "single")
        b.set(qn("w:sz"), "4")
        b.set(qn("w:space"), "0")
        b.set(qn("w:color"), "000000")

    for row_idx, row in enumerate(rows):
        tr = etree.SubElement(tbl, qn("w:tr"))
        for cell in row:
            tc = etree.SubElement(tr, qn("w:tc"))
            tc_pr = etree.SubElement(tc, qn("w:tcPr"))
            etree.SubElement(tc_pr, qn("w:tcW")).set(qn("w:type"), "auto")
            tc.append(paragraph(str(cell), bold=row_idx == 0))
    return tbl


def add_default_content_type(types_root, extension, content_type):
    xpath = f"./ct:Default[@Extension='{extension}']"
    if not types_root.xpath(xpath, namespaces={"ct": CONTENT_NS}):
        elem = etree.Element(ct_qn("Default"))
        elem.set("Extension", extension)
        elem.set("ContentType", content_type)
        types_root.insert(0, elem)


def paragraph_text(p):
    return "".join(p.xpath(".//w:t/text()", namespaces=NS)).strip()


def replace_paragraph_text(p, text):
    text_nodes = p.xpath(".//w:t", namespaces=NS)
    if not text_nodes:
        r = etree.SubElement(p, qn("w:r"))
        t = etree.SubElement(r, qn("w:t"))
        t.text = text
        return

    text_nodes[0].text = text
    if text.startswith(" ") or text.endswith(" "):
        text_nodes[0].set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
    for node in text_nodes[1:]:
        node.text = ""


def db_table_section(title, rows):
    return [
        paragraph(title, "Caption", italic=True, align="center"),
        table([["No", "Nama Kolom (Field)", "Tipe Data", "Keterangan"], *rows]),
    ]


def append_subsection(content, title, descriptions):
    content.append(paragraph(title, "Heading3"))
    if isinstance(descriptions, str):
        descriptions = [descriptions]
    for desc in descriptions:
        content.append(paragraph(desc))


def build_content():
    diagrams = [
        ("Gambar 3.1 Use Case Diagram Sistem Vinstore", ROOT / "docs" / "uml" / "Vinstore-UseCase.png"),
        ("Gambar 3.2 Activity Diagram Proses Checkout", ROOT / "docs" / "uml" / "Vinstore-Activity-Checkout.png"),
        ("Gambar 3.3 Activity Diagram Validasi Produk oleh Validator dan Admin", ROOT / "docs" / "uml" / "Vinstore-Activity-ProductApproval.png"),
        ("Gambar 3.4 Activity Diagram Lelang Barang Antik", ROOT / "docs" / "uml" / "Vinstore-Activity-Auction.png"),
        ("Gambar 3.5 Activity Diagram Barter Antar Toko", ROOT / "docs" / "uml" / "Vinstore-Activity-Barter.png"),
        ("Gambar 3.6 Activity Diagram Tebak Harga", ROOT / "docs" / "uml" / "Vinstore-Activity-PriceGuess.png"),
        ("Gambar 3.7 Sequence Diagram Proses Checkout", ROOT / "docs" / "uml" / "Vinstore-Sequence-Checkout.png"),
        ("Gambar 3.8 Class Diagram Sistem Vinstore", ROOT / "docs" / "uml" / "Vinstore-ClassDiagram.png"),
    ]

    content = []
    content.append(paragraph("3.1 Metode Prototyping", "Heading2"))
    content.append(paragraph(
        "Metode pengembangan sistem yang digunakan pada penelitian ini adalah metode prototyping. Menurut Pressman dan Maxim, "
        "model prototyping merupakan pendekatan pengembangan perangkat lunak yang diawali dengan komunikasi antara pengembang "
        "dan pengguna untuk memahami kebutuhan awal, kemudian dilanjutkan dengan perencanaan cepat, pemodelan rancangan cepat, "
        "konstruksi prototype, serta penyerahan prototype kepada pengguna untuk memperoleh umpan balik (Pressman & Maxim, 2020)."
    ))
    content.append(paragraph(
        "Metode prototyping dipilih karena pengembangan marketplace barang antik membutuhkan proses perancangan yang dapat "
        "dievaluasi secara bertahap. Melalui metode ini, kebutuhan pengguna dapat diterjemahkan ke dalam rancangan awal, "
        "kemudian dikembangkan menjadi prototype yang dapat diuji dan diperbaiki berdasarkan hasil evaluasi."
    ))
    content.append(paragraph(
        "Tahapan prototyping pada penelitian ini meliputi communication, quick plan, modeling quick design, construction, serta "
        "deployment, delivery, and feedback. Tahap communication digunakan untuk mengidentifikasi aktor dan kebutuhan sistem. "
        "Tahap quick plan digunakan untuk menyusun rencana arsitektur dan teknologi yang digunakan. Tahap modeling quick design "
        "digunakan untuk membuat rancangan proses, diagram UML, ERD, dan rancangan antarmuka. Tahap construction digunakan untuk "
        "mengimplementasikan sistem, sedangkan tahap deployment, delivery, and feedback digunakan untuk menguji dan mengevaluasi "
        "prototype yang telah dibangun."
    ))
    content.append(paragraph("3.2 Tahap Communication", "Heading2"))
    content.append(paragraph("Proses Bisnis", "Heading3"))
    content.append(paragraph(
        "Proses bisnis Vinstore dimulai ketika pengguna melakukan registrasi dan login. Pengguna yang ingin menjual barang "
        "antik dapat mendaftarkan toko, kemudian mengajukan produk. Produk tidak langsung tampil pada marketplace, melainkan "
        "menunggu proses validasi oleh admin atau validator. Setelah produk disetujui, pembeli dapat melihat produk, melakukan "
        "checkout, dan membayar melalui Midtrans sandbox."
    ))
    content.append(paragraph(
        "Selain transaksi jual beli biasa, sistem juga menyediakan proses lelang dan barter. Pada lelang, penjual mengajukan "
        "barang lelang dengan harga awal, minimal kenaikan bid, tanggal mulai, dan tanggal selesai. Admin melakukan validasi "
        "sebelum lelang dapat berjalan. Pembeli dapat mengajukan bid selama lelang aktif dan sistem menentukan pemenang "
        "berdasarkan bid tertinggi setelah waktu lelang selesai."
    ))
    content.append(paragraph("Identifikasi Aktor", "Heading3"))
    content.append(paragraph(
        "Identifikasi aktor dilakukan untuk mengetahui pihak yang berinteraksi langsung dengan sistem. Pada sistem Vinstore, "
        "aktor utama terdiri dari user, seller, validator, dan admin. Setiap aktor memiliki hak akses dan tanggung jawab yang berbeda "
        "sesuai dengan perannya dalam proses marketplace."
    ))
    content.append(paragraph("Tabel 3.1 Identifikasi Aktor", "Caption", italic=True, align="center"))
    content.append(table([
        ["No", "Aktor", "Deskripsi", "Hak Akses / Peran"],
        ["1", "User", "Pengguna yang telah melakukan registrasi dan login sebagai pembeli barang antik.", "Melihat produk, melihat toko, checkout, melakukan pembayaran, melihat riwayat order, mengikuti lelang, mengirim bid, mengikuti tebak harga, mengajukan refund, dan menggunakan chat."],
        ["2", "Seller", "User yang telah mendaftarkan toko dan berperan sebagai penjual pada marketplace.", "Mengelola toko, mengajukan produk, mengelola lelang, mengajukan dan memproses barter, mengelola pesanan toko, melihat saldo, serta mengajukan penarikan saldo."],
        ["3", "Validator", "Aktor yang bertugas memeriksa kelayakan dan keaslian produk barang antik yang diajukan seller sebelum diteruskan ke admin.", "Melihat daftar produk yang menunggu validasi, membuka detail produk, menyetujui produk yang valid agar masuk ke tahap admin, serta menolak produk dengan alasan tertentu."],
        ["4", "Admin", "Pengelola utama sistem yang bertanggung jawab terhadap pengawasan dan validasi akhir data marketplace.", "Mengelola pengguna, seller, toko, kategori, produk, pesanan, lelang, refund, bantuan, dan penarikan saldo seller. Admin juga memberikan persetujuan akhir terhadap produk yang telah divalidasi validator."],
    ]))
    content.append(paragraph("Kebutuhan Fungsional", "Heading3"))
    content.append(paragraph(
        "Kebutuhan fungsional merupakan kebutuhan yang berkaitan dengan layanan atau fungsi yang harus disediakan oleh sistem. "
        "Kebutuhan fungsional Vinstore disusun berdasarkan aktivitas utama user, seller, validator, dan admin."
    ))
    content.append(paragraph("Tabel 3.2 Kebutuhan Fungsional", "Caption", italic=True, align="center"))
    content.append(table([
        ["No", "Aktor", "Kebutuhan Fungsional"],
        ["1", "User", "Melakukan registrasi dan login, melihat katalog produk, melihat detail produk, checkout, membayar pesanan melalui Midtrans sandbox, melihat riwayat order, mengikuti lelang, mengirim bid, mengikuti tebak harga, mengajukan refund, dan menggunakan chat."],
        ["2", "Seller", "Mendaftarkan dan mengelola toko, menambahkan produk, memilih kategori produk dari data admin, mengajukan produk untuk validasi, mengelola lelang, mengajukan ulang lelang tanpa bid, mengelola barter, memperbarui status pesanan, melihat saldo, dan mengajukan penarikan dana."],
        ["3", "Validator", "Melakukan login, melihat daftar produk yang berstatus menunggu validasi validator, melihat detail produk yang diajukan seller, memeriksa informasi produk dan sertifikat, menyetujui produk yang valid agar diteruskan ke admin, serta menolak produk yang tidak memenuhi ketentuan dengan alasan penolakan."],
        ["4", "Admin", "Melakukan login, mengelola data pengguna, seller, toko, kategori, produk, pesanan, pengajuan lelang, pengajuan refund, pesan bantuan atau kontak, serta pengajuan penarikan saldo seller. Admin juga melakukan persetujuan akhir terhadap produk yang telah divalidasi validator."],
    ]))
    content.append(paragraph("Kebutuhan Non Fungsional", "Heading3"))
    content.append(paragraph(
        "Kebutuhan non fungsional menjelaskan batasan kualitas sistem yang perlu dipenuhi agar aplikasi dapat digunakan dengan "
        "baik. Kebutuhan ini meliputi aspek keamanan, kemudahan penggunaan, kinerja, kompatibilitas, dan pemeliharaan."
    ))
    content.append(paragraph("Tabel 3.3 Kebutuhan Non Fungsional", "Caption", italic=True, align="center"))
    content.append(table([
        ["No", "Aspek", "Kebutuhan Non Fungsional"],
        ["1", "Keamanan", "Sistem menggunakan autentikasi pengguna, pembatasan akses berdasarkan role, validasi input, serta public_id agar identitas data yang tampil tidak langsung menggunakan id internal database."],
        ["2", "Usability", "Antarmuka sistem dibuat berbasis web dan responsif sehingga user, seller, validator, dan admin dapat mengakses fitur sesuai kebutuhan dengan navigasi yang jelas."],
        ["3", "Reliability", "Sistem menjaga konsistensi data transaksi, seperti pengurangan dan pengembalian stok, perubahan status order, serta pemrosesan barter dalam transaksi database."],
        ["4", "Compatibility", "Sistem dapat dijalankan melalui browser modern dan dikembangkan menggunakan Laravel, React, Inertia.js, Tailwind CSS, dan MySQL."],
        ["5", "Maintainability", "Kode sistem disusun menggunakan pola Model-View-Controller sehingga proses pengembangan dan pemeliharaan fitur menjadi lebih terstruktur."],
    ]))
    content.append(paragraph("3.3 Tahap Quick Plan", "Heading2"))
    content.append(paragraph("Rencana Arsitektur Sistem", "Heading3"))
    content.append(paragraph(
        "Pada tahap quick plan, rancangan awal arsitektur sistem dibuat untuk menentukan pembagian tanggung jawab antara "
        "antarmuka pengguna, proses bisnis, dan penyimpanan data. Sistem Vinstore dirancang sebagai aplikasi web berbasis "
        "Laravel dan React dengan pola Model-View-Controller. React dan Inertia.js digunakan pada sisi tampilan, Laravel "
        "digunakan untuk route, controller, validasi, dan model, sedangkan MySQL digunakan sebagai penyimpanan data."
    ))
    content.append(paragraph("Tabel 3.4 Rencana Arsitektur Sistem", "Caption", italic=True, align="center"))
    content.append(table([
        ["No", "Lapisan Sistem", "Komponen", "Keterangan"],
        ["1", "Presentation Layer", "ReactJS, Inertia.js, Tailwind CSS", "Menampilkan halaman user, seller, admin, form transaksi, dashboard, katalog produk, dan halaman lelang."],
        ["2", "Application Layer", "Laravel Controller dan Middleware", "Memproses request, validasi input, otorisasi role, checkout, approval produk, lelang, barter, refund, dan penarikan saldo."],
        ["3", "Domain/Data Layer", "Eloquent Model dan Migration", "Mengelola entitas data seperti User, Store, Product, Order, Auction, AuctionBid, BarterRequest, RefundRequest, dan WithdrawalRequest."],
        ["4", "External Service", "Midtrans Sandbox", "Menangani simulasi transaksi pembayaran online dan notifikasi status pembayaran."],
    ]))
    content.append(paragraph("Tech Stack", "Heading3"))
    content.append(paragraph("Tabel 3.5 Teknologi Pendukung", "Caption", italic=True, align="center"))
    content.append(table([
        ["Komponen", "Teknologi yang Digunakan", "Fungsi Utama"],
        ["Backend", "Laravel", "Mengelola route, controller, model, migration, validasi, dan proses bisnis."],
        ["Frontend", "ReactJS dan Inertia.js", "Membangun antarmuka pengguna berbasis web."],
        ["Styling", "Tailwind CSS", "Membuat tampilan responsif dan konsisten."],
        ["Database", "MySQL", "Menyimpan data pengguna, toko, produk, pesanan, lelang, refund, dan chat."],
        ["Payment Gateway", "Midtrans Sandbox", "Simulasi pembayaran online."],
        ["Testing", "Playwright", "Menjalankan pengujian blackbox berbasis browser."],
    ]))
    content.append(paragraph("3.4 Tahap Modeling Quick Design", "Heading2"))
    content.append(paragraph("Unified Modeling Language (UML)", "Heading3"))
    content.append(paragraph("Use Case Diagram Sistem", "Heading3"))
    content.append(paragraph(
        "Use case diagram digunakan untuk menggambarkan hubungan antara aktor dan fungsi yang tersedia dalam sistem. Aktor "
        "utama pada sistem Vinstore adalah admin, validator, penjual, dan pembeli. Setiap aktor memiliki hak akses berbeda "
        "sesuai kebutuhan dan tanggung jawab masing-masing."
    ))
    content.append(("image", diagrams[0]))
    content.append(paragraph(
        "Gambar 3.1 menunjukkan ruang lingkup fitur Vinstore. Admin mengelola pengguna, kategori, toko, produk, pesanan, "
        "lelang, refund, bantuan, dan penarikan saldo. Validator membantu proses validasi produk. Penjual mengelola toko, "
        "produk, lelang, barter, pesanan, chat, dan saldo. Pembeli dapat melihat katalog, melakukan checkout, mengikuti lelang, "
        "mengajukan refund, melakukan barter, dan menggunakan chat."
    ))
    content.append(paragraph("Activity Diagram", "Heading3"))
    content.append(paragraph(
        "Activity diagram digunakan untuk menggambarkan alur aktivitas pada sistem. Pada penelitian ini activity diagram "
        "difokuskan pada proses utama marketplace, yaitu checkout, validasi produk, lelang, barter, dan tebak harga. Kelima "
        "proses tersebut dipilih karena mewakili alur transaksi, kelayakan produk, serta fitur khusus yang menjadi pembeda "
        "sistem Vinstore."
    ))
    content.append(("image", diagrams[1]))
    content.append(paragraph(
        "Gambar 3.2 menunjukkan proses checkout. Pembeli memilih produk, sistem memvalidasi stok, membuat data order, "
        "menghubungkan transaksi ke Midtrans sandbox, lalu memperbarui status pembayaran sesuai respons pembayaran."
    ))
    content.append(("image", diagrams[2]))
    content.append(paragraph(
        "Gambar 3.3 menunjukkan proses validasi produk. Produk yang diajukan penjual diperiksa terlebih dahulu oleh validator, "
        "kemudian diteruskan kepada admin untuk persetujuan akhir. Produk dapat disetujui agar tampil di katalog atau ditolak "
        "dengan alasan tertentu."
    ))
    content.append(("image", diagrams[3]))
    content.append(paragraph(
        "Gambar 3.4 menunjukkan proses lelang barang antik. Penjual mengajukan barang lelang, admin melakukan validasi, pembeli "
        "mengajukan bid selama lelang aktif, kemudian sistem menentukan pemenang berdasarkan bid tertinggi saat waktu lelang berakhir."
    ))
    content.append(("image", diagrams[4]))
    content.append(paragraph(
        "Gambar 3.5 menunjukkan proses barter antar toko. Seller memilih produk seller lain, menawarkan produk miliknya sendiri, "
        "lalu seller pemilik produk dapat menerima atau menolak pengajuan. Jika diterima, sistem menukar kepemilikan kedua produk "
        "dalam satu transaksi database."
    ))
    content.append(("image", diagrams[5]))
    content.append(paragraph(
        "Gambar 3.6 menunjukkan proses tebak harga. Produk yang telah disetujui memasuki periode tebak harga, pembeli mengirimkan "
        "tebakan satu kali, lalu sistem menentukan pemenang berdasarkan tebakan yang paling mendekati harga asli."
    ))
    content.append(paragraph("Sequence Diagram", "Heading3"))
    content.append(("image", diagrams[6]))
    content.append(paragraph(
        "Gambar 3.7 menggambarkan urutan interaksi pada proses checkout antara pembeli, halaman checkout, controller, database, "
        "dan Midtrans sandbox. Diagram ini memperlihatkan bagaimana order dibuat, snap token diproses, dan status pembayaran disimpan."
    ))
    content.append(paragraph("Class Diagram", "Heading3"))
    content.append(("image", diagrams[7]))
    content.append(paragraph(
        "Gambar 3.8 menunjukkan class diagram sistem Vinstore. Class diagram menggambarkan hubungan antara User, Store, Product, "
        "Category, Order, Auction, AuctionBid, RefundRequest, WithdrawalRequest, Conversation, Message, BarterRequest, dan PriceGuess."
    ))
    content.append(paragraph("Struktur Tabel Database", "Heading3"))
    for section in [
        db_table_section("Tabel 3.6 Users", [
            ["1", "id", "bigint", "Primary key internal pengguna."],
            ["2", "public_id", "varchar", "Kode publik pengguna yang ditampilkan ke sistem."],
            ["3", "username", "varchar", "Username unik pengguna."],
            ["4", "email", "varchar", "Email pengguna untuk login dan reset password."],
            ["5", "google_id", "varchar", "Identitas akun Google jika pengguna login dengan Google."],
            ["6", "role", "varchar", "Hak akses pengguna seperti admin, validator, seller, atau user."],
        ]),
        db_table_section("Tabel 3.7 Stores", [
            ["1", "id", "bigint", "Primary key internal toko."],
            ["2", "public_id", "varchar", "Kode publik toko."],
            ["3", "user_id", "bigint", "Relasi ke pengguna pemilik toko."],
            ["4", "store_name", "varchar", "Nama toko penjual."],
            ["5", "location", "varchar", "Lokasi toko."],
            ["6", "available_balance", "decimal", "Saldo tersedia milik penjual."],
        ]),
        db_table_section("Tabel 3.8 Products", [
            ["1", "id", "bigint", "Primary key internal produk."],
            ["2", "public_id", "varchar", "Kode publik produk."],
            ["3", "store_id", "bigint", "Relasi ke toko pemilik produk."],
            ["4", "name", "varchar", "Nama produk barang antik."],
            ["5", "price", "decimal", "Harga produk."],
            ["6", "approval_status", "varchar", "Status validasi produk."],
        ]),
        db_table_section("Tabel 3.9 Orders", [
            ["1", "id", "bigint", "Primary key internal pesanan."],
            ["2", "public_id", "varchar", "Kode publik order atau invoice."],
            ["3", "user_id", "bigint", "Relasi ke pembeli."],
            ["4", "product_id", "bigint", "Relasi ke produk jika berasal dari pembelian biasa."],
            ["5", "auction_id", "bigint", "Relasi ke lelang jika berasal dari pemenang lelang."],
            ["6", "payment_status", "varchar", "Status pembayaran order."],
        ]),
        db_table_section("Tabel 3.10 Auctions", [
            ["1", "id", "bigint", "Primary key internal lelang."],
            ["2", "public_id", "varchar", "Kode publik lelang."],
            ["3", "store_id", "bigint", "Relasi ke toko pemilik barang lelang."],
            ["4", "starting_price", "decimal", "Harga awal lelang."],
            ["5", "min_increment", "decimal", "Minimal kenaikan bid."],
            ["6", "status", "varchar", "Status lelang."],
        ]),
        db_table_section("Tabel 3.11 Refund Requests", [
            ["1", "id", "bigint", "Primary key internal refund."],
            ["2", "public_id", "varchar", "Kode publik refund."],
            ["3", "order_id", "bigint", "Relasi ke pesanan."],
            ["4", "reason", "text", "Alasan pengajuan refund."],
            ["5", "proof_image", "varchar", "Foto bukti refund."],
            ["6", "status", "varchar", "Status review admin."],
        ]),
    ]:
        content.extend(section)
    content.append(paragraph("Entity Relation Diagram (ERD)", "Heading3"))
    content.append(paragraph(
        "ERD Vinstore terdiri dari relasi utama antara users, stores, products, orders, auctions, auction_bids, refund_requests, "
        "withdrawal_requests, conversations, messages, barter_requests, dan price_guesses. Relasi tersebut menunjukkan alur data "
        "mulai dari pengguna, toko, produk, transaksi, lelang, komunikasi, sampai pengajuan refund dan penarikan saldo."
    ))
    content.append(paragraph("Rancangan Antar Muka", "Heading3"))
    for title, desc in [
        ("Halaman Login dan Register", [
            "Halaman autentikasi dirancang sebagai gerbang awal sebelum pengguna mengakses fitur utama sistem. Pada halaman ini pengguna dapat melakukan login manual, login menggunakan Google, registrasi akun baru, lupa password, dan reset password.",
            "Perancangan halaman autentikasi memperhatikan kebutuhan keamanan dan kemudahan penggunaan. Form login menyediakan input identitas pengguna dan password, sedangkan register menyediakan data dasar pengguna yang dibutuhkan sistem untuk membedakan akun pembeli, penjual, validator, dan admin.",
        ]),
        ("Halaman Beranda Marketplace", [
            "Halaman beranda marketplace dirancang untuk memberikan gambaran awal mengenai produk barang antik yang tersedia pada sistem. Komponen utama halaman ini meliputi identitas Vinstore, daftar produk populer, kategori barang antik, dan navigasi menuju katalog produk, toko, serta lelang.",
            "Rancangan halaman beranda dibuat agar pengguna dapat langsung memahami fungsi utama sistem. Dengan menampilkan produk dan kategori pada halaman awal, pengguna dapat melakukan eksplorasi sebelum masuk ke proses transaksi.",
        ]),
        ("Halaman Produk dan Detail Produk", [
            "Halaman produk dirancang untuk menampilkan katalog barang antik yang telah disetujui oleh admin atau validator. Informasi yang ditampilkan meliputi gambar produk, nama produk, harga, stok, kategori, dan tombol aksi untuk melihat detail atau melakukan pemesanan.",
            "Halaman detail produk menampilkan informasi yang lebih lengkap, seperti deskripsi produk, sertifikat, data toko, dan aksi pembelian. Rancangan ini penting karena barang antik membutuhkan informasi yang lebih jelas agar pembeli dapat mempertimbangkan kondisi dan nilai barang sebelum melakukan transaksi.",
        ]),
        ("Dashboard Seller", [
            "Dashboard seller dirancang sebagai pusat kendali penjual dalam mengelola aktivitas toko. Melalui halaman ini seller dapat melihat produk, pesanan, lelang, barter, saldo, dan fitur penarikan dana.",
            "Rancangan dashboard seller membantu penjual memantau status produk yang diajukan, apakah masih menunggu validasi, disetujui, atau ditolak. Dengan adanya ringkasan ini, penjual dapat mengetahui proses bisnis tokonya tanpa harus membuka setiap menu secara terpisah.",
        ]),
        ("Dashboard Validator", [
            "Dashboard validator dirancang untuk menampilkan daftar produk yang menunggu proses validasi. Validator dapat membuka detail produk sebelum memberikan keputusan apakah produk layak disetujui atau perlu ditolak.",
            "Perancangan halaman validator dipisahkan dari admin agar proses pemeriksaan produk dapat lebih terfokus. Validator hanya diarahkan pada aktivitas verifikasi kelayakan produk, seperti memeriksa gambar, deskripsi, kategori, harga, dan sertifikat produk.",
        ]),
        ("Dashboard Admin", [
            "Dashboard admin dirancang untuk mengelola seluruh data utama sistem, seperti pengguna, toko, kategori, produk, pesanan, lelang, refund, bantuan, dan penarikan seller. Admin memiliki cakupan akses paling luas karena bertanggung jawab menjaga operasional sistem.",
            "Perancangan dashboard admin dibuat sebagai pusat monitoring agar admin dapat melakukan pengawasan terhadap aktivitas marketplace. Dengan adanya menu manajemen yang terpisah, admin dapat menangani data dan transaksi sesuai jenisnya.",
        ]),
        ("Halaman Lelang", [
            "Halaman lelang dirancang untuk menampilkan daftar barang antik yang dijual melalui mekanisme penawaran. Informasi yang ditampilkan meliputi harga awal, harga tertinggi saat ini, jumlah penawar, sisa waktu, riwayat bid, dan tombol ajukan penawaran.",
            "Rancangan halaman lelang dibuat agar proses penawaran berjalan transparan. Riwayat bid membantu pengguna melihat perkembangan penawaran, sedangkan aturan minimal kenaikan bid membantu menjaga agar proses lelang berjalan sesuai ketentuan sistem.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("3.5 Tahap Construction", "Heading2"))
    content.append(paragraph(
        "Pada tahap construction, rancangan sistem yang telah dibuat pada tahap modeling quick design diimplementasikan menjadi "
        "prototype aplikasi. Implementasi dilakukan dengan membangun struktur backend, struktur frontend, database, serta modul "
        "fitur utama yang dibutuhkan pada marketplace Vinstore."
    ))
    content.append(paragraph("3.5.1 Struktur Backend", "Heading3"))
    content.append(paragraph(
        "Struktur backend pada sistem Vinstore dibangun menggunakan Laravel. Backend bertugas mengatur route, controller, model, "
        "middleware, validasi data, proses autentikasi, transaksi, serta integrasi dengan layanan eksternal seperti Midtrans."
    ))
    content.append(paragraph("Tabel 3.12 Struktur Backend", "Caption", italic=True, align="center"))
    content.append(table([
        ["Komponen", "Fungsi"],
        ["app/Models", "Berisi model Eloquent yang merepresentasikan tabel database, seperti User, Store, Product, Order, Auction, AuctionBid, BarterRequest, RefundRequest, dan WithdrawalRequest."],
        ["app/Http/Controllers", "Berisi controller yang menangani proses bisnis aplikasi, seperti autentikasi, produk, checkout, lelang, barter, refund, validasi produk, dan dashboard."],
        ["app/Http/Middleware", "Mengatur proses penyaringan request, termasuk pembatasan akses berdasarkan autentikasi dan role pengguna."],
        ["app/Services", "Berisi service class untuk logika khusus seperti MidtransService dan PriceGuessService agar controller tidak terlalu padat."],
        ["routes/web.php", "Mendefinisikan route aplikasi yang menghubungkan URL dengan controller dan halaman Inertia."],
        ["database/migrations", "Berisi migration untuk membuat dan mengubah struktur tabel database."],
        ["storage/app/public", "Digunakan untuk menyimpan file upload seperti gambar produk, foto toko, sertifikat, dan bukti refund."],
    ]))
    content.append(paragraph("3.5.2 Struktur Frontend", "Heading3"))
    content.append(paragraph(
        "Struktur frontend pada sistem Vinstore dibangun menggunakan ReactJS dan Inertia.js. Frontend bertugas menampilkan "
        "antarmuka pengguna, form input, dashboard, katalog produk, halaman transaksi, serta halaman admin dan validator."
    ))
    content.append(paragraph("Tabel 3.13 Struktur Frontend", "Caption", italic=True, align="center"))
    content.append(table([
        ["Komponen", "Fungsi"],
        ["resources/js/app.jsx", "Menginisialisasi aplikasi React dan Inertia.js sebagai entry point frontend."],
        ["resources/views/app.blade.php", "Template utama Laravel yang memuat directive @inertia sebagai root HTML sebelum halaman React dirender."],
        ["resources/js/pages", "Berisi halaman aplikasi seperti home, produk, checkout, order, invoice, toko, lelang, admin, seller, validator, dan support."],
        ["resources/js/components", "Berisi komponen reusable seperti navbar, footer, product card, search input, location picker, button, card, dropdown, dan table."],
        ["resources/js/layouts", "Berisi layout utama aplikasi, seperti main layout dan form layout."],
        ["resources/js/lib", "Berisi helper atau konfigurasi library frontend, misalnya konfigurasi Leaflet dan utility function."],
        ["resources/css/app.css", "Berisi styling utama aplikasi menggunakan Tailwind CSS."],
    ]))

    module_sections = [
        ("3.5.3 Implementasi Modul Autentikasi", [
            "Modul autentikasi digunakan untuk mengelola proses akses pengguna ke dalam sistem Vinstore. Modul ini mencakup login manual, login Google, registrasi, logout, lupa password, reset password, dan pembatasan akses berdasarkan role.",
            "Tabel 3.14 Tabel Implementasi Modul Autentikasi",
            [
                ["Komponen", "Fungsi"],
                ["LoginController", "Mengelola proses login dan logout pengguna."],
                ["RegisterController", "Mengelola proses pendaftaran akun pengguna baru."],
                ["SocialAuthController", "Mengelola proses login menggunakan akun Google."],
                ["ForgotPasswordController", "Mengelola permintaan link reset password."],
                ["ResetPasswordController", "Mengelola proses pembuatan password baru."],
                ["User", "Model yang merepresentasikan data pengguna dan role pada sistem."],
                ["resources/js/pages/auth", "Berisi halaman login, register, forgot password, dan reset password."],
            ],
        ]),
        ("3.5.4 Implementasi Modul Produk", [
            "Modul produk digunakan untuk mengelola data produk barang antik yang diajukan oleh seller. Produk tidak langsung tampil pada katalog, tetapi melalui validasi validator dan persetujuan akhir admin.",
            "Tabel 3.15 Tabel Implementasi Modul Produk",
            [
                ["Komponen", "Fungsi"],
                ["ProductController", "Mengelola proses tambah, edit, update, dan hapus produk oleh seller."],
                ["ValidatorController", "Mengelola validasi awal produk oleh validator sebelum diteruskan ke admin."],
                ["AdminProductController", "Mengelola persetujuan akhir atau penolakan produk oleh admin."],
                ["Product", "Model yang merepresentasikan produk, stok, harga, jenis penjualan, dan status validasi."],
                ["Category", "Model yang merepresentasikan kategori produk yang disediakan oleh admin."],
                ["resources/js/pages/seller/products", "Berisi halaman tambah dan edit produk seller."],
                ["resources/js/pages/validator", "Berisi dashboard dan detail validasi produk untuk validator."],
                ["resources/js/pages/admin/products", "Berisi halaman admin untuk daftar produk dan produk pending."],
            ],
        ]),
        ("3.5.5 Implementasi Modul Transaksi", [
            "Modul transaksi digunakan untuk mengelola proses pembelian produk, mulai dari checkout, pembuatan order, pembayaran Midtrans sandbox, pembatalan pesanan, pengembalian stok, hingga invoice.",
            "Tabel 3.16 Tabel Implementasi Modul Transaksi",
            [
                ["Komponen", "Fungsi"],
                ["OrderController", "Mengelola checkout, pembuatan order, riwayat pesanan, invoice, pembatalan order, dan update status pesanan."],
                ["MidtransService", "Mengelola pembuatan transaksi pembayaran dan pengecekan status pembayaran Midtrans."],
                ["MidtransNotificationController", "Mengelola webhook atau notifikasi pembayaran dari Midtrans."],
                ["Order", "Model yang merepresentasikan data pesanan dan status pembayaran."],
                ["CartController", "Mengelola proses keranjang belanja sebelum checkout."],
                ["Cart", "Model yang merepresentasikan item keranjang pengguna."],
                ["resources/js/pages/checkout.jsx", "Halaman frontend checkout produk."],
                ["resources/js/pages/order.jsx", "Halaman frontend riwayat pesanan pengguna."],
                ["resources/js/pages/invoice.jsx", "Halaman frontend invoice pesanan."],
            ],
        ]),
        ("3.5.6 Implementasi Modul Lelang", [
            "Modul lelang digunakan untuk mengelola penjualan barang antik melalui mekanisme penawaran. Modul ini mencakup pengajuan lelang oleh seller, validasi admin, bid pembeli, riwayat bid, finalisasi pemenang, dan pembayaran pemenang lelang.",
            "Tabel 3.17 Tabel Implementasi Modul Lelang",
            [
                ["Komponen", "Fungsi"],
                ["AuctionController", "Mengelola pengajuan lelang, edit, relist, approval admin, bid, finalisasi pemenang, dan pembayaran lelang."],
                ["Auction", "Model yang merepresentasikan data barang lelang, harga awal, harga tertinggi, status, dan pemenang."],
                ["AuctionBid", "Model yang menyimpan riwayat penawaran pengguna pada lelang."],
                ["Order", "Menyimpan transaksi pembayaran untuk pemenang lelang."],
                ["resources/js/pages/auctions", "Berisi halaman daftar dan detail lelang untuk pengguna."],
                ["resources/js/pages/seller/auctions", "Berisi halaman tambah, edit, dan relist lelang untuk seller."],
                ["resources/js/pages/admin/auctions/index.jsx", "Halaman admin untuk menyetujui atau menolak pengajuan lelang."],
            ],
        ]),
        ("3.5.7 Implementasi Modul Barter", [
            "Modul barter digunakan untuk memfasilitasi pertukaran produk antar seller. Seller dapat mengajukan barter, sedangkan seller pemilik produk dapat menerima, menolak, atau membatalkan pengajuan sesuai kondisi barter.",
            "Tabel 3.18 Tabel Implementasi Modul Barter",
            [
                ["Komponen", "Fungsi"],
                ["BarterController", "Mengelola pengajuan barter, persetujuan, penolakan, pembatalan, dan pertukaran kepemilikan produk."],
                ["BarterPaymentNotificationController", "Mengelola notifikasi pembayaran apabila barter menggunakan pembayaran tambahan."],
                ["BarterRequest", "Model yang merepresentasikan data pengajuan barter antar toko."],
                ["Product", "Digunakan untuk memvalidasi produk yang ditawarkan dan produk yang diminta dalam barter."],
                ["resources/js/pages/seller/barter/index.jsx", "Halaman frontend untuk daftar produk barter, pengajuan masuk, dan pengajuan keluar."],
                ["resources/js/pages/seller/barter/payment.jsx", "Halaman frontend pembayaran tambahan barter jika diperlukan."],
            ],
        ]),
        ("3.5.8 Implementasi Modul Tebak Harga", [
            "Modul tebak harga digunakan untuk produk dengan mekanisme harga tersembunyi. User dapat mengirim tebakan selama periode aktif, kemudian sistem menentukan pemenang berdasarkan tebakan yang paling mendekati harga asli.",
            "Tabel 3.19 Tabel Implementasi Modul Tebak Harga",
            [
                ["Komponen", "Fungsi"],
                ["PriceGuessController", "Mengelola pengiriman tebakan harga oleh user."],
                ["PriceGuessService", "Mengelola sinkronisasi status tebak harga, aktivasi periode, finalisasi pemenang, dan perubahan status menjadi public."],
                ["PriceGuess", "Model yang menyimpan data tebakan harga pengguna."],
                ["Product", "Menyimpan informasi sale_type, guess_status, guess_winner_id, dan periode tebak harga."],
                ["resources/js/pages/products/index.jsx", "Menampilkan produk tebak harga dengan harga yang disembunyikan saat periode aktif."],
            ],
        ]),
        ("3.5.9 Implementasi Modul Refund dan Saldo", [
            "Modul refund digunakan user untuk mengajukan pengembalian dana dengan alasan dan foto bukti. Modul saldo digunakan seller untuk melihat saldo hasil transaksi dan mengajukan pencairan dana kepada admin.",
            "Tabel 3.20 Tabel Implementasi Modul Refund dan Saldo",
            [
                ["Komponen", "Fungsi"],
                ["RefundRequestController", "Mengelola pengajuan refund oleh user serta approval atau reject refund oleh admin."],
                ["RefundRequest", "Model yang merepresentasikan data refund, alasan, foto bukti, status, dan reviewer."],
                ["WithdrawalRequestController", "Mengelola pengajuan pencairan saldo seller serta approval atau reject oleh admin."],
                ["WithdrawalRequest", "Model yang merepresentasikan data permintaan penarikan saldo seller."],
                ["SellerDashboardController", "Menampilkan saldo tersedia, saldo tertarik, dan data transaksi pada dashboard seller."],
                ["resources/js/pages/admin/refunds/index.jsx", "Halaman admin untuk meninjau pengajuan refund."],
                ["resources/js/pages/admin/withdrawals/index.jsx", "Halaman admin untuk memproses pengajuan penarikan saldo."],
            ],
        ]),
        ("3.5.10 Implementasi Modul Chat dan Bantuan", [
            "Modul chat dan bantuan digunakan untuk menyediakan komunikasi antara pengguna dengan sistem atau pengelola. Fitur ini membantu pengguna menyampaikan pertanyaan, kendala transaksi, dan pesan bantuan.",
            "Tabel 3.21 Tabel Implementasi Modul Chat dan Bantuan",
            [
                ["Komponen", "Fungsi"],
                ["SupportController", "Mengelola halaman dan pesan bantuan pengguna."],
                ["ContactController", "Mengelola pengiriman pesan kontak dari user."],
                ["AdminContactController", "Mengelola pesan kontak dan balasan admin."],
                ["SupportMessage", "Model yang menyimpan pesan bantuan."],
                ["Contact", "Model yang menyimpan pesan kontak user dan balasan admin."],
                ["resources/js/pages/support/index.jsx", "Halaman frontend chat atau bantuan."],
                ["resources/js/pages/admin/support/index.jsx", "Halaman admin untuk memantau pesan bantuan."],
                ["resources/js/pages/admin/contacts/index.jsx", "Halaman admin untuk mengelola pesan kontak."],
            ],
        ]),
    ]
    for title, data in module_sections:
        desc, caption, rows = data
        content.append(paragraph(title, "Heading3"))
        content.append(paragraph(desc))
        content.append(paragraph(caption, "Caption", italic=True, align="center"))
        content.append(table(rows))
    content.append(paragraph("3.6 Tahap Deployment, Delivery, and Feedback", "Heading2"))
    content.append(paragraph("Rancangan Pengujian", "Heading3"))
    content.append(paragraph(
        "Tahap deployment, delivery, and feedback dilakukan dengan menjalankan prototype pada server lokal dan melakukan pengujian "
        "fitur utama. Rancangan pengujian dilakukan menggunakan metode blackbox testing. Pengujian berfokus pada masukan dan "
        "keluaran sistem dari sudut pandang pengguna akhir tanpa melihat struktur kode program."
    ))
    content.append(paragraph("Tabel 3.22 Rancangan Pengujian Blackbox", "Caption", italic=True, align="center"))
    content.append(table([
        ["No", "Fitur Sistem", "Skenario Pengujian", "Input", "Output yang Diharapkan"],
        ["1", "Registrasi", "User baru melakukan registrasi.", "Data user valid", "Akun berhasil dibuat."],
        ["2", "Produk Seller", "Seller mengajukan produk baru.", "Data produk, kategori, gambar", "Produk tersimpan dengan status pending."],
        ["3", "Checkout", "Buyer membeli produk.", "Produk dan jumlah pembelian", "Order dibuat dan diarahkan ke Midtrans."],
        ["4", "Barter", "Seller mengajukan barter.", "Produk penawaran", "Pengajuan barter tersimpan."],
        ["5", "Lelang", "Seller mengajukan barang lelang.", "Data lelang lengkap", "Lelang menunggu validasi."],
        ["6", "Bid Lelang", "Buyer mengajukan bid valid.", "Nominal bid", "Bid tersimpan dan harga tertinggi berubah."],
        ["7", "Tebak Harga", "Buyer mengirim tebakan harga.", "Nominal tebakan", "Tebakan tersimpan."],
    ]))

    content.append(page_break())
    content.append(paragraph("BAB 4 HASIL DAN PEMBAHASAN", "Heading1"))
    content.append(paragraph("Teknologi Yang Digunakan", "Heading2"))
    content.append(paragraph(
        "Implementasi Vinstore menggunakan Laravel sebagai backend, ReactJS dan Inertia.js sebagai frontend, Tailwind CSS untuk tampilan, "
        "MySQL sebagai database, Midtrans sandbox untuk simulasi pembayaran, Leaflet.js untuk peta lokasi toko, dan Playwright untuk "
        "pengujian otomatis. Kombinasi teknologi tersebut mendukung pengembangan sistem berbasis web yang terstruktur dan dapat diuji."
    ))
    content.append(paragraph("Tampilan User", "Heading2"))
    for title, desc in [
        ("Halaman Beranda", [
            "Halaman beranda merupakan halaman pertama yang dilihat pengguna ketika membuka aplikasi Vinstore. Halaman ini menampilkan identitas marketplace, produk populer, kategori barang antik, serta navigasi menuju fitur utama seperti produk, toko, dan lelang.",
            "Dari sisi implementasi, halaman beranda mengambil data produk yang telah disetujui agar hanya produk valid yang ditampilkan kepada pengguna. Hal ini mendukung kebutuhan sistem terkait kontrol kualitas produk sebelum dipublikasikan pada marketplace.",
            "Kode tampilan halaman beranda berada pada resources/js/pages/home.jsx. Halaman ini dirender melalui Inertia.js sehingga data dari controller Laravel dapat diterima sebagai props React. Komponen kartu produk yang digunakan pada halaman beranda memanfaatkan komponen ProductCard agar tampilan produk tetap konsisten dengan halaman katalog.",
            "Halaman ini juga berfungsi sebagai pintu eksplorasi awal. Pengguna dapat melihat gambaran produk yang tersedia sebelum memilih untuk membuka halaman katalog, detail produk, atau fitur lelang.",
        ]),
        ("Halaman Produk", [
            "Halaman produk menampilkan katalog barang antik yang telah melewati proses persetujuan. Informasi yang ditampilkan meliputi gambar produk, nama produk, kategori, harga, stok, dan tombol aksi untuk membuka detail produk.",
            "Fitur filter produk digunakan untuk membantu pengguna menemukan barang sesuai kebutuhan. Pengguna dapat menelusuri produk berdasarkan kategori atau kata kunci tertentu sehingga proses pencarian menjadi lebih efektif.",
            "Implementasi halaman produk berada pada resources/js/pages/products/index.jsx dan berhubungan dengan ProductController. Controller bertugas mengambil produk yang telah disetujui, sedangkan komponen React menampilkan data dalam bentuk daftar produk dan menyediakan kontrol filter agar pengguna dapat melakukan pencarian berdasarkan kebutuhan.",
            "Implementasi halaman produk menunjukkan bahwa sistem tidak hanya berfungsi sebagai daftar barang, tetapi juga sebagai media pencarian dan perbandingan produk bagi pembeli sebelum melakukan transaksi.",
        ]),
        ("Halaman Detail Produk", [
            "Halaman detail produk digunakan untuk menampilkan informasi produk secara lebih lengkap. Informasi yang disajikan meliputi nama produk, deskripsi, harga, stok, gambar, sertifikat apabila tersedia, serta informasi toko penjual.",
            "Pada halaman ini pengguna dapat menentukan tindakan berikutnya, seperti membeli produk, mengajukan barter, atau menghubungi penjual melalui fitur chat. Dengan demikian, halaman detail produk menjadi titik penting sebelum transaksi dilakukan.",
            "Pada bagian kode, detail produk diproses oleh ProductController dan ditampilkan melalui halaman React yang menerima objek produk, toko, dan status fitur seperti barter atau tebak harga. Kondisi tombol aksi ditentukan dari data produk, misalnya apakah produk sudah disetujui, stok masih tersedia, atau produk sedang berada pada periode tebak harga.",
            "Kelengkapan informasi pada halaman detail produk menjadi bagian penting dalam marketplace barang antik karena pembeli membutuhkan deskripsi yang jelas untuk menilai kondisi, keunikan, dan nilai produk.",
        ]),
        ("Halaman Checkout dan Pembayaran", [
            "Halaman checkout menampilkan ringkasan pembelian sebelum pengguna diarahkan ke proses pembayaran. Data yang ditampilkan meliputi produk yang dibeli, jumlah pembelian, harga, dan total pembayaran.",
            "Setelah pengguna melanjutkan checkout, sistem membuat data order dan menghubungkannya dengan Midtrans sandbox. Penggunaan Midtrans sandbox memungkinkan proses pembayaran diuji tanpa menggunakan transaksi uang nyata.",
            "Kode tampilan checkout berada pada resources/js/pages/checkout.jsx, sedangkan proses bisnisnya ditangani oleh OrderController. Method checkout memvalidasi produk, jumlah pembelian, dan stok, kemudian membuat order serta meminta snap token kepada MidtransService agar pengguna dapat diarahkan ke halaman pembayaran sandbox.",
            "Implementasi halaman checkout juga berkaitan dengan pengelolaan stok. Sistem perlu memastikan stok produk berkurang ketika order dibuat dan dapat dikembalikan apabila order dibatalkan sebelum pembayaran selesai.",
        ]),
        ("Halaman Riwayat Order", [
            "Halaman riwayat order digunakan pembeli untuk memantau transaksi yang pernah dibuat. Pada halaman ini pengguna dapat melihat status pesanan, status pembayaran, tanggal transaksi, invoice, dan aksi yang dapat dilakukan.",
            "Apabila pembayaran belum selesai, sistem dapat menampilkan tombol bayar. Namun apabila pembayaran sudah berhasil, tombol bayar tidak lagi ditampilkan sehingga tampilan sesuai dengan status transaksi.",
            "Kode halaman riwayat order berada pada resources/js/pages/order.jsx. Data order dikirim dari OrderController, kemudian halaman React menampilkan tombol aksi secara kondisional berdasarkan status pembayaran dan status pesanan. Kondisi ini penting agar tombol bayar tidak muncul pada pesanan yang sudah lunas.",
            "Halaman ini juga mendukung pengajuan refund apabila transaksi memenuhi syarat. Pengguna dapat memilih alasan refund, menulis alasan tambahan, serta mengunggah foto bukti untuk membantu proses review admin.",
        ]),
        ("Halaman Lelang", [
            "Halaman lelang menampilkan daftar barang antik yang dilelang oleh penjual dan telah disetujui admin. Informasi yang ditampilkan meliputi harga awal, harga tertinggi saat ini, jumlah penawar, sisa waktu, dan status lelang.",
            "Pada detail lelang, pengguna dapat mengajukan penawaran dengan nominal yang harus lebih tinggi dari bid terakhir sesuai minimal kenaikan. Sistem menolak penawaran yang tidak memenuhi aturan agar proses lelang tetap valid.",
            "Tampilan daftar dan detail lelang berada pada resources/js/pages/auctions/index.jsx dan resources/js/pages/auctions/show.jsx. Proses bid, sinkronisasi status lelang, finalisasi pemenang, dan pembayaran pemenang ditangani oleh AuctionController. Halaman detail menerima data bid terakhir dan riwayat bid agar proses penawaran dapat ditampilkan secara transparan.",
            "Riwayat bid ditampilkan agar proses lelang lebih transparan. Dengan adanya riwayat ini, pengguna dapat melihat perkembangan penawaran yang telah terjadi selama periode lelang berlangsung.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Tampilan Seller", "Heading2"))
    for title, desc in [
        ("Dashboard Seller", [
            "Dashboard seller menampilkan ringkasan aktivitas toko, seperti daftar produk, pesanan, lelang, barter, saldo tersedia, dan fitur penarikan dana. Halaman ini menjadi pusat kontrol bagi penjual untuk memantau aktivitas bisnisnya.",
            "Pada dashboard seller, penjual dapat melihat status produk yang telah diajukan. Produk dapat berada pada status pending, approved, atau rejected sesuai hasil validasi admin atau validator.",
            "Kode tampilan dashboard seller berada pada resources/js/pages/seller/dashboard.jsx dan data ringkasannya disiapkan oleh SellerDashboardController. Controller menghitung data seperti produk seller, order toko, saldo tersedia, serta permintaan pencairan agar halaman dashboard dapat menampilkan informasi operasional toko secara terpusat.",
            "Selain itu, dashboard seller mendukung pemantauan saldo penjual. Dana dari transaksi tidak langsung dapat dicairkan sebelum pesanan selesai, sehingga alur ini mendukung konsep escrow sederhana pada sistem marketplace.",
        ]),
        ("Halaman Tambah Produk", [
            "Halaman tambah produk digunakan penjual untuk mengajukan produk barang antik baru. Form yang tersedia meliputi nama produk, deskripsi, kategori, harga, stok, gambar, dan sertifikat apabila tersedia.",
            "Kategori produk diambil dari data kategori yang dibuat oleh admin. Hal ini bertujuan agar data kategori lebih konsisten dan tidak dibuat bebas oleh setiap seller.",
            "Implementasi halaman tambah produk berada pada resources/js/pages/seller/products/create.jsx. Form dikirim ke ProductController yang melakukan validasi input, menyimpan file gambar ke storage, mengisi kategori berdasarkan pilihan admin, dan mengatur approval_status menjadi pending_validator agar produk masuk ke alur validasi.",
            "Setelah produk disimpan, produk tidak langsung tampil pada katalog. Produk masuk ke status menunggu validasi agar admin atau validator dapat memeriksa kelayakan data sebelum produk dilihat pembeli.",
        ]),
        ("Halaman Lelang Seller", [
            "Halaman lelang seller digunakan penjual untuk mengajukan barang antik yang ingin dijual melalui mekanisme lelang. Penjual mengisi nama barang, deskripsi, foto, harga awal, minimal kenaikan bid, tanggal mulai, dan tanggal selesai.",
            "Barang lelang yang diajukan seller juga melewati proses validasi admin. Validasi ini diperlukan agar barang yang tampil pada halaman lelang memenuhi ketentuan sistem dan tidak langsung dipublikasikan tanpa pemeriksaan.",
            "Kode tampilan tambah, edit, dan relist lelang berada pada resources/js/pages/seller/auctions/create.jsx, edit.jsx, dan relist.jsx. Seluruh proses penyimpanan ditangani oleh AuctionController, termasuk validasi tanggal mulai, tanggal selesai, gambar, harga awal, minimal kenaikan, serta pengaturan status awal approval_status=pending dan status=pending.",
            "Apabila lelang telah selesai tanpa adanya bid, seller dapat mengajukan ulang lelang dengan jadwal baru. Fitur ini membantu seller tetap dapat menawarkan barangnya tanpa harus membuat data lelang dari awal.",
        ]),
        ("Halaman Barter", [
            "Halaman barter digunakan seller untuk mengajukan pertukaran produk dengan seller lain. Seller dapat memilih produk yang dimiliki dan produk milik seller lain yang ingin diajukan sebagai objek barter.",
            "Pemilik produk dapat menerima atau menolak pengajuan barter. Dengan demikian, sistem tetap memberikan kontrol kepada pemilik barang sebelum pertukaran diproses.",
            "Tampilan barter berada pada resources/js/pages/seller/barter/index.jsx, sedangkan proses pengajuan, penerimaan, penolakan, dan pembatalan barter ditangani oleh BarterController. Saat barter disetujui, controller menjalankan transaksi database untuk menukar store_id kedua produk dan menonaktifkan status barterable pada produk yang sudah berpindah kepemilikan.",
            "Fitur barter menjadi pembeda sistem Vinstore karena transaksi barang antik tidak selalu bergantung pada pembelian langsung. Beberapa pengguna dapat memiliki ketertarikan untuk menukar barang berdasarkan nilai historis atau koleksi.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Tampilan Validator", "Heading2"))
    for title, desc in [
        ("Dashboard Validator", [
            "Dashboard validator menampilkan daftar produk barang antik yang diajukan oleh seller dan masih menunggu proses validasi. Melalui halaman ini validator dapat melihat ringkasan produk, nama toko pengaju, status validasi, dan aksi untuk membuka detail produk.",
            "Pemisahan peran validator dari admin bertujuan agar proses pemeriksaan produk dapat dilakukan oleh aktor yang fokus pada kelayakan barang. Dengan adanya validator, admin tidak harus menangani seluruh proses operasional secara langsung.",
            "Kode dashboard validator berada pada resources/js/pages/validator/dashboard.jsx dan datanya disiapkan oleh ValidatorController. Controller mengambil produk dengan approval_status pending_validator serta riwayat produk yang sudah divalidasi oleh validator, sehingga halaman dapat membedakan daftar pekerjaan aktif dan riwayat keputusan.",
            "Dashboard validator membantu menjaga kualitas katalog marketplace. Produk yang belum sesuai dapat ditolak, sedangkan produk yang layak dapat disetujui agar tampil pada halaman produk dan dapat dibeli oleh pengguna.",
        ]),
        ("Halaman Detail Validasi Produk", [
            "Halaman detail validasi produk digunakan validator untuk memeriksa informasi produk secara lebih lengkap. Data yang ditampilkan meliputi nama produk, kategori, deskripsi, harga, stok, gambar, sertifikat, dan data toko.",
            "Pada halaman ini validator dapat menilai apakah informasi produk sudah lengkap dan sesuai. Jika data produk dianggap valid, validator dapat menyetujui produk agar tampil pada marketplace.",
            "Proses approve dan reject pada halaman detail validasi tetap melewati ValidatorController. Ketika validator menyetujui produk, status berubah dari pending_validator menjadi pending_admin dan sistem mencatat validated_by serta validated_at. Jika ditolak, sistem menyimpan rejection_reason agar seller mengetahui alasan penolakan.",
            "Apabila produk tidak memenuhi ketentuan, validator dapat menolak produk dengan alasan tertentu. Alasan penolakan ini penting agar seller mengetahui bagian yang perlu diperbaiki sebelum produk diajukan kembali.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Tampilan Admin", "Heading2"))
    for title, desc in [
        ("Dashboard Admin", [
            "Dashboard admin menjadi pusat pengelolaan keseluruhan sistem. Admin dapat mengakses pengelolaan pengguna, toko, kategori, produk, pesanan, lelang, refund, bantuan, dan penarikan saldo.",
            "Halaman ini digunakan untuk memantau aktivitas marketplace secara umum. Dengan adanya menu yang terpisah, admin dapat mengelola data sesuai jenisnya tanpa mencampurkan data transaksi, data master, dan data permintaan pengguna.",
            "Kode dashboard admin berada pada resources/js/pages/admin/dashboard.jsx dan data ringkasannya berasal dari AdminDashboardController. Controller menghitung total pengguna, seller, produk pending, order, refund, dan permintaan lain agar admin mendapatkan gambaran kondisi sistem pada satu halaman.",
            "Peran admin diperlukan untuk menjaga keberlangsungan sistem, terutama pada proses validasi, penanganan komplain, pengelolaan kategori, dan pengawasan transaksi.",
        ]),
        ("Halaman Validasi Produk", [
            "Halaman validasi produk pada admin digunakan untuk memantau produk yang diajukan penjual sebelum tampil pada marketplace. Admin dapat melihat daftar produk pending, detail produk, dan status validasi produk.",
            "Meskipun validator memiliki halaman validasi khusus, admin tetap dapat memantau proses ini sebagai pengelola utama sistem. Hal tersebut memberi fleksibilitas apabila admin perlu mengambil alih proses validasi.",
            "Kode tampilan validasi produk admin berada pada resources/js/pages/admin/products/pending.jsx dan proses persetujuannya berada pada AdminProductController. Admin hanya memproses produk yang sudah berstatus pending_admin, kemudian mengubah status menjadi approved atau rejected sesuai hasil pemeriksaan akhir.",
        ]),
        ("Halaman Validasi Lelang", [
            "Halaman validasi lelang digunakan admin untuk menyetujui atau menolak barang lelang yang diajukan seller. Validasi lelang diperlukan karena barang yang dilelang dapat memiliki nilai tinggi dan membutuhkan informasi yang jelas.",
            "Admin dapat memeriksa nama barang, deskripsi, foto, harga awal, minimal kenaikan bid, tanggal mulai, dan tanggal selesai. Setelah disetujui, lelang dapat berjalan sesuai jadwal yang ditentukan.",
            "Tampilan validasi lelang berada pada resources/js/pages/admin/auctions/index.jsx, sedangkan aksi approve dan reject ditangani oleh AuctionController. Ketika disetujui, sistem menentukan status lelang menjadi scheduled apabila waktu mulai masih di masa depan atau active apabila periode lelang sudah berjalan.",
        ]),
        ("Halaman Kelola Refund", [
            "Halaman kelola refund digunakan admin untuk meninjau pengajuan pengembalian dana dari pembeli. Pengajuan refund memuat alasan, catatan tambahan, serta foto bukti yang diunggah pengguna.",
            "Admin dapat menyetujui atau menolak pengajuan refund berdasarkan informasi yang diterima. Fitur ini membantu sistem menangani kendala transaksi secara lebih terstruktur.",
            "Kode halaman refund admin berada pada resources/js/pages/admin/refunds/index.jsx dan proses review ditangani oleh RefundRequestController. Ketika refund disetujui, sistem memperbarui status refund dan status pembayaran order menjadi refunded sehingga riwayat transaksi tetap tercatat.",
        ]),
        ("Halaman Kelola Penarikan", [
            "Halaman kelola penarikan digunakan admin untuk memproses permintaan pencairan saldo dari seller. Penarikan saldo hanya dapat dilakukan setelah dana tersedia sesuai status transaksi.",
            "Tampilan penarikan berada pada resources/js/pages/admin/withdrawals/index.jsx dan proses approve atau reject ditangani oleh WithdrawalRequestController. Controller memastikan status permintaan masih pending sebelum mengubah status pencairan agar permintaan yang sudah diproses tidak diproses ulang.",
            "Dengan adanya halaman ini, sistem dapat mencatat permintaan pencairan, data rekening seller, nominal penarikan, status review, dan catatan admin. Alur ini mendukung transparansi antara seller dan pengelola marketplace.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Pengujian", "Heading2"))
    content.append(paragraph("Blackbox", "Heading3"))
    content.append(paragraph(
        "Pengujian blackbox pada sistem Vinstore dilakukan untuk mengetahui apakah fitur yang telah dibangun berjalan sesuai "
        "dengan kebutuhan fungsional dari sudut pandang pengguna. Pengujian ini tidak menilai struktur kode program secara "
        "langsung, melainkan menilai keluaran sistem berdasarkan masukan dan aksi yang dilakukan oleh aktor pengguna. Pendekatan "
        "ini sesuai dengan karakteristik marketplace karena fitur utama sistem berhubungan erat dengan interaksi pengguna, "
        "seperti registrasi, pengajuan produk, checkout, barter, lelang, dan tebak harga."
    ))
    content.append(paragraph(
        "Pengujian dilakukan menggunakan Playwright dengan browser Chromium terhadap aplikasi Laravel yang berjalan pada server "
        "lokal. Sebelum pengujian dijalankan, data uji disiapkan menggunakan script seed agar setiap skenario memiliki kondisi "
        "awal yang konsisten. Setelah itu, Playwright menjalankan skenario secara otomatis melalui antarmuka web sebagaimana "
        "pengguna berinteraksi dengan sistem. Perintah yang digunakan adalah npm.cmd run test:e2e:seed untuk menyiapkan data "
        "dan npm.cmd run test:e2e untuk menjalankan seluruh pengujian."
    ))
    content.append(paragraph(
        "Lingkungan pengujian yang digunakan adalah aplikasi Vinstore berbasis Laravel, React, dan Inertia.js, database MySQL "
        "lokal, browser Chromium, serta base URL http://127.0.0.1:8001. Pengujian dilaksanakan pada tanggal 18 Juli 2026. "
        "Penggunaan Playwright membantu proses pengujian menjadi lebih konsisten karena setiap langkah pengguna, seperti membuka "
        "halaman, mengisi form, mengunggah file, menekan tombol, dan memeriksa pesan hasil, dapat dijalankan secara otomatis."
    ))
    content.append(paragraph(
        "Pada hasil eksekusi Playwright tercatat 9 pengujian otomatis berhasil dijalankan dalam waktu 2,6 menit. Di dalam tabel "
        "hasil pengujian, skenario ditulis menjadi 10 kode test case karena alur barter terdiri dari dua validasi fungsional, "
        "yaitu pengajuan barter oleh Seller A dan persetujuan barter oleh Seller B, tetapi keduanya dijalankan dalam satu alur "
        "end-to-end yang sama pada script barter.spec.js."
    ))
    content.append(paragraph("Tabel 4.1 Hasil Pengujian Blackbox", "Caption", italic=True, align="center"))
    content.append(table([
        ["Kode", "Fitur", "Skenario Pengujian", "Hasil"],
        ["TC-BA-01", "Barter", "Seller A mengajukan barter terhadap produk milik Seller B.", "Passed"],
        ["TC-BA-02", "Barter", "Seller B menyetujui pengajuan barter dari Seller A.", "Passed"],
        ["TC-JB-01", "Jual Beli", "User baru melakukan registrasi akun.", "Passed"],
        ["TC-JB-02", "Produk Seller", "Seller mengajukan produk baru untuk divalidasi.", "Passed"],
        ["TC-JB-03", "Checkout", "Buyer melakukan pembelian produk dan diarahkan ke Midtrans.", "Passed"],
        ["TC-LE-01", "Lelang", "Seller mengajukan barang lelang baru.", "Passed"],
        ["TC-LE-02", "Lelang", "Buyer mengirim bid dengan nominal valid.", "Passed"],
        ["TC-LE-03", "Lelang", "Sistem menolak bid di bawah minimal kenaikan.", "Passed"],
        ["TC-TH-01", "Tebak Harga", "Harga asli produk tebak harga disembunyikan selama periode aktif.", "Passed"],
        ["TC-TH-02", "Tebak Harga", "Buyer mengirim tebakan harga.", "Passed"],
    ]))
    content.append(paragraph(
        "Berdasarkan tabel hasil pengujian, seluruh skenario yang diuji memperoleh hasil Passed. Pada fitur jual beli, sistem "
        "berhasil melakukan registrasi pengguna, menyimpan pengajuan produk seller, dan menjalankan checkout hingga diarahkan "
        "ke payment gateway Midtrans sandbox. Hal ini menunjukkan bahwa alur dasar marketplace telah berjalan dari sisi pembeli "
        "dan penjual."
    ))
    content.append(paragraph(
        "Pada fitur barter, pengujian menunjukkan bahwa Seller A dapat mengajukan pertukaran produk kepada Seller B dan Seller B "
        "dapat menyetujui pengajuan tersebut. Hasil ini menunjukkan bahwa sistem mampu memproses interaksi antar seller serta "
        "menjalankan perubahan status pengajuan barter sesuai kondisi yang diharapkan."
    ))
    content.append(paragraph(
        "Pada fitur lelang, sistem berhasil menyimpan pengajuan barang lelang, menerima bid dengan nominal valid, dan menolak bid "
        "yang berada di bawah batas minimal kenaikan. Hasil ini membuktikan bahwa aturan bisnis pada proses bidding telah bekerja, "
        "khususnya aturan harga tertinggi dan minimal kenaikan penawaran."
    ))
    content.append(paragraph(
        "Pada fitur tebak harga, sistem berhasil menyembunyikan harga asli produk selama periode tebak harga aktif dan menyimpan "
        "tebakan yang dikirim oleh pembeli. Hasil ini menunjukkan bahwa mekanisme penyembunyian harga dan pembatasan interaksi "
        "pengguna pada fitur tebak harga telah berjalan sesuai rancangan."
    ))
    content.append(paragraph(
        "Dengan hasil 9 pengujian otomatis Passed dan 0 Failed, serta 10 kode skenario fungsional yang tervalidasi, fitur utama "
        "Vinstore dapat dinyatakan berjalan sesuai kebutuhan fungsional yang dirancang. Meskipun demikian, pengujian ini masih "
        "berfokus pada validasi fungsional melalui antarmuka pengguna, sehingga pengujian lain seperti keamanan, performa, dan "
        "usability tetap diperlukan pada pengembangan berikutnya."
    ))
    content.append(paragraph("Pembahasan", "Heading2"))
    content.append(paragraph(
        "Hasil implementasi menunjukkan bahwa sistem Vinstore mampu menyediakan alur transaksi marketplace barang antik yang lebih lengkap "
        "dibandingkan katalog produk biasa. Sistem tidak hanya menyediakan jual beli, tetapi juga validasi produk oleh admin, pembayaran sandbox, "
        "lelang, barter, tebak harga, refund, chat, dan pengelolaan saldo penjual. Penggunaan public_id pada beberapa data utama juga membuat "
        "identitas data yang tampil ke pengguna menjadi lebih aman karena tidak langsung menggunakan id internal database."
    ))
    content.append(paragraph(
        "Meskipun demikian, sistem yang dibangun masih berada pada tahap prototype. Beberapa fitur seperti payment gateway masih menggunakan mode "
        "sandbox, pengiriman barang belum terhubung dengan layanan kurir secara langsung, dan validasi keaslian barang antik masih dilakukan secara "
        "manual oleh admin. Hal tersebut menjadi batasan penelitian sekaligus peluang pengembangan sistem pada tahap berikutnya."
    ))
    content.append(paragraph(
        "Dari sisi alur pengguna, sistem telah membedakan kebutuhan pembeli, seller, validator, dan admin. Pembeli difokuskan pada aktivitas "
        "pencarian produk, checkout, pembayaran, lelang, refund, dan komunikasi. Seller difokuskan pada pengelolaan toko, produk, lelang, barter, "
        "dan saldo. Validator difokuskan pada pemeriksaan produk, sedangkan admin berperan dalam pengelolaan data dan pengawasan keseluruhan sistem."
    ))
    content.append(paragraph(
        "Pembagian peran tersebut membuat sistem lebih terstruktur karena setiap aktor memiliki batasan akses yang jelas. Hal ini penting pada "
        "marketplace barang antik karena transaksi tidak hanya berkaitan dengan jual beli, tetapi juga validasi informasi produk, kepercayaan "
        "pengguna, status pembayaran, dan penyelesaian kendala setelah transaksi."
    ))

    content.append(page_break())
    content.append(paragraph("BAB 5 KESIMPULAN DAN SARAN", "Heading1"))
    content.append(paragraph("Kesimpulan", "Heading2"))
    content.append(paragraph(
        "Berdasarkan hasil perancangan, implementasi, dan pengujian yang telah dilakukan, penelitian ini menghasilkan prototype marketplace "
        "barang antik berbasis web bernama Vinstore. Sistem dikembangkan untuk membantu proses jual beli barang antik secara digital dengan "
        "menambahkan fitur yang sesuai dengan karakteristik barang antik, seperti validasi produk, lelang, barter, tebak harga, lokasi toko, "
        "dan mekanisme pembayaran menggunakan payment gateway sandbox."
    ))
    for item in [
        "Prototype marketplace barang antik berbasis web berhasil dirancang dan dibangun menggunakan Laravel, ReactJS, Inertia.js, Tailwind CSS, MySQL, Midtrans sandbox, dan Playwright.",
        "Sistem Vinstore menyediakan fitur utama marketplace, yaitu registrasi dan login, manajemen produk, validasi produk oleh admin, katalog produk, checkout, pembayaran, riwayat pesanan, refund, saldo penjual, dan penarikan saldo.",
        "Sistem juga menyediakan fitur khusus untuk transaksi barang antik, yaitu lelang, riwayat bid, barter, tebak harga, lokasi toko, serta chat antara pembeli dan penjual.",
        "Penggunaan public_id pada data utama seperti user, order, store, category, product, cart, dan data transaksi membuat identitas yang tampil kepada pengguna lebih aman dibandingkan menampilkan id internal database.",
        "Berdasarkan pengujian blackbox menggunakan Playwright, 9 pengujian otomatis berhasil dijalankan dengan hasil 9 passed dan 0 failed, serta merepresentasikan 10 kode skenario fungsional pada fitur utama Vinstore.",
    ]:
        content.append(paragraph(item, "ListParagraph"))
    content.append(paragraph(
        "Dengan demikian, sistem yang dibangun telah memenuhi tujuan penelitian pada tahap prototype. Walaupun belum digunakan sebagai sistem "
        "produksi penuh, Vinstore telah menunjukkan rancangan dan implementasi awal marketplace barang antik yang memiliki alur transaksi, "
        "validasi, dan pengujian yang dapat dikembangkan lebih lanjut."
    ))
    content.append(paragraph("Saran", "Heading2"))
    content.append(paragraph(
        "Berdasarkan keterbatasan sistem dan hasil pengujian, terdapat beberapa saran yang dapat dipertimbangkan untuk pengembangan berikutnya. "
        "Saran ini ditujukan agar sistem dapat ditingkatkan dari sisi keamanan, kelengkapan fitur, kesiapan produksi, serta pengalaman pengguna."
    ))
    for item in [
        "Sistem dapat dikembangkan ke mode produksi dengan konfigurasi payment gateway Midtrans production setelah seluruh aspek legalitas dan keamanan transaksi terpenuhi.",
        "Fitur pengiriman dapat dikembangkan dengan integrasi API kurir agar pengguna dapat melihat ongkos kirim, nomor resi, dan pelacakan pengiriman secara real-time.",
        "Validasi barang antik dapat diperkuat dengan mekanisme verifikasi yang lebih detail, misalnya dokumen sertifikat, riwayat kepemilikan, atau peran validator khusus.",
        "Fitur notifikasi dapat ditambahkan melalui email, WhatsApp, atau push notification untuk memberi informasi pembayaran, status pesanan, lelang, refund, dan chat.",
        "Pengujian dapat diperluas dengan performance testing, security testing, dan usability testing agar sistem lebih siap digunakan oleh pengguna nyata.",
        "Dashboard admin dapat dikembangkan dengan laporan transaksi, laporan saldo penjual, grafik penjualan, dan ekspor data untuk kebutuhan monitoring sistem.",
        "Sistem dapat dikembangkan dengan fitur reputasi toko dan ulasan pembeli agar calon pembeli memiliki informasi tambahan sebelum melakukan transaksi.",
        "Fitur keamanan dapat ditingkatkan dengan audit akses, pembatasan percobaan login, validasi file upload yang lebih ketat, dan pencatatan aktivitas penting pada sistem.",
    ]:
        content.append(paragraph(item, "ListParagraph"))
    content.append(page_break())
    content.append(paragraph("DAFTAR REFERENSI", "Heading1"))
    for item in [
        "Akbar, I. S., & Haryanti, T. (2023). Pengembangan Entity Relationship Diagram Database Toko Online Ira Surabaya. Computing Insight: Journal of Computer Science, 3(2). https://journal.um-surabaya.ac.id/CI/article/view/12002",
        "Kirvan, P., Terrell Hanna, K., & Biscobing, J. (2025). What is an entity relationship diagram (ERD)? TechTarget. https://www.techtarget.com/searchdatamanagement/definition/entity-relationship-diagram-ERD",
        "Pressman, R. S., & Maxim, B. R. (2020). Software Engineering: A Practitioner's Approach (9th ed.). McGraw-Hill Education.",
        "Robinson, S., & Heusser, M. (2024). What is black box testing? TechTarget. https://www.techtarget.com/searchsoftwarequality/definition/black-box",
        "Yasar, K. (2022). What is software testing? Definition, types and importance. TechTarget. https://www.techtarget.com/whatis/definition/software-testing",
        "TechTarget Contributor. (2023). What is unit testing? TechTarget. https://www.techtarget.com/searchsoftwarequality/definition/unit-testing",
        "Golian, N., Golian, V., & Afanasieva, I. (2022). Black and white-box unit testing for web applications. Bulletin of National Technical University KhPI. Series: System Analysis, Control and Information Technologies, 1(7), 79-83. https://doi.org/10.20998/2079-0023.2022.01.13",
    ]:
        content.append(paragraph(item, "ListParagraph"))
    return content


def build_bab2_completion():
    content = []
    content.append(paragraph("ERD", "Heading2"))
    content.append(paragraph(
        "Entity Relationship Diagram (ERD) merupakan diagram yang digunakan untuk menggambarkan hubungan antar entitas "
        "dalam basis data. ERD membantu perancang sistem memahami struktur data, atribut yang dimiliki setiap entitas, "
        "serta relasi antar tabel yang akan digunakan pada sistem. Dalam pengembangan aplikasi berbasis database, ERD "
        "berperan sebagai acuan awal sebelum struktur tabel diimplementasikan ke dalam Database Management System "
        "(Akbar & Haryanti, 2023)."
    ))
    content.append(paragraph(
        "ERD umumnya terdiri dari beberapa komponen utama, yaitu entitas, atribut, relasi, dan kardinalitas. Entitas "
        "merepresentasikan objek atau data utama yang disimpan dalam sistem, seperti pengguna, produk, pesanan, toko, "
        "atau transaksi. Atribut menjelaskan informasi yang dimiliki oleh entitas, misalnya nama, email, harga, stok, "
        "status, dan waktu pembuatan data. Relasi menggambarkan hubungan antar entitas, sedangkan kardinalitas menunjukkan "
        "jumlah keterhubungan antar data, seperti one-to-one, one-to-many, atau many-to-many (Kirvan et al., 2025)."
    ))
    content.append(paragraph(
        "Pada penelitian ini, ERD digunakan untuk merancang struktur basis data marketplace barang antik Vinstore. Entitas "
        "utama yang digunakan antara lain users, stores, categories, products, orders, auctions, auction_bids, refund_requests, "
        "withdrawal_requests, conversations, messages, barter_requests, dan price_guesses. Perancangan ERD diperlukan agar "
        "hubungan antara pengguna, toko, produk, transaksi, lelang, bid, refund, dan chat dapat tergambar secara jelas."
    ))
    content.append(paragraph(
        "Dengan adanya ERD, proses implementasi database menjadi lebih terarah karena setiap tabel dan relasinya telah "
        "didefinisikan sebelum sistem dibangun. ERD juga membantu mengurangi risiko duplikasi data, kesalahan relasi, "
        "dan ketidaksesuaian struktur database dengan kebutuhan fitur sistem."
    ))

    content.append(paragraph("Black Box Testing", "Heading2"))
    content.append(paragraph(
        "Black Box Testing merupakan metode pengujian perangkat lunak yang berorientasi pada perilaku eksternal sistem. "
        "Dalam metode ini, penguji tidak perlu mengetahui struktur internal kode program, algoritma, maupun rancangan modul "
        "di dalam aplikasi. Fokus pengujian diarahkan pada hubungan antara masukan, proses yang terlihat dari sisi pengguna, "
        "dan keluaran yang dihasilkan oleh sistem. Dengan demikian, Black Box Testing digunakan untuk menilai apakah fungsi "
        "aplikasi telah sesuai dengan kebutuhan yang telah ditetapkan (Robinson & Heusser, 2024)."
    ))
    content.append(paragraph(
        "Pengujian Black Box memiliki karakteristik utama berupa penyusunan skenario berdasarkan spesifikasi kebutuhan dan "
        "alur penggunaan sistem. Penguji memberikan input tertentu, menjalankan aksi melalui antarmuka atau endpoint aplikasi, "
        "kemudian membandingkan keluaran aktual dengan keluaran yang diharapkan. Apabila keluaran aktual sesuai dengan keluaran "
        "yang diharapkan, maka fitur dapat dinyatakan valid untuk skenario tersebut. Sebaliknya, apabila keluaran tidak sesuai, "
        "maka kondisi tersebut menunjukkan adanya kesalahan fungsional yang perlu diperbaiki."
    ))
    content.append(paragraph(
        "Metode Black Box Testing dapat diterapkan pada berbagai aspek sistem, seperti validasi form, autentikasi pengguna, "
        "otorisasi berdasarkan role, penyimpanan data, perubahan status transaksi, dan respons sistem terhadap kondisi tidak "
        "valid. Pengujian ini sesuai digunakan pada sistem berbasis web karena skenario pengujian dapat dibuat berdasarkan "
        "aktivitas pengguna secara langsung, misalnya login, registrasi, menambahkan produk, checkout, mengajukan bid, atau "
        "mengajukan refund (Yasar, 2022)."
    ))
    content.append(paragraph(
        "Kelebihan Black Box Testing adalah mampu menggambarkan kualitas sistem dari sudut pandang pengguna akhir karena "
        "pengujian dilakukan berdasarkan fungsi yang terlihat dan dapat digunakan. Namun, metode ini juga memiliki keterbatasan "
        "karena tidak dapat memastikan kualitas struktur kode internal, efisiensi algoritma, atau cakupan cabang logika program "
        "secara mendalam. Oleh karena itu, Black Box Testing umumnya digunakan bersama metode pengujian lain untuk memperoleh "
        "hasil evaluasi perangkat lunak yang lebih menyeluruh."
    ))
    content.append(paragraph(
        "Dalam penelitian ini, Black Box Testing digunakan untuk menguji fitur utama marketplace Vinstore dari sisi pengguna "
        "akhir. Fitur yang diuji meliputi registrasi, pengajuan produk oleh seller, checkout produk, barter, lelang, bid, dan "
        "tebak harga. Pengujian dilakukan untuk memastikan setiap fitur menghasilkan output yang sesuai dengan rancangan kebutuhan sistem."
    ))
    content.append(paragraph(
        "Pengujian Black Box pada Vinstore dibantu menggunakan Playwright sebagai alat pengujian end-to-end. Playwright "
        "memungkinkan proses pengujian dilakukan melalui browser secara otomatis sehingga skenario pengguna dapat dijalankan "
        "secara berulang, konsisten, dan terdokumentasi. Dengan pendekatan tersebut, hasil pengujian dapat digunakan sebagai "
        "dasar untuk menilai kesesuaian fungsi sistem terhadap kebutuhan yang telah dirancang."
    ))

    content.append(paragraph("Unit Testing", "Heading2"))
    content.append(paragraph(
        "Unit Testing merupakan metode pengujian perangkat lunak yang dilakukan pada bagian kecil dari sistem atau unit program "
        "tertentu. Unit yang diuji dapat berupa fungsi, method, class, service, atau komponen logika lain yang memiliki tanggung "
        "jawab tertentu dalam sistem (TechTarget Contributor, 2023)."
    ))
    content.append(paragraph(
        "Tujuan Unit Testing adalah memastikan bahwa setiap unit program berjalan sesuai dengan logika yang diharapkan. Dengan "
        "pengujian ini, kesalahan pada bagian kecil sistem dapat ditemukan lebih awal sebelum memengaruhi fitur yang lebih besar. "
        "Unit Testing juga membantu pengembang ketika melakukan perubahan kode, karena hasil pengujian dapat menunjukkan apakah "
        "perubahan tersebut menimbulkan dampak pada logika yang sudah ada (Golian et al., 2022)."
    ))
    content.append(paragraph(
        "Pada sistem marketplace Vinstore, Unit Testing dapat diterapkan pada logika yang memiliki aturan khusus, seperti validasi "
        "nominal bid lelang, sinkronisasi status tebak harga, pengembalian stok ketika order dibatalkan, penghitungan saldo seller, "
        "serta pembatasan hak akses berdasarkan role pengguna. Pengujian unit pada bagian tersebut membantu memastikan bahwa aturan "
        "bisnis berjalan konsisten."
    ))
    content.append(paragraph(
        "Meskipun penelitian ini lebih menekankan pengujian Black Box untuk memvalidasi fitur dari sudut pandang pengguna, konsep "
        "Unit Testing tetap digunakan sebagai landasan teori karena pengujian unit dapat mendukung kualitas kode dan menjaga stabilitas "
        "sistem saat fitur dikembangkan lebih lanjut."
    ))
    return content


def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    with ZipFile(SOURCE_DOCX, "r") as zin:
        entries = {name: zin.read(name) for name in zin.namelist()}

    document = etree.fromstring(entries["word/document.xml"])
    rels = etree.fromstring(entries["word/_rels/document.xml.rels"])
    content_types = etree.fromstring(entries["[Content_Types].xml"])
    body = document.xpath(".//w:body", namespaces=NS)[0]
    sect_pr = body[-1] if body[-1].tag == qn("w:sectPr") else None

    bab2_start_idx = None
    bab2_end_idx = None
    for idx, child in enumerate(body):
        if child.tag == qn("w:p") and paragraph_text(child) == "ERD":
            bab2_start_idx = idx
            continue
        if bab2_start_idx is not None and child.tag == qn("w:p") and paragraph_text(child).startswith("BAB 3"):
            bab2_end_idx = idx
            break
    if bab2_start_idx is not None and bab2_end_idx is not None:
        for child in list(body)[bab2_start_idx:bab2_end_idx]:
            body.remove(child)
        insert_at = bab2_start_idx
        for item in build_bab2_completion():
            body.insert(insert_at, item)
            insert_at += 1

    start_idx = None
    for idx, child in enumerate(body):
        if child.tag == qn("w:p") and paragraph_text(child).startswith("BAB 3"):
            replace_paragraph_text(child, "BAB 3 METODOLOGI PENELITIAN")
            start_idx = idx + 1
            break
    if start_idx is None:
        raise RuntimeError("Tidak menemukan heading 'BAB 3' pada dokumen.")

    end_idx = len(body) - 1 if sect_pr is not None else len(body)
    for child in list(body)[start_idx:end_idx]:
        body.remove(child)

    existing_rids = []
    for rel in rels:
        rid = rel.get("Id") or rel.get(rel_qn("Id"))
        if rid and re.fullmatch(r"rId\d+", rid):
            existing_rids.append(int(rid[3:]))
    next_rid = max(existing_rids or [0]) + 1

    existing_media = set(name for name in entries if name.startswith("word/media/"))
    next_image_number = 1
    while f"word/media/vinstore_bab_image_{next_image_number}.png" in existing_media:
        next_image_number += 1

    insert_at = start_idx
    extra_files = {}
    for item in build_content():
        if isinstance(item, tuple) and item[0] == "image":
            caption, image_path = item[1]
            image_path = Path(image_path)
            if not image_path.exists():
                body.insert(insert_at, paragraph(caption, "Caption", italic=True, align="center"))
                insert_at += 1
                body.insert(insert_at, paragraph(
                    f"Catatan: file gambar {image_path.name} belum tersedia. Render file PlantUML terkait menjadi PNG agar gambar dapat ditampilkan pada dokumen final."
                ))
                insert_at += 1
                continue
            rid = f"rId{next_rid}"
            next_rid += 1
            image_name = f"vinstore_bab_image_{next_image_number}.png"
            next_image_number += 1
            target = f"media/{image_name}"
            extra_files[f"word/{target}"] = image_path.read_bytes()

            rel = etree.Element(rel_qn("Relationship"))
            rel.set("Id", rid)
            rel.set("Type", "http://schemas.openxmlformats.org/officeDocument/2006/relationships/image")
            rel.set("Target", target)
            rels.append(rel)

            body.insert(insert_at, image_paragraph(rid, image_name, image_path))
            insert_at += 1
            body.insert(insert_at, paragraph(caption, "Caption", italic=True, align="center"))
            insert_at += 1
        else:
            body.insert(insert_at, item)
            insert_at += 1

    add_default_content_type(content_types, "png", "image/png")

    entries["word/document.xml"] = etree.tostring(document, xml_declaration=True, encoding="UTF-8", standalone="yes")
    entries["word/_rels/document.xml.rels"] = etree.tostring(rels, xml_declaration=True, encoding="UTF-8", standalone="yes")
    entries["[Content_Types].xml"] = etree.tostring(content_types, xml_declaration=True, encoding="UTF-8", standalone="yes")
    if "word/settings.xml" in entries:
        settings = etree.fromstring(entries["word/settings.xml"])
        update_fields = settings.xpath("./w:updateFields", namespaces=NS)
        if update_fields:
            update_fields[0].set(qn("w:val"), "true")
        else:
            elem = etree.Element(qn("w:updateFields"))
            elem.set(qn("w:val"), "true")
            settings.insert(0, elem)
        entries["word/settings.xml"] = etree.tostring(settings, xml_declaration=True, encoding="UTF-8", standalone="yes")
    entries.update(extra_files)

    with ZipFile(OUTPUT_DOCX, "w", ZIP_DEFLATED) as zout:
        for name, data in entries.items():
            zout.writestr(name, data)
    print(OUTPUT_DOCX)


if __name__ == "__main__":
    main()
