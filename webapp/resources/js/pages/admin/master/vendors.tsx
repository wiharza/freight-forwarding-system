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
    name: string;
    vendor_type: 'eksternal' | 'internal';
    contact_person: string | null;
    phone: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Vendor Trucking', href: '/admin/master/vendors' },
];

export default function MasterVendors({ items }: { items: Item[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        vendor_type: 'eksternal',
        contact_person: '',
        phone: '',
        address: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.master.vendors.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Vendor Trucking" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <h2 className="text-lg font-medium">Master Vendor Trucking (§5.1)</h2>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="rounded-xl border p-4 md:col-span-1">
                        <h3 className="mb-3 font-medium">Tambah Vendor</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="name">Nama Vendor</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="vendor_type">Tipe Vendor</Label>
                                <select
                                    id="vendor_type"
                                    className="bg-background w-full rounded-md border p-2 text-sm"
                                    value={data.vendor_type}
                                    onChange={(e) => setData('vendor_type', e.target.value)}
                                >
                                    <option value="eksternal">Eksternal (Pihak Ketiga)</option>
                                    <option value="internal">Internal (Armada Sendiri)</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="contact_person">PIC / Kontak</Label>
                                <Input id="contact_person" value={data.contact_person} onChange={(e) => setData('contact_person', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="phone">Telepon</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                Simpan Vendor
                            </Button>
                        </form>
                    </div>

                    <div className="overflow-x-auto rounded-xl border md:col-span-2">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 border-b text-left">
                                    <th className="px-3 py-2">Nama</th>
                                    <th className="px-3 py-2">Tipe</th>
                                    <th className="px-3 py-2">PIC</th>
                                    <th className="px-3 py-2">Telepon</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-medium">{item.name}</td>
                                        <td className="px-3 py-2">
                                            <Badge variant={item.vendor_type === 'internal' ? 'default' : 'secondary'}>{item.vendor_type}</Badge>
                                        </td>
                                        <td className="px-3 py-2">{item.contact_person || '-'}</td>
                                        <td className="px-3 py-2">{item.phone || '-'}</td>
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
