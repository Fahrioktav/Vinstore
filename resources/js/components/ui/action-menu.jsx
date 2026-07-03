import { Link } from '@inertiajs/react';
import { MoreVertical } from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

/**
 * Menu aksi berbentuk titik tiga (kebab) + dropdown.
 *
 * Props:
 * - items: array of action descriptors. Item `false`/`null` akan diabaikan
 *   sehingga bisa dipakai dengan kondisi (mis. `cond && {...}`).
 *   Bentuk item:
 *     { label, href }                       -> link (Inertia)
 *     { label, onClick }                    -> tombol aksi
 *     { label, onClick, variant: 'destructive' } -> aksi merah (hapus/tolak)
 *     { label, icon }                       -> emoji/elemen ikon opsional
 *     { separator: true }                   -> garis pemisah
 * - align: posisi dropdown ('end' default)
 */
export default function ActionMenu({ items = [], align = 'end' }) {
  const visible = items.filter(Boolean);
  if (visible.length === 0) {
    return <span className="text-xs text-gray-400">-</span>;
  }

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          aria-label="Aksi"
          className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:cursor-pointer hover:bg-gray-100 hover:text-gray-800 focus:outline-none"
        >
          <MoreVertical className="h-5 w-5" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align={align} className="w-48 border bg-white p-1 shadow-lg">
        {visible.map((item, i) => {
          if (item.separator) {
            return <DropdownMenuSeparator key={`sep-${i}`} />;
          }

          const content = (
            <span className="flex items-center gap-2">
              {item.icon ? <span className="text-sm">{item.icon}</span> : null}
              <span>{item.label}</span>
            </span>
          );

          const itemClass = cn(
            'cursor-pointer rounded-md px-3 py-2 text-sm font-medium text-gray-700 focus:bg-[#E9E19E] focus:text-black',
            item.variant === 'destructive' &&
              'text-red-600 focus:bg-red-50 focus:text-red-700'
          );

          if (item.href) {
            return (
              <DropdownMenuItem key={i} asChild className={itemClass}>
                <Link href={item.href}>{content}</Link>
              </DropdownMenuItem>
            );
          }

          return (
            <DropdownMenuItem
              key={i}
              className={itemClass}
              onSelect={(e) => {
                e.preventDefault();
                item.onClick?.();
              }}
            >
              {content}
            </DropdownMenuItem>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
