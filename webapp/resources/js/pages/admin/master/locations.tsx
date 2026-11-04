import { MasterTabs } from '@/components/master-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type Item = {
    id: number;
    code: string;
    name: string;
    type: 'port' | 'depot' | 'warehouse';
    city: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Lokasi', href: '/admin/master/locations' },
];

export default function MasterLocations({ items }: { items: Item[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        name: '',
        type: 'port',
        city: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.master.locations.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Lokasi" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <h2 className="text-lg font-medium">Master Lokasi (Pelabuhan, Depot, Gudang)</h2>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="rounded-xl border p-4 md:col-span-1">
                        <h3 className="mb-3 font-medium">Tambah Lokasi</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="code">Kode Lokasi (e.g. IDTPP)</Label>
                                <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} required />
                                {errors.code && <p className="text-xs text-red-600">{errors.code}</p>}
                            </div>
                            <div>
                                <Label htmlFor="name">Nama Lokasi</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="type">Tipe</Label>
                                <select
                                    id="type"
                                    className="bg-background w-full rounded-md border p-2 text-sm"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                >
                                    <option value="port">Pelabuhan (Port)</option>
                                    <option value="depot">Depot Kontainer</option>
                                    <option value="warehouse">Gudang / Tujuan</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="city">Kota</Label>
                                <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                Simpan Lokasi
                            </Button>
                        </form>
                    </div>

                    <div className="overflow-x-auto rounded-xl border md:col-span-2">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 border-b text-left">
                                    <th className="px-3 py-2">Kode</th>
                                    <th className="px-3 py-2">Nama</th>
                                    <th className="px-3 py-2">Tipe</th>
                                    <th className="px-3 py-2">Kota</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-mono">{item.code}</td>
                                        <td className="px-3 py-2">{item.name}</td>
                                        <td className="px-3 py-2">
                                            <Badge variant="outline">{item.type}</Badge>
                                        </td>
                                        <td className="px-3 py-2">{item.city || '-'}</td>
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
