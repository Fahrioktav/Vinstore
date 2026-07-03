import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet menentukan path ikon marker secara relatif terhadap CSS-nya,
// yang rusak saat di-bundle oleh Vite. Kita timpa dengan URL asset
// yang sudah di-resolve oleh bundler agar marker tampil dengan benar.
let configured = false;

export function setupLeafletIcons() {
  if (configured) return;

  delete L.Icon.Default.prototype._getIconUrl;
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
  });

  configured = true;
}

// Pusat default peta: kira-kira tengah Indonesia.
export const INDONESIA_CENTER = [-2.5489, 118.0149];

// Fallback ketika hanya butuh satu titik default (Jakarta).
export const JAKARTA_CENTER = [-6.2088, 106.8456];

export default L;
