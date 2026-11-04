import { MasterTabs } from '@/components/master-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';

type MfnItem = {
    id: number;
    hs_code: string;
    description: string | null;
    bm_rate: string;
    effective_from: string;
    effective_to: string | null;
    verification_status: 'terverifikasi' | 'perlu_verifikasi';
    legal_basis: string | null;
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Tarif MFN', href: '/admin/master/mfn-tariffs' },
];

export default function MfnTariffs({ items, search }: { items: Paginated<MfnItem>; search: string | null }) {
    const authUser = usePage<SharedData>().props.auth.user;

    const form = useForm({
        hs_code: '',
        description: '',
        bm_rate: '0.05',
        effective_from: '2026-01-01',
        effective_to: '',
        verification_status: 'terverifikasi',
        legal_basis: 'BTKI 2022',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('admin.master.mfn-tariffs.store'), {
            onSuccess: () => form.reset('hs_code', 'description'),
        });
    };

    const approve = (id: number) => {
        const legalBasis = prompt('Masukkan dasar hukum persetujuan (e.g. PMK 62/2025):', 'PMK 62/2025');
        if (legalBasis) {
            router.post(route('admin.master.mfn-tariffs.approve', id), { legal_basis: legalBasis });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Tarif MFN" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />

                <div>
                    <h2 className="text-lg font-medium">Tarif Bea Masuk MFN (BTKI 2022 + PMK 62/2025)</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Status verifikasi hukum (ADR 0008). Item berstatus Perlu Verifikasi dilarang untuk filing resmi.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="rounded-xl border p-4 lg:col-span-1">
                        <h3 className="mb-3 font-medium">Tambah Tarif MFN Baru</h3>
                        <form onSubmit={submit} className="space-y-3 text-sm">
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
                                <Label htmlFor="description">Uraian Barang</Label>
                                <Input id="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="bm_rate">Tarif Bea Masuk (Desimal, e.g. 0.05 = 5%)</Label>
                                <Input
                                    id="bm_rate"
                                    type="number"
                                    step="0.0001"
                                    value={form.data.bm_rate}
                                    onChange={(e) => form.setData('bm_rate', e.target.value)}
                                    required
                                />
                                {form.errors.bm_rate && <p className="text-xs text-red-600">{form.errors.bm_rate}</p>}
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
                                <Label htmlFor="verification_status">Status Verifikasi Hukum</Label>
                                <select
                                    id="verification_status"
                                    className="bg-background w-full rounded-md border p-2 text-sm"
                                    value={form.data.verification_status}
                                    onChange={(e) => form.setData('verification_status', e.target.value as 'terverifikasi' | 'perlu_verifikasi')}
                                >
                                    <option value="terverifikasi">Terverifikasi (Sah untuk Filing)</option>
                                    <option value="perlu_verifikasi">Perlu Verifikasi (Draf Saja)</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="legal_basis">Dasar Hukum (PMK)</Label>
                                <Input
                                    id="legal_basis"
                                    value={form.data.legal_basis}
                                    onChange={(e) => form.setData('legal_basis', e.target.value)}
                                    required
                                />
                            </div>
                            <Button type="submit" disabled={form.processing} className="w-full">
                                Simpan Pos Tarif
                            </Button>
                        </form>
                    </div>

                    <div className="space-y-4 lg:col-span-2">
                        <div className="flex gap-2">
                            <Input
                                placeholder="Cari Kode HS atau Uraian..."
                                defaultValue={search || ''}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        router.get(route('admin.master.mfn-tariffs'), { q: (e.target as HTMLInputElement).value });
                                    }
                                }}
                            />
                        </div>

                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/50 border-b text-left">
                                        <th className="px-3 py-2">Kode HS</th>
                                        <th className="px-3 py-2">Uraian</th>
                                        <th className="px-3 py-2">Tarif BM</th>
                                        <th className="px-3 py-2">Dasar Hukum</th>
                                        <th className="px-3 py-2">Status</th>
                                        <th className="px-3 py-2">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.data.map((item) => {
                                        const isCreator = authUser?.id === item.creator?.id;
                                        return (
                                            <tr key={item.id} className="border-b last:border-b-0">
                                                <td className="px-3 py-2 font-mono font-bold">{item.hs_code}</td>
                                                <td className="px-3 py-2 text-xs">{item.description || '-'}</td>
                                                <td className="px-3 py-2 font-mono">{(parseFloat(item.bm_rate) * 100).toFixed(1)}%</td>
                                                <td className="text-muted-foreground px-3 py-2 text-xs">{item.legal_basis || '-'}</td>
                                                <td className="px-3 py-2">
                                                    <Badge variant={item.verification_status === 'terverifikasi' ? 'default' : 'secondary'}>
                                                        {item.verification_status}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.verification_status === 'perlu_verifikasi' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={isCreator}
                                                            title={isCreator ? 'Pembuat tidak boleh approve sendiri (ADR 0006)' : 'Verifikasi'}
                                                            onClick={() => approve(item.id)}
                                                        >
                                                            Verifikasi
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
