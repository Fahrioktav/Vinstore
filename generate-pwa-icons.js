/**
 * Script untuk generate PWA icons dari SVG
 * 
 * Cara pakai:
 * 1. npm install sharp -D
 * 2. node generate-pwa-icons.js
 */

import sharp from 'sharp';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const sizes = [72, 96, 128, 144, 152, 192, 384, 512];
const inputFile = path.join(__dirname, 'public', 'icons', 'icon.svg');
const outputDir = path.join(__dirname, 'public', 'icons');

async function generateIcons() {
  console.log('🎨 Generating PWA icons...\n');

  // Check if input file exists
  if (!fs.existsSync(inputFile)) {
    console.error('❌ Error: icon.svg not found at', inputFile);
    console.log('💡 Please create icon.svg first or update inputFile path');
    return;
  }

  // Create output directory if it doesn't exist
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  try {
    // Generate each size
    for (const size of sizes) {
      const outputFile = path.join(outputDir, `icon-${size}x${size}.png`);
      
      await sharp(inputFile)
        .resize(size, size, {
          fit: 'contain',
          background: { r: 31, g: 41, b: 55, alpha: 1 } // #1f2937
        })
        .png()
        .toFile(outputFile);
      
      console.log(`✅ Generated icon-${size}x${size}.png`);
    }

    console.log('\n🎉 All icons generated successfully!');
    console.log('📁 Icons saved to:', outputDir);
    console.log('\n💡 Next steps:');
    console.log('1. Review the generated icons');
    console.log('2. Optionally create custom screenshots for PWA');
    console.log('3. Test PWA installation in browser');
    
  } catch (error) {
    console.error('❌ Error generating icons:', error.message);
    
    if (error.message.includes('sharp')) {
      console.log('\n💡 Please install sharp first:');
      console.log('   npm install sharp --save-dev');
    }
  }
}

// Run the script
generateIcons();
