import { formatIDR } from '@/lib/utils';
import { ListIcon, WinnerIcon } from '@/components/icons';

/**
 * Papan tebakan harga — versi tebak harga dari "Riwayat Bid" pada lelang.
 *
 * Tampilannya berbeda menurut status, dan bedanya disengaja (lihat
 * PriceGuessService::leaderboard()):
 *
 * - Selama periode berjalan: urut waktu, terbaru di atas, tanpa peringkat, dan
 *   nominal tebakan peserta lain ditutup — hanya tebakan sendiri yang terbuka.
 *   Dua tebakan yang mengapit sudah cukup untuk menyimpulkan letak harga
 *   diskonnya, sehingga membukanya menguntungkan yang menebak paling akhir.
 * - Setelah selesai: urut kedekatan, lengkap dengan peringkat dan pemenang.
 */
export default function GuessLeaderboard({ entries = [], status }) {
  const finished = status === 'ended' || status === 'public';

  const formatTime = (value) =>
    value
      ? new Intl.DateTimeFormat('id-ID', {
          day: '2-digit',
          month: 'short',
          hour: '2-digit',
          minute: '2-digit',
        }).format(new Date(value))
      : '-';

  return (
    <div className="mt-5 rounded-lg border border-gray-200 bg-white p-4">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h4 className="flex items-center gap-2 font-bold text-[#53685B]">
          {finished ? (
            <>
              <WinnerIcon className="h-5 w-5" />
              Hasil Tebakan
            </>
          ) : (
            <>
              <ListIcon className="h-5 w-5" />
              Tebakan yang Sudah Masuk
            </>
          )}
        </h4>
        <span className="text-xs text-gray-500">{entries.length} tebakan</span>
      </div>

      <p className="mb-3 text-xs text-gray-500">
        {finished
          ? 'Diurutkan dari tebakan yang paling mendekati harga diskon.'
          : 'Diurutkan dari yang terbaru. Nominal tebakan peserta lain dan peringkatnya baru dibuka setelah sesi selesai, agar tidak ada yang bisa menebak dari tebakan orang lain.'}
      </p>

      <div className="overflow-x-auto rounded-lg border border-gray-200">
        <table className="w-full min-w-[26rem] text-sm">
          <thead className="bg-[#53685B] text-white">
            <tr>
              <th className="px-3 py-2 text-left">
                {finished ? '#' : 'Waktu'}
              </th>
              <th className="px-3 py-2 text-left">Penebak</th>
              <th className="px-3 py-2 text-right">Tebakan</th>
              {finished && <th className="px-3 py-2 text-right">Selisih</th>}
            </tr>
          </thead>
          <tbody>
            {entries.length > 0 ? (
              entries.map((entry) => (
                <tr
                  key={entry.public_id}
                  className={`border-t ${
                    entry.is_winner
                      ? 'bg-green-50'
                      : entry.is_mine
                        ? 'bg-amber-50'
                        : ''
                  }`}
                >
                  <td className="px-3 py-2 text-xs whitespace-nowrap text-gray-500">
                    {finished ? entry.rank : formatTime(entry.created_at)}
                  </td>
                  <td className="px-3 py-2">
                    {entry.username}
                    {entry.is_mine && (
                      <span className="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                        ANDA
                      </span>
                    )}
                    {entry.is_winner && (
                      <span className="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-semibold text-green-700">
                        PEMENANG
                      </span>
                    )}
                    {entry.is_exact && (
                      <span className="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">
                        TEPAT
                      </span>
                    )}
                  </td>
                  <td className="px-3 py-2 text-right font-semibold">
                    {entry.amount === null || entry.amount === undefined ? (
                      <span
                        className="text-gray-400"
                        title="Nominal tebakan peserta lain dibuka setelah sesi selesai"
                      >
                        ???
                      </span>
                    ) : (
                      formatIDR(entry.amount)
                    )}
                  </td>
                  {finished && (
                    <td className="px-3 py-2 text-right text-xs text-gray-500">
                      {entry.difference === null ||
                      entry.difference === undefined
                        ? '—'
                        : entry.difference < 1
                          ? 'Tepat'
                          : `± ${formatIDR(entry.difference)}`}
                    </td>
                  )}
                </tr>
              ))
            ) : (
              <tr>
                <td
                  colSpan={finished ? 4 : 3}
                  className="px-3 py-6 text-center text-gray-500"
                >
                  Belum ada yang menebak. Jadilah yang pertama!
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {finished && entries.some((entry) => entry.difference === null) && (
        <p className="mt-2 text-xs text-gray-400">
          Selisih baru ditampilkan setelah harga diskonnya dibuka untuk umum.
        </p>
      )}
    </div>
  );
}
