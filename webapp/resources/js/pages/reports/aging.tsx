import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type AgingInvoice = {
    invoice_number: string;
    importir_name: string;
    due_date: string;
    sisa: number;
    bucket: string;
};

export default function ReportAging({ invoices }: { invoices: AgingInvoice[] }) {
    return (
        <AppLayout>
            <Head title="Piutang & Aging" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Piutang & Aging (§7c)</h2>
                <table className="mt-4 w-full overflow-hidden rounded-xl border text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-3 py-2">Invoice</th>
                            <th className="px-3 py-2">Importir</th>
                            <th className="px-3 py-2">Jatuh Tempo</th>
                            <th className="px-3 py-2">Sisa</th>
                            <th className="px-3 py-2">Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.map((inv, i: number) => (
                            <tr key={i} className="border-b">
                                <td className="px-3 py-2 font-mono">{inv.invoice_number}</td>
                                <td className="px-3 py-2">{inv.importir_name}</td>
                                <td className="px-3 py-2">{inv.due_date}</td>
                                <td className="px-3 py-2 font-mono">Rp {inv.sisa.toLocaleString()}</td>
                                <td className="px-3 py-2 font-medium">{inv.bucket}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
