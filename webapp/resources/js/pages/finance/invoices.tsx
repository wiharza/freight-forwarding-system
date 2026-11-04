import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type InvoiceRow = {
    id: number;
    invoice_number: string;
    invoice_date: string;
    due_date: string | null;
    status: string;
    total_invoice: string;
    total_paid: string;
    job: { id: number; job_number: string } | null;
    importir: { id: number; name: string } | null;
};

type JobOption = { id: number; job_number: string; importir?: { name: string } | null };

const statusColor = (status: string) =>
    status === 'locked' ? 'default' : status === 'approved' ? 'secondary' : status === 'cancelled' ? 'destructive' : 'outline';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '#' },
    { title: 'Invoice', href: '/finance/invoices' },
];

export default function Invoices({ invoices, jobs }: { invoices: { data: InvoiceRow[] }; jobs: JobOption[] }) {
    const [jobId, setJobId] = useState<string>('');

    const openCreate = () => {
        if (!jobId) return;
        router.get(route('finance.invoices.create', parseInt(jobId)));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoice" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h2 className="text-lg font-medium">Invoice (§6.4–6.5 — 3 Blok + Batas Approve)</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Blok (a) pungutan negara dari Snapshot PIB (ADR 0004); Blok (b) pass-through; Blok (c) jasa + PPN DPP 11/12.
                    </p>
                </div>

                <div className="space-y-3 rounded-xl border p-4">
                    <h3 className="text-sm font-medium">Buat Invoice Baru dari Job</h3>
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="min-w-64">
                            <Label htmlFor="job_pick">Pilih Job</Label>
                            <select
                                id="job_pick"
                                className="bg-background mt-1 w-full rounded-md border p-2 text-sm"
                                value={jobId}
                                onChange={(e) => setJobId(e.target.value)}
                            >
                                <option value="">— Pilih job —</option>
                                {jobs.map((j) => (
                                    <option key={j.id} value={j.id}>
                                        {j.job_number}
                                        {j.importir ? ` — ${j.importir.name}` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button onClick={openCreate} disabled={!jobId}>
                            Buka Form Invoice
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 border-b text-left">
                                <th className="px-3 py-2">Nomor</th>
                                <th className="px-3 py-2">Job</th>
                                <th className="px-3 py-2">Importir</th>
                                <th className="px-3 py-2">Tanggal</th>
                                <th className="px-3 py-2">Jatuh Tempo</th>
                                <th className="px-3 py-2 text-right">Total</th>
                                <th className="px-3 py-2 text-right">Dibayar</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="text-muted-foreground px-3 py-4 text-center">
                                        Belum ada invoice.
                                    </td>
                                </tr>
                            )}
                            {invoices.data.map((inv) => (
                                <tr key={inv.id} className="border-b last:border-b-0">
                                    <td className="px-3 py-2 font-mono">{inv.invoice_number}</td>
                                    <td className="px-3 py-2">{inv.job?.job_number ?? '-'}</td>
                                    <td className="px-3 py-2">{inv.importir?.name ?? '-'}</td>
                                    <td className="px-3 py-2">{inv.invoice_date}</td>
                                    <td className="px-3 py-2">{inv.due_date ?? '-'}</td>
                                    <td className="px-3 py-2 text-right font-mono">Rp {Number(inv.total_invoice).toLocaleString('id-ID')}</td>
                                    <td className="px-3 py-2 text-right font-mono">Rp {Number(inv.total_paid).toLocaleString('id-ID')}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant={statusColor(inv.status) as never}>{inv.status}</Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Link href={route('finance.invoices.show', inv.id)} className="text-xs underline">
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
