import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';

type TripDetail = {
    id: number;
    trip_number: string;
    scheduled_date: string;
    status: string;
    driver_name: string | null;
    driver_phone: string | null;
    vehicle_number: string | null;
    actual_arrival: string | null;
    notes: string | null;
    job: { id: number; job_number: string; importir?: { name: string } | null } | null;
    vendor: { id: number; name: string } | null;
    origin: { id: number; name: string } | null;
    destination: { id: number; name: string } | null;
    containers: { id: number; container_number: string; size: string; type: string; pivot?: { sequence?: number } }[];
    surat_jalan: { id: number; sj_number: string; status: string } | null;
    vendor_invoices: { id: number; invoice_number: string; amount: string }[];
};

const STATUSES = ['assigned', 'confirmed', 'in_transit', 'delivered', 'cancelled'] as const;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trucking', href: '/trips' },
    { title: 'Detail Trip', href: '#' },
];

export default function TripsShow({ trip }: { trip: TripDetail }) {
    const statusForm = useForm({ status: trip.status });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={trip.trip_number} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-5xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-medium font-mono">{trip.trip_number}</h2>
                        <p className="text-xs text-muted-foreground mt-1">
                            Job {trip.job?.job_number ?? '-'} · Vendor {trip.vendor?.name ?? '-'} · {trip.scheduled_date}
                        </p>
                    </div>
                    <Badge variant={trip.status === 'delivered' ? 'default' : 'outline'}>{trip.status}</Badge>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border p-4 space-y-2 bg-card text-sm">
                        <h3 className="font-medium text-sm border-b pb-1">Rute & Pengemudi</h3>
                        <p>Asal: {trip.origin?.name ?? '-'}</p>
                        <p>Tujuan: {trip.destination?.name ?? '-'}</p>
                        <p>Pengemudi: {trip.driver_name ?? '-'} {trip.driver_phone ? `(${trip.driver_phone})` : ''}</p>
                        <p>Kendaraan: <span className="font-mono">{trip.vehicle_number ?? '-'}</span></p>
                        <p>Kedatangan aktual: {trip.actual_arrival ?? '-'}</p>
                    </div>
                    <div className="rounded-xl border p-4 space-y-2 bg-card text-sm">
                        <h3 className="font-medium text-sm border-b pb-1">Kontainer</h3>
                        <ul className="list-disc list-inside font-mono text-xs">
                            {trip.containers.map((c) => (
                                <li key={c.id}>{c.container_number} — {c.size}' {c.type}</li>
                            ))}
                        </ul>
                        <h3 className="font-medium text-sm border-b pb-1 mt-3">Surat Jalan</h3>
                        {trip.surat_jalan ? (
                            <p>
                                <Link href={route('surat-jalan.show', trip.surat_jalan.id)} className="underline font-mono">
                                    {trip.surat_jalan.sj_number}
                                </Link>{' '}
                                <Badge variant="outline">{trip.surat_jalan.status}</Badge>
                            </p>
                        ) : (
                            <p className="text-muted-foreground">Belum ada — buat di bawah.</p>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border p-4 space-y-3 bg-card">
                    <h3 className="font-medium text-sm">Status Trip</h3>
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <Label htmlFor="status">Ubah Status</Label>
                            <select
                                id="status"
                                className="border rounded-md p-2 text-sm bg-background mt-1"
                                value={statusForm.data.status}
                                onChange={(e) => statusForm.setData('status', e.target.value)}
                            >
                                {STATUSES.map((s) => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </select>
                        </div>
                        <Button variant="outline" disabled={statusForm.processing} onClick={() => statusForm.patch(route('trips.status', trip.id))}>
                            Simpan Status
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Link href={route('surat-jalan.create', trip.id)}>
                        <Button variant={trip.surat_jalan ? 'outline' : 'default'}>
                            {trip.surat_jalan ? 'Lihat / Perbarui Surat Jalan' : 'Buat Surat Jalan'}
                        </Button>
                    </Link>
                    <Button variant="outline" onClick={() => router.post(route('trips.print-sj', trip.id))}>
                        Cetak Surat Jalan
                    </Button>
                </div>

                {trip.vendor_invoices.length > 0 && (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-left">
                                    <th className="px-3 py-2">Invoice Vendor</th>
                                    <th className="px-3 py-2 text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                {trip.vendor_invoices.map((vi) => (
                                    <tr key={vi.id} className="border-b last:border-b-0">
                                        <td className="px-3 py-2 font-mono">{vi.invoice_number}</td>
                                        <td className="px-3 py-2 text-right font-mono">{vi.amount}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
