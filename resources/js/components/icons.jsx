/**
 * Daftar ikon terpusat.
 *
 * Seluruh halaman mengambil ikon dari berkas ini, BUKAN langsung dari
 * '@heroicons/react'. Dengan begitu satu konsep selalu memakai ikon yang sama
 * di seluruh aplikasi, dan mengganti ikon (atau bahkan pindah pustaka) cukup
 * disunting di satu tempat.
 *
 * Penamaannya mengikuti KONSEP domain, bukan bentuk gambarnya: `AuctionIcon`,
 * bukan `TrendingUpIcon`. Nama bentuk membuat pemanggilnya ikut terkunci pada
 * gambar tertentu.
 *
 * Setiap ikon punya UKURAN DEFAULT `h-5 w-5`, dan pemanggil boleh menimpanya
 * lewat className seperti biasa (`h-4 w-4`, `h-7 w-7`) — penimpaannya ditangani
 * `cn()` yang memakai tailwind-merge, jadi kelas terakhirlah yang menang.
 *
 * Dulu ukurannya sengaja tidak dipasang di sini, dengan alasan pemanggil yang
 * menentukan. Akibatnya satu pemanggil yang lupa menulis kelas ukuran
 * menghasilkan SVG tanpa lebar dan tinggi — dan SVG semacam itu memuai
 * memenuhi wadahnya, bukan mengecil. Ikon "Edit Photo" di halaman profil dan
 * tombol simpan di halaman edit toko sempat sebesar setengah layar karenanya.
 * Default ini membuat kelalaian itu tidak lagi merusak tata letak.
 */

import {
  // navigasi & identitas
  Bars3Icon,
  UserIcon as HeroUserIcon,
  UserCircleIcon,
  UserGroupIcon,
  ArrowRightStartOnRectangleIcon,
  QuestionMarkCircleIcon,
  BellIcon,
  EyeIcon,
  LockClosedIcon,

  // belanja & uang
  ShoppingCartIcon,
  ShoppingBagIcon,
  BanknotesIcon,
  CreditCardIcon,
  ReceiptPercentIcon,
  TruckIcon,
  BuildingStorefrontIcon,
  ChartBarIcon,

  // barang & kategori
  CubeIcon,
  ArchiveBoxIcon,
  Squares2X2Icon,
  RectangleStackIcon,
  FolderIcon,
  ScaleIcon,
  TagIcon,

  // lokasi
  MapPinIcon,
  MapIcon,
  ArrowsPointingOutIcon,

  // aksi
  MagnifyingGlassIcon,
  PencilSquareIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  ArrowPathIcon,
  PrinterIcon,
  ArrowsRightLeftIcon,
  PlusIcon,
  XMarkIcon,

  // status
  CheckIcon as HeroCheckIcon,
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  NoSymbolIcon,
  ClockIcon,
  TrophyIcon,
  SparklesIcon,
  LightBulbIcon,
  ArrowTrendingUpIcon,

  // dokumen & media
  DocumentTextIcon,
  ClipboardDocumentListIcon,
  CameraIcon as HeroCameraIcon,
  VideoCameraIcon,
  PhotoIcon,
  InboxIcon,

  // komunikasi
  EnvelopeIcon,
  PhoneIcon as HeroPhoneIcon,
  ChatBubbleLeftRightIcon,
} from '@heroicons/react/24/outline';

import {
  CheckBadgeIcon as SolidCheckBadgeIcon,
  ExclamationCircleIcon as SolidExclamationCircleIcon,
} from '@heroicons/react/24/solid';

import { cn } from '@/lib/utils';

/**
 * Bungkus ikon heroicons dengan ukuran default.
 *
 * `cn()` memakai tailwind-merge, sehingga className dari pemanggil menimpa
 * ukuran default alih-alih bertabrakan dengannya.
 */
function ikon(Icon) {
  return function VinstoreIcon({ className, ...props }) {
    return <Icon className={cn('h-5 w-5', className)} {...props} />;
  };
}

/* ===================== Navigasi & identitas ===================== */

export const MenuIcon = ikon(Bars3Icon);
export const UserIcon = ikon(HeroUserIcon);
export const ProfileIcon = ikon(UserCircleIcon);
export const UsersIcon = ikon(UserGroupIcon);
export const LogoutIcon = ikon(ArrowRightStartOnRectangleIcon);
export const HelpIcon = ikon(QuestionMarkCircleIcon);
export const NotificationIcon = ikon(BellIcon);
export const ViewIcon = ikon(EyeIcon);
export const LockIcon = ikon(LockClosedIcon);

/* ===================== Belanja & uang ===================== */

export const CartIcon = ikon(ShoppingCartIcon);
export const OrderIcon = ikon(ShoppingBagIcon);
export const MoneyIcon = ikon(BanknotesIcon);
export const PaymentIcon = ikon(CreditCardIcon);
export const InvoiceIcon = ikon(ReceiptPercentIcon);
export const ShippingIcon = ikon(TruckIcon);
export const StoreIcon = ikon(BuildingStorefrontIcon);
export const StatsIcon = ikon(ChartBarIcon);

/* ===================== Barang & kategori ===================== */

export const ProductIcon = ikon(CubeIcon);
export const CategoryIcon = ikon(Squares2X2Icon);
export const CategoryFolderIcon = ikon(FolderIcon);
export const StockIcon = ikon(RectangleStackIcon);
export const WeightIcon = ikon(ScaleIcon);
export const PriceTagIcon = ikon(TagIcon);

/**
 * Jenis pengemasan. Peti kayu memakai ikon peti tertutup, pengemasan standar
 * memakai ikon kardus — dua bentuk yang jelas berbeda sekilas pandang.
 */
export const PackagingStandardIcon = ikon(CubeIcon);
export const PackagingWoodIcon = ikon(ArchiveBoxIcon);

/* ===================== Lokasi ===================== */

export const LocationIcon = ikon(MapPinIcon);
export const MapViewIcon = ikon(MapIcon);
export const DistanceIcon = ikon(ArrowsPointingOutIcon);

/* ===================== Aksi ===================== */

export const SearchIcon = ikon(MagnifyingGlassIcon);
export const EditIcon = ikon(PencilSquareIcon);
export const DeleteIcon = ikon(TrashIcon);
export const SaveIcon = ikon(ArrowDownTrayIcon);
export const RefreshIcon = ikon(ArrowPathIcon);
export const PrintIcon = ikon(PrinterIcon);
export const AddIcon = ikon(PlusIcon);
export const CloseIcon = ikon(XMarkIcon);

/* ===================== Status ===================== */

export const CheckIcon = ikon(HeroCheckIcon);
export const SuccessIcon = ikon(CheckCircleIcon);
export const FailIcon = ikon(XCircleIcon);
export const WarningIcon = ikon(ExclamationTriangleIcon);
export const BlockedIcon = ikon(NoSymbolIcon);
export const PendingIcon = ikon(ClockIcon);
export const WinnerIcon = ikon(TrophyIcon);
export const CelebrateIcon = ikon(SparklesIcon);

/* ===================== Fitur khas Vinstore ===================== */

/** Tukar Tambah: panah dua arah, menggantikan karakter ⇄ yang dulu dipakai. */
export const TradeInIcon = ikon(ArrowsRightLeftIcon);

/** Lelang: harga yang terus naik. Heroicons tidak punya ikon palu lelang. */
export const AuctionIcon = ikon(ArrowTrendingUpIcon);

/** Tebak harga: bola lampu, sebuah tebakan. */
export const GuessIcon = ikon(LightBulbIcon);

/** Tanda produk sudah divalidasi keasliannya. */
export const BadgeIcon = ikon(SolidCheckBadgeIcon);

/* ===================== Dokumen & media ===================== */

export const CertificateIcon = ikon(DocumentTextIcon);
export const DocumentIcon = ikon(DocumentTextIcon);
export const ListIcon = ikon(ClipboardDocumentListIcon);
export const CameraIcon = ikon(HeroCameraIcon);
export const VideoIcon = ikon(VideoCameraIcon);
export const ImageIcon = ikon(PhotoIcon);
export const EmptyInboxIcon = ikon(InboxIcon);

/** Dipakai pada tombol ganti foto profil/toko. */
export const EditPhotoIcon = ikon(HeroCameraIcon);

/* ===================== Komunikasi ===================== */

export const MailIcon = ikon(EnvelopeIcon);
export const PhoneIcon = ikon(HeroPhoneIcon);
export const ChatIcon = ikon(ChatBubbleLeftRightIcon);

/* ===================== Bentuk khusus ===================== */

/**
 * Ikon galat berbentuk solid. Sengaja solid, bukan outline: pesan kesalahan
 * harus lebih dulu tertangkap mata daripada ikon di sekitarnya.
 */
export const ErrorIcon = ikon(SolidExclamationCircleIcon);

/**
 * Penanda "sedang memproses".
 *
 * Heroicons tidak menyediakan spinner, jadi dipakai ikon panah berputar yang
 * diberi animasi. `animate-spin` sudah menempel di sini supaya tiap pemanggil
 * tidak perlu mengingatnya.
 */
export function SpinnerIcon({ className = '', ...props }) {
  return (
    <ArrowPathIcon
      className={cn('h-5 w-5 animate-spin', className)}
      {...props}
    />
  );
}

/**
 * Keadaan kosong: tidak ada data untuk ditampilkan.
 * Dipakai menggantikan emoji 🕯️ dan 📭 pada daftar yang kosong.
 */
export const EmptyStateIcon = ikon(InboxIcon);
