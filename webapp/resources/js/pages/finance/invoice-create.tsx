import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type CostRow = {
    id: number;
    cost_type: string;
    currency_code: string;
    amount_valas: string;
    amount_idr: string;
};

type JobLite = {
    id: number;
    job_number: string;
    importir: { id: number; name: string; payment_terms_days?: number | null } | null;
};

const rupiah = (v: string | number) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '#' },
    { title: 'Invoice', href: '/finance/invoices' },
    { title: 'Buat Baru', href: '#' },
];

export default function InvoiceCreate({ job, billableCosts }: { job: JobLite; billableCosts: CostRow[] }) {
    const { data, setData, post, processing, errors } = useForm({
        invoice_date: new Date().toISOString().split('T')[0],
        due_date: '',
        blok_c_service_fee: '',
        blok_c_ppn_rate: '0.12',
        faktur_pajak_number: '',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('finance.invoices.store', job.id));
    };

    const serviceFee = parseFloat(data.blok_c_service_fee || '0');
    const dppLain = serviceFee * (11 / 12);
    const ppnJasa = dppLain * parseFloat(data.blok_c_ppn_rate || '0');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Buat Invoice" />
            <div className="flex h-full max-w-5xl flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h2 className="text-lg font-medium">Buat Invoice — Job {job.job_number}</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Importir: {job.importir?.name ?? '-'} · Blok (a) diambil otomatis dari Snapshot PIB ter-approve (ADR 0004).
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Blok (a) — diambil otomatis server dari snapshot */}
                    <div className="bg-card space-y-2 rounded-xl border p-4">
                        <h3 className="border-b pb-2 text-sm font-medium">Blok (a) — Pungutan Negara (otomatis dari Snapshot PIB)</h3>
                        <p className="text-muted-foreground text-xs">
                            Tidak bisa diedit di sini — sumbernya Snapshot SPT PIB, bukan Kalkulator (ADR 0004). Jika masih 0, pastikan draf PIB sudah
                            di-approve di halaman Kalkulator.
                        </p>
                    </div>

                    {/* Blok (b) — biaya pihak ketiga */}
                    <div className="bg-card space-y-2 rounded-xl border p-4">
                        <h3 className="border-b pb-2 text-sm font-medium">Blok (b) — Biaya Pihak Ketiga (pass-through)</h3>
                        {billableCosts.length === 0 && <p className="text-muted-foreground text-xs">Belum ada biaya aktual yang dapat ditagihkan.</p>}
                        <table className="w-full text-sm">
                            <tbody>
                                {billableCosts.map((c) => (
                                    <tr key={c.id} className="border-b last:border-b-0">
                                        <td className="py-1.5">{c.cost_type}</td>
                                        <td className="py-1.5 text-right font-mono">
                                            {c.currency_code} {Number(c.amount_valas).toLocaleString()}
                                        </td>
                                        <td className="py-1.5 text-right font-mono">{rupiah(c.amount_idr)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Blok (c) — jasa PPJK */}
                    <div className="bg-card space-y-4 rounded-xl border p-4">
                        <h3 className="border-b pb-2 text-sm font-medium">Blok (c) — Jasa PPJK + PPN</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="blok_c_service_fee">Service Fee (Rp)</Label>
                                <Input
                                    id="blok_c_service_fee"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.blok_c_service_fee}
                                    onChange={(e) => setData('blok_c_service_fee', e.target.value)}
                                    required
                                />
                                {errors.blok_c_service_fee && <p className="text-xs text-red-600">{errors.blok_c_service_fee}</p>}
                            </div>
                            <div>
                                <Label htmlFor="blok_c_ppn_rate">Tarif PPN Jasa (0.12 = 12%)</Label>
                                <Input
                                    id="blok_c_ppn_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="0.13"
                                    value={data.blok_c_ppn_rate}
                                    onChange={(e) => setData('blok_c_ppn_rate', e.target.value)}
                                    required
                                />
                            </div>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            DPP = 11/12 × {rupiah(serviceFee)} = {rupiah(dppLain)} → PPN ≈ {rupiah(ppnJasa)}
                        </p>
                    </div>

                    {/* Header invoice */}
                    <div className="bg-card space-y-4 rounded-xl border p-4">
                        <h3 className="border-b pb-2 text-sm font-medium">Header Invoice</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="invoice_date">Tanggal Invoice</Label>
                                <Input
                                    id="invoice_date"
                                    type="date"
                                    value={data.invoice_date}
                                    onChange={(e) => setData('invoice_date', e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="due_date">Jatuh Tempo (opsional — default sesuai termin)</Label>
                                <Input id="due_date" type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                                {errors.due_date && <p className="text-xs text-red-600">{errors.due_date}</p>}
                            </div>
                            <div>
                                <Label htmlFor="faktur_pajak_number">Nomor Faktur Pajak (opsional saat draf)</Label>
                                <Input
                                    id="faktur_pajak_number"
                                    value={data.faktur_pajak_number}
                                    onChange={(e) => setData('faktur_pajak_number', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="notes">Catatan</Label>
                                <Input id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing} className="px-8">
                            Simpan Draf Invoice
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
