import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, CloudOff, Send, Smartphone } from 'lucide-react';
import React, { useEffect, useState } from 'react';

type ContainerItem = { id: number; container_number: string; size: string; type: string };
type JobItem = {
    id: number;
    job_number: string;
    shipping_line: { id: number; code: string } | null;
    containers: ContainerItem[];
};

// Item antrean offline (disimpan ke localStorage saat Field tidak ada sinyal)
type OfflineMilestone = {
    id: number;
    job_id: number;
    job_number: string;
    milestone_type: string;
    occurred_at: string;
    backdate_reason: string;
    notes: string;
    container_ids: number[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mobile Field PWA', href: '/pwa/field' }];

export default function PwaFieldMilestone({ jobs }: { jobs: JobItem[] }) {
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [selectedJobId, setSelectedJobId] = useState<number>(jobs[0]?.id ?? 0);
    const [selectedContainerIds, setSelectedContainerIds] = useState<number[]>([]);
    const [offlineQueue, setOfflineQueue] = useState<OfflineMilestone[]>([]);

    const selectedJob = jobs.find((j) => j.id === selectedJobId);

    const { data, setData, processing, errors, reset } = useForm({
        milestone_type: 'gate_out_full',
        occurred_at: new Date().toISOString().slice(0, 16),
        backdate_reason: '',
        notes: '',
        proof_file: null as File | null,
    });

    useEffect(() => {
        const handleOnline = () => setIsOnline(true);
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        // Muat offline queue dari localStorage
        const saved = localStorage.getItem('field_offline_milestones');
        if (saved) {
            try {
                setOfflineQueue(JSON.parse(saved));
            } catch {
                // Data antrean rusak — abaikan dan mulai dengan antrean kosong.
            }
        }

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    const toggleContainer = (id: number) => {
        if (selectedContainerIds.includes(id)) {
            setSelectedContainerIds(selectedContainerIds.filter((cid) => cid !== id));
        } else {
            setSelectedContainerIds([...selectedContainerIds, id]);
        }
    };

    const selectAllContainers = () => {
        if (!selectedJob) return;
        if (selectedContainerIds.length === selectedJob.containers.length) {
            setSelectedContainerIds([]);
        } else {
            setSelectedContainerIds(selectedJob.containers.map((c) => c.id));
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedJob) return;

        if (selectedContainerIds.length === 0) {
            alert('Pilih minimal satu kontainer untuk entri milestone!');
            return;
        }

        // Jika offline, simpan ke antrian localStorage PWA (§2.7, Q12)
        if (!navigator.onLine) {
            const queueItem = {
                id: Date.now(),
                job_id: selectedJob.id,
                job_number: selectedJob.job_number,
                container_ids: selectedContainerIds,
                milestone_type: data.milestone_type,
                occurred_at: data.occurred_at,
                backdate_reason: data.backdate_reason,
                notes: data.notes,
            };

            const updated = [...offlineQueue, queueItem];
            setOfflineQueue(updated);
            localStorage.setItem('field_offline_milestones', JSON.stringify(updated));
            alert('Sinyal offline di pelabuhan. Draf milestone berhasil disimpan di memori HP dan siap disinkronkan saat online!');
            reset('notes', 'backdate_reason');
            setSelectedContainerIds([]);
            return;
        }

        // Jika online, kirim langsung
        const formData = new FormData();
        formData.append('milestone_type', data.milestone_type);
        formData.append('occurred_at', data.occurred_at);
        if (data.backdate_reason) formData.append('backdate_reason', data.backdate_reason);
        if (data.notes) formData.append('notes', data.notes);
        if (data.proof_file) formData.append('proof_file', data.proof_file);
        selectedContainerIds.forEach((cid) => formData.append('container_ids[]', cid.toString()));

        router.post(route('jobs.milestones.store', selectedJob.id), formData, {
            onSuccess: () => {
                alert('Milestone fisik kontainer berhasil terkirim dan dicatat!');
                reset('notes', 'backdate_reason', 'proof_file');
                setSelectedContainerIds([]);
            },
        });
    };

    const syncOfflineItem = (item: OfflineMilestone) => {
        const formData = new FormData();
        formData.append('milestone_type', item.milestone_type);
        formData.append('occurred_at', item.occurred_at);
        if (item.backdate_reason) formData.append('backdate_reason', item.backdate_reason);
        if (item.notes) formData.append('notes', item.notes);
        item.container_ids.forEach((cid: number) => formData.append('container_ids[]', cid.toString()));

        router.post(route('jobs.milestones.store', item.job_id), formData, {
            onSuccess: () => {
                const remaining = offlineQueue.filter((q) => q.id !== item.id);
                setOfflineQueue(remaining);
                localStorage.setItem('field_offline_milestones', JSON.stringify(remaining));
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PWA Field Milestone" />
            <div className="mx-auto flex h-full w-full max-w-xl flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Header status koneksi */}
                <div className="flex items-center justify-between border-b pb-3">
                    <div className="flex items-center gap-2">
                        <Smartphone className="text-primary size-5" />
                        <h2 className="text-base font-bold">Entri Lapangan (Field PWA)</h2>
                    </div>
                    <Badge variant={isOnline ? 'default' : 'destructive'} className="flex items-center gap-1 text-xs">
                        {isOnline ? (
                            'Online'
                        ) : (
                            <>
                                <CloudOff className="size-3" /> Offline
                            </>
                        )}
                    </Badge>
                </div>

                {/* Antrian Draf Offline bila ada */}
                {offlineQueue.length > 0 && (
                    <div className="space-y-2 rounded-xl border border-amber-500/50 bg-amber-500/10 p-3 text-xs">
                        <div className="flex items-center justify-between font-medium text-amber-700 dark:text-amber-400">
                            <span>{offlineQueue.length} Draf Belum Terkirim (Tersimpan di HP)</span>
                            {isOnline && <span className="text-[10px]">Klik kirim saat online</span>}
                        </div>
                        {offlineQueue.map((item) => (
                            <div key={item.id} className="bg-card flex items-center justify-between rounded border p-2">
                                <div>
                                    <span className="font-mono font-bold">{item.job_number}</span>: {item.milestone_type} ({item.container_ids.length}{' '}
                                    kontainer)
                                </div>
                                {isOnline && (
                                    <Button size="sm" variant="outline" className="h-7 text-xs" onClick={() => syncOfflineItem(item)}>
                                        <Send className="mr-1 size-3" /> Kirim
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* Form Input Cepat Mobile */}
                <form onSubmit={submit} className="bg-card space-y-4 rounded-xl border p-4 text-sm shadow-sm">
                    <div>
                        <Label htmlFor="job">Pilih Job Penugasan</Label>
                        <select
                            id="job"
                            className="bg-background mt-1 w-full rounded-md border p-2 font-mono text-sm"
                            value={selectedJobId}
                            onChange={(e) => {
                                setSelectedJobId(parseInt(e.target.value));
                                setSelectedContainerIds([]);
                            }}
                        >
                            {jobs.map((j) => (
                                <option key={j.id} value={j.id}>
                                    {j.job_number} ({j.shipping_line?.code} - {j.containers.length} Kontainer)
                                </option>
                            ))}
                        </select>
                    </div>

                    {selectedJob && (
                        <div>
                            <div className="mb-1.5 flex items-center justify-between">
                                <Label>Pilih Kontainer (Bisa Batch §2.7)</Label>
                                <Button type="button" variant="ghost" size="sm" className="h-6 text-xs" onClick={selectAllContainers}>
                                    {selectedContainerIds.length === selectedJob.containers.length ? 'Batal Semua' : 'Pilih Semua'}
                                </Button>
                            </div>
                            <div className="bg-muted/20 grid max-h-48 grid-cols-2 gap-2 overflow-y-auto rounded-lg border p-2.5">
                                {selectedJob.containers.map((c) => (
                                    <label
                                        key={c.id}
                                        className="bg-card flex cursor-pointer items-center gap-2 rounded border p-1.5 font-mono text-xs"
                                    >
                                        <Checkbox checked={selectedContainerIds.includes(c.id)} onCheckedChange={() => toggleContainer(c.id)} />
                                        <span>
                                            {c.container_number} ({c.size}')
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    <div>
                        <Label htmlFor="milestone_type">Peristiwa Fisik (Milestone)</Label>
                        <select
                            id="milestone_type"
                            className="bg-background mt-1 w-full rounded-md border p-2 text-sm font-medium"
                            value={data.milestone_type}
                            onChange={(e) => setData('milestone_type', e.target.value)}
                        >
                            <option value="gate_out_full">Gate-Out Full (Keluar Pelabuhan)</option>
                            <option value="delivered">Delivered (Tiba di Gudang Klien)</option>
                            <option value="gate_in_empty">Gate-In Empty (Kembali ke Depo Line)</option>
                        </select>
                    </div>

                    <div>
                        <Label htmlFor="occurred_at">Waktu Kejadian Riil</Label>
                        <Input
                            id="occurred_at"
                            type="datetime-local"
                            value={data.occurred_at}
                            onChange={(e) => setData('occurred_at', e.target.value)}
                            required
                        />
                    </div>

                    <div>
                        <Label htmlFor="backdate_reason">Alasan Backdate (Wajib bila tanggal kemarin)</Label>
                        <Input
                            id="backdate_reason"
                            placeholder="Contoh: Sinyal hilang di dermaga, fisik kemarin malam"
                            value={data.backdate_reason}
                            onChange={(e) => setData('backdate_reason', e.target.value)}
                        />
                        {errors.backdate_reason && <p className="mt-1 text-xs text-red-600">{errors.backdate_reason}</p>}
                    </div>

                    <div>
                        <Label htmlFor="proof_file">Foto Kontainer / Surat Jalan (Kamera HP)</Label>
                        <div className="mt-1 flex items-center gap-2">
                            <Input
                                id="proof_file"
                                type="file"
                                accept="image/*"
                                capture="environment"
                                onChange={(e) => setData('proof_file', e.target.files?.[0] || null)}
                            />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="notes">Catatan Tambahan</Label>
                        <Input
                            id="notes"
                            placeholder="Catatan kondisi kontainer/seal..."
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                    </div>

                    <Button type="submit" disabled={processing} className="h-11 w-full text-base font-semibold">
                        <Check className="mr-2 size-4" /> Simpan & Kirim Milestone
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
