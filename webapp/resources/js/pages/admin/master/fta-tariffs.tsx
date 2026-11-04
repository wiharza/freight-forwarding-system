import { MasterTabs } from '@/components/master-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

type FtaItem = {
    id: number;
    scheme_code: string;
    hs_code: string;
    fta_rate: string;
    coo_type: string | null;
    effective_from: string;
    verification_status: 'terverifikasi' | 'perlu_verifikasi';
    legal_basis: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Tarif Preferensi FTA', href: '/admin/master/fta-tariffs' },
];

export default function FtaTariffs({
    items,
    schemes,
    selectedScheme,
}: {
    items: Paginated<FtaItem>;
    schemes: Record<string, string>;
    selectedScheme: string | null;
}) {
    const form = useForm({
        scheme_code: selectedScheme || 'ACFTA',
        hs_code: '',
        fta_rate: '0.00',
        effective_from: '2023-01-01',
        effective_to: '2027-12-31',
        verification_status: 'terverifikasi',
        legal_basis: 'PMK 88/2023',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('admin.master.fta-tariffs.store'), {
            onSuccess: () => form.reset('hs_code'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Tarif Preferensi FTA" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />

                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium">Tarif Preferensi FTA (9 Skema Aktif, PMK 88/2023)</h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Form E, Form D, Form AK, Form AI, Form ANZ, Form IJEPA, Form IA-CEPA, Form IC, Form RCEP (§3.5).
                        </p>
                    </div>

                    <div className="w-56">
                        <Label htmlFor="schemeSelect" className="text-xs">
                            Filter Skema FTA
                        </Label>
                        <select
                            id="schemeSelect"
                            className="bg-background mt-1 w-full rounded-md border p-2 text-sm"
                            value={selectedScheme || ''}
                            onChange={(e) => router.get(route('admin.master.fta-tariffs', { scheme: e.target.value }))}
                        >
                            <option value="">Semua Skema (9 Aktif)</option>
                            {Object.entries(schemes).map(([code, formName]) => (
                                <option key={code} value={code}>
                                    {code} ({formName})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="rounded-xl border p-4 lg:col-span-1">
                        <h3 className="mb-3 font-medium">Tambah Tarif Preferensi</h3>
                        <form onSubmit={submit} className="space-y-3 text-sm">
                            <div>
                                <Label htmlFor="scheme_code">Skema FTA</Label>
                                <select
                                    id="scheme_code"
                                    className="bg-background w-full rounded-md border p-2 text-sm"
                                    value={form.data.scheme_code}
                                    onChange={(e) => form.setData('scheme_code', e.target.value)}
                                >
                                    {Object.entries(schemes).map(([code, formName]) => (
                                        <option key={code} value={code}>
                                            {code} - {formName}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="hs_code">Kode HS (8 digit)</Label>
                                <Input
                                    id="hs_code"
                                    value={form.data.hs_code}
                                    onChange={(e) => form.setData('hs_code', e.target.value)}
                                    placeholder="Contoh: 8471.30.20"
                                    required
                                />
                                {form.errors.hs_code && <p className="text-xs text-red-600">{form.errors.hs_code}</p>}
                            </div>
                            <div>
                                <Label htmlFor="fta_rate">Tarif Preferensi (0.00 = 0%)</Label>
                                <Input
                                    id="fta_rate"
                                    type="number"
                                    step="0.0001"
                                    value={form.data.fta_rate}
                                    onChange={(e) => form.setData('fta_rate', e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="effective_from">Berlaku Dari</Label>
                                <Input
                                    id="effective_from"
                                    type="date"
                                    value={form.data.effective_from}
                                    onChange={(e) => form.setData('effective_from', e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="legal_basis">Dasar Hukum</Label>
                                <Input
                                    id="legal_basis"
                                    value={form.data.legal_basis}
                                    onChange={(e) => form.setData('legal_basis', e.target.value)}
                                    required
                                />
                            </div>
                            <Button type="submit" disabled={form.processing} className="w-full">
                                Simpan Tarif Preferensi
                            </Button>
                        </form>
                    </div>

                    <div className="space-y-4 lg:col-span-2">
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b text-left">
                                        <th className="px-3 py-2">Skema</th>
                                        <th className="px-3 py-2">Form SKA</th>
                                        <th className="px-3 py-2">Kode HS</th>
                                        <th className="px-3 py-2">Tarif FTA</th>
                                        <th className="px-3 py-2">Dasar Hukum</th>
                                        <th className="px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.data.map((item) => (
                                        <tr key={item.id} className="border-b last:border-b-0">
                                            <td className="px-3 py-2 font-bold">{item.scheme_code}</td>
                                            <td className="px-3 py-2 text-xs">{item.coo_type}</td>
                                            <td className="px-3 py-2 font-mono">{item.hs_code}</td>
                                            <td className="px-3 py-2 font-mono font-bold text-green-700 dark:text-green-400">
                                                {(parseFloat(item.fta_rate) * 100).toFixed(1)}%
                                            </td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{item.legal_basis}</td>
                                            <td className="px-3 py-2">
                                                <Badge variant="default">{item.verification_status}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                    {items.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="text-muted-foreground py-6 text-center">
                                                Tidak ada data tarif untuk filter skema ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
