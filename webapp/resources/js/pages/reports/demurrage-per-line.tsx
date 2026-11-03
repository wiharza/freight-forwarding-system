import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function ReportDemurrage({ summary }: { summary: any[] }) {
    return (
        <AppLayout>
            <Head title="Denda per Line" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Denda Demurrage/Detention per Line (§7e)</h2>
                <table className="mt-4 w-full text-sm border rounded-xl overflow-hidden">
                    <thead className="bg-muted/50 text-left"><tr><th className="px-3 py-2">Line</th><th className="px-3 py-2">Kontainer</th><th className="px-3 py-2">Demurrage (hr)</th><th className="px-3 py-2">Detention (hr)</th></tr></thead>
                    <tbody>{summary.map((s: any, i: number) => (<tr key={i} className="border-b"><td className="px-3 py-2 font-bold">{s.line}</td><td className="px-3 py-2">{s.total_containers}</td><td className="px-3 py-2 font-mono">{s.total_demurrage_days}</td><td className="px-3 py-2 font-mono">{s.total_detention_days}</td></tr>))}</tbody>
                </table>
            </div>
        </AppLayout>
    );
}