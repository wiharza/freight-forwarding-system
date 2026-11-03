import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type TripRow = {
    id: number;
    trip_number: string;
    scheduled_date: string;
    status: string;
    vendor: { id: number; name: string } | null;
    job: { id: number; job_number: string } | null;
    containers: { id: number; container_number: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trucking', href: '#' },
    { title: 'Trips', href: '/trips' },
];

export default function TripsIndex({ trips, canCreate }: { trips: { data: TripRow[] }; canCreate: boolean }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trip Trucking" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium">Trip Trucking (§5 — cabang logistik)</h2>
                        <p className="text-xs text-muted-foreground mt-1">discharge → DO → gate-out → terkirim → gate-in.</p>
                    </div>
                    {canCreate && <Link href={route('trips.create')}><Button>+ Trip Baru</Button></Link>}
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left">
                                <th className="px-3 py-2">No. Trip</th>
                                <th className="px-3 py-2">Job</th>
                                <th className="px-3 py-2">Vendor</th>
                                <th className="px-3 py-2">Jadwal</th>
                                <th className="px-3 py-2">Kontainer</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {trips.data.length === 0 && (
                                <tr><td colSpan={7} className="px-3 py-4 text-center text-muted-foreground">Belum ada trip.</td></tr>
                            )}
                            {trips.data.map((t) => (
                                <tr key={t.id} className="border-b last:border-b-0">
                                    <td className="px-3 py-2 font-mono">{t.trip_number}</td>
                                    <td className="px-3 py-2">{t.job?.job_number ?? '-'}</td>
                                    <td className="px-3 py-2">{t.vendor?.name ?? '-'}</td>
                                    <td className="px-3 py-2">{t.scheduled_date}</td>
                                    <td className="px-3 py-2 font-mono text-xs">{t.containers.map((c) => c.container_number).join(', ')}</td>
                                    <td className="px-3 py-2"><Badge variant="outline">{t.status}</Badge></td>
                                    <td className="px-3 py-2"><Link href={route('trips.show', t.id)} className="text-xs underline">Detail</Link></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
