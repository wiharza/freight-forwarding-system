import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type CurrencyItem = { id: number; code: string; name: string };
type RateItem = {
    id: number;
    rate_date: string;
    currency_code: string;
    rate_to_idr: string;
    cutoff_time: string;
    is_locked: boolean;
    creator: { id: number; name: string } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '#' },
    { title: 'Kurs Internal', href: '/finance/rates' },
];

export default function ExchangeRates({
    rates,
    activeCurrencies,
    todayRates,
    today,
}: {
    rates: RateItem[];
    activeCurrencies: CurrencyItem[];
    todayRates: Record<string, RateItem>;
    today: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        rate_date: today,
        currency_code: activeCurrencies[0]?.code ?? 'USD',
        rate_to_idr: '',
        cutoff_time: '09:00',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('finance.rates.store'), {
            onSuccess: () => reset('rate_to_idr'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kurs Internal Harian" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h2 className="text-lg font-medium">Kurs Internal Perusahaan (§6.6, Q14, Q24, ADR 0003)</h2>
                    <p className="text-xs text-muted-foreground mt-1">
                        Satu kurs resmi per hari per company per mata uang dengan jam cutoff (09:00). Dipisahkan dari Kurs Pajak (NDPBM).
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-1 rounded-xl border p-4">
                        <h3 className="font-medium mb-3">Input Kurs Hari Ini ({today})</h3>
                        <form onSubmit={submit} className="space-y-3 text-sm">
                            <div>
                                <Label htmlFor="rate_date">Tanggal Kurs</Label>
                                <Input
                                    id="rate_date"
                                    type="date"
                                    value={data.rate_date}
                                    onChange={(e) => setData('rate_date', e.target.value)}
                                    required
                                />
                                {errors.rate_date && <p className="text-xs text-red-600">{errors.rate_date}</p>}
                            </div>

                            <div>
                                <Label htmlFor="currency_code">Mata Uang</Label>
                                <select
                                    id="currency_code"
                                    className="w-full border rounded-md p-2 bg-background"
                                    value={data.currency_code}
                                    onChange={(e) => setData('currency_code', e.target.value)}
                                >
                                    {activeCurrencies.map((c) => (
                                        <option key={c.id} value={c.code}>
                                            {c.code} - {c.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.currency_code && <p className="text-xs text-red-600">{errors.currency_code}</p>}
                            </div>

                            <div>
                                <Label htmlFor="rate_to_idr">Kurs ke IDR (Rupiah)</Label>
                                <Input
                                    id="rate_to_idr"
                                    type="number"
                                    step="0.01"
                                    placeholder="Contoh: 15850.00"
                                    value={data.rate_to_idr}
                                    onChange={(e) => setData('rate_to_idr', e.target.value)}
                                    required
                                />
                                {errors.rate_to_idr && <p className="text-xs text-red-600">{errors.rate_to_idr}</p>}
                            </div>

                            <div>
                                <Label htmlFor="cutoff_time">Jam Cutoff</Label>
                                <Input
                                    id="cutoff_time"
                                    type="time"
                                    value={data.cutoff_time}
                                    onChange={(e) => setData('cutoff_time', e.target.value)}
                                />
                            </div>

                            <Button type="submit" disabled={processing} className="w-full">
                                Simpan Kurs Resmi
                            </Button>
                        </form>
                    </div>

                    <div className="lg:col-span-2 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left">
                                    <th className="px-3 py-2">Tanggal</th>
                                    <th className="px-3 py-2">Valas</th>
                                    <th className="px-3 py-2">Kurs (IDR)</th>
                                    <th className="px-3 py-2">Cutoff</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Diinput Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rates.map((r) => (
                                    <tr key={r.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-mono">{r.rate_date}</td>
                                        <td className="px-3 py-2 font-bold">{r.currency_code}</td>
                                        <td className="px-3 py-2 font-mono font-medium">
                                            Rp {parseFloat(r.rate_to_idr).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                                        </td>
                                        <td className="px-3 py-2 text-xs">{r.cutoff_time}</td>
                                        <td className="px-3 py-2">
                                            <Badge variant={r.is_locked ? 'default' : 'secondary'}>
                                                {r.is_locked ? 'Terkunci (Invoice)' : 'Bisa Diubah'}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">{r.creator?.name || '-'}</td>
                                    </tr>
                                ))}
                                {rates.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-center py-6 text-muted-foreground">
                                            Belum ada riwayat kurs internal.
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
