import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type NotificationItem = {
    id: number;
    subject: string | null;
    body: string | null;
    trigger: string | null;
    trigger_label: string | null;
    charge_type: string | null;
    charge_label: string | null;
    container_number: string | null;
    free_time_date: string | null;
    job_id: number | null;
    job_number: string | null;
    sent_at: string | null;
    is_read: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifikasi', href: '/notifications' }];

export default function NotificationsIndex({ notifications, unread_count }: { notifications: Paginated<NotificationItem>; unread_count: number }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifikasi" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-medium">Notifikasi</h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Alarm Free Time H-2 / H-0 / H+1 untuk kontainer Anda (§2.6, Q8). {unread_count} belum dibaca.
                        </p>
                    </div>
                    {unread_count > 0 && (
                        <Button variant="outline" onClick={() => router.post(route('notifications.read-all'))}>
                            Tandai semua sudah dibaca
                        </Button>
                    )}
                </div>

                {notifications.data.length === 0 ? (
                    <p className="text-muted-foreground text-sm">Belum ada notifikasi.</p>
                ) : (
                    <div className="flex flex-col gap-3">
                        {notifications.data.map((item) => (
                            <div
                                key={item.id}
                                className={`rounded-xl border p-4 ${item.is_read ? 'border-neutral-200 bg-white dark:border-neutral-800' : 'border-blue-200 bg-blue-50/60 dark:border-blue-900 dark:bg-blue-950/30'}`}
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    {item.charge_label && <Badge variant="outline">{item.charge_label}</Badge>}
                                    {item.trigger_label && <Badge variant="secondary">{item.trigger_label}</Badge>}
                                    {!item.is_read && <Badge>Belum dibaca</Badge>}
                                    <span className="text-muted-foreground ml-auto text-xs">{item.sent_at}</span>
                                </div>
                                <p className="mt-2 text-sm font-medium">{item.subject}</p>
                                {item.free_time_date && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Kontainer {item.container_number} · Last Free Date {item.free_time_date}
                                    </p>
                                )}
                                <div className="mt-3 flex flex-wrap items-center gap-3">
                                    {item.job_id && item.job_number && (
                                        <Link href={route('jobs.show', item.job_id)} className="text-primary text-sm hover:underline">
                                            Lihat Job {item.job_number}
                                        </Link>
                                    )}
                                    {!item.is_read && (
                                        <Button variant="ghost" size="sm" onClick={() => router.post(route('notifications.read', item.id))}>
                                            Tandai sudah dibaca
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {notifications.last_page > 1 && (
                    <div className="text-muted-foreground text-xs">
                        Halaman {notifications.current_page} dari {notifications.last_page} · total {notifications.total}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
