import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';

type SuratJalanDetail = {
    id: number;
    sj_number: string;
    sj_date: string;
    status: string;
    driver_name: string;
    driver_phone: string;
    vehicle_number: string;
    origin_address: string;
    destination_address: string;
    containers_info: { container_number: string; seal_number?: string | null; gross_weight_kg?: string | null }[];
    pod_file_path: string | null;
    pod_signed_at: string | null;
    trip: {
        id: number;
        trip_number: string;
        job?: { job_number: string; importir?: { name: string } | null } | null;
        vendor?: { name: string } | null;
    } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trucking', href: '/trips' },
    { title: 'Surat Jalan', href: '#' },
];

export default function SuratJalanShow({ sj }: { sj: SuratJalanDetail }) {
    const podForm = useForm({ file: null as File | null });

    const submitPod = (e: React.FormEvent) => {
        e.preventDefault();
        podForm.post(route('surat-jalan.upload-pod', sj.id), {
            forceFormData: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={sj.sj_number} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-4xl">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium font-mono">{sj.sj_number}</h2>
                        <p className="text-xs text-muted-foreground mt-1">
                            Trip {sj.trip?.trip_number ?? '-'} · {sj.sj_date}
                        </p>
                    </div>
                    <Badge variant={sj.status === 'signed' ? 'default' : 'outline'}>{sj.status}</Badge>
                </div>

                <div className="grid gap-4 md:grid-cols-2 text-sm">
                    <div className="rounded-xl border p-4 space-y-2 bg-card">
                        <h3 className="font-medium text-sm border-b pb-1">Pengemudi & Kendaraan</h3>
                        <p>{sj.driver_name} ({sj.driver_phone})</p>
                        <p className="font-mono">{sj.vehicle_number}</p>
                        <p className="text-muted-foreground text-xs pt-2">Ditandatangani POD: {sj.pod_signed_at ?? '-'}</p>
                    </div>
                    <div className="rounded-xl border p-4 space-y-2 bg-card">
                        <h3 className="font-medium text-sm border-b pb-1">Rute</h3>
                        <p><span className="text-muted-foreground text-xs block">Asal</span>{sj.origin_address}</p>
                        <p><span className="text-muted-foreground text-xs block">Tujuan</span>{sj.destination_address}</p>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left">
                                <th className="px-3 py-2">Kontainer</th>
                                <th className="px-3 py-2">Seal</th>
                                <th className="px-3 py-2 text-right">Berat (KG)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sj.containers_info.map((c, i) => (
                                <tr key={i} className="border-b last:border-b-0">
                                    <td className="px-3 py-2 font-mono">{c.container_number}</td>
                                    <td className="px-3 py-2 font-mono">{c.seal_number || '-'}</td>
                                    <td className="px-3 py-2 text-right font-mono">{c.gross_weight_kg ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-3">
                    {sj.status === 'draft' && (
                        <Button onClick={() => router.post(route('surat-jalan.sign', sj.id))}>
                            Tandatangani (jadi POD)
                        </Button>
                    )}
                    {sj.pod_file_path && (
                        <a href={`/storage/${sj.pod_file_path}`} target="_blank" rel="noopener noreferrer">
                            <Button variant="outline">Lihat File POD</Button>
                        </a>
                    )}
                </div>

                <form onSubmit={submitPod} className="rounded-xl border p-4 space-y-3 bg-card">
                    <h3 className="font-medium text-sm">Upload Bukti POD (file)</h3>
                    <input
                        type="file"
                        accept="image/*,application/pdf"
                        onChange={(e) => podForm.setData('file', e.target.files?.[0] ?? null)}
                    />
                    {podForm.errors.file && <p className="text-xs text-red-600">{podForm.errors.file}</p>}
                    <Button type="submit" size="sm" disabled={podForm.processing || !podForm.data.file}>
                        Upload POD
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
