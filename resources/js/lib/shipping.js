/**
 * Cerminan sisi klien dari App\Services\ShippingCostService.
 *
 * Fungsinya HANYA untuk pratinjau: pembeli perlu melihat ongkir berubah saat
 * memindahkan titik antar tanpa menunggu server. Yang benar-benar ditagihkan
 * selalu hasil hitungan PHP — server tidak pernah memakai angka dari sini.
 *
 * Kalau rumus di PHP berubah, ubah file ini juga; tarifnya sendiri datang dari
 * config/marketplace.php lewat prop `feeRates` sehingga tidak perlu disalin.
 */

const EARTH_RADIUS_KM = 6371;

export function haversineKm(lat1, lng1, lat2, lng2) {
  const toRad = (deg) => (deg * Math.PI) / 180;

  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(lat1)) *
      Math.cos(toRad(lat2)) *
      Math.sin(dLng / 2) *
      Math.sin(dLng / 2);

  return EARTH_RADIUS_KM * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

const isNumber = (value) =>
  value !== null &&
  value !== undefined &&
  value !== '' &&
  !isNaN(Number(value));

/**
 * Jarak toko -> titik antar, atau null bila salah satu titik tidak diketahui.
 * null berarti "tidak bisa dihitung", bukan "nol".
 */
export function distanceKm(store, destLat, destLng) {
  if (!store || !isNumber(store.latitude) || !isNumber(store.longitude)) {
    return null;
  }

  if (!isNumber(destLat) || !isNumber(destLng)) {
    return null;
  }

  return (
    Math.round(
      haversineKm(
        Number(store.latitude),
        Number(store.longitude),
        Number(destLat),
        Number(destLng)
      ) * 100
    ) / 100
  );
}

export function shippingCost(km, method, rates) {
  const config = rates.shipping;
  const normalized = method === 'express' ? 'express' : 'standard';

  if (km === null) {
    return Number(config.flat_fallback[normalized] ?? 0);
  }

  const billable =
    Math.round(
      Math.min(
        Math.max(km, Number(config.min_distance_km)),
        Number(config.max_distance_km)
      ) * 100
    ) / 100;

  const multiplier = Number(config.method_multiplier[normalized] ?? 1);

  return Math.round(
    (Number(config.base_fee) + billable * Number(config.per_km)) * multiplier
  );
}

export function weightFee(totalGram, rates) {
  if (totalGram <= 0) return 0;

  return Math.ceil(totalGram / 1000) * Number(rates.weight.per_kg);
}

/**
 * Jenis pengemasan yang valid, atau jenis default bila pilihannya tidak
 * dikenal. Cermin ShippingCostService::normalizePackagingType().
 */
export function normalizePackagingType(type, rates) {
  return rates.packaging.options?.[type] ? type : rates.packaging.default;
}

export function packagingOption(type, rates) {
  return rates.packaging.options[normalizePackagingType(type, rates)];
}

export function packagingFee(quantity, firstOfStore, rates, type) {
  const option = packagingOption(type, rates);

  return (
    (firstOfStore ? Number(option.base_fee) : 0) +
    Number(option.per_item_fee) * Math.max(1, quantity)
  );
}

/**
 * Berat volumetrik satu unit dalam gram: (P x L x T) / pembagi kilogram.
 * Nol bila dimensinya belum diisi seller.
 */
export function volumetricWeightGram(product, rates) {
  const length = Number(product?.length ?? 0);
  const width = Number(product?.width ?? 0);
  const height = Number(product?.height ?? 0);

  if (length <= 0 || width <= 0 || height <= 0) return 0;

  const divisor = Math.max(1, Number(rates.weight.volumetric_divisor));

  return Math.round(((length * width * height) / divisor) * 1000);
}

export function serviceFee(subtotal, rates) {
  if (subtotal <= 0) return 0;

  const config = rates.service_fee;
  const fee = Math.round((subtotal * Number(config.percent)) / 100);

  return Math.min(Math.max(fee, Number(config.min)), Number(config.max));
}

export function productWeightGram(product, rates) {
  const weight = Number(product?.weight ?? 0);

  return weight > 0 ? weight : Number(rates.weight.default_gram);
}

/**
 * Rincian biaya satu baris pesanan.
 *
 * @param {boolean} firstOfStore Baris pertama dari toko ini. Ongkir dan biaya
 *   peti hanya ditagih sekali per toko karena barang dari toko yang sama
 *   dikirim dalam satu paket.
 */
export function quoteLine({
  product,
  quantity,
  unitPrice,
  method,
  destLat,
  destLng,
  firstOfStore = true,
  packagingType,
  rates,
}) {
  const qty = Math.max(1, Number(quantity) || 1);
  const subtotal = Number(unitPrice) * qty;

  const km = distanceKm(product?.store, destLat, destLng);
  const shipping = firstOfStore ? shippingCost(km, method, rates) : 0;

  // Yang ditagih adalah berat terbesar antara berat asli dan volumetrik.
  const actualGram = productWeightGram(product, rates) * qty;
  const volumetricGram = volumetricWeightGram(product, rates) * qty;
  const gram = Math.max(actualGram, volumetricGram);

  const line = {
    distance_km: km,
    shipping_cost: shipping,
    weight_gram: gram,
    actual_weight_gram: actualGram,
    volumetric_weight_gram: volumetricGram,
    weight_fee: weightFee(gram, rates),
    packaging_type: normalizePackagingType(packagingType, rates),
    packaging_fee: packagingFee(qty, firstOfStore, rates, packagingType),
    service_fee: serviceFee(subtotal, rates),
    subtotal,
  };

  line.total =
    line.subtotal +
    line.shipping_cost +
    line.weight_fee +
    line.packaging_fee +
    line.service_fee;

  return line;
}

/**
 * Jumlahkan beberapa baris menjadi satu ringkasan untuk kotak pembayaran.
 */
export function summarize(lines) {
  return lines.reduce(
    (total, line) => ({
      subtotal: total.subtotal + line.subtotal,
      shipping_cost: total.shipping_cost + line.shipping_cost,
      weight_fee: total.weight_fee + line.weight_fee,
      weight_gram: total.weight_gram + line.weight_gram,
      actual_weight_gram: total.actual_weight_gram + line.actual_weight_gram,
      volumetric_weight_gram:
        total.volumetric_weight_gram + line.volumetric_weight_gram,
      packaging_fee: total.packaging_fee + line.packaging_fee,
      service_fee: total.service_fee + line.service_fee,
      total: total.total + line.total,
    }),
    {
      subtotal: 0,
      shipping_cost: 0,
      weight_fee: 0,
      weight_gram: 0,
      actual_weight_gram: 0,
      volumetric_weight_gram: 0,
      packaging_fee: 0,
      service_fee: 0,
      total: 0,
    }
  );
}

/**
 * Kunci pengelompokan sebuah baris keranjang.
 *
 * Harus sama persis dengan yang dipakai OrderController::checkoutFromCart(),
 * karena kunci inilah yang dikirim sebagai `store_options[...]`.
 */
export function storeKeyOf(item) {
  return item?.product?.store?.public_id ?? 'none';
}

/**
 * Hitung rincian seluruh isi keranjang.
 *
 * Metode pengiriman dan jenis pengemasan dipilih PER TOKO, jadi `storeOptions`
 * berupa peta { [storeKey]: { shipping_method, packaging_type } }. Ongkir dan
 * biaya kemasan dasar tetap ditagih sekali per toko — barang dari toko yang
 * sama masuk satu paket.
 */
export function quoteCart({
  cartItems,
  storeOptions = {},
  destLat,
  destLng,
  rates,
}) {
  const seenStores = new Set();

  return cartItems.map((item) => {
    const storeKey = storeKeyOf(item);
    const firstOfStore = !seenStores.has(storeKey);
    seenStores.add(storeKey);

    const options = storeOptions[storeKey] ?? {};

    return {
      item,
      storeKey,
      ...quoteLine({
        product: item.product,
        quantity: item.quantity,
        unitPrice: Number(item.product?.price ?? 0),
        method: options.shipping_method ?? 'standard',
        destLat,
        destLng,
        firstOfStore,
        packagingType: options.packaging_type,
        rates,
      }),
    };
  });
}
