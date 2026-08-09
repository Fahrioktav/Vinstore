import { useEffect, useRef, useState } from 'react';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { WarningIcon, DeleteIcon, DocumentIcon } from '@/components/icons';

/**
 * Pengganti `confirm()` dan `prompt()` bawaan browser.
 *
 * Bentuk pemakaiannya sengaja meniru `toast()` dari sonner — dipanggil sebagai
 * fungsi biasa dari mana saja, tanpa provider atau state di tiap halaman:
 *
 *     if (!(await confirmDialog({ title: 'Hapus produk?' }))) return;
 *
 *     const alasan = await promptDialog({ title: 'Alasan penolakan' });
 *     if (alasan === null) return;   // dibatalkan
 *
 * Bedanya dengan aslinya: keduanya asinkron. `confirm()` bawaan menghentikan
 * seluruh halaman sampai dijawab; dialog ini tidak, jadi pemanggilnya harus
 * `await`. Itu sebabnya tiap handler yang memakainya menjadi `async`.
 *
 * Nilai kembalian dibuat sama persis dengan aslinya supaya alur pemanggil tidak
 * perlu berubah: confirm -> true/false, prompt -> string atau null saat batal.
 */

/* ============================ Penyimpanan ============================ */

// Satu antrean sederhana. Dialog selalu tunggal, jadi cukup satu slot aktif —
// permintaan yang datang saat ada dialog terbuka menunggu gilirannya.
let current = null;
const queue = [];
const listeners = new Set();

function notify() {
  listeners.forEach((listener) => listener(current));
}

function open(request) {
  return new Promise((resolve) => {
    const entry = { ...request, resolve };

    if (current) {
      queue.push(entry);

      return;
    }

    current = entry;
    notify();
  });
}

function close(value) {
  if (!current) return;

  const { resolve } = current;

  current = queue.shift() ?? null;
  notify();

  resolve(value);
}

/* ============================ API publik ============================ */

/**
 * @returns {Promise<boolean>} true bila pengguna menekan tombol konfirmasi.
 */
export function confirmDialog({
  title,
  description = '',
  confirmLabel = 'Ya, lanjutkan',
  cancelLabel = 'Batal',
  variant = 'default',
} = {}) {
  return open({
    kind: 'confirm',
    title,
    description,
    confirmLabel,
    cancelLabel,
    variant,
  });
}

/**
 * Versi confirm untuk tindakan yang menghapus atau menolak sesuatu.
 * Tombolnya merah, supaya bahayanya terlihat sebelum diklik.
 */
export function confirmDestructive(options = {}) {
  return confirmDialog({
    confirmLabel: 'Ya, hapus',
    ...options,
    variant: 'destructive',
  });
}

/**
 * @returns {Promise<string|null>} isi masukan, atau null bila dibatalkan.
 *   String kosong berarti pengguna menekan lanjut tanpa mengisi — berbeda dari
 *   null, persis seperti `prompt()` bawaan.
 */
export function promptDialog({
  title,
  description = '',
  label = '',
  placeholder = '',
  defaultValue = '',
  confirmLabel = 'Kirim',
  cancelLabel = 'Batal',
  required = false,
  multiline = true,
  variant = 'default',
} = {}) {
  return open({
    kind: 'prompt',
    title,
    description,
    label,
    placeholder,
    defaultValue,
    confirmLabel,
    cancelLabel,
    required,
    multiline,
    variant,
  });
}

/* ============================ Komponen host ============================ */

const VARIANT_STYLES = {
  default: {
    button: 'bg-[#53685B] hover:bg-[#3c4a3e]',
    ring: 'bg-[#53685B]/10 text-[#53685B]',
  },
  destructive: {
    button: 'bg-red-600 hover:bg-red-700',
    ring: 'bg-red-100 text-red-600',
  },
};

/**
 * Dipasang sekali di tiap layout. Tanpa ini, confirmDialog() tidak akan pernah
 * menampilkan apa pun dan janjinya menggantung selamanya.
 */
export function DialogHost() {
  const [request, setRequest] = useState(current);
  const [value, setValue] = useState('');
  const [touched, setTouched] = useState(false);
  const inputRef = useRef(null);

  useEffect(() => {
    listeners.add(setRequest);

    return () => listeners.delete(setRequest);
  }, []);

  // Setiap permintaan baru memulai dengan isian bersih; tanpa ini sisa jawaban
  // dialog sebelumnya ikut terbawa ke dialog berikutnya.
  useEffect(() => {
    setValue(request?.defaultValue ?? '');
    setTouched(false);
  }, [request]);

  if (!request) return null;

  const isPrompt = request.kind === 'prompt';
  const styles = VARIANT_STYLES[request.variant] ?? VARIANT_STYLES.default;
  const isEmpty = value.trim() === '';
  const blocked = isPrompt && request.required && isEmpty;

  const submit = () => {
    if (blocked) {
      setTouched(true);
      inputRef.current?.focus();

      return;
    }

    close(isPrompt ? value : true);
  };

  const cancel = () => close(isPrompt ? null : false);

  const Icon =
    request.variant === 'destructive'
      ? DeleteIcon
      : isPrompt
        ? DocumentIcon
        : WarningIcon;

  return (
    <Dialog
      open
      onOpenChange={(isOpen) => {
        // Ditutup lewat Escape, klik latar, atau tombol silang — semuanya
        // diperlakukan sebagai pembatalan, sama seperti confirm() bawaan.
        if (!isOpen) cancel();
      }}
    >
      <DialogContent
        onSubmit={(e) => {
          e.preventDefault();
          submit();
        }}
      >
        <DialogHeader>
          <div
            className={`mb-1 flex h-11 w-11 items-center justify-center rounded-full ${styles.ring}`}
          >
            <Icon className="h-5 w-5" />
          </div>
          <DialogTitle>{request.title}</DialogTitle>
          {request.description && (
            <DialogDescription>{request.description}</DialogDescription>
          )}
        </DialogHeader>

        {isPrompt && (
          <div>
            {request.label && (
              <label
                htmlFor="vinstore-prompt-input"
                className="mb-1 block text-sm font-medium text-gray-700"
              >
                {request.label}
                {!request.required && (
                  <span className="ml-1 font-normal text-gray-400">
                    (opsional)
                  </span>
                )}
              </label>
            )}

            {request.multiline ? (
              <textarea
                id="vinstore-prompt-input"
                ref={inputRef}
                autoFocus
                rows={3}
                value={value}
                placeholder={request.placeholder}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                  // Enter mengirim, Shift+Enter membuat baris baru.
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    submit();
                  }
                }}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B] focus:outline-none"
              />
            ) : (
              <input
                id="vinstore-prompt-input"
                ref={inputRef}
                autoFocus
                type="text"
                value={value}
                placeholder={request.placeholder}
                onChange={(e) => setValue(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-[#53685B] focus:ring-2 focus:ring-[#53685B] focus:outline-none"
              />
            )}

            {blocked && touched && (
              <p className="mt-1 text-xs text-red-600">
                Bagian ini wajib diisi.
              </p>
            )}
          </div>
        )}

        <DialogFooter>
          <button
            type="button"
            onClick={cancel}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100"
          >
            {request.cancelLabel}
          </button>
          <button
            type="button"
            onClick={submit}
            className={`rounded-lg px-4 py-2 text-sm font-semibold text-white transition ${styles.button} ${
              blocked ? 'cursor-not-allowed opacity-60' : ''
            }`}
          >
            {request.confirmLabel}
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
