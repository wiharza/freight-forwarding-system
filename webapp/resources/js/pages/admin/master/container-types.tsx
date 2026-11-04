import { MasterTabs } from '@/components/master-tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type Item = {
    id: number;
    size: string;
    type: string;
    description: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Jenis Kontainer', href: '/admin/master/container-types' },
];

export default function MasterContainerTypes({ items }: { items: Item[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        size: '20',
        type: 'Dry',
        description: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.master.container-types.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Jenis Kontainer" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />
                <h2 className="text-lg font-medium">Master Jenis Kontainer (§2.1, Q10)</h2>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="rounded-xl border p-4 md:col-span-1">
                        <h3 className="mb-3 font-medium">Tambah Jenis Kontainer</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <Label htmlFor="size">Ukuran (feet)</Label>
                                <select
                                    id="size"
                                    className="bg-background w-full rounded-md border p-2 text-sm"
                                    value={data.size}
                                    onChange={(e) => setData('size', e.target.value)}
                                >
                                    <option value="20">20' (Twenty-foot)</option>
                                    <option value="40">40' (Forty-foot)</option>
                                    <option value="45">45' (Forty-five-foot)</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="type">Tipe Kontainer</Label>
                                <Input
                                    id="type"
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    placeholder="Dry / Reefer / High Cube / dst."
                                    required
                                />
                                {errors.type && <p className="text-xs text-red-600">{errors.type}</p>}
                            </div>
                            <div>
                                <Label htmlFor="description">Deskripsi</Label>
                                <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </div>
                            <Button type="submit" disabled={processing} className="w-full">
                                Simpan Jenis Kontainer
                            </Button>
                        </form>
                    </div>

                    <div className="overflow-x-auto rounded-xl border md:col-span-2">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 border-b text-left">
                                    <th className="px-3 py-2">Ukuran</th>
                                    <th className="px-3 py-2">Tipe</th>
                                    <th className="px-3 py-2">Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-mono font-medium">{item.size}'</td>
                                        <td className="px-3 py-2">{item.type}</td>
                                        <td className="text-muted-foreground px-3 py-2">{item.description || '-'}</td>
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
