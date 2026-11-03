import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MasterTabs } from '@/components/master-tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

type Item = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    effective_date: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Mata Uang', href: '/admin/master/currencies' },
];

export default function MasterCurrencies({ items }: { items: Item[] }) {
    const toggleActive = (item: Item) => {
        router.patch(route('admin.master.currencies.update', item.id), {
            is_active: !item.is_active,
            effective_date: !item.is_active ? new Date().toISOString().split('T')[0] : item.effective_date,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Mata Uang" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <div>
                    <h2 className="text-lg font-medium">Master Mata Uang (§6.6, Q24)</h2>
                    <p className="text-xs text-muted-foreground mt-1">
                        Struktur sistem siap multi-valas. Sesuai keputusan Q24, pada versi 1 hanya USD yang aktif.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-xl border max-w-2xl">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left">
                                <th className="px-4 py-3">Kode</th>
                                <th className="px-4 py-3">Nama Mata Uang</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Tanggal Berlaku</th>
                                <th className="px-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id} className="border-b last:border-b-0">
                                    <td className="px-4 py-3 font-mono font-bold">{item.code}</td>
                                    <td className="px-4 py-3">{item.name}</td>
                                    <td className="px-4 py-3">
                                        <Badge variant={item.is_active ? 'default' : 'secondary'}>
                                            {item.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-xs">{item.effective_date || '-'}</td>
                                    <td className="px-4 py-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => toggleActive(item)}
                                        >
                                            {item.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                        </Button>
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
