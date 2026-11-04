import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type DepositFlowRow = {
    job_number: string;
    container_number: string;
    status: string;
    amount_usd: number;
    days_held: number;
};

export default function ReportDepositFlow({ deposits }: { deposits: DepositFlowRow[] }) {
    return (
        <AppLayout>
            <Head title="Arus Deposit Kontainer" />
            <div className="p-4">
                <h2 className="text-lg font-medium">Arus Deposit Kontainer (§7f)</h2>
                <table className="mt-4 w-full overflow-hidden rounded-xl border text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-3 py-2">Job</th>
                            <th className="px-3 py-2">Kontainer</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">USD</th>
                            <th className="px-3 py-2">Hari Ditahan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {deposits.map((d, i: number) => (
                            <tr key={i} className="border-b">
                                <td className="px-3 py-2 font-mono">{d.job_number}</td>
                                <td className="px-3 py-2 font-mono">{d.container_number}</td>
                                <td className="px-3 py-2">{d.status}</td>
                                <td className="px-3 py-2 font-mono">{d.amount_usd}</td>
                                <td className="px-3 py-2">{d.days_held}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
