import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';

type ItemRow = {
    id: number;
    blok: 'a' | 'b' | 'c';
    description: string;
    currency_code: string;
    amount_valas: string;
    kurs: string;
    dpp: string;
    ppn_rate: string;
    ppn_amount: string;
    total_line: string;
};

type AllocationRow = { id: number; amount: string; blok: string; reason: string | null };
type PaymentRow = {
    id: number;
    payment_date: string;
    amount: string;
    method: string;
    reference: string | null;
    allocations: AllocationRow[];
};

type InvoiceDetail = {
    id: number;
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    status: string;
    blok_a_total: string;
    blok_a_bm: string;
    blok_a_ppn_impor: string;
    blok_a_pph22: string;
    blok_a_ppnbm: string;
    blok_a_pungutan_lain: string;
    blok_b_total: string;
    blok_c_service_fee: string;
    blok_c_dpp_lain: string;
    blok_c_ppn_rate: string;
    blok_c_ppn: string;
    total_invoice: string;
    total_paid: string;
    faktur_pajak_number: string | null;
    notes: string | null;
    created_by_user_id: number | null;
    job: { id: number; job_number: string } | null;
    importir: { id: number; name: string } | null;
    items: ItemRow[];
    payments: PaymentRow[];
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
};

const rupiah = (v: string | number) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '#' },
    { title: 'Invoice', href: '/finance/invoices' },
    { title: 'Detail', href: '#' },
];

export default function InvoiceShow({ invoice }: { invoice: InvoiceDetail }) {
    const authUserId = usePage<SharedData>().props.auth.user.id;
    const sisa = Number(invoice.total_invoice) - Number(invoice.total_paid);

    const approveForm = useForm({ faktur_pajak_number: '' });
    const paymentForm = useForm({ payment_date: new Date().toISOString().split('T')[0], amount: '', method: 'transfer', reference: '', notes: '' });
    const allocForm = useForm({
        invoice_id: String(invoice.id),
        allocations: [{ invoice_item_id: '' as string, amount: '', blok: 'a' as string, reason: '' }],
    });

    const canApprove = invoice.status === 'draft' && invoice.created_by_user_id !== authUserId;

    const addAllocation = () =>
        allocForm.setData('allocations', [...allocForm.data.allocations, { invoice_item_id: '', amount: '', blok: 'a', reason: '' }]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={invoice.invoice_number} />
            <div className="flex h-full max-w-6xl flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="font-mono text-lg font-medium">{invoice.invoice_number}</h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Job {invoice.job?.job_number ?? '-'} · {invoice.importir?.name ?? '-'} · Dibuat oleh {invoice.creator?.name ?? '-'}{' '}
                            {invoice.approver ? `· Disetujui ${invoice.approver.name}` : ''}
                        </p>
                    </div>
                    <Badge variant={invoice.status === 'locked' ? 'default' : invoice.status === 'approved' ? 'secondary' : 'outline'}>
                        {invoice.status}
                    </Badge>
                </div>

                {/* Ringkasan 3 blok */}
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="bg-card rounded-xl border p-4">
                        <h3 className="mb-2 border-b pb-1 text-sm font-medium">Blok (a) — Pungutan Negara</h3>
                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <dt>Bea Masuk</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_a_bm)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>PPN Impor</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_a_ppn_impor)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>PPh 22</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_a_pph22)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>PPnBM</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_a_ppnbm)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>Pungutan Lain</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_a_pungutan_lain)}</dd>
                            </div>
                            <div className="flex justify-between border-t pt-1 font-medium">
                                <dt>Total Blok A</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_a_total)}</dd>
                            </div>
                        </dl>
                        <p className="text-muted-foreground mt-2 text-[10px]">Tidak termasuk Batas Approve (ADR 0007).</p>
                    </div>
                    <div className="bg-card rounded-xl border p-4">
                        <h3 className="mb-2 border-b pb-1 text-sm font-medium">Blok (b) — Pihak Ketiga</h3>
                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between font-medium">
                                <dt>Pass-through</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_b_total)}</dd>
                            </div>
                        </dl>
                        <h3 className="mt-4 mb-2 border-b pb-1 text-sm font-medium">Blok (c) — Jasa PPJK</h3>
                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <dt>Service Fee</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_c_service_fee)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>DPP (11/12)</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_c_dpp_lain)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>PPN × {invoice.blok_c_ppn_rate}</dt>
                                <dd className="font-mono">{rupiah(invoice.blok_c_ppn)}</dd>
                            </div>
                        </dl>
                    </div>
                    <div className="bg-card rounded-xl border p-4">
                        <h3 className="mb-2 border-b pb-1 text-sm font-medium">Total</h3>
                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <dt>Total Invoice</dt>
                                <dd className="font-mono font-medium">{rupiah(invoice.total_invoice)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt>Sudah Dibayar</dt>
                                <dd className="font-mono">{rupiah(invoice.total_paid)}</dd>
                            </div>
                            <div className="flex justify-between border-t pt-1 font-medium">
                                <dt>Sisa</dt>
                                <dd className="font-mono">{rupiah(sisa)}</dd>
                            </div>
                        </dl>
                        {invoice.faktur_pajak_number && (
                            <p className="mt-2 text-xs">
                                FP: <span className="font-mono">{invoice.faktur_pajak_number}</span>
                            </p>
                        )}
                    </div>
                </div>

                {/* Items */}
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 border-b text-left">
                                <th className="px-3 py-2">Blok</th>
                                <th className="px-3 py-2">Keterangan</th>
                                <th className="px-3 py-2 text-right">DPP</th>
                                <th className="px-3 py-2 text-right">PPN</th>
                                <th className="px-3 py-2 text-right">Total Baris</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoice.items.map((it) => (
                                <tr key={it.id} className="border-b last:border-b-0">
                                    <td className="px-3 py-2 uppercase">{it.blok}</td>
                                    <td className="px-3 py-2">{it.description}</td>
                                    <td className="px-3 py-2 text-right font-mono">{rupiah(it.dpp)}</td>
                                    <td className="px-3 py-2 text-right font-mono">{rupiah(it.ppn_amount)}</td>
                                    <td className="px-3 py-2 text-right font-mono">{rupiah(it.total_line)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Approve */}
                {canApprove && (
                    <div className="bg-card space-y-3 rounded-xl border p-4">
                        <h3 className="text-sm font-medium">Setujui Invoice (Maker-Checker, ADR 0006)</h3>
                        <p className="text-muted-foreground text-xs">
                            Batas approve hanya atas {rupiah(Number(invoice.total_invoice) - Number(invoice.blok_a_total))} (di luar Blok Pungutan
                            Negara, ADR 0007).
                        </p>
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <Label htmlFor="faktur_pajak_number">Nomor Faktur Pajak (opsional)</Label>
                                <Input
                                    id="faktur_pajak_number"
                                    value={approveForm.data.faktur_pajak_number}
                                    onChange={(e) => approveForm.setData('faktur_pajak_number', e.target.value)}
                                />
                            </div>
                            <Button
                                disabled={approveForm.processing}
                                onClick={() => router.post(route('finance.invoices.approve', invoice.id), approveForm.data)}
                            >
                                Setujui
                            </Button>
                        </div>
                    </div>
                )}

                {/* Pembayaran */}
                {invoice.status !== 'draft' && (
                    <div className="bg-card space-y-3 rounded-xl border p-4">
                        <h3 className="text-sm font-medium">Catat Pembayaran</h3>
                        <div className="grid items-end gap-3 sm:grid-cols-5">
                            <div>
                                <Label htmlFor="payment_date">Tanggal</Label>
                                <Input
                                    id="payment_date"
                                    type="date"
                                    value={paymentForm.data.payment_date}
                                    onChange={(e) => paymentForm.setData('payment_date', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="amount">Jumlah (Rp)</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    value={paymentForm.data.amount}
                                    onChange={(e) => paymentForm.setData('amount', e.target.value)}
                                />
                                {paymentForm.errors.amount && <p className="text-xs text-red-600">{paymentForm.errors.amount}</p>}
                            </div>
                            <div>
                                <Label htmlFor="method">Metode</Label>
                                <Input id="method" value={paymentForm.data.method} onChange={(e) => paymentForm.setData('method', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="reference">Referensi</Label>
                                <Input
                                    id="reference"
                                    value={paymentForm.data.reference}
                                    onChange={(e) => paymentForm.setData('reference', e.target.value)}
                                />
                            </div>
                            <Button
                                disabled={paymentForm.processing}
                                onClick={() =>
                                    paymentForm.post(route('finance.invoices.payments.store', invoice.id), {
                                        onSuccess: () => paymentForm.reset('amount', 'reference', 'notes'),
                                    })
                                }
                            >
                                Simpan
                            </Button>
                        </div>

                        {invoice.payments.length > 0 && (
                            <div className="space-y-3 pt-2">
                                {invoice.payments.map((p) => (
                                    <div key={p.id} className="bg-muted/20 rounded-lg border p-3">
                                        <div className="flex flex-wrap justify-between text-sm">
                                            <span>
                                                {p.payment_date} · {p.method} {p.reference ? `· ${p.reference}` : ''}
                                            </span>
                                            <span className="font-mono">
                                                {rupiah(p.amount)} · teralokasi {rupiah(p.allocations.reduce((s, a) => s + Number(a.amount), 0))}
                                            </span>
                                        </div>
                                        {p.allocations.length > 0 && (
                                            <ul className="text-muted-foreground mt-1 list-inside list-disc text-xs">
                                                {p.allocations.map((a) => (
                                                    <li key={a.id}>
                                                        Blok {a.blok.toUpperCase()} — {rupiah(a.amount)} {a.reason ? `(${a.reason})` : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Alokasi 2-langkah */}
                {invoice.status !== 'draft' && invoice.payments.some((p) => p.allocations.length === 0) && (
                    <div className="bg-card space-y-3 rounded-xl border p-4">
                        <h3 className="text-sm font-medium">Alokasi Pembayaran (2-langkah, §6.3)</h3>
                        {allocForm.data.allocations.map((a, i) => (
                            <div key={i} className="grid items-end gap-3 sm:grid-cols-4">
                                <div>
                                    <Label className="text-xs">Blok</Label>
                                    <select
                                        className="bg-background mt-1 w-full rounded-md border p-2 text-sm"
                                        value={a.blok}
                                        onChange={(e) => {
                                            const next = [...allocForm.data.allocations];
                                            next[i] = { ...next[i], blok: e.target.value };
                                            allocForm.setData('allocations', next);
                                        }}
                                    >
                                        <option value="a">A — Pungutan Negara</option>
                                        <option value="b">B — Pihak Ketiga</option>
                                        <option value="c">C — Jasa PPJK</option>
                                    </select>
                                </div>
                                <div>
                                    <Label className="text-xs">Jumlah (Rp)</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={a.amount}
                                        onChange={(e) => {
                                            const next = [...allocForm.data.allocations];
                                            next[i] = { ...next[i], amount: e.target.value };
                                            allocForm.setData('allocations', next);
                                        }}
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <Label className="text-xs">Alasan (opsional)</Label>
                                    <Input
                                        value={a.reason}
                                        onChange={(e) => {
                                            const next = [...allocForm.data.allocations];
                                            next[i] = { ...next[i], reason: e.target.value };
                                            allocForm.setData('allocations', next);
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                        {allocForm.errors.allocations && <p className="text-xs text-red-600">{allocForm.errors.allocations}</p>}
                        <div className="flex gap-3">
                            <Button variant="outline" size="sm" onClick={addAllocation}>
                                + Baris
                            </Button>
                            <Button
                                size="sm"
                                disabled={allocForm.processing}
                                onClick={() =>
                                    router.post(
                                        route('finance.payments.allocate', invoice.payments.find((p) => p.allocations.length === 0)!.id),
                                        allocForm.data,
                                        { onSuccess: () => allocForm.reset() },
                                    )
                                }
                            >
                                Alokasikan
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
