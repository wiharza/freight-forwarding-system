import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type DemurrageSummary = {
    line: string;
    total_containers: number;
    total_demurrage_days: number;
    total_detention_days: number;
};

export default function ReportDemurrage({ summary }: { summary: DemurrageSummary[] }) {
    return (
        <AppLayout>
            <Head title="Denda per Line" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Denda Demurrage/Detention per Line (§7e)</h2>
                <table className="mt-4 w-full overflow-hidden rounded-xl border text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-3 py-2">Line</th>
                            <th className="px-3 py-2">Kontainer</th>
                            <th className="px-3 py-2">Demurrage (hr)</th>
                            <th className="px-3 py-2">Detention (hr)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {summary.map((s, i: number) => (
                            <tr key={i} className="border-b">
                                <td className="px-3 py-2 font-bold">{s.line}</td>
                                <td className="px-3 py-2">{s.total_containers}</td>
                                <td className="px-3 py-2 font-mono">{s.total_demurrage_days}</td>
                                <td className="px-3 py-2 font-mono">{s.total_detention_days}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
