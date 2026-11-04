import { MasterTabs } from '@/components/master-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type ImportirItem = {
    id: number;
    name: string;
    npwp: string;
    nib: string | null;
    status_api: string;
    phone: string | null;
    email: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Importir', href: '/admin/master/importirs' },
];

export default function MasterImportirs({ items }: { items: ImportirItem[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        npwp: '',
        nib: '',
        status_api: 'API-U',
        phone: '',
        email: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.master.importirs.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Importir" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium">Master Data Importir</h2>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="rounded-xl border p-4 md:col-span-1">
                        <h3 className="mb-3 font-medium">Tambah Importir Baru</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="name">Nama Perusahaan</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="npwp">NPWP (Kunci Dedup §1.7)</Label>
                                <Input id="npwp" value={data.npwp} onChange={(e) => setData('npwp', e.target.value)} required />
                                {errors.npwp && <p className="text-xs text-red-600">{errors.npwp}</p>}
                            </div>
                            <div>
                                <Label htmlFor="nib">NIB</Label>
                                <Input id="nib" value={data.nib} onChange={(e) => setData('nib', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="status_api">Status API</Label>
                                <select
                                    id="status_api"
                                    className="bg-background w-full rounded-md border p-2 text-sm"
                                    value={data.status_api}
                                    onChange={(e) => setData('status_api', e.target.value)}
                                >
                                    <option value="API-U">API-U (Umum)</option>
                                    <option value="API-P">API-P (Produsen)</option>
                                    <option value="NON-API">Non-API</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="phone">Telepon</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                Simpan Importir
                            </Button>
                        </form>
                    </div>

                    <div className="overflow-x-auto rounded-xl border md:col-span-2">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 border-b text-left">
                                    <th className="px-3 py-2">Nama</th>
                                    <th className="px-3 py-2">NPWP</th>
                                    <th className="px-3 py-2">NIB</th>
                                    <th className="px-3 py-2">API</th>
                                    <th className="px-3 py-2">Kontak</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-medium">{item.name}</td>
                                        <td className="px-3 py-2 font-mono text-xs">{item.npwp}</td>
                                        <td className="px-3 py-2">{item.nib || '-'}</td>
                                        <td className="px-3 py-2">
                                            <Badge variant="outline">{item.status_api}</Badge>
                                        </td>
                                        <td className="px-3 py-2">{item.phone || '-'}</td>
                                    </tr>
                                ))}
                                {items.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground py-6 text-center">
                                            Belum ada data importir.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
