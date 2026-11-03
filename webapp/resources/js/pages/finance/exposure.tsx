import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

type ExposureRow = {
    importir_id: number;
    importir_name: string;
    exposure_bruto: number;
    titipan_dana_saldo: number;
};

const rupiah = (v: number) => `Rp ${Number(v ?? 0).toLocaleString('id-ID')}`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '#' },
    { title: 'Eksposur per Importir', href: '/finance/exposure' },
];

export default function Exposure({ exposures }: { exposures: ExposureRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Eksposur per Importir" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h2 className="text-lg font-medium">Eksposur per Importir (§7b, Q18)</h2>
                    <p className="text-xs text-muted-foreground mt-1">
                        Eksposur bruto = real-cost tertagih − alokasi pembayaran. Saldo Titipan Dana ditampilkan terpisah (bukan pengurang).
                    </p>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left">
                                <th className="px-3 py-2">Importir</th>
                                <th className="px-3 py-2 text-right">Eksposur Bruto</th>
                                <th className="px-3 py-2 text-right">Saldo Titipan Dana</th>
                            </tr>
                        </thead>
                        <tbody>
                            {exposures.length === 0 && (
                                <tr><td colSpan={3} className="px-3 py-4 text-center text-muted-foreground">Belum ada data.</td></tr>
                            )}
                            {exposures.map((e) => (
                                <tr key={e.importir_id} className="border-b last:border-b-0">
                                    <td className="px-3 py-2">{e.importir_name}</td>
                                    <td className={'px-3 py-2 text-right font-mono ' + (e.exposure_bruto > 0 ? 'text-red-600' : '')}>
                                        {rupiah(e.exposure_bruto)}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono">{rupiah(e.titipan_dana_saldo)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
