import { MasterTabs } from '@/components/master-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';

type Line = {
    id: number;
    code: string;
    name: string;
    free_days_demurrage: number;
    free_days_detention: number;
    start_offset_demurrage: string;
    start_offset_detention: string;
    calendar_type_demurrage: string;
    calendar_type_detention: string;
    mode: string;
};

type Tariff = {
    id: number;
    charge_type: 'demurrage' | 'detention';
    container_size: string;
    day_from: number;
    day_to: number | null;
    currency_code: string;
    rate_per_day: string;
    effective_from: string;
    effective_to: string | null;
    status: 'draft' | 'active' | 'archived';
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Data', href: '#' },
    { title: 'Tarif Line', href: '/admin/master/line-tariffs' },
];

export default function LineTariffs({ lines, selectedLine, tariffs }: { lines: Line[]; selectedLine: Line | null; tariffs: Tariff[] }) {
    const authUser = usePage<SharedData>().props.auth.user;

    const configForm = useForm({
        free_days_demurrage: selectedLine?.free_days_demurrage ?? 7,
        free_days_detention: selectedLine?.free_days_detention ?? 7,
        start_offset_demurrage: selectedLine?.start_offset_demurrage ?? 'H+1',
        start_offset_detention: selectedLine?.start_offset_detention ?? 'H+1',
        calendar_type_demurrage: selectedLine?.calendar_type_demurrage ?? 'calendar',
        calendar_type_detention: selectedLine?.calendar_type_detention ?? 'calendar',
        mode: selectedLine?.mode ?? 'terpisah',
    });

    const tariffForm = useForm({
        shipping_line_id: selectedLine?.id ?? '',
        charge_type: 'demurrage',
        container_size: '20',
        day_from: 1,
        day_to: '' as string | number,
        currency_code: 'USD',
        rate_per_day: '',
        effective_from: new Date().toISOString().split('T')[0],
        effective_to: '',
        notes: '',
    });

    const submitConfig = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedLine) return;
        configForm.patch(route('admin.master.shipping-lines.config', selectedLine.id));
    };

    const submitTariff = (e: React.FormEvent) => {
        e.preventDefault();
        tariffForm.post(route('admin.master.line-tariffs.store'), {
            onSuccess: () => tariffForm.reset('rate_per_day', 'notes'),
        });
    };

    const approveTariff = (tariffId: number) => {
        router.post(route('admin.master.line-tariffs.approve', tariffId));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tarif Line & Konfigurasi" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <MasterTabs />

                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium">Tarif Line & Konfigurasi Free Time (§4.2, §4.3)</h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Tarif ber-versi dengan tanggal berlaku. Tidak ada tarif default yang di-hardcode.
                        </p>
                    </div>

                    <div className="w-64">
                        <Label htmlFor="lineSelect" className="text-xs">
                            Pilih Pelayaran
                        </Label>
                        <select
                            id="lineSelect"
                            className="bg-background mt-1 w-full rounded-md border p-2 text-sm"
                            value={selectedLine?.id ?? ''}
                            onChange={(e) => router.get(route('admin.master.line-tariffs', { shipping_line_id: e.target.value }))}
                        >
                            {lines.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.code} - {l.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {selectedLine && (
                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* 1. Form Konfigurasi Free Time Line */}
                        <div className="rounded-xl border p-4 lg:col-span-1">
                            <h3 className="mb-3 font-medium">Aturan Free Time: {selectedLine.code}</h3>
                            <form onSubmit={submitConfig} className="space-y-3 text-sm">
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label htmlFor="free_dem">Free Demurrage</Label>
                                        <Input
                                            id="free_dem"
                                            type="number"
                                            value={configForm.data.free_days_demurrage}
                                            onChange={(e) => configForm.setData('free_days_demurrage', parseInt(e.target.value) || 0)}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="free_det">Free Detention</Label>
                                        <Input
                                            id="free_det"
                                            type="number"
                                            value={configForm.data.free_days_detention}
                                            onChange={(e) => configForm.setData('free_days_detention', parseInt(e.target.value) || 0)}
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label htmlFor="offset_dem">Offset Demurrage</Label>
                                        <select
                                            id="offset_dem"
                                            className="bg-background w-full rounded-md border p-2"
                                            value={configForm.data.start_offset_demurrage}
                                            onChange={(e) => configForm.setData('start_offset_demurrage', e.target.value)}
                                        >
                                            <option value="H+1">H+1 (Mulai esok)</option>
                                            <option value="H+0">H+0 (Hari bongkar)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="offset_det">Offset Detention</Label>
                                        <select
                                            id="offset_det"
                                            className="bg-background w-full rounded-md border p-2"
                                            value={configForm.data.start_offset_detention}
                                            onChange={(e) => configForm.setData('start_offset_detention', e.target.value)}
                                        >
                                            <option value="H+1">H+1 (Mulai esok)</option>
                                            <option value="H+0">H+0 (Hari gate-out)</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label htmlFor="cal_dem">Kalender Demurrage</Label>
                                        <select
                                            id="cal_dem"
                                            className="bg-background w-full rounded-md border p-2"
                                            value={configForm.data.calendar_type_demurrage}
                                            onChange={(e) => configForm.setData('calendar_type_demurrage', e.target.value)}
                                        >
                                            <option value="calendar">Kalender Penuh</option>
                                            <option value="working">Hari Kerja Saja</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="cal_det">Kalender Detention</Label>
                                        <select
                                            id="cal_det"
                                            className="bg-background w-full rounded-md border p-2"
                                            value={configForm.data.calendar_type_detention}
                                            onChange={(e) => configForm.setData('calendar_type_detention', e.target.value)}
                                        >
                                            <option value="calendar">Kalender Penuh</option>
                                            <option value="working">Hari Kerja Saja</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="mode">Mode Perhitungan</Label>
                                    <select
                                        id="mode"
                                        className="bg-background w-full rounded-md border p-2"
                                        value={configForm.data.mode}
                                        onChange={(e) => configForm.setData('mode', e.target.value)}
                                    >
                                        <option value="terpisah">Terpisah (Demurrage & Detention Mandiri)</option>
                                        <option value="gabungan">Gabungan (Combined Free Time Pool)</option>
                                    </select>
                                </div>

                                <Button type="submit" disabled={configForm.processing} className="w-full">
                                    Simpan Konfigurasi Line
                                </Button>
                            </form>
                        </div>

                        {/* 2. Daftar & Input Tarif Tiering */}
                        <div className="space-y-6 lg:col-span-2">
                            <div className="rounded-xl border p-4">
                                <h3 className="mb-3 font-medium">Tambah Tiering Tarif Baru (Draft)</h3>
                                <form onSubmit={submitTariff} className="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                    <div>
                                        <Label htmlFor="charge_type">Jenis Biaya</Label>
                                        <select
                                            id="charge_type"
                                            className="bg-background w-full rounded-md border p-2"
                                            value={tariffForm.data.charge_type}
                                            onChange={(e) => tariffForm.setData('charge_type', e.target.value)}
                                        >
                                            <option value="demurrage">Demurrage</option>
                                            <option value="detention">Detention</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="size">Ukuran Kontainer</Label>
                                        <select
                                            id="size"
                                            className="bg-background w-full rounded-md border p-2"
                                            value={tariffForm.data.container_size}
                                            onChange={(e) => tariffForm.setData('container_size', e.target.value)}
                                        >
                                            <option value="20">20'</option>
                                            <option value="40">40'</option>
                                            <option value="45">45'</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label htmlFor="day_from">Hari Mulai</Label>
                                        <Input
                                            id="day_from"
                                            type="number"
                                            min="1"
                                            value={tariffForm.data.day_from}
                                            onChange={(e) => tariffForm.setData('day_from', parseInt(e.target.value) || 1)}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="day_to">Hari Sampai (Kosong = s/d Selesai)</Label>
                                        <Input
                                            id="day_to"
                                            type="number"
                                            placeholder="e.g. 3 atau kosong"
                                            value={tariffForm.data.day_to}
                                            onChange={(e) => tariffForm.setData('day_to', e.target.value ? parseInt(e.target.value) : '')}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="rate">Tarif / Hari (USD)</Label>
                                        <Input
                                            id="rate"
                                            type="number"
                                            step="0.01"
                                            placeholder="Contoh: 35.00"
                                            value={tariffForm.data.rate_per_day}
                                            onChange={(e) => tariffForm.setData('rate_per_day', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="eff_from">Berlaku Dari</Label>
                                        <Input
                                            id="eff_from"
                                            type="date"
                                            value={tariffForm.data.effective_from}
                                            onChange={(e) => tariffForm.setData('effective_from', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="col-span-2 flex items-end">
                                        <Button type="submit" disabled={tariffForm.processing} className="w-full">
                                            Ajukan Tarif (Draft)
                                        </Button>
                                    </div>
                                </form>
                            </div>

                            <div className="overflow-x-auto rounded-xl border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted/50 border-b text-left">
                                            <th className="px-3 py-2">Jenis</th>
                                            <th className="px-3 py-2">Ukuran</th>
                                            <th className="px-3 py-2">Tier Hari</th>
                                            <th className="px-3 py-2">Tarif/Hari</th>
                                            <th className="px-3 py-2">Berlaku</th>
                                            <th className="px-3 py-2">Status</th>
                                            <th className="px-3 py-2">Pembuat</th>
                                            <th className="px-3 py-2">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tariffs.map((t) => {
                                            const isCreator = authUser?.id === t.creator?.id;
                                            return (
                                                <tr key={t.id} className="border-b last:border-b-0">
                                                    <td className="px-3 py-2 font-medium capitalize">{t.charge_type}</td>
                                                    <td className="px-3 py-2 font-mono">{t.container_size}'</td>
                                                    <td className="px-3 py-2 font-mono">
                                                        Hari {t.day_from} {t.day_to ? `s.d ${t.day_to}` : 'ke atas'}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono font-bold">
                                                        {t.currency_code} {parseFloat(t.rate_per_day).toFixed(2)}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs">
                                                        {t.effective_from} {t.effective_to ? `s.d ${t.effective_to}` : ''}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Badge variant={t.status === 'active' ? 'default' : 'secondary'}>{t.status}</Badge>
                                                    </td>
                                                    <td className="text-muted-foreground px-3 py-2 text-xs">{t.creator?.name || '-'}</td>
                                                    <td className="px-3 py-2">
                                                        {t.status === 'draft' && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                disabled={isCreator}
                                                                title={isCreator ? 'Pembuat tidak boleh approve sendiri (ADR 0006)' : 'Setujui Tarif'}
                                                                onClick={() => approveTariff(t.id)}
                                                            >
                                                                {isCreator ? 'Menunggu Approval' : 'Setujui'}
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {tariffs.length === 0 && (
                                            <tr>
                                                <td colSpan={8} className="text-muted-foreground py-6 text-center">
                                                    Belum ada tarif terdaftar untuk pelayaran ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
