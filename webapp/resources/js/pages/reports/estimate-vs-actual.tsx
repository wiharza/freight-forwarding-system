import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type EstimateRow = {
    job_number: string;
    importir_name: string | null;
    total_estimasi: number;
    total_aktual: number;
    selisih: number;
};

export default function ReportEstimateVsActual({ rows }: { rows: EstimateRow[] }) {
    return (
        <AppLayout>
            <Head title="Laporan Estimasi vs Aktual" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Estimasi vs Aktual per Job (§7d)</h2>
                <table className="mt-4 w-full overflow-hidden rounded-xl border text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-3 py-2">Job</th>
                            <th className="px-3 py-2">Importir</th>
                            <th className="px-3 py-2">Estimasi</th>
                            <th className="px-3 py-2">Aktual</th>
                            <th className="px-3 py-2">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r, i: number) => (
                            <tr key={i} className="border-b">
                                <td className="px-3 py-2 font-mono">{r.job_number}</td>
                                <td className="px-3 py-2">{r.importir_name ?? '-'}</td>
                                <td className="px-3 py-2 font-mono">Rp {r.total_estimasi.toLocaleString()}</td>
                                <td className="px-3 py-2 font-mono">Rp {r.total_aktual.toLocaleString()}</td>
                                <td className="px-3 py-2 font-mono">Rp {r.selisih.toLocaleString()}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
