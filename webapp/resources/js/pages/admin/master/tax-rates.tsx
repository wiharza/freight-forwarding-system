import { MasterTabs } from '@/components/master-tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type RateRow = {
    id: number;
    kmk_number: string;
    valid_from: string;
    valid_to: string;
    currency_code: string;
    rate_to_idr: string;
    source: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Kurs Pajak (KMK)', href: '/admin/master/tax-rates' },
];

export default function TaxRates({ rates }: { rates: RateRow[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        kmk_number: '',
        valid_from: '',
        valid_to: '',
        currency_code: 'USD',
        rate_to_idr: '',
        source: 'manual',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.master.tax-rates.store'), { onSuccess: () => reset('kmk_number', 'rate_to_idr') });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kurs Pajak (NDPBM)" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <div>
                    <h2 className="text-lg font-medium">Kurs Pajak Kemenkeu — NDPBM (§3.5, PV-3)</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Satu KMK per pekan: berlaku <b>Rabu s.d. Selasa</b> (7 hari). Dipisahkan dari Kurs Internal (ADR 0003).
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="rounded-xl border p-4 md:col-span-1">
                        <h3 className="mb-3 text-sm font-medium">Simpan Kurs Pajak</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="kmk_number">Nomor KMK</Label>
                                <Input
                                    id="kmk_number"
                                    value={data.kmk_number}
                                    onChange={(e) => setData('kmk_number', e.target.value)}
                                    placeholder="KMK 51/MK/EF.2/2026"
                                    required
                                />
                                {errors.kmk_number && <p className="text-xs text-red-600">{errors.kmk_number}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="valid_from">Mulai (Rabu)</Label>
                                    <Input
                                        id="valid_from"
                                        type="date"
                                        value={data.valid_from}
                                        onChange={(e) => setData('valid_from', e.target.value)}
                                        required
                                    />
                                    {errors.valid_from && <p className="text-xs text-red-600">{errors.valid_from}</p>}
                                </div>
                                <div>
                                    <Label htmlFor="valid_to">Akhir (Selasa)</Label>
                                    <Input
                                        id="valid_to"
                                        type="date"
                                        value={data.valid_to}
                                        onChange={(e) => setData('valid_to', e.target.value)}
                                        required
                                    />
                                    {errors.valid_to && <p className="text-xs text-red-600">{errors.valid_to}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="currency_code">Mata Uang</Label>
                                    <Input
                                        id="currency_code"
                                        value={data.currency_code}
                                        onChange={(e) => setData('currency_code', e.target.value.toUpperCase())}
                                        required
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="rate_to_idr">Kurs (Rp)</Label>
                                    <Input
                                        id="rate_to_idr"
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        value={data.rate_to_idr}
                                        onChange={(e) => setData('rate_to_idr', e.target.value)}
                                        required
                                    />
                                    {errors.rate_to_idr && <p className="text-xs text-red-600">{errors.rate_to_idr}</p>}
                                </div>
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                Simpan
                            </Button>
                        </form>
                    </div>

                    <div className="overflow-x-auto rounded-xl border md:col-span-2">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 border-b text-left">
                                    <th className="px-3 py-2">KMK</th>
                                    <th className="px-3 py-2">Periode</th>
                                    <th className="px-3 py-2">Valas</th>
                                    <th className="px-3 py-2 text-right">Kurs</th>
                                    <th className="px-3 py-2">Sumber</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rates.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-3 py-4 text-center">
                                            Belum ada kurs pajak.
                                        </td>
                                    </tr>
                                )}
                                {rates.map((r) => (
                                    <tr key={r.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-mono text-xs">{r.kmk_number}</td>
                                        <td className="px-3 py-2 text-xs">
                                            {r.valid_from.slice(0, 10)} → {r.valid_to.slice(0, 10)}
                                        </td>
                                        <td className="px-3 py-2 font-mono">{r.currency_code}</td>
                                        <td className="px-3 py-2 text-right font-mono">
                                            {Number(r.rate_to_idr).toLocaleString('id-ID', { maximumFractionDigits: 4 })}
                                        </td>
                                        <td className="px-3 py-2 text-xs">{r.source}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
