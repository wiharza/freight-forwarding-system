import { MasterTabs } from '@/components/master-tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type Item = {
    id: number;
    name: string;
    imo_number: string | null;
    call_sign: string | null;
    shipping_line_id: number | null;
    shipping_line?: { id: number; name: string } | null;
};
type LineItem = { id: number; name: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Kapal', href: '/admin/master/vessels' },
];

export default function MasterVessels({ items, shippingLines }: { items: Item[]; shippingLines: LineItem[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        imo_number: '',
        call_sign: '',
        shipping_line_id: '' as number | '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.master.vessels.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Kapal (IMO)" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <div>
                    <h2 className="text-lg font-medium">Master Kapal</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Kapal dipilih dari daftar ini saat entri Job agar tidak ada salah ketik nama kapal / nomor IMO (check digit IMO divalidasi
                        otomatis).
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="rounded-xl border p-4 md:col-span-1">
                        <h3 className="mb-3 font-medium">Tambah Kapal</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="name">Nama Kapal</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="imo_number">Nomor IMO (7 digit, opsional)</Label>
                                <Input
                                    id="imo_number"
                                    value={data.imo_number}
                                    onChange={(e) => setData('imo_number', e.target.value)}
                                    placeholder="Contoh: IMO9074729"
                                />
                                {errors.imo_number && <p className="text-xs text-red-600">{errors.imo_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="call_sign">Call Sign (opsional)</Label>
                                <Input id="call_sign" value={data.call_sign} onChange={(e) => setData('call_sign', e.target.value)} />
                                {errors.call_sign && <p className="text-xs text-red-600">{errors.call_sign}</p>}
                            </div>
                            <div>
                                <Label htmlFor="shipping_line_id">Pemilik / Operator (opsional)</Label>
                                <select
                                    id="shipping_line_id"
                                    className="bg-background mt-1 w-full rounded-md border p-2 text-sm"
                                    value={data.shipping_line_id}
                                    onChange={(e) => setData('shipping_line_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                >
                                    <option value="">— Tidak terikat line —</option>
                                    {shippingLines.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
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
                                    <th className="px-3 py-2">Nama Kapal</th>
                                    <th className="px-3 py-2">IMO</th>
                                    <th className="px-3 py-2">Call Sign</th>
                                    <th className="px-3 py-2">Line</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="text-muted-foreground px-3 py-4 text-center">
                                            Belum ada kapal terdaftar.
                                        </td>
                                    </tr>
                                )}
                                {items.map((item) => (
                                    <tr key={item.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2">{item.name}</td>
                                        <td className="px-3 py-2 font-mono">{item.imo_number ?? '-'}</td>
                                        <td className="px-3 py-2 font-mono">{item.call_sign ?? '-'}</td>
                                        <td className="px-3 py-2">{item.shipping_line?.name ?? '-'}</td>
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
