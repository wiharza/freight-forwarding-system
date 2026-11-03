import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function ReportAging({ invoices }: { invoices: any[] }) {
    return (
        <AppLayout>
            <Head title="Piutang & Aging" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Piutang & Aging (§7c)</h2>
                <table className="mt-4 w-full text-sm border rounded-xl overflow-hidden">
                    <thead className="bg-muted/50 text-left"><tr><th className="px-3 py-2">Invoice</th><th className="px-3 py-2">Importir</th><th className="px-3 py-2">Jatuh Tempo</th><th className="px-3 py-2">Sisa</th><th className="px-3 py-2">Bucket</th></tr></thead>
                    <tbody>{invoices.map((inv: any, i: number) => (<tr key={i} className="border-b"><td className="px-3 py-2 font-mono">{inv.invoice_number}</td><td className="px-3 py-2">{inv.importir_name}</td><td className="px-3 py-2">{inv.due_date}</td><td className="px-3 py-2 font-mono">Rp {inv.sisa.toLocaleString()}</td><td className="px-3 py-2 font-medium">{inv.bucket}</td></tr>))}</tbody>
                </table>
            </div>
        </AppLayout>
    );
}