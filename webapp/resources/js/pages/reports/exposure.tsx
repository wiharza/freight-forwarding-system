import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function ReportExposure({ exposures }: { exposures: any[] }) {
    return (
        <AppLayout>
            <Head title="Laporan Eksposur" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Eksposur per Importir (§7b)</h2>
                <table className="mt-4 w-full text-sm border rounded-xl overflow-hidden">
                    <thead className="bg-muted/50 text-left"><tr><th className="px-3 py-2">Importir</th><th className="px-3 py-2">Eksposur Bruto</th><th className="px-3 py-2">Titipan Dana</th></tr></thead>
                    <tbody>{exposures.map((e: any, i: number) => (<tr key={i} className="border-b"><td className="px-3 py-2">{e.importir_name}</td><td className="px-3 py-2 font-mono">Rp {e.exposure_bruto.toLocaleString()}</td><td className="px-3 py-2 font-mono">Rp {e.titipan_dana_saldo.toLocaleString()}</td></tr>))}</tbody>
                </table>
            </div>
        </AppLayout>
    );
}