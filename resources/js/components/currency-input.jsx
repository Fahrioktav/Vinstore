import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { AuthInput } from '@/components/auth/auth-layout';
import { formatIDR } from '@/lib/utils';

/**
 * Kolom isian nominal rupiah.
 *
 * Yang dilihat pengguna berformat ribuan ("1.000.000"), yang dikirim ke server
 * tetap angka polos ("1000000") lewat input tersembunyi — jadi tidak ada aturan
 * validasi di Laravel yang perlu ikut berubah.
 *
 * Jenisnya `text`, bukan `number`: input number menolak titik pemisah, padahal
 * pemisah itulah inti komponen ini. Sebagai gantinya `inputMode="numeric"` tetap
 * memunculkan papan tombol angka di ponsel.
 *
 * Bisa dipakai terkendali maupun tidak:
 *
 *   <CurrencyInput name="price" defaultValue={product.price} required />
 *   <CurrencyInput value={data.price} onChange={(nilai) => setData('price', nilai)} />
 *
 * `onChange` menerima STRING ANGKA MENTAH, bukan event — pemanggilnya tidak
 * perlu tahu apa pun soal titik pemisah.
 *
 * `min` menghidupkan kembali penjagaan yang dulu diberikan atribut `min` pada
 * input number. Atribut itu tidak berlaku pada input teks, jadi batasnya
 * ditegakkan lewat `setCustomValidity()`: peramban menolak kiriman dan
 * menampilkan pesannya sebelum permintaan dikirim, persis seperti sebelumnya
 * (temuan V8-07).
 */
export default function CurrencyInput({
  name,
  value,
  defaultValue,
  onChange,
  min,
  prefix = 'Rp',
  className,
  style,
  ...props
}) {
  const terkendali = value !== undefined;
  const [digitLokal, setDigitLokal] = useState(() => keDigit(defaultValue));

  // Selama pengguna belum menyentuh kolomnya, yang dikirim adalah nilai ASLI
  // dari server, apa adanya. Tampilannya memang membuang bagian pecahan —
  // "150000.75" tampil sebagai 150.000 — dan tanpa penjagaan ini menyimpan
  // ulang form akan diam-diam menghapus sennya (temuan V8-07).
  const [disunting, setDisunting] = useState(false);

  const digit = terkendali ? keDigit(value) : digitLokal;
  const nilaiAsli = terkendali ? value : defaultValue;

  const inputRef = useRef(null);
  const karetRef = useRef(null);

  // Tanpa pemulihan ini karet melompat ke ujung setiap kali jumlah titik
  // pemisah berubah, sehingga menyunting angka di tengah menjadi mustahil.
  useLayoutEffect(() => {
    if (karetRef.current === null || !inputRef.current) {
      return;
    }

    inputRef.current.setSelectionRange(karetRef.current, karetRef.current);
    karetRef.current = null;
  });

  useEffect(() => {
    const elemen = inputRef.current;

    if (!elemen) {
      return;
    }

    const kurang =
      min !== undefined &&
      min !== null &&
      digit !== '' &&
      Number(digit) < Number(min);

    elemen.setCustomValidity(
      kurang ? `Nominal minimal ${formatIDR(min)}.` : ''
    );
  }, [digit, min]);

  const tangkapPerubahan = (event) => {
    const elemen = event.target;
    const posisi = elemen.selectionStart ?? elemen.value.length;

    // Karet dipatok pada JUMLAH DIGIT di depannya, bukan pada indeks huruf:
    // indeksnya bergeser sendiri saat titik pemisah bertambah atau berkurang.
    const digitSebelumKaret = hanyaDigit(elemen.value.slice(0, posisi)).length;
    const digitBaru = hanyaDigit(elemen.value);

    karetRef.current = posisiKaret(kelompokkan(digitBaru), digitSebelumKaret);

    setDisunting(true);

    if (!terkendali) {
      setDigitLokal(digitBaru);
    }

    onChange?.(digitBaru);
  };

  return (
    <div className="relative">
      {prefix && (
        <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-gray-500">
          {prefix}
        </span>
      )}

      <AuthInput
        {...props}
        ref={inputRef}
        type="text"
        inputMode="numeric"
        autoComplete="off"
        value={kelompokkan(digit)}
        onChange={tangkapPerubahan}
        className={className}
        // Ruang untuk awalan "Rp" diberikan lewat style, bukan kelas: pemanggil
        // membawa kelas padding-nya sendiri (px-4), dan siapa yang menang di
        // antara dua kelas padding tidak pasti.
        style={prefix ? { paddingLeft: '2.5rem', ...style } : style}
      />

      {/* Inilah yang benar-benar terkirim ke server. */}
      {name && (
        <input
          type="hidden"
          name={name}
          value={disunting ? digit : keNilaiKirim(nilaiAsli)}
        />
      )}
    </div>
  );
}

function hanyaDigit(teks) {
  return String(teks ?? '').replace(/\D/g, '');
}

/**
 * Nilai dari server bisa berupa desimal ("1000000.00"). Rupiah di aplikasi ini
 * tidak mengenal sen, jadi bagian pecahannya dibuang untuk KEPERLUAN TAMPILAN —
 * kalau tidak, dua angka nol di belakang koma ikut terbaca sebagai bagian dari
 * nominal. Yang dikirim ke server tetap nilai aslinya selama kolomnya belum
 * disunting; lihat `keNilaiKirim()`.
 */
function keDigit(nilai) {
  if (nilai === null || nilai === undefined || nilai === '') {
    return '';
  }

  const cocok = String(nilai)
    .trim()
    .match(/^-?(\d+)(?:[.,]\d+)?$/);

  return cocok ? cocok[1] : hanyaDigit(nilai);
}

function keNilaiKirim(nilai) {
  return nilai === null || nilai === undefined ? '' : String(nilai);
}

function kelompokkan(digit) {
  return digit.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/**
 * Posisi karet tepat setelah digit ke-`jumlahDigit` pada teks yang sudah
 * berformat.
 */
function posisiKaret(teksBerformat, jumlahDigit) {
  if (jumlahDigit <= 0) {
    return 0;
  }

  let dihitung = 0;

  for (let i = 0; i < teksBerformat.length; i++) {
    if (/\d/.test(teksBerformat[i])) {
      dihitung++;

      if (dihitung === jumlahDigit) {
        return i + 1;
      }
    }
  }

  return teksBerformat.length;
}
