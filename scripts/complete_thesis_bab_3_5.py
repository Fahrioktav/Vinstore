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
    content.append(paragraph("Desain Sistem", "Heading2"))
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
        db_table_section("Tabel 3.1 Users", [
            ["1", "id", "bigint", "Primary key internal pengguna."],
            ["2", "public_id", "varchar", "Kode publik pengguna yang ditampilkan ke sistem."],
            ["3", "username", "varchar", "Username unik pengguna."],
            ["4", "email", "varchar", "Email pengguna untuk login dan reset password."],
            ["5", "google_id", "varchar", "Identitas akun Google jika pengguna login dengan Google."],
            ["6", "role", "varchar", "Hak akses pengguna seperti admin, validator, seller, atau user."],
        ]),
        db_table_section("Tabel 3.2 Stores", [
            ["1", "id", "bigint", "Primary key internal toko."],
            ["2", "public_id", "varchar", "Kode publik toko."],
            ["3", "user_id", "bigint", "Relasi ke pengguna pemilik toko."],
            ["4", "store_name", "varchar", "Nama toko penjual."],
            ["5", "location", "varchar", "Lokasi toko."],
            ["6", "available_balance", "decimal", "Saldo tersedia milik penjual."],
        ]),
        db_table_section("Tabel 3.3 Products", [
            ["1", "id", "bigint", "Primary key internal produk."],
            ["2", "public_id", "varchar", "Kode publik produk."],
            ["3", "store_id", "bigint", "Relasi ke toko pemilik produk."],
            ["4", "name", "varchar", "Nama produk barang antik."],
            ["5", "price", "decimal", "Harga produk."],
            ["6", "approval_status", "varchar", "Status validasi produk."],
        ]),
        db_table_section("Tabel 3.4 Orders", [
            ["1", "id", "bigint", "Primary key internal pesanan."],
            ["2", "public_id", "varchar", "Kode publik order atau invoice."],
            ["3", "user_id", "bigint", "Relasi ke pembeli."],
            ["4", "product_id", "bigint", "Relasi ke produk jika berasal dari pembelian biasa."],
            ["5", "auction_id", "bigint", "Relasi ke lelang jika berasal dari pemenang lelang."],
            ["6", "payment_status", "varchar", "Status pembayaran order."],
        ]),
        db_table_section("Tabel 3.5 Auctions", [
            ["1", "id", "bigint", "Primary key internal lelang."],
            ["2", "public_id", "varchar", "Kode publik lelang."],
            ["3", "store_id", "bigint", "Relasi ke toko pemilik barang lelang."],
            ["4", "starting_price", "decimal", "Harga awal lelang."],
            ["5", "min_increment", "decimal", "Minimal kenaikan bid."],
            ["6", "status", "varchar", "Status lelang."],
        ]),
        db_table_section("Tabel 3.6 Refund Requests", [
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
    content.append(paragraph("Perancangan Antar Muka", "Heading3"))
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
    content.append(paragraph("Pengembangan Sistem", "Heading2"))
    content.append(paragraph("Model Pengembangan Model-View-Controller (MVC)", "Heading3"))
    content.append(paragraph(
        "Sistem dikembangkan menggunakan pola Model-View-Controller. Model digunakan untuk mengelola data dan relasi database, "
        "controller menangani proses bisnis seperti validasi produk, checkout, lelang, refund, dan chat, sedangkan tampilan dibangun "
        "menggunakan ReactJS melalui Inertia.js."
    ))
    content.append(paragraph("Bahasa Pemrograman dan Teknologi Pendukung", "Heading3"))
    content.append(paragraph("Tabel 3.7 Teknologi Pendukung", "Caption", italic=True, align="center"))
    content.append(table([
        ["Komponen", "Teknologi yang Digunakan", "Fungsi Utama"],
        ["Backend", "Laravel", "Mengelola route, controller, model, migration, validasi, dan proses bisnis."],
        ["Frontend", "ReactJS dan Inertia.js", "Membangun antarmuka pengguna berbasis web."],
        ["Styling", "Tailwind CSS", "Membuat tampilan responsif dan konsisten."],
        ["Database", "MySQL", "Menyimpan data pengguna, toko, produk, pesanan, lelang, refund, dan chat."],
        ["Payment Gateway", "Midtrans Sandbox", "Simulasi pembayaran online."],
        ["Testing", "Playwright", "Menjalankan pengujian blackbox berbasis browser."],
    ]))
    content.append(paragraph("Pengujian dan Penyelesaian", "Heading2"))
    content.append(paragraph(
        "Rancangan pengujian dilakukan menggunakan metode blackbox testing. Pengujian berfokus pada masukan dan keluaran sistem "
        "dari sudut pandang pengguna akhir tanpa melihat struktur kode program."
    ))
    content.append(paragraph("Tabel 3.8 Rancangan Pengujian Blackbox", "Caption", italic=True, align="center"))
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
            "Halaman ini juga berfungsi sebagai pintu eksplorasi awal. Pengguna dapat melihat gambaran produk yang tersedia sebelum memilih untuk membuka halaman katalog, detail produk, atau fitur lelang.",
        ]),
        ("Halaman Produk", [
            "Halaman produk menampilkan katalog barang antik yang telah melewati proses persetujuan. Informasi yang ditampilkan meliputi gambar produk, nama produk, kategori, harga, stok, dan tombol aksi untuk membuka detail produk.",
            "Fitur filter produk digunakan untuk membantu pengguna menemukan barang sesuai kebutuhan. Pengguna dapat menelusuri produk berdasarkan kategori atau kata kunci tertentu sehingga proses pencarian menjadi lebih efektif.",
            "Implementasi halaman produk menunjukkan bahwa sistem tidak hanya berfungsi sebagai daftar barang, tetapi juga sebagai media pencarian dan perbandingan produk bagi pembeli sebelum melakukan transaksi.",
        ]),
        ("Halaman Detail Produk", [
            "Halaman detail produk digunakan untuk menampilkan informasi produk secara lebih lengkap. Informasi yang disajikan meliputi nama produk, deskripsi, harga, stok, gambar, sertifikat apabila tersedia, serta informasi toko penjual.",
            "Pada halaman ini pengguna dapat menentukan tindakan berikutnya, seperti membeli produk, mengajukan barter, atau menghubungi penjual melalui fitur chat. Dengan demikian, halaman detail produk menjadi titik penting sebelum transaksi dilakukan.",
            "Kelengkapan informasi pada halaman detail produk menjadi bagian penting dalam marketplace barang antik karena pembeli membutuhkan deskripsi yang jelas untuk menilai kondisi, keunikan, dan nilai produk.",
        ]),
        ("Halaman Checkout dan Pembayaran", [
            "Halaman checkout menampilkan ringkasan pembelian sebelum pengguna diarahkan ke proses pembayaran. Data yang ditampilkan meliputi produk yang dibeli, jumlah pembelian, harga, dan total pembayaran.",
            "Setelah pengguna melanjutkan checkout, sistem membuat data order dan menghubungkannya dengan Midtrans sandbox. Penggunaan Midtrans sandbox memungkinkan proses pembayaran diuji tanpa menggunakan transaksi uang nyata.",
            "Implementasi halaman checkout juga berkaitan dengan pengelolaan stok. Sistem perlu memastikan stok produk berkurang ketika order dibuat dan dapat dikembalikan apabila order dibatalkan sebelum pembayaran selesai.",
        ]),
        ("Halaman Riwayat Order", [
            "Halaman riwayat order digunakan pembeli untuk memantau transaksi yang pernah dibuat. Pada halaman ini pengguna dapat melihat status pesanan, status pembayaran, tanggal transaksi, invoice, dan aksi yang dapat dilakukan.",
            "Apabila pembayaran belum selesai, sistem dapat menampilkan tombol bayar. Namun apabila pembayaran sudah berhasil, tombol bayar tidak lagi ditampilkan sehingga tampilan sesuai dengan status transaksi.",
            "Halaman ini juga mendukung pengajuan refund apabila transaksi memenuhi syarat. Pengguna dapat memilih alasan refund, menulis alasan tambahan, serta mengunggah foto bukti untuk membantu proses review admin.",
        ]),
        ("Halaman Lelang", [
            "Halaman lelang menampilkan daftar barang antik yang dilelang oleh penjual dan telah disetujui admin. Informasi yang ditampilkan meliputi harga awal, harga tertinggi saat ini, jumlah penawar, sisa waktu, dan status lelang.",
            "Pada detail lelang, pengguna dapat mengajukan penawaran dengan nominal yang harus lebih tinggi dari bid terakhir sesuai minimal kenaikan. Sistem menolak penawaran yang tidak memenuhi aturan agar proses lelang tetap valid.",
            "Riwayat bid ditampilkan agar proses lelang lebih transparan. Dengan adanya riwayat ini, pengguna dapat melihat perkembangan penawaran yang telah terjadi selama periode lelang berlangsung.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Tampilan Seller", "Heading2"))
    for title, desc in [
        ("Dashboard Seller", [
            "Dashboard seller menampilkan ringkasan aktivitas toko, seperti daftar produk, pesanan, lelang, barter, saldo tersedia, dan fitur penarikan dana. Halaman ini menjadi pusat kontrol bagi penjual untuk memantau aktivitas bisnisnya.",
            "Pada dashboard seller, penjual dapat melihat status produk yang telah diajukan. Produk dapat berada pada status pending, approved, atau rejected sesuai hasil validasi admin atau validator.",
            "Selain itu, dashboard seller mendukung pemantauan saldo penjual. Dana dari transaksi tidak langsung dapat dicairkan sebelum pesanan selesai, sehingga alur ini mendukung konsep escrow sederhana pada sistem marketplace.",
        ]),
        ("Halaman Tambah Produk", [
            "Halaman tambah produk digunakan penjual untuk mengajukan produk barang antik baru. Form yang tersedia meliputi nama produk, deskripsi, kategori, harga, stok, gambar, dan sertifikat apabila tersedia.",
            "Kategori produk diambil dari data kategori yang dibuat oleh admin. Hal ini bertujuan agar data kategori lebih konsisten dan tidak dibuat bebas oleh setiap seller.",
            "Setelah produk disimpan, produk tidak langsung tampil pada katalog. Produk masuk ke status menunggu validasi agar admin atau validator dapat memeriksa kelayakan data sebelum produk dilihat pembeli.",
        ]),
        ("Halaman Lelang Seller", [
            "Halaman lelang seller digunakan penjual untuk mengajukan barang antik yang ingin dijual melalui mekanisme lelang. Penjual mengisi nama barang, deskripsi, foto, harga awal, minimal kenaikan bid, tanggal mulai, dan tanggal selesai.",
            "Barang lelang yang diajukan seller juga melewati proses validasi admin. Validasi ini diperlukan agar barang yang tampil pada halaman lelang memenuhi ketentuan sistem dan tidak langsung dipublikasikan tanpa pemeriksaan.",
            "Apabila lelang telah selesai tanpa adanya bid, seller dapat mengajukan ulang lelang dengan jadwal baru. Fitur ini membantu seller tetap dapat menawarkan barangnya tanpa harus membuat data lelang dari awal.",
        ]),
        ("Halaman Barter", [
            "Halaman barter digunakan seller untuk mengajukan pertukaran produk dengan seller lain. Seller dapat memilih produk yang dimiliki dan produk milik seller lain yang ingin diajukan sebagai objek barter.",
            "Pemilik produk dapat menerima atau menolak pengajuan barter. Dengan demikian, sistem tetap memberikan kontrol kepada pemilik barang sebelum pertukaran diproses.",
            "Fitur barter menjadi pembeda sistem Vinstore karena transaksi barang antik tidak selalu bergantung pada pembelian langsung. Beberapa pengguna dapat memiliki ketertarikan untuk menukar barang berdasarkan nilai historis atau koleksi.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Tampilan Validator", "Heading2"))
    for title, desc in [
        ("Dashboard Validator", [
            "Dashboard validator menampilkan daftar produk barang antik yang diajukan oleh seller dan masih menunggu proses validasi. Melalui halaman ini validator dapat melihat ringkasan produk, nama toko pengaju, status validasi, dan aksi untuk membuka detail produk.",
            "Pemisahan peran validator dari admin bertujuan agar proses pemeriksaan produk dapat dilakukan oleh aktor yang fokus pada kelayakan barang. Dengan adanya validator, admin tidak harus menangani seluruh proses operasional secara langsung.",
            "Dashboard validator membantu menjaga kualitas katalog marketplace. Produk yang belum sesuai dapat ditolak, sedangkan produk yang layak dapat disetujui agar tampil pada halaman produk dan dapat dibeli oleh pengguna.",
        ]),
        ("Halaman Detail Validasi Produk", [
            "Halaman detail validasi produk digunakan validator untuk memeriksa informasi produk secara lebih lengkap. Data yang ditampilkan meliputi nama produk, kategori, deskripsi, harga, stok, gambar, sertifikat, dan data toko.",
            "Pada halaman ini validator dapat menilai apakah informasi produk sudah lengkap dan sesuai. Jika data produk dianggap valid, validator dapat menyetujui produk agar tampil pada marketplace.",
            "Apabila produk tidak memenuhi ketentuan, validator dapat menolak produk dengan alasan tertentu. Alasan penolakan ini penting agar seller mengetahui bagian yang perlu diperbaiki sebelum produk diajukan kembali.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Tampilan Admin", "Heading2"))
    for title, desc in [
        ("Dashboard Admin", [
            "Dashboard admin menjadi pusat pengelolaan keseluruhan sistem. Admin dapat mengakses pengelolaan pengguna, toko, kategori, produk, pesanan, lelang, refund, bantuan, dan penarikan saldo.",
            "Halaman ini digunakan untuk memantau aktivitas marketplace secara umum. Dengan adanya menu yang terpisah, admin dapat mengelola data sesuai jenisnya tanpa mencampurkan data transaksi, data master, dan data permintaan pengguna.",
            "Peran admin diperlukan untuk menjaga keberlangsungan sistem, terutama pada proses validasi, penanganan komplain, pengelolaan kategori, dan pengawasan transaksi.",
        ]),
        ("Halaman Validasi Produk", [
            "Halaman validasi produk pada admin digunakan untuk memantau produk yang diajukan penjual sebelum tampil pada marketplace. Admin dapat melihat daftar produk pending, detail produk, dan status validasi produk.",
            "Meskipun validator memiliki halaman validasi khusus, admin tetap dapat memantau proses ini sebagai pengelola utama sistem. Hal tersebut memberi fleksibilitas apabila admin perlu mengambil alih proses validasi.",
        ]),
        ("Halaman Validasi Lelang", [
            "Halaman validasi lelang digunakan admin untuk menyetujui atau menolak barang lelang yang diajukan seller. Validasi lelang diperlukan karena barang yang dilelang dapat memiliki nilai tinggi dan membutuhkan informasi yang jelas.",
            "Admin dapat memeriksa nama barang, deskripsi, foto, harga awal, minimal kenaikan bid, tanggal mulai, dan tanggal selesai. Setelah disetujui, lelang dapat berjalan sesuai jadwal yang ditentukan.",
        ]),
        ("Halaman Kelola Refund", [
            "Halaman kelola refund digunakan admin untuk meninjau pengajuan pengembalian dana dari pembeli. Pengajuan refund memuat alasan, catatan tambahan, serta foto bukti yang diunggah pengguna.",
            "Admin dapat menyetujui atau menolak pengajuan refund berdasarkan informasi yang diterima. Fitur ini membantu sistem menangani kendala transaksi secara lebih terstruktur.",
        ]),
        ("Halaman Kelola Penarikan", [
            "Halaman kelola penarikan digunakan admin untuk memproses permintaan pencairan saldo dari seller. Penarikan saldo hanya dapat dilakukan setelah dana tersedia sesuai status transaksi.",
            "Dengan adanya halaman ini, sistem dapat mencatat permintaan pencairan, data rekening seller, nominal penarikan, status review, dan catatan admin. Alur ini mendukung transparansi antara seller dan pengelola marketplace.",
        ]),
    ]:
        append_subsection(content, title, desc)
    content.append(paragraph("Pengujian", "Heading2"))
    content.append(paragraph("Blackbox", "Heading3"))
    content.append(paragraph(
        "Pengujian blackbox dilakukan menggunakan Playwright dengan browser Chromium terhadap aplikasi Laravel yang berjalan secara lokal. "
        "Pengujian dilakukan pada tanggal 18 Juli 2026 terhadap sembilan skenario utama. Hasil pengujian menunjukkan seluruh skenario berhasil dijalankan."
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
        "Berdasarkan hasil pengujian, total skenario yang diuji berjumlah sembilan test case dengan hasil sembilan berhasil dan nol gagal. "
        "Dengan demikian, fitur utama yang diuji dapat dinyatakan berjalan sesuai kebutuhan fungsional yang telah dirancang."
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
        "Berdasarkan pengujian blackbox menggunakan Playwright, sembilan skenario pengujian utama berhasil dijalankan dengan hasil sembilan passed dan nol failed, sehingga fitur yang diuji dapat dinyatakan berjalan sesuai skenario.",
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
        "Black Box Testing merupakan metode pengujian perangkat lunak yang berfokus pada fungsi sistem berdasarkan input "
        "dan output tanpa melihat struktur kode program di dalamnya. Penguji hanya memperhatikan apakah sistem memberikan "
        "hasil yang sesuai dengan kebutuhan ketika pengguna menjalankan suatu fitur (Robinson & Heusser, 2024)."
    ))
    content.append(paragraph(
        "Metode Black Box Testing dapat digunakan untuk menguji validasi form, alur transaksi, hak akses pengguna, proses "
        "penyimpanan data, dan respons sistem terhadap kondisi tertentu. Pengujian ini sesuai digunakan pada sistem berbasis "
        "web karena skenario pengujian dapat disusun berdasarkan aktivitas pengguna secara langsung, seperti login, registrasi, "
        "menambahkan produk, checkout, mengajukan bid, atau mengajukan refund (Yasar, 2022)."
    ))
    content.append(paragraph(
        "Dalam penelitian ini, Black Box Testing digunakan untuk menguji fitur utama marketplace Vinstore dari sisi pengguna "
        "akhir. Fitur yang diuji meliputi registrasi, pengajuan produk oleh seller, checkout produk, barter, lelang, bid, dan "
        "tebak harga. Pengujian dilakukan untuk memastikan setiap fitur menghasilkan output yang sesuai dengan rancangan kebutuhan sistem."
    ))
    content.append(paragraph(
        "Pengujian Black Box pada Vinstore dibantu menggunakan Playwright. Playwright memungkinkan proses pengujian dilakukan "
        "melalui browser secara otomatis sehingga skenario pengguna dapat dijalankan secara berulang dan lebih konsisten. "
        "Dengan demikian, pengujian dapat membantu memastikan alur utama sistem berjalan sesuai dengan kebutuhan fungsional."
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
        if child.tag == qn("w:p") and paragraph_text(child) == "Perancangan Sistem":
            start_idx = idx
            break
    if start_idx is None:
        raise RuntimeError("Tidak menemukan heading 'Perancangan Sistem' pada dokumen.")

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
