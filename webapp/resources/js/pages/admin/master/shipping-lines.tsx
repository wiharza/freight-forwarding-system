import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MasterTabs } from '@/components/master-tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type Item = { id: number; code: string; name: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Shipping Line', href: '/admin/master/shipping-lines' },
];

export default function MasterShippingLines({ items }: { items: Item[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        name: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.master.shipping-lines.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Shipping Line" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <h2 className="text-lg font-medium">Master Shipping Line</h2>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-1 rounded-xl border p-4">
                        <h3 className="font-medium mb-3">Tambah Shipping Line</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="code">Kode Line (e.g. MSK, SITC)</Label>
                                <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} required />
                                {errors.code && <p className="text-xs text-red-600">{errors.code}</p>}
                            </div>
                            <div>
                                <Label htmlFor="name">Nama Shipping Line</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                Simpan
                            </Button>
                        </form>
                    </div>

                    <div className="md:col-span-2 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left">
                                    <th className="px-3 py-2">Kode</th>
                                    <th className="px-3 py-2">Nama Pelayaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-mono">{item.code}</td>
                                        <td className="px-3 py-2">{item.name}</td>
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
