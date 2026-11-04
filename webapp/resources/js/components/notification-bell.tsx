import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

/**
 * Lonceng notifikasi (§2.6, Q8, Tiket 15) — angka = jumlah entri kotak
 * masuk yang belum dibaca pengguna yang login (shared prop
 * unreadNotificationsCount). Klik menuju daftar notifikasi.
 */
export function NotificationBell() {
    const unread = (usePage<SharedData>().props.unreadNotificationsCount as number | undefined) ?? 0;

    return (
        <Link
            href={route('notifications.index')}
            aria-label={`Notifikasi${unread > 0 ? `, ${unread} belum dibaca` : ''}`}
            className="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-700 hover:bg-neutral-100 dark:text-neutral-200 dark:hover:bg-neutral-800"
        >
            <Bell className="h-5 w-5" />
            {unread > 0 && (
                <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                    {unread > 99 ? '99+' : unread}
                </span>
            )}
        </Link>
    );
}
