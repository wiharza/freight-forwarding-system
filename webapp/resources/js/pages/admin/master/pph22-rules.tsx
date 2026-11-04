import { MasterTabs } from '@/components/master-tabs';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

type Pph22Item = {
    id: number;
    hs_code: string;
    lampiran: string;
    rate: string;
    verification_status: string;
    legal_basis: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'PPh 22 (PMK 51/2025)', href: '/admin/master/pph22-rules' },
];

export default function Pph22Rules({ items, search, totalRows }: { items: Paginated<Pph22Item>; search: string | null; totalRows: number }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Mapping PPh 22 Impor" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />

                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium">Mapping Tarif PPh 22 Impor (PMK 51/2025)</h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Memuat {totalRows} pos tarif HS resmi hasil ekstraksi JDIH Lampiran A (10%) & B (7.5%) — PV-8 Terverifikasi.
                        </p>
                    </div>

                    <div className="w-72">
                        <Input
                            placeholder="Cari Pos HS (e.g. 33.04, 87.03)..."
                            defaultValue={search || ''}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    router.get(route('admin.master.pph22-rules'), { q: (e.target as HTMLInputElement).value });
                                }
                            }}
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 border-b text-left">
                                <th className="px-4 py-3">Pos Tarif HS</th>
                                <th className="px-4 py-3">Kategori Lampiran</th>
                                <th className="px-4 py-3">Tarif Impor</th>
                                <th className="px-4 py-3">Dasar Hukum</th>
                                <th className="px-4 py-3">Status Verifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.data.map((item) => (
                                <tr key={item.id} className="border-b last:border-b-0">
                                    <td className="px-4 py-3 font-mono text-base font-bold">{item.hs_code}</td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline">Lampiran {item.lampiran}</Badge>
                                    </td>
                                    <td className="px-4 py-3 font-mono font-bold text-blue-700 dark:text-blue-400">
                                        {(parseFloat(item.rate) * 100).toFixed(1)}%
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 text-xs">{item.legal_basis}</td>
                                    <td className="px-4 py-3">
                                        <Badge variant="default">{item.verification_status}</Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
