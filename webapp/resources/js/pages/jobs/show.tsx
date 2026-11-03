import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Edit2, FileText, PlusCircle, RotateCcw, Upload, UserCheck } from 'lucide-react';
import React, { useState } from 'react';

type ContainerItem = {
    id: number;
    container_number: string;
    size: string;
    type: string;
    seal_number: string | null;
    gross_weight_kg: string;
};

type DocumentItem = {
    id: number;
    phase: 'pra_filing' | 'pasca_filing' | 'pasca_delivery';
    document_type: string;
    status: 'belum_ada' | 'diupload' | 'diverifikasi' | 'tidak_berlaku';
    file_path: string | null;
    file_name: string | null;
    uploaded_at: string | null;
    uploader: { id: number; name: string } | null;
    verifier: { id: number; name: string } | null;
    verified_at: string | null;
    notes: string | null;
};

type MilestoneItem = {
    id: number;
    branch: 'pabean' | 'logistik';
    milestone_type: string;
    occurred_at: string;
    proof_number: string | null;
    proof_file_path: string | null;
    notes: string | null;
    is_backdate: boolean;
    backdate_reason: string | null;
    is_active: boolean;
    supersedes_id: number | null;
    container_id: number | null;
    container: { id: number; container_number: string; size: string } | null;
    creator: { id: number; name: string } | null;
    verifier: { id: number; name: string } | null;
    verified_at: string | null;
};

type DemurrageContainerResult = {
    container_id: number;
    container_number: string;
    size: string;
    demurrage: {
        has_started: boolean;
        is_final: boolean;
        total_days: number;
        overdue_days: number;
        last_free_date: string | null;
        remaining_free_days: number;
        lampu: 'hijau' | 'kuning' | 'merah';
        cost_usd: number;
        cost_idr: number;
    };
    detention: {
        has_started: boolean;
        is_final: boolean;
        total_days: number;
        overdue_days: number;
        last_free_date: string | null;
        remaining_free_days: number;
        lampu: 'hijau' | 'kuning' | 'merah';
        cost_usd: number;
        cost_idr: number;
    };
    total_usd: number;
    total_idr: number;
    is_official_kurs: boolean;
    is_all_final: boolean;
};

type DemurrageData = {
    job_id: number;
    job_number: string;
    containers: DemurrageContainerResult[];
    total_usd: number;
    total_idr: number;
    is_final: boolean;
};

type JobDetail = {
    id: number;
    job_number: string;
    bl_number: string;
    bl_date: string;
    vessel_name: string | null;
    voyage_number: string | null;
    eta: string | null;
    ata: string | null;
    claim_fta: boolean;
    fta_scheme: string | null;
    problem_flag: 'Hold' | 'Dispute' | 'Dokumen Bermasalah' | null;
    cached_status: string;
    importir: { id: number; name: string; npwp: string; status_api: string } | null;
    shipping_line: { id: number; code: string; name: string } | null;
    vessel: { id: number; name: string; imo_number: string | null; call_sign: string | null } | null;
    pod: { id: number; code: string; name: string } | null;
    containers: ContainerItem[];
    documents: DocumentItem[];
    milestones: MilestoneItem[];
    fields: { id: number; name: string }[];
};

export default function JobsShow({
    job,
    demurrage,
    fieldUsers,
    canAssignField,
    canVerifyDoc,
}: {
    job: JobDetail;
    demurrage?: DemurrageData;
    fieldUsers: { id: number; name: string }[];
    canAssignField: boolean;
    canVerifyDoc: boolean;
}) {
    const authUser = usePage().props.auth.user;
    const [selectedDocId, setSelectedDocId] = useState<number | null>(null);
    const [docFile, setDocFile] = useState<File | null>(null);
    const [showMilestoneModal, setShowMilestoneModal] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Daftar Job', href: '/jobs' },
        { title: job.job_number, href: `/jobs/${job.id}` },
    ];

    const assignForm = useForm({
        field_user_id: fieldUsers[0]?.id ?? '',
    });

    const flagForm = useForm({
        problem_flag: job.problem_flag || '',
        reason: '',
    });

    const milestoneForm = useForm({
        milestone_type: 'discharge',
        container_id: '' as string | number,
        occurred_at: new Date().toISOString().slice(0, 16),
        proof_number: '',
        backdate_reason: '',
        notes: '',
        proof_file: null as File | null,
    });

    const handleAssignField = (e: React.FormEvent) => {
        e.preventDefault();
        assignForm.post(route('jobs.assign-field', job.id));
    };

    const handleUpdateFlag = (e: React.FormEvent) => {
        e.preventDefault();
        flagForm.patch(route('jobs.problem-flag', job.id));
    };

    const handleUploadDoc = (documentId: number) => {
        if (!docFile) return;
        const formData = new FormData();
        formData.append('file', docFile);

        router.post(route('jobs.documents.upload', { job: job.id, document: documentId }), formData, {
            onSuccess: () => {
                setSelectedDocId(null);
                setDocFile(null);
            },
        });
    };

    const handleVerifyDoc = (documentId: number) => {
        const notes = prompt('Catatan verifikasi (opsional):') || undefined;
        router.post(route('jobs.documents.verify', { job: job.id, document: documentId }), { notes });
    };

    const handleStoreMilestone = (e: React.FormEvent) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('milestone_type', milestoneForm.data.milestone_type);
        formData.append('occurred_at', milestoneForm.data.occurred_at);
        if (milestoneForm.data.container_id) formData.append('container_id', milestoneForm.data.container_id.toString());
        if (milestoneForm.data.proof_number) formData.append('proof_number', milestoneForm.data.proof_number);
        if (milestoneForm.data.backdate_reason) formData.append('backdate_reason', milestoneForm.data.backdate_reason);
        if (milestoneForm.data.notes) formData.append('notes', milestoneForm.data.notes);
        if (milestoneForm.data.proof_file) formData.append('proof_file', milestoneForm.data.proof_file);

        router.post(route('jobs.milestones.store', job.id), formData, {
            onSuccess: () => {
                setShowMilestoneModal(false);
                milestoneForm.reset('proof_number', 'backdate_reason', 'notes', 'proof_file');
            },
        });
    };

    const handleVerifyMilestone = (milestoneId: number) => {
        router.post(route('milestones.verify', milestoneId));
    };

    const handleSupersedeMilestone = (m: MilestoneItem) => {
        const newDate = prompt(`Koreksi tanggal untuk ${m.milestone_type} (YYYY-MM-DD HH:mm):`, m.occurred_at.slice(0, 16).replace('T', ' '));
        if (!newDate) return;
        const reason = prompt('Alasan koreksi pengganti (wajib masuk audit trail):');
        if (!reason) return;

        router.post(route('milestones.supersede', m.id), {
            occurred_at: newDate,
            reason: reason,
        });
    };

    const praFilingDocs = job.documents.filter((d) => d.phase === 'pra_filing');
    const pascaFilingDocs = job.documents.filter((d) => d.phase === 'pasca_filing');
    const pascaDeliveryDocs = job.documents.filter((d) => d.phase === 'pasca_delivery');

    const hasDoReleased = job.milestones.some((m) => m.milestone_type === 'do_release' && m.is_active);

    const renderDocRow = (d: DocumentItem) => (
        <tr key={d.id} className="border-b last:border-b-0">
            <td className="px-3 py-2 font-medium capitalize">{d.document_type.replace(/_/g, ' ')}</td>
            <td className="px-3 py-2">
                <Badge
                    variant={
                        d.status === 'diverifikasi'
                            ? 'default'
                            : d.status === 'diupload'
                            ? 'outline'
                            : d.status === 'tidak_berlaku'
                            ? 'secondary'
                            : 'destructive'
                    }
                >
                    {d.status}
                </Badge>
            </td>
            <td className="px-3 py-2 text-xs">
                {d.file_name ? (
                    <span className="font-mono text-primary">{d.file_name}</span>
                ) : (
                    <span className="text-muted-foreground">-</span>
                )}
            </td>
            <td className="px-3 py-2 text-xs text-muted-foreground">
                {d.verifier?.name ? `Oleh: ${d.verifier.name}` : '-'}
            </td>
            <td className="px-3 py-2 flex items-center gap-2">
                {d.status !== 'diverifikasi' && d.status !== 'tidak_berlaku' && (
                    <>
                        {selectedDocId === d.id ? (
                            <div className="flex items-center gap-2">
                                <input
                                    type="file"
                                    className="text-xs max-w-xs"
                                    onChange={(e) => setDocFile(e.target.files?.[0] || null)}
                                />
                                <Button size="sm" onClick={() => handleUploadDoc(d.id)} disabled={!docFile}>
                                    Upload
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setSelectedDocId(null)}>
                                    Batal
                                </Button>
                            </div>
                        ) : (
                            <Button size="sm" variant="outline" onClick={() => setSelectedDocId(d.id)}>
                                <Upload className="size-3 mr-1" /> Unggah
                            </Button>
                        )}

                        {d.status === 'diupload' && canVerifyDoc && (
                            <Button size="sm" variant="default" onClick={() => handleVerifyDoc(d.id)}>
                                <CheckCircle2 className="size-3 mr-1" /> Verifikasi
                            </Button>
                        )}
                    </>
                )}
            </td>
        </tr>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Job ${job.job_number}`} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-6xl">
                {/* Header Title & Status */}
                <div className="flex flex-wrap items-center justify-between gap-4 border-b pb-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-2xl font-bold font-mono">{job.job_number}</h2>
                            <Badge variant={job.cached_status === 'Draft' ? 'outline' : 'default'} className="text-sm">
                                {job.cached_status}
                            </Badge>
                            {hasDoReleased && (
                                <Badge variant="secondary" className="text-sm bg-blue-500/10 text-blue-700 dark:text-blue-300">
                                    DO Sudah Terbit (§2.2)
                                </Badge>
                            )}
                            {job.problem_flag && (
                                <Badge variant="destructive" className="text-sm">
                                    Flag: {job.problem_flag}
                                </Badge>
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            B/L: <span className="font-mono font-medium">{job.bl_number}</span> | Importir: <span className="font-medium">{job.importir?.name}</span> | Pelayaran: <span className="font-medium">{job.shipping_line?.name}</span>
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link href={route('jobs.calculator', job.id)}>
                            <Button size="sm" variant="default" className="bg-blue-600 hover:bg-blue-700">
                                <FileText className="size-4 mr-1.5" /> Kalkulator PIB
                            </Button>
                        </Link>

                        <Button size="sm" variant="outline" onClick={() => setShowMilestoneModal(true)}>
                            <PlusCircle className="size-4 mr-1.5" /> + Catat Milestone
                        </Button>

                        {/* Problem Flag Control */}
                        <form onSubmit={handleUpdateFlag} className="flex items-center gap-1.5">
                            <select
                                className="border rounded-md p-1.5 text-xs bg-background"
                                value={flagForm.data.problem_flag}
                                onChange={(e) => flagForm.setData('problem_flag', e.target.value as any)}
                            >
                                <option value="">Tanpa Flag</option>
                                <option value="Hold">Hold</option>
                                <option value="Dispute">Dispute</option>
                                <option value="Dokumen Bermasalah">Dokumen Bermasalah</option>
                            </select>
                            <Button size="sm" variant="outline" type="submit" disabled={flagForm.processing}>
                                Update
                            </Button>
                        </form>
                    </div>
                </div>

                {/* Live Early Warning Demurrage & Detention Monitoring Card (§4.1–§4.4, Q9) */}
                {demurrage && (
                    <div className="rounded-xl border p-4 bg-card shadow-sm space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                            <div className="flex items-center gap-2">
                                <Clock className="size-5 text-amber-500" />
                                <h3 className="font-semibold text-base">Pemantauan Demurrage & Detention (Live Alarm §4.4)</h3>
                                <Badge variant={demurrage.is_final ? 'default' : 'secondary'} className="text-xs">
                                    {demurrage.is_final ? 'Biaya Terkunci (Final)' : 'Estimasi Berjalan (Live)'}
                                </Badge>
                            </div>
                            <div className="text-right">
                                <div className="text-sm font-mono font-bold text-red-600 dark:text-red-400">
                                    Total Denda: USD {demurrage.total_usd.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                    <span className="text-xs font-normal text-muted-foreground ml-1.5">
                                        (≈ Rp {demurrage.total_idr.toLocaleString('id-ID')})
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* List Kontainer dengan Lampu Hijau / Kuning / Merah */}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {demurrage.containers.map((c) => {
                                const demColor = c.demurrage.lampu === 'merah' ? 'bg-red-500' : c.demurrage.lampu === 'kuning' ? 'bg-amber-500' : 'bg-emerald-500';
                                const detColor = c.detention.lampu === 'merah' ? 'bg-red-500' : c.detention.lampu === 'kuning' ? 'bg-amber-500' : 'bg-emerald-500';

                                return (
                                    <div key={c.container_id} className="p-3 rounded-lg border bg-muted/20 text-xs space-y-2">
                                        <div className="flex items-center justify-between font-mono font-bold text-sm border-b pb-1">
                                            <span>{c.container_number}</span>
                                            <span className="text-xs font-normal text-muted-foreground">{c.size}'</span>
                                        </div>

                                        {/* Status Demurrage */}
                                        <div className="space-y-1">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-1.5">
                                                    <span className={`size-2.5 rounded-full ${demColor}`} />
                                                    <span className="font-medium">Demurrage:</span>
                                                </div>
                                                <span className="font-mono">
                                                    {c.demurrage.overdue_days > 0 ? (
                                                        <span className="text-red-600 font-bold">Telat {c.demurrage.overdue_days} hr</span>
                                                    ) : (
                                                        <span className="text-emerald-600 font-medium">Sisa {c.demurrage.remaining_free_days} hr</span>
                                                    )}
                                                </span>
                                            </div>
                                            <div className="text-[11px] text-muted-foreground flex justify-between">
                                                <span>Last Free: {c.demurrage.last_free_date || '-'}</span>
                                                {c.demurrage.cost_usd > 0 && <span className="font-mono font-bold text-red-600">USD {c.demurrage.cost_usd}</span>}
                                            </div>
                                        </div>

                                        {/* Status Detention */}
                                        <div className="space-y-1 border-t pt-1">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-1.5">
                                                    <span className={`size-2.5 rounded-full ${detColor}`} />
                                                    <span className="font-medium">Detention:</span>
                                                </div>
                                                <span className="font-mono">
                                                    {c.detention.overdue_days > 0 ? (
                                                        <span className="text-red-600 font-bold">Telat {c.detention.overdue_days} hr</span>
                                                    ) : (
                                                        <span className="text-emerald-600 font-medium">Sisa {c.detention.remaining_free_days} hr</span>
                                                    )}
                                                </span>
                                            </div>
                                            <div className="text-[11px] text-muted-foreground flex justify-between">
                                                <span>Last Free: {c.detention.last_free_date || '-'}</span>
                                                {c.detention.cost_usd > 0 && <span className="font-mono font-bold text-red-600">USD {c.detention.cost_usd}</span>}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Kolom Kiri: Detail B/L & Kontainer & Staf Field */}
                    <div className="lg:col-span-1 space-y-6">
                        {/* Detail Job Card */}
                        <div className="rounded-xl border p-4 space-y-3 bg-card text-sm">
                            <h3 className="font-medium border-b pb-1 text-xs uppercase text-muted-foreground">Detail Muatan</h3>
                            <div>
                                <span className="text-muted-foreground text-xs block">Kapal & Voyage:</span>
                                <span>{job.vessel_name || '-'} {job.voyage_number ? `(${job.voyage_number})` : ''}</span>
                                {job.vessel?.imo_number && (
                                    <span className="text-muted-foreground text-xs block">
                                        IMO {job.vessel.imo_number}
                                        {job.vessel.call_sign ? ` · Call Sign ${job.vessel.call_sign}` : ''}
                                    </span>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <span className="text-muted-foreground text-xs block">ETA:</span>
                                    <span>{job.eta || '-'}</span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs block">ATA:</span>
                                    <span>{job.ata || '-'}</span>
                                </div>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs block">Pelabuhan Bongkar (POD):</span>
                                <span>{job.pod?.name} ({job.pod?.code})</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground text-xs block">Klaim FTA:</span>
                                <span>{job.claim_fta ? `Ya (${job.fta_scheme})` : 'Tidak (MFN Normal)'}</span>
                            </div>
                        </div>

                        {/* Kontainer Fisik Card */}
                        <div className="rounded-xl border p-4 space-y-3 bg-card">
                            <h3 className="font-medium border-b pb-1 text-xs uppercase text-muted-foreground">
                                Kontainer ({job.containers.length} Unit)
                            </h3>
                            <div className="space-y-2">
                                {job.containers.map((c) => (
                                    <div key={c.id} className="p-2.5 rounded-lg border bg-muted/20 text-xs space-y-1">
                                        <div className="flex justify-between font-mono font-bold text-sm">
                                            <span>{c.container_number}</span>
                                            <span>{c.size}' {c.type}</span>
                                        </div>
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>Seal: {c.seal_number || '-'}</span>
                                            <span>{parseFloat(c.gross_weight_kg).toLocaleString()} KG</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Staf Field yang Ditugaskan */}
                        <div className="rounded-xl border p-4 space-y-3 bg-card text-sm">
                            <h3 className="font-medium border-b pb-1 text-xs uppercase text-muted-foreground">
                                Petugas Lapangan (Field)
                            </h3>
                            <div className="flex flex-wrap gap-1">
                                {job.fields.map((f) => (
                                    <Badge key={f.id} variant="secondary" className="flex items-center gap-1">
                                        <UserCheck className="size-3" /> {f.name}
                                    </Badge>
                                ))}
                                {job.fields.length === 0 && (
                                    <span className="text-xs text-muted-foreground">Belum ada Field ditugaskan.</span>
                                )}
                            </div>

                            {canAssignField && (
                                <form onSubmit={handleAssignField} className="flex gap-2 pt-2 border-t">
                                    <select
                                        className="border rounded-md p-1.5 text-xs bg-background flex-1"
                                        value={assignForm.data.field_user_id}
                                        onChange={(e) => assignForm.setData('field_user_id', parseInt(e.target.value))}
                                    >
                                        {fieldUsers.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name}
                                            </option>
                                        ))}
                                    </select>
                                    <Button size="sm" type="submit" disabled={assignForm.processing}>
                                        Tugaskan
                                    </Button>
                                </form>
                            )}
                        </div>
                    </div>

                    {/* Kolom Kanan: Milestone Dua Cabang & Checklist Dokumen */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* 1. Riwayat Milestone Dua Cabang (§2.2) */}
                        <div className="rounded-xl border p-4 space-y-3 bg-card">
                            <div className="flex items-center justify-between border-b pb-2">
                                <h3 className="font-medium text-sm flex items-center gap-2">
                                    <Clock className="size-4 text-primary" />
                                    Perjalanan Milestone Dua Cabang (Pabean & Logistik)
                                </h3>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-xs">
                                    <thead>
                                        <tr className="border-b bg-muted/40 text-left">
                                            <th className="px-3 py-2">Cabang</th>
                                            <th className="px-3 py-2">Milestone</th>
                                            <th className="px-3 py-2">Kontainer</th>
                                            <th className="px-3 py-2">Waktu Kejadian</th>
                                            <th className="px-3 py-2">Penginput</th>
                                            <th className="px-3 py-2">Verifikator</th>
                                            <th className="px-3 py-2">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {job.milestones.map((m) => {
                                            const isCreator = authUser?.id === m.creator?.id;
                                            return (
                                                <tr key={m.id} className={`border-b last:border-b-0 ${!m.is_active ? 'opacity-40 line-through' : ''}`}>
                                                    <td className="px-3 py-2 uppercase font-mono font-bold text-[10px]">
                                                        {m.branch}
                                                    </td>
                                                    <td className="px-3 py-2 font-medium capitalize">
                                                        {m.milestone_type.replace(/_/g, ' ')}
                                                        {m.proof_number && ` (${m.proof_number})`}
                                                        {m.is_backdate && <Badge variant="outline" className="ml-1 text-[9px]">Backdate</Badge>}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono">
                                                        {m.container?.container_number || 'Semua (Job)'}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono">{m.occurred_at.slice(0, 16).replace('T', ' ')}</td>
                                                    <td className="px-3 py-2 text-muted-foreground">{m.creator?.name || '-'}</td>
                                                    <td className="px-3 py-2">
                                                        {m.verifier ? (
                                                            <span className="text-green-700 dark:text-green-400 font-medium">✓ {m.verifier.name}</span>
                                                        ) : (
                                                            <span className="text-muted-foreground">Belum</span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 flex items-center gap-1.5">
                                                        {m.is_active && !m.verifier && canVerifyDoc && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-6 text-[10px]"
                                                                disabled={isCreator}
                                                                title={isCreator ? 'Penginput dilarang verifikasi sendiri (ADR 0006)' : 'Verifikasi'}
                                                                onClick={() => handleVerifyMilestone(m.id)}
                                                            >
                                                                Verifikasi
                                                            </Button>
                                                        )}
                                                        {m.is_active && canVerifyDoc && (
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="h-6 text-[10px]"
                                                                title="Koreksi pengganti beralasan (§2.7)"
                                                                onClick={() => handleSupersedeMilestone(m)}
                                                            >
                                                                <Edit2 className="size-3" />
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {job.milestones.length === 0 && (
                                            <tr>
                                                <td colSpan={7} className="text-center py-6 text-muted-foreground">
                                                    Belum ada milestone tercatat untuk Job ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* 2. Checklist Dokumen Berfase (§2.4) */}
                        <div className="rounded-xl border p-4 space-y-4 bg-card">
                            <h3 className="font-medium text-sm border-b pb-2">Checklist Dokumen Berfase</h3>

                            {/* Fase 1: Pra-Filing */}
                            <div className="space-y-1.5">
                                <h4 className="text-xs font-semibold text-muted-foreground uppercase">Fase 1: Pra-Filing (Syarat Maju PIB)</h4>
                                <table className="w-full text-xs border rounded-lg overflow-hidden">
                                    <thead>
                                        <tr className="bg-muted/40 text-left">
                                            <th className="px-3 py-1.5">Dokumen</th>
                                            <th className="px-3 py-1.5">Status</th>
                                            <th className="px-3 py-1.5">File</th>
                                            <th className="px-3 py-1.5">Verifikator</th>
                                            <th className="px-3 py-1.5">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>{praFilingDocs.map(renderDocRow)}</tbody>
                                </table>
                            </div>

                            {/* Fase 2: Pasca-Filing */}
                            <div className="space-y-1.5">
                                <h4 className="text-xs font-semibold text-muted-foreground uppercase">Fase 2: Pasca-Filing (DO & SPPB)</h4>
                                <table className="w-full text-xs border rounded-lg overflow-hidden">
                                    <thead>
                                        <tr className="bg-muted/40 text-left">
                                            <th className="px-3 py-1.5">Dokumen</th>
                                            <th className="px-3 py-1.5">Status</th>
                                            <th className="px-3 py-1.5">File</th>
                                            <th className="px-3 py-1.5">Verifikator</th>
                                            <th className="px-3 py-1.5">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>{pascaFilingDocs.map(renderDocRow)}</tbody>
                                </table>
                            </div>

                            {/* Fase 3: Pasca-Delivery */}
                            <div className="space-y-1.5">
                                <h4 className="text-xs font-semibold text-muted-foreground uppercase">Fase 3: Pasca-Delivery (Bukti Gate & POD)</h4>
                                <table className="w-full text-xs border rounded-lg overflow-hidden">
                                    <thead>
                                        <tr className="bg-muted/40 text-left">
                                            <th className="px-3 py-1.5">Dokumen</th>
                                            <th className="px-3 py-1.5">Status</th>
                                            <th className="px-3 py-1.5">File</th>
                                            <th className="px-3 py-1.5">Verifikator</th>
                                            <th className="px-3 py-1.5">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>{pascaDeliveryDocs.map(renderDocRow)}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Modal Catat Milestone */}
                {showMilestoneModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
                        <div className="bg-card rounded-xl border p-6 max-w-md w-full space-y-4 shadow-xl">
                            <h3 className="font-bold text-base">Catat Milestone Peristiwa</h3>

                            <form onSubmit={handleStoreMilestone} className="space-y-3 text-sm">
                                <div>
                                    <Label htmlFor="m_type">Jenis Milestone</Label>
                                    <select
                                        id="m_type"
                                        className="w-full border rounded-md p-2 bg-background mt-1"
                                        value={milestoneForm.data.milestone_type}
                                        onChange={(e) => milestoneForm.setData('milestone_type', e.target.value)}
                                    >
                                        <optgroup label="Cabang Logistik (Fisik)">
                                            <option value="discharge">Discharge (Bongkar Kapal)</option>
                                            <option value="do_release">DO Release (Delivery Order Terbit)</option>
                                            <option value="gate_out_full">Gate-Out Full (Keluar Pelabuhan)</option>
                                            <option value="delivered">Delivered (Tiba di Gudang)</option>
                                            <option value="gate_in_empty">Gate-In Empty (Kembali Depo)</option>
                                        </optgroup>
                                        <optgroup label="Cabang Pabean">
                                            <option value="pib_filed">PIB Filed (Didaftarkan CEISA)</option>
                                            <option value="sppb">SPPB (Surat Persetujuan Pengeluaran)</option>
                                        </optgroup>
                                    </select>
                                </div>

                                {['gate_out_full', 'delivered', 'gate_in_empty'].includes(milestoneForm.data.milestone_type) && (
                                    <div>
                                        <Label htmlFor="m_container">Pilih Kontainer</Label>
                                        <select
                                            id="m_container"
                                            className="w-full border rounded-md p-2 bg-background mt-1 font-mono text-xs"
                                            value={milestoneForm.data.container_id}
                                            onChange={(e) => milestoneForm.setData('container_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Pilih Kontainer</option>
                                            {job.containers.map((c) => (
                                                <option key={c.id} value={c.id}>
                                                    {c.container_number} ({c.size}')
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                <div>
                                    <Label htmlFor="m_occurred">Waktu Kejadian</Label>
                                    <Input
                                        id="m_occurred"
                                        type="datetime-local"
                                        value={milestoneForm.data.occurred_at}
                                        onChange={(e) => milestoneForm.setData('occurred_at', e.target.value)}
                                        required
                                    />
                                </div>

                                {['do_release', 'sppb'].includes(milestoneForm.data.milestone_type) && (
                                    <div>
                                        <Label htmlFor="m_proof_no">Nomor Dokumen (No DO / No SPPB)</Label>
                                        <Input
                                            id="m_proof_no"
                                            value={milestoneForm.data.proof_number}
                                            onChange={(e) => milestoneForm.setData('proof_number', e.target.value)}
                                            required
                                        />
                                    </div>
                                )}

                                <div>
                                    <Label htmlFor="m_backdate">Alasan Backdate (Wajib jika tanggal masa lalu)</Label>
                                    <Input
                                        id="m_backdate"
                                        placeholder="Contoh: Sinyal hilang di dermaga..."
                                        value={milestoneForm.data.backdate_reason}
                                        onChange={(e) => milestoneForm.setData('backdate_reason', e.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="m_notes">Catatan Tambahan</Label>
                                    <Input
                                        id="m_notes"
                                        value={milestoneForm.data.notes}
                                        onChange={(e) => milestoneForm.setData('notes', e.target.value)}
                                    />
                                </div>

                                <div className="flex justify-end gap-2 pt-2 border-t">
                                    <Button type="button" variant="outline" onClick={() => setShowMilestoneModal(false)}>
                                        Batal
                                    </Button>
                                    <Button type="submit">
                                        Simpan Milestone
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
