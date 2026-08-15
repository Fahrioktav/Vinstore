import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/main-layout';
import { formatIDR, storageMedia } from '@/lib/utils';

const statusColors = {
  pending: 'bg-gray-100 text-gray-700',
  paid: 'bg-blue-100 text-blue-700',
  applied: 'bg-green-100 text-green-700',
  refund_requested: 'bg-yellow-100 text-yellow-700',
  refunded: 'bg-green-100 text-green-700',
  forfeited: 'bg-red-100 text-red-700',
  expired: 'bg-gray-100 text-gray-500',
};

const statusLabels = {
  pending: 'Menunggu pembayaran',
  paid: 'Aktif',
  applied: 'Dipakai sebagai uang muka',
  refund_requested: 'Pengembalian diproses admin',
  refunded: 'Sudah dikembalikan',
  forfeited: 'Hangus',
  expired: 'Kedaluwarsa',
};

/**
 * Daftar uang jaminan lelang milik pembeli.
 *
 * Di sinilah peserta yang kalah meminta uangnya kembali. Admin yang akan
 * mentransfernya ke rekening yang diisi di halaman ini.
 */
export default function MyAuctionDeposits() {
  const { deposits, flash } = usePage().props;

  return (
    <>
      <Head title="Deposit Lelang" />
      <div className="mx-auto max-w-4xl px-4 py-6 sm:py-8">
        {flash?.success && (
          <div className="mb-6 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-green-700">
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="mb-6 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-red-700">
            {flash.error}
          </div>
        )}

        <div className="rounded-2xl bg-white p-6 shadow-md">
          <h2 className="text-2xl font-bold text-[#53685B]">Deposit Lelang</h2>
          <p className="mt-2 mb-6 text-sm text-gray-500">
            Uang jaminan yang Anda bayarkan untuk mengikuti lelang bernilai
            tinggi. Bila Anda menang, deposit menjadi uang muka pembayaran. Bila
            kalah, ajukan pengembaliannya di sini dan admin akan mentransfernya
            ke rekening Anda.
          </p>

          {deposits.length === 0 ? (
            <div className="rounded-lg bg-gray-50 px-4 py-10 text-center text-gray-500">
              Anda belum pernah membayar deposit lelang.
            </div>
          ) : (
            <div className="space-y-4">
              {deposits.map((deposit) => (
                <DepositCard key={deposit.public_id} deposit={deposit} />
              ))}
            </div>
          )}
        </div>
      </div>
    </>
  );
}

function DepositCard({ deposit }) {
  const [showForm, setShowForm] = useState(false);

  return (
    <div className="rounded-lg border border-gray-200 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link
            href={`/auctions/${deposit.auction?.public_id}`}
            className="font-semibold text-[#2F3E46] hover:underline"
          >
            {deposit.auction?.name || 'Lelang tidak tersedia'}
          </Link>
          <p className="text-xs text-gray-500">
            {deposit.auction?.store?.store_name || '-'} · {deposit.public_id}
          </p>
        </div>
        <div className="text-right">
          <p className="font-bold text-[#B77C4C]">
            {formatIDR(deposit.amount)}
          </p>
          <span
            className={`mt-1 inline-block rounded-full px-3 py-1 text-xs font-semibold ${statusColors[deposit.status] || 'bg-gray-100 text-gray-700'}`}
          >
            {statusLabels[deposit.status] || deposit.status}
          </span>
        </div>
      </div>

      {deposit.admin_note && (
        <p className="mt-3 rounded bg-gray-50 px-3 py-2 text-xs text-gray-600">
          Catatan admin: {deposit.admin_note}
        </p>
      )}

      {deposit.transfer_proof && (
        <a
          href={storageMedia(deposit.transfer_proof)}
          target="_blank"
          rel="noreferrer"
          className="mt-3 inline-block text-xs font-semibold text-[#53685B] underline"
        >
          Lihat bukti transfer
        </a>
      )}

      {deposit.can_request_refund ? (
        showForm ? (
          <RefundForm deposit={deposit} onCancel={() => setShowForm(false)} />
        ) : (
          <button
            type="button"
            onClick={() => setShowForm(true)}
            className="mt-3 rounded-lg bg-[#53685B] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#3c4a3e]"
          >
            Ajukan Pengembalian
          </button>
        )
      ) : (
        deposit.refund_block_reason && (
          <p className="mt-3 text-xs text-gray-500">
            {deposit.refund_block_reason}
          </p>
        )
      )}
    </div>
  );
}

function RefundForm({ deposit, onCancel }) {
  const { data, setData, post, processing, errors } = useForm({
    bank_name: '',
    account_number: '',
    account_holder: '',
  });

  const submit = (e) => {
    e.preventDefault();
    post(`/deposit-lelang/${deposit.public_id}/refund`, {
      preserveScroll: true,
    });
  };

  return (
    <form
      onSubmit={submit}
      className="mt-4 space-y-3 rounded-lg bg-gray-50 p-4"
    >
      <p className="text-xs text-gray-500">
        Isi rekening tujuan pengembalian. Pastikan namanya sesuai dengan pemilik
        rekening — admin memakai data ini apa adanya saat mentransfer.
      </p>

      <div className="grid gap-3 sm:grid-cols-3">
        <div>
          <label className="mb-1 block text-xs font-semibold text-gray-700">
            Nama Bank
          </label>
          <input
            type="text"
            value={data.bank_name}
            onChange={(e) => setData('bank_name', e.target.value)}
            placeholder="BCA"
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
            required
          />
          {errors.bank_name && (
            <p className="mt-1 text-xs text-red-600">{errors.bank_name}</p>
          )}
        </div>
        <div>
          <label className="mb-1 block text-xs font-semibold text-gray-700">
            Nomor Rekening
          </label>
          <input
            type="text"
            value={data.account_number}
            onChange={(e) => setData('account_number', e.target.value)}
            placeholder="1234567890"
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
            required
          />
          {errors.account_number && (
            <p className="mt-1 text-xs text-red-600">{errors.account_number}</p>
          )}
        </div>
        <div>
          <label className="mb-1 block text-xs font-semibold text-gray-700">
            Atas Nama
          </label>
          <input
            type="text"
            value={data.account_holder}
            onChange={(e) => setData('account_holder', e.target.value)}
            placeholder="Nama pemilik rekening"
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
            required
          />
          {errors.account_holder && (
            <p className="mt-1 text-xs text-red-600">{errors.account_holder}</p>
          )}
        </div>
      </div>

      <div className="flex gap-2">
        <button
          type="submit"
          disabled={processing}
          className="rounded-lg bg-[#53685B] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#3c4a3e] disabled:opacity-50"
        >
          {processing ? 'Mengirim...' : 'Kirim Pengajuan'}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600"
        >
          Batal
        </button>
      </div>
    </form>
  );
}

MyAuctionDeposits.layout = (page) => (
  <MainLayout title="Deposit Lelang" heroText="Deposit Lelang">
    {page}
  </MainLayout>
);
