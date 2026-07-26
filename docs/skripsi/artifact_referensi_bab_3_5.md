# Artifact Referensi BAB 3-5

Referensi: `C:\Users\fahri\Downloads\1d900bb73621ae22f65f115e3c5147e6.docx`

SHA-256: `147057f86e942ddc4761bc9195f344f557e17593d2862581a3a226bf50787750`

## Pola Struktur

Referensi menggunakan pola BAB 3 sebagai berikut:

- BAB 3 METODE PENELITIAN
- Tahapan Penelitian
- Alur dan Metode Penelitian
- Tahap Perencanaan Kebutuhan
- Desain Sistem
- Unified Modeling Language (UML)
- Struktur Tabel Database
- Entity Relation Diagram (ERD)
- Perancangan Antar Muka
- Pengembangan Sistem
- Pengujian dan Penyelesaian

Referensi menggunakan pola BAB 4 sebagai berikut:

- BAB 4 HASIL DAN PEMBAHASAN
- Teknologi Yang Digunakan
- Tampilan User
- Tampilan Admin
- Pengujian
- Blackbox
- Unit Testing
- UAT

Referensi menggunakan pola BAB 5 sebagai berikut:

- BAB 5 KESIMPULAN DAN SARAN
- Kesimpulan
- Saran

## Adaptasi Pada Draft Vinstore

Draft Vinstore disesuaikan dengan pola referensi mulai dari bagian `Perancangan Sistem` pada dokumen asli. Bagian tersebut diganti menjadi `Desain Sistem`, kemudian dilanjutkan dengan UML, struktur tabel database, ERD, perancangan antarmuka, pengembangan sistem, rancangan pengujian, BAB 4, dan BAB 5.

BAB 4 disesuaikan menjadi bagian `Teknologi Yang Digunakan`, `Tampilan User`, `Tampilan Seller`, `Tampilan Admin`, dan `Pengujian`. Bagian seller ditambahkan karena sistem Vinstore memiliki aktor seller yang menjadi bagian utama marketplace.

## Batasan

Render visual tidak dapat dilakukan pada environment ini karena helper render membutuhkan dependency `pdf2image` dan LibreOffice tidak tersedia. Verifikasi dilakukan melalui struktur OOXML: `word/document.xml`, `word/_rels/document.xml.rels`, `[Content_Types].xml`, `word/settings.xml`, jumlah heading, caption, drawing, serta flag `w:updateFields=true`.
