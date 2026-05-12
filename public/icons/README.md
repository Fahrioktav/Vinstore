# PWA Icons Guide

## Generate Icons

Anda perlu membuat icon dalam berbagai ukuran untuk PWA. Berikut cara termudah:

### Option 1: Menggunakan Online Tool (Recommended)
1. Buka https://realfavicongenerator.net/ atau https://www.pwabuilder.com/imageGenerator
2. Upload logo Vinstore Anda (minimal 512x512 px)
3. Generate semua ukuran icon yang dibutuhkan
4. Download dan ekstrak ke folder `public/icons/`

### Option 2: Menggunakan ImageMagick (CLI)
Jika Anda punya logo.png (512x512), jalankan:

```bash
# Install ImageMagick dulu: https://imagemagick.org/script/download.php

# Generate all sizes
magick logo.png -resize 72x72 icon-72x72.png
magick logo.png -resize 96x96 icon-96x96.png
magick logo.png -resize 128x128 icon-128x128.png
magick logo.png -resize 144x144 icon-144x144.png
magick logo.png -resize 152x152 icon-152x152.png
magick logo.png -resize 192x192 icon-192x192.png
magick logo.png -resize 384x384 icon-384x384.png
magick logo.png -resize 512x512 icon-512x512.png
```

### Option 3: Menggunakan Node.js Script

Buat file `generate-icons.js`:

```javascript
const sharp = require('sharp');
const fs = require('fs');

const sizes = [72, 96, 128, 144, 152, 192, 384, 512];
const inputFile = 'logo.png'; // Your source logo

async function generateIcons() {
  if (!fs.existsSync('public/icons')) {
    fs.mkdirSync('public/icons', { recursive: true });
  }

  for (const size of sizes) {
    await sharp(inputFile)
      .resize(size, size)
      .toFile(`public/icons/icon-${size}x${size}.png`);
    console.log(`✓ Generated icon-${size}x${size}.png`);
  }
}

generateIcons();
```

Install sharp: `npm install sharp -D`
Run: `node generate-icons.js`

## Required Icon Sizes
- 72x72 (minimum)
- 96x96
- 128x128
- 144x144
- 152x152 (Apple Touch Icon)
- 192x192 (Android standard)
- 384x384
- 512x512 (recommended)

## Temporary Placeholder
Untuk testing, sudah ada `icon.svg` di folder ini. Anda bisa:
1. Convert SVG ke PNG menggunakan online tool
2. Atau gunakan sebagai placeholder sementara

## Screenshot (Optional)
Untuk PWA screenshot, capture halaman utama Vinstore dengan ukuran 540x720 (mobile) dan simpan di `public/screenshots/home.png`
