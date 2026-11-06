import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type FreeTimeInfo = {
    remaining_days: number;
    last_free_date: string | null;
    lampu: 'hijau' | 'kuning' | 'merah';
    containers_running: number;
};

type DashboardJobRow = {
    id: number;
    job_number: string;
    importir_name: string | null;
    cached_status: string;
    problem_flag: string | null;
    free_time: FreeTimeInfo | null;
    free_time_unavailable: boolean;
};

type ActivityItem = {
    id: number;
    action: string;
    object_type: string;
    object_id: number;
    reason: string | null;
    user_name: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type DashboardProps = {
    stats: {
        jobs_active: number;
        jobs_by_status: Record<string, number>;
        jobs_with_flag: number;
        flags: Record<string, number>;
        free_time: { overdue: number; urgent: number; ok: number; unavailable: number };
        unread_notifications: number;
    };
    unpaid: { count: number; total: number } | null;
    can_see_finance: boolean;
    jobs: Paginated<DashboardJobRow>;
    activity: ActivityItem[];
};

function freeTimeLabel(job: DashboardJobRow): { text: string; tone: 'red' | 'amber' | 'green' | 'muted' } {
    if (job.free_time_unavailable) {
        return { text: 'Kurs belum diatur', tone: 'muted' };
    }
    const ft = job.free_time;
    if (!ft) {
        return { text: '—', tone: 'muted' };
    }
    if (ft.remaining_days < 0) {
        return { text: `Lewat ${-ft.remaining_days} hari · terakhir ${ft.last_free_date ?? '-'}`, tone: 'red' };
    }
    if (ft.remaining_days === 0) {
        return { text: `Hari terakhir · ${ft.last_free_date ?? '-'}`, tone: 'amber' };
    }
    return {
        text: `${ft.remaining_days} hari lagi · ${ft.last_free_date ?? '-'}`,
        tone: ft.remaining_days <= 3 ? 'amber' : 'green',
    };
}

const toneClass: Record<'red' | 'amber' | 'green' | 'muted', string> = {
    red: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
    green: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
    muted: 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300',
};

export default function Dashboard({ stats, unpaid, can_see_finance, jobs, activity }: DashboardProps) {
    const statusEntries = Object.entries(stats.jobs_by_status);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Job Aktif</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold">{stats.jobs_active}</div>
                            <div className="text-muted-foreground mt-2 space-y-1 text-sm">
                                {statusEntries.length === 0 && <div>Belum ada Job aktif.</div>}
                                {statusEntries.map(([status, total]) => (
                                    <div key={status} className="flex justify-between gap-2">
                                        <span className="truncate">{status}</span>
                                        <span className="text-foreground font-medium">{total}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Perlu Perhatian</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold">{stats.jobs_with_flag}</div>
                            <div className="text-muted-foreground mt-2 space-y-1 text-sm">
                                {Object.entries(stats.flags).length === 0 && <div>Tidak ada Problem Flag.</div>}
                                {Object.entries(stats.flags).map(([flag, total]) => (
                                    <div key={flag} className="flex justify-between gap-2">
                                        <span className="truncate">{flag}</span>
                                        <span className="text-foreground font-medium">{total}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Free Time</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold">{stats.free_time.overdue + stats.free_time.urgent}</div>
                            <div className="text-muted-foreground mt-2 space-y-1 text-sm">
                                <div className="flex justify-between gap-2">
                                    <span>Lewat batas</span>
                                    <span className="font-medium text-red-600">{stats.free_time.overdue} kontainer</span>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <span>≤ 3 hari lagi</span>
                                    <span className="font-medium text-amber-600">{stats.free_time.urgent} kontainer</span>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <span>Aman</span>
                                    <span className="text-foreground font-medium">{stats.free_time.ok} kontainer</span>
                                </div>
                                {stats.free_time.unavailable > 0 && (
                                    <div className="flex justify-between gap-2">
                                        <span>Tidak terhitung (kurs belum diatur)</span>
                                        <span className="text-foreground font-medium">{stats.free_time.unavailable} Job</span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {can_see_finance && unpaid ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Tagihan Belum Lunas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold">{unpaid.count}</div>
                                <div className="text-muted-foreground mt-2 text-sm">
                                    Total tunggakan: <span className="text-foreground font-medium">Rp {unpaid.total.toLocaleString('id-ID')}</span>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Notifikasi</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-bold">{stats.unread_notifications}</div>
                                <div className="text-muted-foreground mt-2 text-sm">
                                    <Link href={route('notifications.index')} className="underline">
                                        Buka kotak masuk notifikasi
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Job Aktif</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {jobs.data.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada Job aktif.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-muted-foreground border-b text-left">
                                            <th className="py-2 pr-4 font-medium">Job</th>
                                            <th className="py-2 pr-4 font-medium">Importir</th>
                                            <th className="py-2 pr-4 font-medium">Status</th>
                                            <th className="py-2 pr-4 font-medium">Problem Flag</th>
                                            <th className="py-2 font-medium">Free Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {jobs.data.map((job) => {
                                            const ft = freeTimeLabel(job);
                                            return (
                                                <tr key={job.id} className="border-b last:border-0">
                                                    <td className="py-2 pr-4">
                                                        <Link href={route('jobs.show', job.id)} className="font-medium underline">
                                                            {job.job_number}
                                                        </Link>
                                                    </td>
                                                    <td className="py-2 pr-4">{job.importir_name ?? '—'}</td>
                                                    <td className="py-2 pr-4">{job.cached_status}</td>
                                                    <td className="py-2 pr-4">
                                                        {job.problem_flag ? <Badge variant="destructive">{job.problem_flag}</Badge> : '—'}
                                                    </td>
                                                    <td className="py-2">
                                                        <span
                                                            className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${toneClass[ft.tone]}`}
                                                        >
                                                            {ft.text}
                                                        </span>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {jobs.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Menampilkan {jobs.from ?? 0}–{jobs.to ?? 0} dari {jobs.total} Job aktif
                                </span>
                                <div className="flex gap-2">
                                    {jobs.current_page > 1 ? (
                                        <Link
                                            href={route('dashboard', { page: jobs.current_page - 1 })}
                                            className="rounded-md border px-3 py-1 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                        >
                                            ← Sebelumnya
                                        </Link>
                                    ) : (
                                        <span className="text-muted-foreground cursor-not-allowed rounded-md border px-3 py-1 opacity-50">
                                            ← Sebelumnya
                                        </span>
                                    )}
                                    <span className="text-muted-foreground px-1 py-1">
                                        Halaman {jobs.current_page} dari {jobs.last_page}
                                    </span>
                                    {jobs.current_page < jobs.last_page ? (
                                        <Link
                                            href={route('dashboard', { page: jobs.current_page + 1 })}
                                            className="rounded-md border px-3 py-1 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                        >
                                            Berikutnya →
                                        </Link>
                                    ) : (
                                        <span className="text-muted-foreground cursor-not-allowed rounded-md border px-3 py-1 opacity-50">
                                            Berikutnya →
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Aktivitas Terakhir</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {activity.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada aktivitas tercatat.</p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {activity.map((item) => (
                                    <li key={item.id} className="flex flex-wrap items-baseline gap-x-2 border-b pb-2 last:border-0 last:pb-0">
                                        <span className="font-medium">{item.action}</span>
                                        <span className="text-muted-foreground">
                                            {item.object_type} #{item.object_id}
                                        </span>
                                        {item.user_name && <span className="text-muted-foreground">oleh {item.user_name}</span>}
                                        {item.reason && <span className="text-muted-foreground">— {item.reason}</span>}
                                        <span className="text-muted-foreground ml-auto text-xs">{item.created_at ?? ''}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
