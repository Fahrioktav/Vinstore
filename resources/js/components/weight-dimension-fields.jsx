import { AuthInput, AuthLabel } from '@/components/auth/auth-layout';

/**
 * Isian berat & dimensi paket.
 *
 * Dipakai ketiga form lelang (tambah, edit, ajukan ulang) supaya keterangannya
 * tidak berbeda-beda antar halaman. Bentuknya menyamai blok yang sama pada form
 * produk biasa — komponen ini tidak menambah aturan baru, hanya menyatukan
 * markup yang kalau tidak begitu akan ditulis tiga kali.
 *
 * Dua cara pakai:
 *  - Tanpa `onChange`: field tak terkendali, untuk `<Form>` Inertia yang membaca
 *    nilai langsung dari DOM.
 *  - Dengan `onChange(field, value)`: field terkendali, untuk `useForm`.
 */
export default function WeightDimensionFields({ values = {}, onChange }) {
  const controlled = typeof onChange === 'function';

  const bind = (field, fallback = '') =>
    controlled
      ? {
          value: values[field] ?? '',
          onChange: (e) => onChange(field, e.target.value),
        }
      : { defaultValue: values[field] ?? fallback };

  return (
    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
      <p className="mb-4 text-sm font-semibold text-[#2F3E46]">
        Berat &amp; Dimensi Paket
      </p>

      <div className="grid gap-4 md:grid-cols-4">
        <div>
          <AuthLabel htmlFor="weight">Berat (gram)</AuthLabel>
          <AuthInput
            id="weight"
            type="number"
            name="weight"
            required
            min="1"
            {...bind('weight', 1000)}
          />
        </div>
        <div>
          <AuthLabel htmlFor="length">Panjang (cm)</AuthLabel>
          <AuthInput
            id="length"
            type="number"
            name="length"
            min="1"
            placeholder="opsional"
            {...bind('length')}
          />
        </div>
        <div>
          <AuthLabel htmlFor="width">Lebar (cm)</AuthLabel>
          <AuthInput
            id="width"
            type="number"
            name="width"
            min="1"
            placeholder="opsional"
            {...bind('width')}
          />
        </div>
        <div>
          <AuthLabel htmlFor="height">Tinggi (cm)</AuthLabel>
          <AuthInput
            id="height"
            type="number"
            name="height"
            min="1"
            placeholder="opsional"
            {...bind('height')}
          />
        </div>
      </div>
    </div>
  );
}
