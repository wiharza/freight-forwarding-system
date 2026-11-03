import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

type RouteRow = {
    id: number;
    container_size: string;
    base_price_usd: string;
    include_toll: boolean;
    include_unloading: boolean;
    include_waiting: boolean;
    waiting_rate_per_hour_usd: string | null;
    effective_from: string;
    effective_to: string | null;
    vendor: { id: number; name: string } | null;
    origin: { id: number; code: string; name: string } | null;
    destination: { id: number; code: string; name: string } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Rute Vendor Trucking', href: '/vendor-routes' },
];

export default function VendorRoutesIndex({
    routes,
    vendors,
    locations,
}: {
    routes: { data: RouteRow[] };
    vendors: { id: number; name: string }[];
    locations: { id: number; name: string; type: string }[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        vendor_id: vendors[0]?.id ?? ('' as number | ''),
        origin_location_id: '' as number | '',
        destination_location_id: '' as number | '',
        container_size: '20',
        base_price_usd: '',
        include_toll: false,
        include_unloading: true,
        include_waiting: false,
        waiting_rate_per_hour_usd: '',
        effective_from: new Date().toISOString().split('T')[0],
        effective_to: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('vendor-routes.store'), { onSuccess: () => reset('base_price_usd', 'waiting_rate_per_hour_usd') });
    };

    const destroy = (id: number) => {
        router.delete(route('vendor-routes.destroy', id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rute Vendor Trucking" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h2 className="text-lg font-medium">Rute Vendor Trucking (§5.2, Q13)</h2>
                    <p className="text-xs text-muted-foreground mt-1">
                        Harga per rute per ukuran kontainer — acuan estimasi biaya trucking (Tanpa markup saat ditagih ke importir).
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-1 rounded-xl border p-4">
                        <h3 className="font-medium mb-3 text-sm">Tambah Rute</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="vendor_id">Vendor</Label>
                                <select
                                    id="vendor_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.vendor_id}
                                    onChange={(e) => setData('vendor_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                    required
                                >
                                    {vendors.map((v) => (
                                        <option key={v.id} value={v.id}>{v.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="origin_location_id">Asal</Label>
                                <select
                                    id="origin_location_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.origin_location_id}
                                    onChange={(e) => setData('origin_location_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                >
                                    <option value="">—</option>
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>{l.name} ({l.type})</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="destination_location_id">Tujuan</Label>
                                <select
                                    id="destination_location_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.destination_location_id}
                                    onChange={(e) => setData('destination_location_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                    required
                                >
                                    <option value="">—</option>
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>{l.name} ({l.type})</option>
                                    ))}
                                </select>
                                {errors.destination_location_id && <p className="text-xs text-red-600">{errors.destination_location_id}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="container_size">Ukuran</Label>
                                    <select
                                        id="container_size"
                                        className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                        value={data.container_size}
                                        onChange={(e) => setData('container_size', e.target.value)}
                                    >
                                        <option value="20">20'</option>
                                        <option value="40">40'</option>
                                        <option value="45">45'</option>
                                    </select>
                                </div>
                                <div>
                                    <Label htmlFor="base_price_usd">Harga (USD)</Label>
                                    <Input id="base_price_usd" type="number" step="0.01" min="0" value={data.base_price_usd} onChange={(e) => setData('base_price_usd', e.target.value)} required />
                                    {errors.base_price_usd && <p className="text-xs text-red-600">{errors.base_price_usd}</p>}
                                </div>
                            </div>
                            <div className="space-y-1 text-sm">
                                {([
                                    ['include_toll', 'Termasuk tol'],
                                    ['include_unloading', 'Termasuk bongkar'],
                                    ['include_waiting', 'Tarif menunggu terpisah'],
                                ] as const).map(([field, label]) => (
                                    <label key={field} className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data[field]}
                                            onChange={(e) => setData(field, e.target.checked)}
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                            {data.include_waiting && (
                                <div>
                                    <Label htmlFor="waiting_rate_per_hour_usd">Tarif Menunggu / jam (USD)</Label>
                                    <Input id="waiting_rate_per_hour_usd" type="number" step="0.01" min="0" value={data.waiting_rate_per_hour_usd} onChange={(e) => setData('waiting_rate_per_hour_usd', e.target.value)} />
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="effective_from">Berlaku Dari</Label>
                                    <Input id="effective_from" type="date" value={data.effective_from} onChange={(e) => setData('effective_from', e.target.value)} required />
                                </div>
                                <div>
                                    <Label htmlFor="effective_to">Sampai (opsional)</Label>
                                    <Input id="effective_to" type="date" value={data.effective_to} onChange={(e) => setData('effective_to', e.target.value)} />
                                </div>
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">Simpan</Button>
                        </form>
                    </div>

                    <div className="md:col-span-2 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left">
                                    <th className="px-3 py-2">Vendor</th>
                                    <th className="px-3 py-2">Rute</th>
                                    <th className="px-3 py-2">Ukuran</th>
                                    <th className="px-3 py-2 text-right">Harga</th>
                                    <th className="px-3 py-2">Termasuk</th>
                                    <th className="px-3 py-2">Berlaku</th>
                                    <th className="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {routes.data.length === 0 && (
                                    <tr><td colSpan={7} className="px-3 py-4 text-center text-muted-foreground">Belum ada rute.</td></tr>
                                )}
                                {routes.data.map((r) => (
                                    <tr key={r.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2">{r.vendor?.name ?? '-'}</td>
                                        <td className="px-3 py-2">
                                            {r.origin?.name ?? '?'} → {r.destination?.name ?? '?'}
                                        </td>
                                        <td className="px-3 py-2">{r.container_size}'</td>
                                        <td className="px-3 py-2 text-right font-mono">USD {Number(r.base_price_usd).toLocaleString()}</td>
                                        <td className="px-3 py-2 text-xs">
                                            {[
                                                r.include_toll && 'tol',
                                                r.include_unloading && 'bongkar',
                                                r.include_waiting && `menunggu ${r.waiting_rate_per_hour_usd ?? '0'}/j`,
                                            ].filter(Boolean).join(', ') || '—'}
                                        </td>
                                        <td className="px-3 py-2 text-xs">{r.effective_from} → {r.effective_to ?? '…'}</td>
                                        <td className="px-3 py-2">
                                            <Button variant="ghost" size="sm" className="text-red-600" onClick={() => destroy(r.id)}>
                                                Hapus
                                            </Button>
                                        </td>
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
