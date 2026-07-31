from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import re
import struct

from lxml import etree


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DOCX = Path(
    r"C:\Users\fahri\OneDrive\Documents\JO\Kuliah\Semester 8\Tugas Akhir\1152200016 - TUGAS AKHIR FAHRI OCTAVIAN FINAL.docx"
)
OUTPUT_DOCX = ROOT / "docs" / "skripsi" / "1152200016 - TUGAS AKHIR FAHRI OCTAVIAN FINAL - BAB 4 Penjelasan Gambar Kode V3.docx"
TAMBAH_PRODUK_CODE_IMAGE = Path(r"C:\Users\fahri\Downloads\Tambah Produk.png")

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


def paragraph_text(p):
    return "".join(p.xpath(".//w:t/text()", namespaces=NS)).strip()


def normalize(text):
    return re.sub(r"\s+", " ", text).strip()


def paragraph(text):
    p = etree.Element(qn("w:p"))
    r = etree.SubElement(p, qn("w:r"))
    t = etree.SubElement(r, qn("w:t"))
    t.text = text
    return p


def add_default_content_type(types_root, extension, content_type):
    xpath = f"./ct:Default[@Extension='{extension}']"
    if not types_root.xpath(xpath, namespaces={"ct": CONTENT_NS}):
        elem = etree.Element(ct_qn("Default"))
        elem.set("Extension", extension)
        elem.set("ContentType", content_type)
        types_root.insert(0, elem)


def image_size(path):
    data = Path(path).read_bytes()
    if data.startswith(b"\x89PNG\r\n\x1a\n"):
        return struct.unpack(">II", data[16:24])
    raise ValueError(f"Unsupported image format: {path}")


def image_paragraph(rid, image_name, image_path, max_width_inches=5.8):
    width_px, height_px = image_size(image_path)
    width_emu = int(max_width_inches * 914400)
    height_emu = int(width_emu * height_px / width_px)

    p = etree.Element(qn("w:p"))
    ppr = etree.SubElement(p, qn("w:pPr"))
    jc = etree.SubElement(ppr, qn("w:jc"))
    jc.set(qn("w:val"), "center")
    r = etree.SubElement(p, qn("w:r"))
    drawing = etree.SubElement(r, qn("w:drawing"))
    inline = etree.SubElement(drawing, qn("wp:inline"))
    extent = etree.SubElement(inline, qn("wp:extent"))
    extent.set("cx", str(width_emu))
    extent.set("cy", str(height_emu))
    effect_extent = etree.SubElement(inline, qn("wp:effectExtent"))
    for key in ("l", "t", "r", "b"):
        effect_extent.set(key, "0")
    doc_pr = etree.SubElement(inline, qn("wp:docPr"))
    doc_pr.set("id", "1")
    doc_pr.set("name", image_name)
    graphic = etree.SubElement(inline, qn("a:graphic"))
    graphic_data = etree.SubElement(graphic, qn("a:graphicData"))
    graphic_data.set("uri", "http://schemas.openxmlformats.org/drawingml/2006/picture")
    pic = etree.SubElement(graphic_data, qn("pic:pic"))
    nv_pic_pr = etree.SubElement(pic, qn("pic:nvPicPr"))
    c_nv_pr = etree.SubElement(nv_pic_pr, qn("pic:cNvPr"))
    c_nv_pr.set("id", "0")
    c_nv_pr.set("name", image_name)
    etree.SubElement(nv_pic_pr, qn("pic:cNvPicPr"))
    blip_fill = etree.SubElement(pic, qn("pic:blipFill"))
    blip = etree.SubElement(blip_fill, qn("a:blip"))
    blip.set(qn("r:embed"), rid)
    stretch = etree.SubElement(blip_fill, qn("a:stretch"))
    etree.SubElement(stretch, qn("a:fillRect"))
    sp_pr = etree.SubElement(pic, qn("pic:spPr"))
    xfrm = etree.SubElement(sp_pr, qn("a:xfrm"))
    off = etree.SubElement(xfrm, qn("a:off"))
    off.set("x", "0")
    off.set("y", "0")
    ext = etree.SubElement(xfrm, qn("a:ext"))
    ext.set("cx", str(width_emu))
    ext.set("cy", str(height_emu))
    prst_geom = etree.SubElement(sp_pr, qn("a:prstGeom"))
    prst_geom.set("prst", "rect")
    etree.SubElement(prst_geom, qn("a:avLst"))
    return p


EXPLANATIONS = {
    "Gambar 4.1. Halaman Register Akun": [
        "Gambar 4.1 menunjukkan halaman register yang digunakan pengguna untuk membuat akun baru pada sistem Vinstore. Form register mengumpulkan identitas dasar pengguna, seperti username, nama, email, nomor telepon, password, dan alamat, sehingga data pengguna dapat tersimpan dan digunakan pada proses transaksi.",
    ],
    "Gambar 4.2. Kode RegisterController": [
        "Gambar 4.2 menunjukkan potongan kode RegisterController yang menangani proses pendaftaran akun. Kode tersebut melakukan validasi data input, memastikan email atau username tidak digunakan oleh akun lain, mengenkripsi password, kemudian menyimpan data pengguna ke tabel users. Setelah proses berhasil, sistem memberikan respons atau mengarahkan pengguna sesuai alur registrasi.",
    ],
    "Gambar 4. 3. Halaman Login": [
        "Gambar 4.3 menunjukkan halaman login yang digunakan pengguna terdaftar untuk masuk ke sistem. Halaman ini menyediakan input username atau email dan password, serta opsi login Google dan reset password agar proses autentikasi lebih fleksibel.",
    ],
    "Gambar 4. 4. Potongan Kode Auth": [
        "Gambar 4.4 menunjukkan potongan kode autentikasi yang berfungsi memeriksa kredensial pengguna. Sistem memvalidasi input login, mencocokkan data dengan akun yang tersimpan, membuat session ketika login berhasil, dan mengarahkan pengguna ke halaman sesuai role, seperti user, seller, validator, atau admin.",
    ],
    "Gambar 4. 5. Halaman Beranda": [
        "Gambar 4.5 menunjukkan halaman beranda sebagai tampilan awal marketplace. Pada halaman ini, sistem menampilkan produk populer, kategori, dan navigasi utama agar pengguna dapat mulai mengeksplorasi produk, toko, maupun fitur lelang.",
    ],
    "Gambar 4. 6. Halaman Produk": [
        "Gambar 4.6 menunjukkan halaman katalog produk yang berisi daftar barang antik yang telah disetujui. Halaman ini dilengkapi informasi produk dan fitur pencarian atau filter untuk mempermudah pengguna menemukan barang berdasarkan kebutuhan.",
    ],
    "Gambar 4. 7. Halaman Detail Produk": [
        "Gambar 4.7 menunjukkan halaman detail produk yang menampilkan informasi lebih lengkap dibandingkan katalog. Informasi tersebut membantu pembeli menilai produk sebelum melakukan aksi lanjutan, seperti memasukkan produk ke keranjang atau melakukan pembelian langsung.",
    ],
    "Gambar 4. 8. Halaman Checkout": [
        "Gambar 4.8 menunjukkan halaman checkout yang digunakan untuk meninjau pesanan sebelum pembayaran. Pada halaman ini, sistem menampilkan ringkasan produk, jumlah pembelian, alamat pengiriman, metode pengiriman, dan total pembayaran.",
    ],
    "Gambar 4. 9. Potongan Kode Checkout": [
        "Gambar 4.9 menunjukkan potongan kode proses checkout. Kode tersebut memvalidasi kelayakan produk, memastikan stok mencukupi, membuat data order, mengurangi stok produk, membuat payment_reference, serta menghubungkan transaksi dengan Midtrans sandbox agar pembeli dapat melanjutkan pembayaran.",
    ],
    "Gambar 4. 10. Halaman Riwayat Order": [
        "Gambar 4.10 menunjukkan halaman riwayat order yang digunakan pembeli untuk memantau status transaksi. Sistem menampilkan status pesanan dan status pembayaran secara dinamis, sehingga tombol aksi seperti bayar, invoice, pembatalan, atau refund hanya muncul sesuai kondisi transaksi.",
    ],
    "Gambar 4. 11. Halaman Lelang": [
        "Gambar 4.11 menunjukkan halaman lelang yang menampilkan daftar barang antik yang dapat ditawar oleh pengguna. Informasi harga tertinggi, jumlah bid, dan waktu berakhir lelang membantu pengguna memahami kondisi lelang sebelum mengajukan penawaran.",
    ],
    "Gambar 4. 12. Potongan Kode Auction": [
        "Gambar 4.12 menunjukkan potongan kode AuctionController yang mengatur proses lelang. Kode tersebut menangani pengajuan barang lelang, persetujuan admin, validasi nominal bid, pembaruan harga tertinggi, finalisasi lelang, penentuan pemenang, dan pembuatan order untuk pemenang lelang.",
    ],
    "Gambar 4. 13. Halaman Tebak Harga": [
        "Gambar 4.13 menunjukkan halaman tebak harga, yaitu fitur ketika harga asli produk disembunyikan selama periode tertentu. Pengguna hanya dapat mengirim satu tebakan, sehingga sistem dapat menjaga keadilan dalam proses penentuan pemenang.",
    ],
    "Gambar 4. 14. Potongan Kode PriceGuest": [
        "Gambar 4.14 menunjukkan potongan kode fitur tebak harga. Kode tersebut memvalidasi nominal tebakan, memastikan pengguna belum pernah menebak produk yang sama, menyimpan data tebakan, serta menggunakan service untuk menyinkronkan status periode tebak harga.",
    ],
    "Gambar 4. 15. Halaman Dashboard Seller": [
        "Gambar 4.15 menunjukkan dashboard seller sebagai pusat pengelolaan aktivitas toko. Dashboard ini membantu seller memantau produk, order, lelang, saldo, dan performa toko dalam satu halaman.",
    ],
    "Gambar 4. 16. Halaman Tambah Produk": [
        "Gambar 4.16 menunjukkan halaman tambah produk yang digunakan seller untuk mengajukan barang antik baru. Data produk yang dikirim melalui form akan masuk ke proses validasi, sehingga produk belum langsung tampil sebelum diperiksa oleh validator dan admin.",
    ],
    "Gambar 4.16.1. Potongan Kode Tambah Produk": [
        "Gambar 4.16.1 menampilkan potongan kode ProductController pada method store yang digunakan untuk menyimpan produk baru dari seller. Kode ini menjadi bagian utama dari proses tambah produk karena menerima data dari form frontend, melakukan validasi, mengelola file upload, dan menyimpan data produk ke dalam database.",
        "Tahap pertama pada kode tersebut adalah proses validasi input. Sistem memeriksa data wajib seperti nama produk, stok, harga, kategori, dan deskripsi. Kategori divalidasi menggunakan aturan exists terhadap tabel categories berdasarkan nama kategori, sehingga seller hanya dapat memilih kategori yang telah disediakan oleh admin. Validasi juga diterapkan pada file upload seperti gambar utama, galeri gambar, video, dan sertifikat agar format serta ukuran file tetap sesuai dengan ketentuan sistem.",
        "Setelah validasi berhasil, sistem mengambil id toko milik seller yang sedang login melalui Auth::user()->store->id. Selanjutnya, sistem menyiapkan variabel penyimpanan file, seperti imagePath, certificatePath, galleryPaths, dan videoPath. Jika seller mengunggah gambar utama, galeri, video, atau sertifikat, file tersebut disimpan ke direktori storage public dengan folder yang sesuai, misalnya products, product-videos, dan certificates.",
        "Bagian berikutnya menentukan jenis penjualan produk melalui field sale_type. Jika produk menggunakan skema tebak harga, sistem membaca waktu mulai dan waktu selesai tebak harga menggunakan Carbon sesuai timezone aplikasi. Jika produk merupakan penjualan normal, field waktu tebak harga disimpan sebagai null. Dengan cara ini, satu form tambah produk dapat mendukung dua jenis penjualan, yaitu penjualan biasa dan tebak harga.",
        "Pada tahap penyimpanan, sistem membuat data Product baru dengan atribut seperti store_id, name, stock, price, category, description, image, images, video, certificate, is_barterable, sale_type, guess_starts_at, guess_ends_at, dan guess_status. Produk yang baru diajukan diberi approval_status pending_validator, sehingga produk tidak langsung tampil di marketplace sebelum divalidasi oleh validator dan disetujui oleh admin. Setelah proses selesai, sistem mengarahkan seller kembali ke dashboard dengan pesan bahwa produk berhasil dikirim dan menunggu validasi.",
    ],
    "Gambar 4. 17. Halaman Tambah Produk Lelang": [
        "Gambar 4.17 menunjukkan form pengajuan produk lelang. Seller mengisi informasi barang, harga awal, minimal kenaikan bid, dan jadwal lelang agar sistem dapat menjalankan proses lelang sesuai aturan yang ditentukan.",
    ],
    "Gambar 4. 18. Potongan Kode Tambah Produk Lelang": [
        "Gambar 4.18 menunjukkan potongan kode pengajuan lelang. Kode tersebut memvalidasi data lelang, menyimpan gambar, menentukan harga awal dan minimal kenaikan bid, serta memberikan status awal pending agar lelang menunggu persetujuan admin sebelum ditampilkan kepada pengguna.",
    ],
    "Gambar 4. 19. Halaman Barter": [
        "Gambar 4.19 menunjukkan halaman barter yang digunakan seller untuk mengelola pertukaran produk. Melalui halaman ini, seller dapat melihat produk yang tersedia untuk barter, pengajuan masuk, pengajuan keluar, serta status dari setiap proses barter.",
    ],
    "Gambar 4. 20. Potongan Kode Barter": [
        "Gambar 4.20 menunjukkan potongan kode BarterController yang mengatur proses barter antar seller. Kode tersebut memvalidasi produk yang ditawarkan dan produk yang diminta, mencegah barter terhadap produk milik sendiri, menyimpan pengajuan barter, serta menukar kepemilikan produk ketika pengajuan disetujui.",
    ],
    "Gambar 4. 21. Halaman Admin": [
        "Gambar 4.21 menunjukkan dashboard admin yang digunakan untuk memantau ringkasan data sistem. Informasi seperti jumlah pengguna, seller, produk, order, kategori, dan pengajuan pending membantu admin melakukan pengawasan terhadap operasional marketplace.",
    ],
    "Gambar 4. 22. Halaman Validasi Produk Admin": [
        "Gambar 4.22 menunjukkan halaman validasi produk admin. Pada halaman ini, admin melakukan persetujuan akhir terhadap produk yang sebelumnya telah diperiksa oleh validator, sehingga produk yang layak dapat dipublikasikan pada katalog marketplace.",
    ],
    "Gambar 4. 23. Halaman Validasi Lelang": [
        "Gambar 4.23 menunjukkan halaman validasi lelang yang digunakan admin untuk meninjau pengajuan barang lelang. Admin dapat menyetujui atau menolak lelang berdasarkan informasi barang, harga, jadwal, dan kelayakan data yang diajukan seller.",
    ],
    "Gambar 4. 24. Halaman Kelola User": [
        "Gambar 4.24 menunjukkan halaman kelola user yang digunakan admin untuk melihat dan mengelola data pengguna. Halaman ini membantu admin melakukan pengawasan terhadap akun yang terdaftar pada sistem.",
    ],
    "Gambar 4. 25. Halaman Kelola Seller": [
        "Gambar 4.25 menunjukkan halaman kelola seller yang menampilkan data penjual dan toko yang terdaftar. Melalui halaman ini, admin dapat memantau seller yang aktif dalam proses penjualan barang antik.",
    ],
    "Gambar 4. 26. Halaman Kelola Data Produk": [
        "Gambar 4.26 menunjukkan halaman kelola data produk yang digunakan admin untuk melihat daftar produk pada sistem. Halaman ini membantu admin memantau status produk, data toko, kategori, dan informasi barang yang tersedia.",
    ],
    "Gambar 4. 27. Halaman Detail Order": [
        "Gambar 4.27 menunjukkan halaman detail order yang digunakan admin untuk melihat informasi transaksi secara lebih lengkap. Data seperti pembeli, produk, toko, status pesanan, dan status pembayaran membantu admin memantau proses transaksi.",
    ],
    "Gambar 4. 28. Halaman Kelola Kategori": [
        "Gambar 4.28 menunjukkan halaman kelola kategori yang digunakan admin untuk membuat, mengubah, dan menghapus kategori produk. Data kategori ini digunakan seller ketika menambahkan produk agar klasifikasi barang lebih konsisten.",
    ],
    "Gambar 4. 29. Halaman Chat Admin": [
        "Gambar 4.29 menunjukkan halaman chat admin yang digunakan untuk memantau dan merespons pesan bantuan. Fitur ini membantu admin menangani pertanyaan atau kendala pengguna secara lebih terpusat.",
    ],
    "Gambar 4. 30. Halaman Dashboard Validator": [
        "Gambar 4.30 menunjukkan dashboard validator yang berisi daftar produk yang perlu diperiksa. Dashboard ini membantu validator memisahkan produk yang menunggu validasi dengan riwayat produk yang sudah diproses.",
    ],
    "Gambar 4. 31. Halaman Validasi Produk Validator": [
        "Gambar 4.31 menunjukkan halaman detail validasi produk oleh validator. Validator dapat meninjau data produk dan sertifikat, kemudian menyetujui produk agar diteruskan ke admin atau menolak produk dengan alasan tertentu.",
    ],
    "Gambar 4. 32. Potongan kode Validasi Produk Validator": [
        "Gambar 4.32 menunjukkan potongan kode validasi produk pada ValidatorController. Kode tersebut memastikan produk masih berada pada status pending_validator, lalu mengubah status menjadi pending_admin ketika disetujui atau rejected ketika ditolak. Sistem juga mencatat validated_by, validated_at, dan rejection_reason sebagai riwayat proses validasi.",
    ],
}


EXPLANATIONS.update({
    "Gambar 4. 24. Halaman Kelola User": [
        "Gambar 4.24 menunjukkan halaman kelola user yang digunakan admin untuk memantau data akun pengguna yang terdaftar pada sistem Vinstore. Pada halaman ini, admin dapat melihat informasi utama pengguna, seperti identitas akun, data kontak, peran pengguna, dan status akun yang digunakan untuk mengakses marketplace.",
        "Halaman kelola user berfungsi sebagai pusat pengawasan akun agar data pengguna yang tersimpan pada sistem tetap tertata. Melalui halaman ini, admin dapat melakukan pengelolaan apabila terdapat akun yang perlu diperbarui, diperiksa, atau dibatasi aksesnya. Fitur ini penting karena seluruh aktivitas marketplace, seperti pembelian produk, pengajuan refund, percakapan chat, hingga transaksi lelang, selalu berkaitan dengan akun pengguna.",
        "Dengan adanya halaman kelola user, admin dapat memastikan bahwa penggunaan sistem berjalan sesuai hak akses masing-masing aktor. Pengelolaan user juga membantu mengurangi risiko penyalahgunaan akun, duplikasi data, atau kesalahan peran pengguna yang dapat memengaruhi proses bisnis marketplace.",
    ],
    "Gambar 4. 25. Halaman Kelola Seller": [
        "Gambar 4.25 menunjukkan halaman kelola seller yang menampilkan data penjual dan toko yang terdaftar pada marketplace. Informasi yang ditampilkan pada halaman ini membantu admin mengetahui seller mana saja yang aktif menggunakan sistem untuk menjual, melelang, atau melakukan barter barang antik.",
        "Halaman ini digunakan admin untuk memantau keterkaitan antara akun seller dengan data toko yang dimiliki. Data seller menjadi penting karena produk yang tampil pada marketplace berasal dari toko seller, sehingga admin perlu memastikan bahwa data penjual dapat ditelusuri dengan jelas. Dengan tampilan daftar seller, proses pemantauan toko menjadi lebih mudah dan terpusat.",
        "Selain sebagai media pemantauan, halaman kelola seller juga mendukung aspek kepercayaan marketplace. Admin dapat menggunakan halaman ini untuk mengevaluasi seller yang bermasalah, memeriksa data toko, dan menjaga agar aktivitas penjualan tetap sesuai dengan ketentuan sistem.",
    ],
    "Gambar 4. 26. Halaman Kelola Data Produk": [
        "Gambar 4.26 menunjukkan halaman kelola data produk yang digunakan admin untuk melihat seluruh produk yang tersimpan pada sistem. Halaman ini menampilkan informasi produk seperti nama barang, kategori, toko pemilik, harga, stok, dan status persetujuan produk.",
        "Halaman kelola data produk membantu admin melakukan pengawasan terhadap katalog marketplace secara menyeluruh. Produk yang telah diajukan seller dapat dipantau berdasarkan statusnya, baik masih menunggu validasi, sudah disetujui, maupun ditolak. Dengan demikian, admin memiliki kontrol terhadap produk yang layak ditampilkan kepada pengguna.",
        "Fitur ini juga berperan dalam menjaga kualitas data produk barang antik. Karena produk barang antik memiliki nilai keaslian dan deskripsi yang penting, admin perlu memiliki halaman khusus untuk meninjau data produk agar informasi yang muncul pada marketplace tetap relevan, rapi, dan dapat dipertanggungjawabkan.",
    ],
    "Gambar 4. 27. Halaman Detail Order": [
        "Gambar 4.27 menunjukkan halaman detail order yang digunakan admin untuk melihat informasi transaksi secara lebih lengkap. Pada halaman ini, sistem menampilkan data pembeli, produk yang dipesan, toko penjual, jumlah pembelian, harga, status pesanan, status pembayaran, serta informasi invoice.",
        "Halaman detail order berfungsi untuk mendukung proses pelacakan transaksi. Ketika terjadi kendala seperti pembayaran belum berhasil, pesanan dibatalkan, barang belum sampai, atau adanya pengajuan refund, admin dapat menggunakan detail order sebagai acuan untuk memeriksa kondisi transaksi secara objektif.",
        "Dengan adanya halaman ini, proses administrasi transaksi menjadi lebih transparan karena setiap order memiliki data yang dapat ditelusuri. Hal tersebut penting pada marketplace karena transaksi melibatkan beberapa pihak, yaitu pembeli, seller, sistem pembayaran, dan admin sebagai pengawas operasional.",
    ],
    "Gambar 4. 28. Halaman Kelola Kategori": [
        "Gambar 4.28 menunjukkan halaman kelola kategori yang digunakan admin untuk membuat, mengubah, dan menghapus kategori produk. Kategori berfungsi sebagai pengelompokan barang antik agar produk yang dijual pada marketplace dapat disusun berdasarkan jenisnya.",
        "Data kategori yang dibuat oleh admin digunakan pada form tambah produk seller dalam bentuk dropdown. Dengan cara ini, seller tidak perlu mengetik kategori secara manual, tetapi cukup memilih kategori yang telah disediakan. Mekanisme tersebut membuat klasifikasi produk lebih konsisten dan mengurangi kesalahan penulisan kategori.",
        "Pengelolaan kategori juga mendukung fitur pencarian dan filter produk. Semakin rapi data kategori yang tersedia, semakin mudah pengguna menemukan produk sesuai minatnya, misalnya kategori kamera, musik, sepeda, atau barang antik lainnya.",
    ],
    "Gambar 4. 29. Halaman Chat Admin": [
        "Gambar 4.29 menunjukkan halaman chat admin yang digunakan untuk memantau dan merespons pesan bantuan dari pengguna. Fitur ini menjadi media komunikasi antara pengguna dengan pihak pengelola sistem ketika pengguna mengalami kendala atau membutuhkan informasi tambahan.",
        "Melalui halaman chat admin, pesan yang berkaitan dengan akun, produk, order, pembayaran, refund, atau fitur marketplace lainnya dapat ditangani secara lebih terpusat. Admin dapat melihat percakapan yang masuk dan memberikan respons tanpa harus menggunakan media komunikasi di luar sistem.",
        "Keberadaan fitur ini mendukung kualitas layanan pada marketplace. Selain membantu pengguna mendapatkan bantuan, chat admin juga membuat riwayat komunikasi lebih mudah ditelusuri apabila suatu kendala perlu diperiksa kembali pada waktu berikutnya.",
    ],
    "Gambar 4. 30. Halaman Dashboard Validator": [
        "Gambar 4.30 menunjukkan dashboard validator yang digunakan untuk menampilkan daftar produk yang perlu diperiksa sebelum diteruskan kepada admin. Validator berperan sebagai pemeriksa awal terhadap produk barang antik yang diajukan oleh seller.",
        "Pada dashboard ini, validator dapat membedakan produk yang masih menunggu pemeriksaan dengan produk yang sudah diproses. Informasi tersebut membantu validator mengatur prioritas validasi, terutama ketika terdapat banyak produk yang diajukan oleh seller dalam waktu bersamaan.",
        "Dashboard validator dibuat untuk memisahkan tanggung jawab validasi produk dari admin. Validator berfokus pada pemeriksaan kelengkapan dan kelayakan awal produk, seperti deskripsi, gambar, kategori, dan sertifikat jika tersedia. Setelah produk lolos validasi awal, produk dapat diteruskan ke admin untuk mendapatkan persetujuan akhir sebelum tampil pada marketplace.",
    ],
    "Gambar 4.2. Kode RegisterController": [
        "Gambar 4.2 menampilkan potongan kode RegisterController yang digunakan untuk menangani proses pendaftaran pengguna baru pada sistem Vinstore. Controller ini menjadi penghubung antara form register pada frontend dengan proses penyimpanan data pengguna pada backend. Data yang dikirimkan pengguna, seperti username, nama, email, nomor telepon, password, dan alamat, diterima oleh controller untuk diproses lebih lanjut.",
        "Tahap utama pada kode tersebut adalah proses validasi input. Validasi dilakukan untuk memastikan bahwa data yang dikirimkan sudah lengkap, memiliki format yang sesuai, dan tidak menimbulkan duplikasi pada database. Contohnya, email atau username harus bersifat unik, sedangkan password harus memenuhi aturan minimal dan sesuai dengan konfirmasi password. Proses ini penting agar data akun yang tersimpan tetap konsisten dan dapat digunakan dalam proses autentikasi.",
        "Setelah data dinyatakan valid, password pengguna tidak disimpan dalam bentuk teks asli, melainkan diproses menggunakan hashing. Selanjutnya, controller membuat data user baru melalui model User dan menyimpannya ke tabel users. Dengan alur tersebut, RegisterController tidak hanya berfungsi untuk menyimpan akun, tetapi juga menjaga keamanan data autentikasi pengguna.",
        "Hasil dari proses ini adalah terbentuknya akun baru yang dapat digunakan untuk login ke sistem. Apabila proses pendaftaran berhasil, sistem memberikan respons berupa pesan sukses atau mengarahkan pengguna ke halaman berikutnya sesuai alur aplikasi.",
    ],
    "Gambar 4. 4. Potongan Kode Auth": [
        "Gambar 4.4 menampilkan potongan kode autentikasi yang digunakan untuk memproses login pengguna. Kode ini menerima input berupa username atau email dan password dari halaman login, kemudian melakukan pemeriksaan terhadap data tersebut sebelum pengguna diberi akses ke sistem.",
        "Proses awal yang dilakukan adalah validasi input login. Sistem memastikan bahwa kolom identitas dan password telah diisi. Setelah itu, sistem mencocokkan kredensial yang diberikan dengan data pengguna yang tersimpan pada database. Jika data tidak sesuai, sistem mengembalikan pesan kesalahan agar pengguna mengetahui bahwa proses login gagal.",
        "Apabila kredensial sesuai, sistem membuat session login sebagai tanda bahwa pengguna telah berhasil diautentikasi. Session ini digunakan untuk mempertahankan status login pengguna selama mengakses halaman-halaman yang membutuhkan autentikasi. Selain itu, sistem juga membaca role pengguna untuk menentukan hak akses dan halaman tujuan setelah login.",
        "Dengan adanya kode autentikasi ini, sistem dapat membedakan akses antara user, seller, validator, dan admin. Hal tersebut penting karena setiap aktor memiliki fitur dan halaman yang berbeda sesuai dengan tanggung jawabnya dalam marketplace Vinstore.",
    ],
    "Gambar 4. 9. Potongan Kode Checkout": [
        "Gambar 4.9 menampilkan potongan kode checkout yang digunakan untuk memproses pembelian produk oleh user. Kode ini menerima data produk dan jumlah pembelian dari halaman checkout, kemudian memeriksa apakah produk tersebut dapat dibeli oleh pengguna.",
        "Tahap validasi pada proses checkout mencakup beberapa kondisi penting. Sistem memastikan bahwa produk sudah disetujui, stok produk masih tersedia, jumlah pembelian valid, dan produk bukan milik toko pengguna sendiri. Validasi ini diperlukan agar transaksi hanya dapat dilakukan pada produk yang layak dibeli dan sesuai dengan aturan marketplace.",
        "Setelah data pembelian dinyatakan valid, sistem menjalankan transaksi database. Produk dikunci menggunakan mekanisme lockForUpdate agar perubahan stok tidak terganggu oleh transaksi lain yang berjalan bersamaan. Jika stok mencukupi, sistem mengurangi stok produk, membuat data order dengan status awal Waiting dan payment_status pending, lalu membuat payment_reference berdasarkan public_id order.",
        "Tahap berikutnya adalah integrasi dengan MidtransService. Sistem mengirimkan data transaksi ke Midtrans sandbox untuk memperoleh snap_token dan redirect_url pembayaran. Data tersebut disimpan pada order agar pembeli dapat diarahkan ke halaman pembayaran. Dengan demikian, potongan kode checkout berperan dalam pembuatan order, pengelolaan stok, dan integrasi pembayaran.",
    ],
    "Gambar 4. 12. Potongan Kode Auction": [
        "Gambar 4.12 menampilkan potongan kode AuctionController yang digunakan untuk mengelola fitur lelang pada sistem Vinstore. Controller ini menangani beberapa proses utama, mulai dari pengajuan barang lelang oleh seller, persetujuan admin, pengajuan bid oleh user, hingga finalisasi pemenang lelang.",
        "Pada saat seller mengajukan barang lelang, sistem melakukan validasi terhadap data seperti nama barang, deskripsi, foto, harga awal, minimal kenaikan bid, waktu mulai, dan waktu selesai. Setelah data valid, lelang disimpan dengan status awal pending sehingga belum dapat tampil sebagai lelang aktif sebelum disetujui admin.",
        "Pada proses bid, controller memastikan bahwa lelang masih aktif, pengguna bukan pemilik barang, dan nominal bid memenuhi batas minimal. Batas minimal dihitung dari harga tertinggi saat ini ditambah minimal kenaikan bid. Apabila nominal bid valid, sistem menyimpan data AuctionBid dan memperbarui current_price serta bids_count pada data lelang.",
        "Ketika waktu lelang berakhir, sistem menjalankan proses finalisasi. Bid tertinggi ditetapkan sebagai pemenang, status lelang diubah menjadi ended, dan sistem membuat order untuk pemenang agar pembayaran dapat dilanjutkan melalui Midtrans. Dengan demikian, AuctionController mengatur seluruh siklus lelang dari pengajuan hingga transaksi pemenang.",
    ],
    "Gambar 4. 14. Potongan Kode PriceGuest": [
        "Gambar 4.14 menampilkan potongan kode fitur tebak harga yang digunakan untuk menerima tebakan dari pembeli. Pada fitur ini, harga asli produk disembunyikan selama periode tertentu sehingga pengguna hanya dapat mengirimkan perkiraan harga melalui form yang tersedia.",
        "Kode tersebut terlebih dahulu menyinkronkan status tebak harga menggunakan PriceGuessService. Sinkronisasi dilakukan agar sistem mengetahui apakah periode tebak harga masih scheduled, active, ended, atau sudah public. Setelah status terbaru diperoleh, sistem memeriksa apakah produk benar-benar menggunakan jenis penjualan tebak harga dan apakah periode tebakan sedang aktif.",
        "Selanjutnya, sistem melakukan validasi terhadap pengguna. Seller pemilik produk tidak diperbolehkan menebak produk miliknya sendiri. Selain itu, setiap user hanya boleh mengirimkan satu tebakan untuk satu produk. Untuk memastikan aturan tersebut berjalan konsisten, sistem memeriksa apakah user sudah pernah menebak sebelumnya sebelum menyimpan data baru ke tabel price_guesses.",
        "Apabila seluruh validasi terpenuhi, sistem menyimpan nominal tebakan sebagai data PriceGuess. Data ini kemudian digunakan pada proses finalisasi untuk menentukan pemenang berdasarkan tebakan yang paling mendekati harga asli. Dengan demikian, potongan kode ini menjaga aturan utama fitur tebak harga agar berjalan adil dan terkontrol.",
    ],
    "Gambar 4. 18. Potongan Kode Tambah Produk Lelang": [
        "Gambar 4.18 menampilkan potongan kode yang digunakan untuk menyimpan pengajuan barang lelang baru. Kode ini dijalankan ketika seller mengisi form tambah lelang dan mengirimkan data barang kepada sistem.",
        "Pada tahap awal, sistem melakukan validasi terhadap data lelang. Validasi mencakup nama barang, deskripsi, gambar, harga awal, minimal kenaikan bid, tanggal mulai, dan tanggal selesai. Tanggal selesai harus lebih besar dari tanggal mulai agar periode lelang dapat berjalan secara logis. Validasi ini diperlukan untuk mencegah data lelang yang tidak lengkap atau tidak sesuai aturan.",
        "Setelah data valid, sistem memeriksa apakah seller telah memiliki toko. Jika seller belum memiliki toko, sistem mengarahkan pengguna untuk mendaftarkan toko terlebih dahulu. Apabila toko tersedia, file gambar lelang disimpan ke storage, kemudian data lelang dibuat dengan current_price sama dengan starting_price.",
        "Lelang yang baru dibuat diberi approval_status pending dan status pending. Artinya, barang lelang belum langsung tampil sebagai lelang aktif, tetapi harus melalui proses persetujuan admin. Dengan alur ini, sistem dapat menjaga kualitas dan kelayakan barang lelang sebelum dapat diikuti oleh pembeli.",
    ],
    "Gambar 4. 20. Potongan Kode Barter": [
        "Gambar 4.20 menampilkan potongan kode BarterController yang digunakan untuk mengelola fitur barter antar seller. Fitur ini memungkinkan seller mengajukan pertukaran produk dengan seller lain, baik dengan atau tanpa tambahan uang.",
        "Pada proses pengajuan barter, sistem memvalidasi bahwa seller pengaju telah memiliki toko, produk yang diminta tersedia untuk barter, dan produk tersebut bukan milik toko sendiri. Selain itu, sistem juga memeriksa bahwa produk yang ditawarkan benar-benar milik seller pengaju dan masih memenuhi syarat untuk dibarter.",
        "Sistem juga mencegah pengajuan barter duplikat yang masih berstatus pending untuk pasangan produk yang sama. Jika validasi berhasil, data pengajuan disimpan sebagai BarterRequest dengan status pending. Status ini menunjukkan bahwa pengajuan masih menunggu keputusan dari seller pemilik produk yang diminta.",
        "Ketika pengajuan disetujui, controller menjalankan transaksi database untuk menukar store_id dari kedua produk. Setelah kepemilikan produk bertukar, status barter diubah menjadi accepted dan pengajuan pending lain yang melibatkan produk tersebut dibatalkan. Dengan demikian, kode barter menjaga konsistensi kepemilikan produk dan mencegah konflik pengajuan.",
    ],
    "Gambar 4. 32. Potongan kode Validasi Produk Validator": [
        "Gambar 4.32 menampilkan potongan kode ValidatorController yang digunakan untuk memproses validasi awal produk oleh validator. Validasi ini menjadi tahap pemeriksaan sebelum produk diteruskan kepada admin untuk persetujuan akhir.",
        "Pada saat validator menyetujui produk, sistem terlebih dahulu memastikan bahwa produk masih berada pada status pending_validator. Pemeriksaan ini penting agar produk yang sudah diproses tidak divalidasi ulang. Jika status sesuai, sistem mengubah approval_status menjadi pending_admin serta mencatat validated_by dan validated_at sebagai riwayat siapa validator yang memproses produk dan kapan validasi dilakukan.",
        "Apabila produk tidak memenuhi ketentuan, validator dapat menolak produk dengan alasan tertentu. Pada kondisi ini, sistem mengubah approval_status menjadi rejected, mencatat data validator, serta menyimpan rejection_reason. Alasan penolakan digunakan agar seller mengetahui penyebab produk tidak diteruskan ke tahap admin.",
        "Dengan adanya kode validasi ini, proses approval produk menjadi lebih terstruktur karena terdapat pemisahan tanggung jawab antara validator dan admin. Validator berfokus pada pemeriksaan awal kelayakan produk, sedangkan admin melakukan persetujuan akhir sebelum produk tampil pada katalog marketplace.",
    ],
})


def main():
    OUTPUT_DOCX.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(SOURCE_DOCX, "r") as zin:
        entries = {name: zin.read(name) for name in zin.namelist()}

    document = etree.fromstring(entries["word/document.xml"])
    rels = etree.fromstring(entries["word/_rels/document.xml.rels"])
    content_types = etree.fromstring(entries["[Content_Types].xml"])
    body = document.xpath(".//w:body", namespaces=NS)[0]

    existing_rids = []
    for rel in rels:
        rid = rel.get("Id") or rel.get(rel_qn("Id"))
        if rid and re.fullmatch(r"rId\d+", rid):
            existing_rids.append(int(rid[3:]))
    next_rid = max(existing_rids or [0]) + 1

    existing_media = {name for name in entries if name.startswith("word/media/")}
    image_number = 1
    while f"word/media/vinstore_bab4_tambah_produk_{image_number}.png" in existing_media:
        image_number += 1
    inserted_tambah_produk_code = False

    inserted = 0
    for child in list(body):
        if child.tag != qn("w:p"):
            continue
        text = normalize(paragraph_text(child))
        explanations = EXPLANATIONS.get(text)
        if not explanations:
            continue
        idx = list(body).index(child) + 1
        for explanation in explanations:
            body.insert(idx, paragraph(explanation))
            idx += 1
            inserted += 1

        if text == "Gambar 4. 16. Halaman Tambah Produk" and TAMBAH_PRODUK_CODE_IMAGE.exists():
            rid = f"rId{next_rid}"
            next_rid += 1
            image_name = f"vinstore_bab4_tambah_produk_{image_number}.png"
            target = f"media/{image_name}"
            entries[f"word/{target}"] = TAMBAH_PRODUK_CODE_IMAGE.read_bytes()

            rel = etree.Element(rel_qn("Relationship"))
            rel.set("Id", rid)
            rel.set("Type", "http://schemas.openxmlformats.org/officeDocument/2006/relationships/image")
            rel.set("Target", target)
            rels.append(rel)

            body.insert(idx, image_paragraph(rid, image_name, TAMBAH_PRODUK_CODE_IMAGE))
            idx += 1
            caption = "Gambar 4.16.1. Potongan Kode Tambah Produk"
            body.insert(idx, paragraph(caption))
            idx += 1
            for explanation in EXPLANATIONS[caption]:
                body.insert(idx, paragraph(explanation))
                idx += 1
                inserted += 1
            inserted_tambah_produk_code = True

    entries["word/document.xml"] = etree.tostring(
        document, xml_declaration=True, encoding="UTF-8", standalone="yes"
    )
    entries["word/_rels/document.xml.rels"] = etree.tostring(
        rels, xml_declaration=True, encoding="UTF-8", standalone="yes"
    )
    add_default_content_type(content_types, "png", "image/png")
    entries["[Content_Types].xml"] = etree.tostring(
        content_types, xml_declaration=True, encoding="UTF-8", standalone="yes"
    )
    with ZipFile(OUTPUT_DOCX, "w", ZIP_DEFLATED) as zout:
        for name, data in entries.items():
            zout.writestr(name, data)

    print(f"{OUTPUT_DOCX}")
    print(f"Inserted explanations: {inserted}")
    print(f"Inserted tambah produk code image: {inserted_tambah_produk_code}")


if __name__ == "__main__":
    main()
