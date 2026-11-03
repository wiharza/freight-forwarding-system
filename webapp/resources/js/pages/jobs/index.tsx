import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type ContainerItem = { id: number; size: string; type: string; container_number: string };
type JobItem = {
    id: number;
    job_number: string;
    bl_number: string;
    cached_status: string;
    problem_flag: string | null;
    importir: { id: number; name: string } | null;
    shipping_line: { id: number; code: string; name: string } | null;
    containers: ContainerItem[];
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Daftar Job', href: '/jobs' },
];

export default function JobsIndex({
    jobs,
    importirs,
    filters,
    canCreate,
}: {
    jobs: Paginated<JobItem>;
    importirs: { id: number; name: string }[];
    filters: { status: string | null; problem_flag: string | null; importir_id: string | null; q: string | null };
    canCreate: boolean;
}) {
    const handleFilter = (key: string, value: string) => {
        router.get(route('jobs.index'), {
            ...filters,
            [key]: value || undefined,
        }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Daftar Job" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-medium">Pengurusan Impor (Job FCL)</h2>
                        <p className="text-xs text-muted-foreground mt-1">
                            1 Job = 1 Importir + 1 B/L dengan 1..N Kontainer (§2.1, Q10).
                        </p>
                    </div>

                    {canCreate && (
                        <Link href={route('jobs.create')}>
                            <Button>+ Entri Job Baru (FCL)</Button>
                        </Link>
                    )}
                </div>

                {/* Filter Bar */}
                <div className="grid gap-3 rounded-xl border p-4 sm:grid-cols-4 text-sm bg-card">
                    <div>
                        <Input
                            placeholder="Cari No Job / B/L / Kapal..."
                            defaultValue={filters.q || ''}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleFilter('q', (e.target as HTMLInputElement).value);
                                }
                            }}
                        />
                    </div>
                    <div>
                        <select
                            className="w-full border rounded-md p-2 bg-background text-sm"
                            value={filters.status || ''}
                            onChange={(e) => handleFilter('status', e.target.value)}
                        >
                            <option value="">Semua Status Progres</option>
                            <option value="Draft">Draft</option>
                            <option value="Dokumen Lengkap">Dokumen Lengkap</option>
                            <option value="PIB Filed">PIB Filed</option>
                            <option value="SPPB">SPPB</option>
                            <option value="Gate-Out Selesai">Gate-Out Selesai</option>
                            <option value="Gate-In Empty Selesai">Gate-In Empty Selesai</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div>
                        <select
                            className="w-full border rounded-md p-2 bg-background text-sm"
                            value={filters.problem_flag || ''}
                            onChange={(e) => handleFilter('problem_flag', e.target.value)}
                        >
                            <option value="">Semua Problem Flag</option>
                            <option value="Hold">Hold</option>
                            <option value="Dispute">Dispute</option>
                            <option value="Dokumen Bermasalah">Dokumen Bermasalah</option>
                        </select>
                    </div>
                    <div>
                        <select
                            className="w-full border rounded-md p-2 bg-background text-sm"
                            value={filters.importir_id || ''}
                            onChange={(e) => handleFilter('importir_id', e.target.value)}
                        >
                            <option value="">Semua Importir</option>
                            {importirs.map((imp) => (
                                <option key={imp.id} value={imp.id}>
                                    {imp.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Tabel Job */}
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left">
                                <th className="px-4 py-3">No. Job</th>
                                <th className="px-4 py-3">Importir</th>
                                <th className="px-4 py-3">B/L</th>
                                <th className="px-4 py-3">Pelayaran</th>
                                <th className="px-4 py-3">Kontainer</th>
                                <th className="px-4 py-3">Status Progres</th>
                                <th className="px-4 py-3">Problem Flag</th>
                                <th className="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {jobs.data.map((job) => (
                                <tr key={job.id} className="border-b last:border-b-0">
                                    <td className="px-4 py-3 font-mono font-bold">
                                        <Link href={route('jobs.show', job.id)} className="hover:underline text-primary">
                                            {job.job_number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{job.importir?.name || '-'}</td>
                                    <td className="px-4 py-3 font-mono text-xs">{job.bl_number}</td>
                                    <td className="px-4 py-3">{job.shipping_line?.code || '-'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {job.containers.map((c) => (
                                                <Badge key={c.id} variant="outline" className="font-mono text-xs">
                                                    {c.container_number} ({c.size}')
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant={job.cached_status === 'Dokumen Lengkap' ? 'default' : 'secondary'}>
                                            {job.cached_status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {job.problem_flag ? (
                                            <Badge variant="destructive">{job.problem_flag}</Badge>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={route('jobs.show', job.id)}>
                                            <Button variant="outline" size="sm">
                                                Detail
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {jobs.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="text-center py-8 text-muted-foreground">
                                        Tidak ada data Job yang sesuai filter.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
