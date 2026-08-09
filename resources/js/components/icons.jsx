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
 * Ukuran default sengaja tidak dipasang di sini — pemanggil menentukannya lewat
 * className, mengikuti kebiasaan Tailwind di project ini (`h-5 w-5`).
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

/* ===================== Navigasi & identitas ===================== */

export const MenuIcon = Bars3Icon;
export const UserIcon = HeroUserIcon;
export const ProfileIcon = UserCircleIcon;
export const UsersIcon = UserGroupIcon;
export const LogoutIcon = ArrowRightStartOnRectangleIcon;
export const HelpIcon = QuestionMarkCircleIcon;
export const NotificationIcon = BellIcon;
export const ViewIcon = EyeIcon;
export const LockIcon = LockClosedIcon;

/* ===================== Belanja & uang ===================== */

export const CartIcon = ShoppingCartIcon;
export const OrderIcon = ShoppingBagIcon;
export const MoneyIcon = BanknotesIcon;
export const PaymentIcon = CreditCardIcon;
export const InvoiceIcon = ReceiptPercentIcon;
export const ShippingIcon = TruckIcon;
export const StoreIcon = BuildingStorefrontIcon;
export const StatsIcon = ChartBarIcon;

/* ===================== Barang & kategori ===================== */

export const ProductIcon = CubeIcon;
export const CategoryIcon = Squares2X2Icon;
export const CategoryFolderIcon = FolderIcon;
export const StockIcon = RectangleStackIcon;
export const WeightIcon = ScaleIcon;
export const PriceTagIcon = TagIcon;

/**
 * Jenis pengemasan. Peti kayu memakai ikon peti tertutup, pengemasan standar
 * memakai ikon kardus — dua bentuk yang jelas berbeda sekilas pandang.
 */
export const PackagingStandardIcon = CubeIcon;
export const PackagingWoodIcon = ArchiveBoxIcon;

/* ===================== Lokasi ===================== */

export const LocationIcon = MapPinIcon;
export const MapViewIcon = MapIcon;
export const DistanceIcon = ArrowsPointingOutIcon;

/* ===================== Aksi ===================== */

export const SearchIcon = MagnifyingGlassIcon;
export const EditIcon = PencilSquareIcon;
export const DeleteIcon = TrashIcon;
export const SaveIcon = ArrowDownTrayIcon;
export const RefreshIcon = ArrowPathIcon;
export const PrintIcon = PrinterIcon;
export const AddIcon = PlusIcon;
export const CloseIcon = XMarkIcon;

/* ===================== Status ===================== */

export const CheckIcon = HeroCheckIcon;
export const SuccessIcon = CheckCircleIcon;
export const FailIcon = XCircleIcon;
export const WarningIcon = ExclamationTriangleIcon;
export const BlockedIcon = NoSymbolIcon;
export const PendingIcon = ClockIcon;
export const WinnerIcon = TrophyIcon;
export const CelebrateIcon = SparklesIcon;

/* ===================== Fitur khas Vinstore ===================== */

/** Barter: panah dua arah, menggantikan karakter ⇄ yang dulu dipakai. */
export const BarterIcon = ArrowsRightLeftIcon;

/** Lelang: harga yang terus naik. Heroicons tidak punya ikon palu lelang. */
export const AuctionIcon = ArrowTrendingUpIcon;

/** Tebak harga: bola lampu, sebuah tebakan. */
export const GuessIcon = LightBulbIcon;

/** Tanda produk sudah divalidasi keasliannya. */
export const BadgeIcon = SolidCheckBadgeIcon;

/* ===================== Dokumen & media ===================== */

export const CertificateIcon = DocumentTextIcon;
export const DocumentIcon = DocumentTextIcon;
export const ListIcon = ClipboardDocumentListIcon;
export const CameraIcon = HeroCameraIcon;
export const VideoIcon = VideoCameraIcon;
export const ImageIcon = PhotoIcon;
export const EmptyInboxIcon = InboxIcon;

/** Dipakai pada tombol ganti foto profil/toko. */
export const EditPhotoIcon = HeroCameraIcon;

/* ===================== Komunikasi ===================== */

export const MailIcon = EnvelopeIcon;
export const PhoneIcon = HeroPhoneIcon;
export const ChatIcon = ChatBubbleLeftRightIcon;

/* ===================== Bentuk khusus ===================== */

/**
 * Ikon galat berbentuk solid. Sengaja solid, bukan outline: pesan kesalahan
 * harus lebih dulu tertangkap mata daripada ikon di sekitarnya.
 */
export const ErrorIcon = SolidExclamationCircleIcon;

/**
 * Penanda "sedang memproses".
 *
 * Heroicons tidak menyediakan spinner, jadi dipakai ikon panah berputar yang
 * diberi animasi. `animate-spin` sudah menempel di sini supaya tiap pemanggil
 * tidak perlu mengingatnya.
 */
export function SpinnerIcon({ className = '' }) {
  return <ArrowPathIcon className={`animate-spin ${className}`} />;
}

/**
 * Keadaan kosong: tidak ada data untuk ditampilkan.
 * Dipakai menggantikan emoji 🕯️ dan 📭 pada daftar yang kosong.
 */
export const EmptyStateIcon = InboxIcon;
