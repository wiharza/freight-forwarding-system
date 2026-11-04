import { MasterTabs } from '@/components/master-tabs';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type LuxuryItem = {
    id: number;
    hs_code: string;
    category: string;
    is_ppn_luxury: boolean;
    has_ppnbm: boolean;
    ppnbm_rate: string;
    import_value_threshold_usd: string | null;
    verification_status: string;
    legal_basis: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Barang Mewah', href: '/admin/master/luxury-goods' },
];

export default function LuxuryGoods({ items }: { items: LuxuryItem[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Barang Mewah & PPnBM" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />

                <div>
                    <h2 className="text-lg font-medium">Daftar Barang Mewah: PPN 12% Penuh & PPnBM (PP 73/2019, PV-6)</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Dua sub-daftar terpisah: barang mewah PPN (kena 12% penuh tanpa DPP 11/12) dan barang kena PPnBM dengan ambang Nilai Impor
                        dalam USD (§3.2).
                    </p>
                </div>

                <div className="max-w-4xl overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 border-b text-left">
                                <th className="px-4 py-3">Pos HS</th>
                                <th className="px-4 py-3">Kategori</th>
                                <th className="px-4 py-3">PPN 12% Penuh?</th>
                                <th className="px-4 py-3">Kena PPnBM?</th>
                                <th className="px-4 py-3">Tarif PPnBM</th>
                                <th className="px-4 py-3">Ambang Nilai Impor (USD)</th>
                                <th className="px-4 py-3">Dasar Hukum</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id} className="border-b last:border-b-0">
                                    <td className="px-4 py-3 font-mono font-bold">{item.hs_code}</td>
                                    <td className="px-4 py-3 capitalize">{item.category}</td>
                                    <td className="px-4 py-3">
                                        <Badge variant={item.is_ppn_luxury ? 'destructive' : 'outline'}>
                                            {item.is_ppn_luxury ? '12% Penuh' : 'DPP 11/12'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant={item.has_ppnbm ? 'default' : 'secondary'}>{item.has_ppnbm ? 'Ya' : 'Tidak'}</Badge>
                                    </td>
                                    <td className="px-4 py-3 font-mono font-medium">
                                        {item.has_ppnbm ? `${(parseFloat(item.ppnbm_rate) * 100).toFixed(0)}%` : '-'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {item.import_value_threshold_usd
                                            ? `> USD ${parseFloat(item.import_value_threshold_usd).toLocaleString()}`
                                            : 'Tanpa Ambang'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 text-xs">{item.legal_basis}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
