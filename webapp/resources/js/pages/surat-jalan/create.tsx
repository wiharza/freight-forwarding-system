import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type TripLite = {
    id: number;
    trip_number: string;
    driver_name: string | null;
    driver_phone: string | null;
    vehicle_number: string | null;
    job: { job_number: string; importir?: { name: string } | null } | null;
    vendor: { name: string } | null;
    origin: { name: string } | null;
    destination: { name: string } | null;
    containers: { id: number; container_number: string; seal_number?: string | null; gross_weight_kg?: string | null }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trucking', href: '/trips' },
    { title: 'Surat Jalan', href: '#' },
];

export default function SuratJalanCreate({ trip }: { trip: TripLite }) {
    const { data, setData, post, processing, errors } = useForm({
        sj_number: `SJ-${trip.trip_number}`,
        sj_date: new Date().toISOString().split('T')[0],
        driver_name: trip.driver_name ?? '',
        driver_phone: trip.driver_phone ?? '',
        vehicle_number: trip.vehicle_number ?? '',
        origin_address: trip.origin?.name ?? '',
        destination_address: trip.destination?.name ?? '',
        containers_info: trip.containers.map((c) => ({
            container_number: c.container_number,
            seal_number: c.seal_number ?? '',
            gross_weight_kg: c.gross_weight_kg ?? '',
        })),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('surat-jalan.store', trip.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Buat Surat Jalan" />
            <div className="flex h-full max-w-4xl flex-1 flex-col gap-6 rounded-xl p-4">
                <div>
                    <h2 className="text-lg font-medium">Surat Jalan — Trip {trip.trip_number}</h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Job {trip.job?.job_number ?? '-'} · Vendor {trip.vendor?.name ?? '-'} · Data terisi otomatis dari trip.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="bg-card space-y-4 rounded-xl border p-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="sj_number">No. Surat Jalan</Label>
                                <Input id="sj_number" value={data.sj_number} onChange={(e) => setData('sj_number', e.target.value)} required />
                                {errors.sj_number && <p className="text-xs text-red-600">{errors.sj_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="sj_date">Tanggal</Label>
                                <Input id="sj_date" type="date" value={data.sj_date} onChange={(e) => setData('sj_date', e.target.value)} required />
                            </div>
                            <div>
                                <Label htmlFor="driver_name">Nama Pengemudi</Label>
                                <Input id="driver_name" value={data.driver_name} onChange={(e) => setData('driver_name', e.target.value)} required />
                            </div>
                            <div>
                                <Label htmlFor="driver_phone">Telp Pengemudi</Label>
                                <Input
                                    id="driver_phone"
                                    value={data.driver_phone}
                                    onChange={(e) => setData('driver_phone', e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="vehicle_number">No. Kendaraan</Label>
                                <Input
                                    id="vehicle_number"
                                    value={data.vehicle_number}
                                    onChange={(e) => setData('vehicle_number', e.target.value)}
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <div className="bg-card space-y-4 rounded-xl border p-4">
                        <div className="grid gap-4">
                            <div>
                                <Label htmlFor="origin_address">Alamat Asal</Label>
                                <Input
                                    id="origin_address"
                                    value={data.origin_address}
                                    onChange={(e) => setData('origin_address', e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="destination_address">Alamat Tujuan</Label>
                                <Input
                                    id="destination_address"
                                    value={data.destination_address}
                                    onChange={(e) => setData('destination_address', e.target.value)}
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <div className="bg-card space-y-3 rounded-xl border p-4">
                        <h3 className="border-b pb-2 text-sm font-medium">Kontainer Dibawa</h3>
                        {data.containers_info.map((c, i) => (
                            <div key={i} className="grid items-end gap-3 sm:grid-cols-3">
                                <div>
                                    <Label className="text-xs">No. Kontainer</Label>
                                    <Input
                                        value={c.container_number}
                                        onChange={(e) => {
                                            const next = [...data.containers_info];
                                            next[i] = { ...next[i], container_number: e.target.value.toUpperCase() };
                                            setData('containers_info', next);
                                        }}
                                        required
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">No. Seal</Label>
                                    <Input
                                        value={c.seal_number}
                                        onChange={(e) => {
                                            const next = [...data.containers_info];
                                            next[i] = { ...next[i], seal_number: e.target.value };
                                            setData('containers_info', next);
                                        }}
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">Berat (KG)</Label>
                                    <Input
                                        type="number"
                                        value={c.gross_weight_kg}
                                        onChange={(e) => {
                                            const next = [...data.containers_info];
                                            next[i] = { ...next[i], gross_weight_kg: e.target.value };
                                            setData('containers_info', next);
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing} className="px-8">
                            Simpan Surat Jalan
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
