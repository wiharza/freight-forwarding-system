import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type JobOption = {
    id: number;
    job_number: string;
    containers: { id: number; container_number: string; size: string; type: string }[];
};
type VendorOption = { id: number; name: string; vendor_type: string };
type LocationOption = { id: number; name: string; code: string; type: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trucking', href: '/trips' },
    { title: 'Trip Baru', href: '/trips/create' },
];

export default function TripsCreate({
    vendors,
    jobs,
    locations,
}: {
    vendors: VendorOption[];
    jobs: JobOption[];
    locations: LocationOption[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        job_id: '' as number | '',
        vendor_id: vendors[0]?.id ?? ('' as number | ''),
        trip_number: '',
        scheduled_date: new Date().toISOString().split('T')[0],
        driver_name: '',
        driver_phone: '',
        vehicle_number: '',
        origin_location_id: '' as number | '',
        destination_location_id: '' as number | '',
        container_ids: [] as number[],
        notes: '',
    });

    const job = jobs.find((j) => j.id === data.job_id);

    const toggleContainer = (cid: number) => {
        const has = data.container_ids.includes(cid);
        setData('container_ids', has ? data.container_ids.filter((x) => x !== cid) : [...data.container_ids, cid]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('trips.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trip Baru" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-5xl">
                <h2 className="text-lg font-medium">Penugasan Trip Trucking</h2>

                <form onSubmit={submit} className="space-y-6">
                    <div className="rounded-xl border p-4 space-y-4 bg-card">
                        <h3 className="font-medium text-sm border-b pb-2">Job & Vendor</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="job_id">Job</Label>
                                <select
                                    id="job_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.job_id}
                                    onChange={(e) => setData('job_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                    required
                                >
                                    <option value="">— Pilih job —</option>
                                    {jobs.map((j) => (
                                        <option key={j.id} value={j.id}>{j.job_number}</option>
                                    ))}
                                </select>
                                {errors.job_id && <p className="text-xs text-red-600">{errors.job_id}</p>}
                            </div>
                            <div>
                                <Label htmlFor="vendor_id">Vendor Trucking</Label>
                                <select
                                    id="vendor_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.vendor_id}
                                    onChange={(e) => setData('vendor_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                    required
                                >
                                    {vendors.map((v) => (
                                        <option key={v.id} value={v.id}>{v.name} ({v.vendor_type})</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="trip_number">No. Trip</Label>
                                <Input id="trip_number" value={data.trip_number} onChange={(e) => setData('trip_number', e.target.value)} placeholder="TRP-20261104-001" required />
                                {errors.trip_number && <p className="text-xs text-red-600">{errors.trip_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="scheduled_date">Jadwal</Label>
                                <Input id="scheduled_date" type="date" value={data.scheduled_date} onChange={(e) => setData('scheduled_date', e.target.value)} required />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border p-4 space-y-3 bg-card">
                        <h3 className="font-medium text-sm border-b pb-2">Kontainer (pilih 1..N)</h3>
                        {!job && <p className="text-xs text-muted-foreground">Pilih job terlebih dahulu.</p>}
                        {job && job.containers.length === 0 && <p className="text-xs text-muted-foreground">Job ini belum punya kontainer.</p>}
                        {job && job.containers.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {job.containers.map((c) => (
                                    <label key={c.id} className="flex items-center gap-2 border rounded-md px-3 py-2 text-sm cursor-pointer bg-background">
                                        <input
                                            type="checkbox"
                                            checked={data.container_ids.includes(c.id)}
                                            onChange={() => toggleContainer(c.id)}
                                        />
                                        <span className="font-mono">{c.container_number}</span>
                                        <span className="text-muted-foreground">{c.size}' {c.type}</span>
                                    </label>
                                ))}
                            </div>
                        )}
                        {errors.container_ids && <p className="text-xs text-red-600">{errors.container_ids}</p>}
                    </div>

                    <div className="rounded-xl border p-4 space-y-4 bg-card">
                        <h3 className="font-medium text-sm border-b pb-2">Rute & Pengemudi</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="origin_location_id">Asal (depot/port)</Label>
                                <select
                                    id="origin_location_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.origin_location_id}
                                    onChange={(e) => setData('origin_location_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                >
                                    <option value="">—</option>
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>{l.name} ({l.type})</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="destination_location_id">Tujuan</Label>
                                <select
                                    id="destination_location_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.destination_location_id}
                                    onChange={(e) => setData('destination_location_id', e.target.value === '' ? '' : parseInt(e.target.value))}
                                >
                                    <option value="">—</option>
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>{l.name} ({l.type})</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="driver_name">Nama Pengemudi</Label>
                                <Input id="driver_name" value={data.driver_name} onChange={(e) => setData('driver_name', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="driver_phone">Telp Pengemudi</Label>
                                <Input id="driver_phone" value={data.driver_phone} onChange={(e) => setData('driver_phone', e.target.value)} />
                            </div>
                            <div>
                                <Label htmlFor="vehicle_number">No. Kendaraan</Label>
                                <Input id="vehicle_number" value={data.vehicle_number} onChange={(e) => setData('vehicle_number', e.target.value.toUpperCase())} placeholder="B 9123 XYZ" />
                            </div>
                            <div>
                                <Label htmlFor="notes">Catatan</Label>
                                <Input id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing} className="px-8">
                            Simpan Trip
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
