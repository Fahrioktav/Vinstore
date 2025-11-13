// Format angka ke Rupiah
const rupiah = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  minimumFractionDigits: 0, // biar nggak ada ,00
});

export function formatIDR(number) {
  return rupiah.format(number);
}
