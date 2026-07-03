# Diagram UML Vinstore

Folder ini berisi diagram UML sistem **Vinstore** (marketplace barang antik) untuk
keperluan laporan skripsi. Semua diagram ditulis dalam format **PlantUML** (`.puml`)
agar mudah diedit dan dirender ulang.

## Daftar Diagram

| File | Jenis Diagram | Isi |
|------|---------------|-----|
| `01-use-case.puml` | Use Case Diagram | Seluruh aktor (Pengunjung, Pembeli, Penjual, Validator, Admin, Midtrans) dan fungsionalitas sistem |
| `02-class-diagram.puml` | Class Diagram | Model domain (Eloquent), Controller, dan Service beserta relasinya |
| `03-activity-checkout.puml` | Activity Diagram | Alur checkout produk & pembayaran via Midtrans |
| `04-activity-product-approval.puml` | Activity Diagram | Alur pengajuan & validasi produk dua tahap (Validator → Admin) |
| `05-sequence-checkout.puml` | Sequence Diagram | Interaksi objek saat checkout & pembayaran |

## Cara Render Diagram

### Opsi 1 — PlantUML Online (paling cepat, tanpa instalasi)
1. Buka https://www.plantuml.com/plantuml/uml
2. Salin isi salah satu file `.puml`
3. Tempel ke editor, gambar langsung muncul. Bisa diunduh sebagai PNG/SVG.

### Opsi 2 — VS Code
1. Pasang ekstensi **PlantUML** (jebbs.plantuml).
2. Buka file `.puml`, tekan `Alt+D` untuk preview.
3. Export via Command Palette → "PlantUML: Export Current Diagram" (PNG/SVG/PDF).

### Opsi 3 — Command Line (butuh Java)
```bash
# Render semua diagram menjadi PNG
java -jar plantuml.jar docs/uml/*.puml

# atau menjadi SVG (vektor, kualitas terbaik untuk dicetak di skripsi)
java -jar plantuml.jar -tsvg docs/uml/*.puml
```
Unduh `plantuml.jar` dari https://plantuml.com/download

## Catatan
Diagram dibuat berdasarkan kode aktual proyek (routes, controller, model, service)
per Juni 2026. Jika ada perubahan fitur, perbarui file `.puml` yang relevan agar
diagram tetap konsisten dengan implementasi.
