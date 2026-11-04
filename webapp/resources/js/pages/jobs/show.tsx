import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, Download, Edit2, Eye, FileText, PlusCircle, Undo2, Upload, UserCheck } from 'lucide-react';
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
    closed_at: string | null;
    closed_by: { id: number; name: string } | null;
    close_reason: string | null;
    importir: { id: number; name: string; npwp: string; status_api: string } | null;
    shipping_line: { id: number; code: string; name: string } | null;
    vessel: { id: number; name: string; imo_number: string | null; call_sign: string | null } | null;
    pod: { id: number; code: string; name: string } | null;
    containers: ContainerItem[];
    documents: DocumentItem[];
    milestones: MilestoneItem[];
    fields: { id: number; name: string }[];
};

type ClosePrecondition = {
    key: string;
    label: string;
    passed: boolean;
    detail: string | null;
};

export default function JobsShow({
    job,
    demurrage,
    fieldUsers,
    canAssignField,
    canVerifyDoc,
    canUnverifyDoc,
    closePreconditions,
    canClose,
    canReopen,
}: {
    job: JobDetail;
    demurrage?: DemurrageData;
    fieldUsers: { id: number; name: string }[];
    canAssignField: boolean;
    canVerifyDoc: boolean;
    canUnverifyDoc: boolean;
    closePreconditions: ClosePrecondition[];
    canClose: boolean;
    canReopen: boolean;
}) {
    const authUser = usePage<SharedData>().props.auth.user;
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

    const closeForm = useForm({
        reason: '',
    });

    const reopenForm = useForm({
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

    const handleCloseJob = (e: React.FormEvent) => {
        e.preventDefault();
        if (
            !confirm(
                'Tutup Job ini? Job yang tertutup terkunci untuk penulisan (milestone, biaya, invoice, dokumen) dan hanya bisa dibuka kembali oleh Admin/Finance dengan alasan.',
            )
        )
            return;
        closeForm.post(route('jobs.close', job.id));
    };

    const handleReopenJob = (e: React.FormEvent) => {
        e.preventDefault();
        reopenForm.post(route('jobs.reopen', job.id));
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
        // BUGFIX: prompt() mengembalikan null saat Cancel — tanpa cek ini,
        // tekan Cancel pun tetap mengirim verifikasi. OK dengan catatan
        // kosong tetap memverifikasi (catatan opsional).
        const notes = prompt('Catatan verifikasi (opsional):');
        if (notes === null) return;
        router.post(route('jobs.documents.verify', { job: job.id, document: documentId }), { notes: notes || undefined });
    };

    const handleUnverifyDoc = (documentId: number) => {
        // Pola batal yang sama seperti handleVerifyDoc: Cancel = tidak ada
        // yang dikirim; alasan kosong juga ditolak di sini (dan di server).
        const reason = prompt('Alasan pembatalan verifikasi (wajib):');
        if (reason === null) return;
        if (!reason.trim()) {
            alert('Alasan pembatalan wajib diisi.');
            return;
        }
        router.post(route('jobs.documents.unverify', { job: job.id, document: documentId }), { reason });
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

    // Pratinjau native browser: PDF + gambar saja. Harus sama dengan
    // PrivateFile::PREVIEWABLE_EXTENSIONS di server (server tetap menolak
    // 415 bila tipe tidak didukung — UI ini hanya menyembunyikan tombol).
    const PREVIEWABLE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    const isPreviewableFile = (fileName: string | null) => {
        if (!fileName) return false;
        const ext = fileName.split('.').pop()?.toLowerCase() ?? '';
        return PREVIEWABLE_EXTENSIONS.includes(ext);
    };

    // Label manusia untuk badge status (bukan kode enum mentah).
    const DOC_STATUS_LABELS: Record<DocumentItem['status'], string> = {
        belum_ada: 'Belum ada',
        diupload: 'Diupload',
        diverifikasi: 'Diverifikasi',
        tidak_berlaku: 'Tidak berlaku',
    };

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
                    {DOC_STATUS_LABELS[d.status]}
                </Badge>
            </td>
            <td className="max-w-[220px] overflow-hidden px-3 py-2 text-xs">
                {d.file_name ? (
                    <span className="block truncate font-mono text-primary" title={d.file_name}>
                        {d.file_name}
                    </span>
                ) : (
                    <span className="text-muted-foreground">-</span>
                )}
            </td>
            <td className="text-muted-foreground px-3 py-2 text-xs whitespace-nowrap">{d.verifier?.name ? `Oleh: ${d.verifier.name}` : '-'}</td>
            <td className="flex items-center gap-1.5 px-3 py-2 whitespace-nowrap">
                {d.file_path && (
                    <>
                        <Button size="sm" variant="outline" asChild>
                            <a href={route('jobs.documents.download', { job: job.id, document: d.id })}>
                                <Download className="mr-1 size-3" /> Unduh
                            </a>
                        </Button>
                        {isPreviewableFile(d.file_name) && (
                            <Button size="sm" variant="outline" asChild>
                                <a
                                    href={route('jobs.documents.preview', { job: job.id, document: d.id })}
                                    target="_blank"
                                    rel="noreferrer"
                                    title="Pratinjau di tab baru (PDF/gambar)"
                                >
                                    <Eye className="mr-1 size-3" /> Lihat
                                </a>
                            </Button>
                        )}
                    </>
                )}
                {d.status !== 'diverifikasi' && d.status !== 'tidak_berlaku' && (
                    <>
                        {selectedDocId === d.id ? (
                            <div className="flex items-center gap-2">
                                <input type="file" className="max-w-xs text-xs" onChange={(e) => setDocFile(e.target.files?.[0] || null)} />
                                <Button size="sm" onClick={() => handleUploadDoc(d.id)} disabled={!docFile}>
                                    Upload
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setSelectedDocId(null)}>
                                    Batal
                                </Button>
                            </div>
                        ) : (
                            <Button size="sm" variant="outline" onClick={() => setSelectedDocId(d.id)}>
                                <Upload className="mr-1 size-3" /> Unggah
                            </Button>
                        )}

                        {d.status === 'diupload' && canVerifyDoc && (
                            <Button size="sm" variant="default" onClick={() => handleVerifyDoc(d.id)}>
                                <CheckCircle2 className="mr-1 size-3" /> Verifikasi
                            </Button>
                        )}
                    </>
                )}
                {d.status === 'diverifikasi' && canUnverifyDoc && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => handleUnverifyDoc(d.id)}
                        title="Batalkan verifikasi — status kembali ke diupload, alasan wajib dan tercatat di audit"
                    >
                        <Undo2 className="mr-1 size-3" /> Batalkan Verifikasi
                    </Button>
                )}
            </td>
        </tr>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Job ${job.job_number}`} />
            <div className="flex h-full max-w-6xl flex-1 flex-col gap-6 rounded-xl p-4">
                {/* Header Title & Status */}
                <div className="flex flex-wrap items-center justify-between gap-4 border-b pb-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="font-mono text-2xl font-bold">{job.job_number}</h2>
                            <Badge variant={job.cached_status === 'Draft' ? 'outline' : 'default'} className="text-sm">
                                {job.cached_status}
                            </Badge>
                            {hasDoReleased && (
                                <Badge variant="secondary" className="bg-blue-500/10 text-sm text-blue-700 dark:text-blue-300">
                                    DO Sudah Terbit (§2.2)
                                </Badge>
                            )}
                            {job.problem_flag && (
                                <Badge variant="destructive" className="text-sm">
                                    Flag: {job.problem_flag}
                                </Badge>
                            )}
                        </div>
                        <p className="text-muted-foreground mt-1 text-xs">
                            B/L: <span className="font-mono font-medium">{job.bl_number}</span> | Importir:{' '}
                            <span className="font-medium">{job.importir?.name}</span> | Pelayaran:{' '}
                            <span className="font-medium">{job.shipping_line?.name}</span>
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link href={route('jobs.calculator', job.id)}>
                            <Button size="sm" variant="default" className="bg-blue-600 hover:bg-blue-700">
                                <FileText className="mr-1.5 size-4" /> Kalkulator PIB
                            </Button>
                        </Link>

                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setShowMilestoneModal(true)}
                            disabled={!!job.closed_at}
                            title={job.closed_at ? 'Job tertutup — milestone terkunci (§2.3)' : 'Catat milestone'}
                        >
                            <PlusCircle className="mr-1.5 size-4" /> + Catat Milestone
                        </Button>

                        {/* Problem Flag Control */}
                        <form onSubmit={handleUpdateFlag} className="flex items-center gap-1.5">
                            <select
                                className="bg-background rounded-md border p-1.5 text-xs"
                                value={flagForm.data.problem_flag}
                                onChange={(e) => flagForm.setData('problem_flag', e.target.value as 'Hold' | 'Dispute' | 'Dokumen Bermasalah' | '')}
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

                {/* Penutupan Job (§2.3, Q11) */}
                {(job.closed_at || canClose || canReopen) && (
                    <div className="bg-card space-y-3 rounded-xl border p-4 shadow-sm">
                        {job.closed_at ? (
                            <>
                                <h3 className="text-base font-semibold">Job Ditutup (Closed)</h3>
                                <p className="text-sm">
                                    Ditutup oleh <span className="font-medium">{job.closed_by?.name || '-'}</span> pada{' '}
                                    <span className="font-mono">{job.closed_at.slice(0, 16).replace('T', ' ')}</span>
                                    {job.close_reason && (
                                        <>
                                            {' — '}alasan: <span className="font-medium">{job.close_reason}</span>
                                        </>
                                    )}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    Job tertutup terkunci untuk penulisan: milestone, biaya, invoice, dan dokumen tidak bisa lagi diubah.
                                </p>
                                {canReopen && (
                                    <form onSubmit={handleReopenJob} className="flex flex-wrap items-end gap-2 border-t pt-3">
                                        <div className="min-w-64 flex-1">
                                            <Label htmlFor="reopen_reason">Alasan pembukaan kembali (wajib, tercatat di audit trail)</Label>
                                            <Input
                                                id="reopen_reason"
                                                value={reopenForm.data.reason}
                                                onChange={(e) => reopenForm.setData('reason', e.target.value)}
                                                placeholder="Contoh: invoice susulan ditemukan setelah close"
                                                required
                                            />
                                        </div>
                                        <Button type="submit" variant="outline" disabled={reopenForm.processing}>
                                            Buka Kembali Job
                                        </Button>
                                    </form>
                                )}
                            </>
                        ) : canClose ? (
                            <>
                                <h3 className="text-base font-semibold">Penutupan Job (§2.3)</h3>
                                <ul className="space-y-1 text-sm">
                                    {closePreconditions.map((c) => (
                                        <li key={c.key} className="flex items-start gap-2">
                                            <span className={c.passed ? 'font-bold text-green-700 dark:text-green-400' : 'font-bold text-red-600'}>
                                                {c.passed ? '✓' : '✗'}
                                            </span>
                                            <span>
                                                {c.label}
                                                {c.detail && <span className="text-muted-foreground"> — {c.detail}</span>}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <form onSubmit={handleCloseJob} className="flex flex-wrap items-end gap-2 border-t pt-3">
                                    <div className="min-w-64 flex-1">
                                        <Label htmlFor="close_reason">Alasan penutupan (wajib, tercatat di audit trail)</Label>
                                        <Input
                                            id="close_reason"
                                            value={closeForm.data.reason}
                                            onChange={(e) => closeForm.setData('reason', e.target.value)}
                                            placeholder="Contoh: semua kewajiban selesai, arsip lengkap"
                                            required
                                        />
                                    </div>
                                    <Button type="submit" disabled={closeForm.processing || closePreconditions.some((c) => !c.passed)}>
                                        Tutup Job
                                    </Button>
                                </form>
                            </>
                        ) : null}
                    </div>
                )}

                {/* Live Early Warning Demurrage & Detention Monitoring Card (§4.1–§4.4, Q9) */}
                {demurrage && (
                    <div className="bg-card space-y-4 rounded-xl border p-4 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                            <div className="flex items-center gap-2">
                                <Clock className="size-5 text-amber-500" />
                                <h3 className="text-base font-semibold">Pemantauan Demurrage & Detention (Live Alarm §4.4)</h3>
                                <Badge variant={demurrage.is_final ? 'default' : 'secondary'} className="text-xs">
                                    {demurrage.is_final ? 'Biaya Terkunci (Final)' : 'Estimasi Berjalan (Live)'}
                                </Badge>
                            </div>
                            <div className="text-right">
                                <div className="font-mono text-sm font-bold text-red-600 dark:text-red-400">
                                    Total Denda: USD {demurrage.total_usd.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                                    <span className="text-muted-foreground ml-1.5 text-xs font-normal">
                                        (≈ Rp {demurrage.total_idr.toLocaleString('id-ID')})
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* List Kontainer dengan Lampu Hijau / Kuning / Merah */}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {demurrage.containers.map((c) => {
                                const demColor =
                                    c.demurrage.lampu === 'merah' ? 'bg-red-500' : c.demurrage.lampu === 'kuning' ? 'bg-amber-500' : 'bg-emerald-500';
                                const detColor =
                                    c.detention.lampu === 'merah' ? 'bg-red-500' : c.detention.lampu === 'kuning' ? 'bg-amber-500' : 'bg-emerald-500';

                                return (
                                    <div key={c.container_id} className="bg-muted/20 space-y-2 rounded-lg border p-3 text-xs">
                                        <div className="flex items-center justify-between border-b pb-1 font-mono text-sm font-bold">
                                            <span>{c.container_number}</span>
                                            <span className="text-muted-foreground text-xs font-normal">{c.size}'</span>
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
                                                        <span className="font-bold text-red-600">Telat {c.demurrage.overdue_days} hr</span>
                                                    ) : (
                                                        <span className="font-medium text-emerald-600">
                                                            Sisa {c.demurrage.remaining_free_days} hr
                                                        </span>
                                                    )}
                                                </span>
                                            </div>
                                            <div className="text-muted-foreground flex justify-between text-[11px]">
                                                <span>Last Free: {c.demurrage.last_free_date || '-'}</span>
                                                {c.demurrage.cost_usd > 0 && (
                                                    <span className="font-mono font-bold text-red-600">USD {c.demurrage.cost_usd}</span>
                                                )}
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
                                                        <span className="font-bold text-red-600">Telat {c.detention.overdue_days} hr</span>
                                                    ) : (
                                                        <span className="font-medium text-emerald-600">
                                                            Sisa {c.detention.remaining_free_days} hr
                                                        </span>
                                                    )}
                                                </span>
                                            </div>
                                            <div className="text-muted-foreground flex justify-between text-[11px]">
                                                <span>Last Free: {c.detention.last_free_date || '-'}</span>
                                                {c.detention.cost_usd > 0 && (
                                                    <span className="font-mono font-bold text-red-600">USD {c.detention.cost_usd}</span>
                                                )}
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
                    <div className="space-y-6 lg:col-span-1">
                        {/* Detail Job Card */}
                        <div className="bg-card space-y-3 rounded-xl border p-4 text-sm">
                            <h3 className="text-muted-foreground border-b pb-1 text-xs font-medium uppercase">Detail Muatan</h3>
                            <div>
                                <span className="text-muted-foreground block text-xs">Kapal & Voyage:</span>
                                <span>
                                    {job.vessel_name || '-'} {job.voyage_number ? `(${job.voyage_number})` : ''}
                                </span>
                                {job.vessel?.imo_number && (
                                    <span className="text-muted-foreground block text-xs">
                                        IMO {job.vessel.imo_number}
                                        {job.vessel.call_sign ? ` · Call Sign ${job.vessel.call_sign}` : ''}
                                    </span>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <span className="text-muted-foreground block text-xs">ETA:</span>
                                    <span>{job.eta || '-'}</span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground block text-xs">ATA:</span>
                                    <span>{job.ata || '-'}</span>
                                </div>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">Pelabuhan Bongkar (POD):</span>
                                <span>
                                    {job.pod?.name} ({job.pod?.code})
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">Klaim FTA:</span>
                                <span>{job.claim_fta ? `Ya (${job.fta_scheme})` : 'Tidak (MFN Normal)'}</span>
                            </div>
                        </div>

                        {/* Kontainer Fisik Card */}
                        <div className="bg-card space-y-3 rounded-xl border p-4">
                            <h3 className="text-muted-foreground border-b pb-1 text-xs font-medium uppercase">
                                Kontainer ({job.containers.length} Unit)
                            </h3>
                            <div className="space-y-2">
                                {job.containers.map((c) => (
                                    <div key={c.id} className="bg-muted/20 space-y-1 rounded-lg border p-2.5 text-xs">
                                        <div className="flex justify-between font-mono text-sm font-bold">
                                            <span>{c.container_number}</span>
                                            <span>
                                                {c.size}' {c.type}
                                            </span>
                                        </div>
                                        <div className="text-muted-foreground flex justify-between">
                                            <span>Seal: {c.seal_number || '-'}</span>
                                            <span>{parseFloat(c.gross_weight_kg).toLocaleString()} KG</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Staf Field yang Ditugaskan */}
                        <div className="bg-card space-y-3 rounded-xl border p-4 text-sm">
                            <h3 className="text-muted-foreground border-b pb-1 text-xs font-medium uppercase">Petugas Lapangan (Field)</h3>
                            <div className="flex flex-wrap gap-1">
                                {job.fields.map((f) => (
                                    <Badge key={f.id} variant="secondary" className="flex items-center gap-1">
                                        <UserCheck className="size-3" /> {f.name}
                                    </Badge>
                                ))}
                                {job.fields.length === 0 && <span className="text-muted-foreground text-xs">Belum ada Field ditugaskan.</span>}
                            </div>

                            {canAssignField && (
                                <form onSubmit={handleAssignField} className="flex gap-2 border-t pt-2">
                                    <select
                                        className="bg-background flex-1 rounded-md border p-1.5 text-xs"
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
                    <div className="space-y-6 lg:col-span-2">
                        {/* 1. Riwayat Milestone Dua Cabang (§2.2) */}
                        <div className="bg-card space-y-3 rounded-xl border p-4">
                            <div className="flex items-center justify-between border-b pb-2">
                                <h3 className="flex items-center gap-2 text-sm font-medium">
                                    <Clock className="text-primary size-4" />
                                    Perjalanan Milestone Dua Cabang (Pabean & Logistik)
                                </h3>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-xs">
                                    <thead>
                                        <tr className="bg-muted/40 border-b text-left">
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
                                                <tr
                                                    key={m.id}
                                                    className={`border-b last:border-b-0 ${!m.is_active ? 'line-through opacity-40' : ''}`}
                                                >
                                                    <td className="px-3 py-2 font-mono text-[10px] font-bold uppercase">{m.branch}</td>
                                                    <td className="px-3 py-2 font-medium capitalize">
                                                        {m.milestone_type.replace(/_/g, ' ')}
                                                        {m.proof_number && ` (${m.proof_number})`}
                                                        {m.is_backdate && (
                                                            <Badge variant="outline" className="ml-1 text-[9px]">
                                                                Backdate
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono">{m.container?.container_number || 'Semua (Job)'}</td>
                                                    <td className="px-3 py-2 font-mono">{m.occurred_at.slice(0, 16).replace('T', ' ')}</td>
                                                    <td className="text-muted-foreground px-3 py-2">{m.creator?.name || '-'}</td>
                                                    <td className="px-3 py-2">
                                                        {m.verifier ? (
                                                            <span className="font-medium text-green-700 dark:text-green-400">
                                                                ✓ {m.verifier.name}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground">Belum</span>
                                                        )}
                                                    </td>
                                                    <td className="flex items-center gap-1.5 px-3 py-2">
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
                                                <td colSpan={7} className="text-muted-foreground py-6 text-center">
                                                    Belum ada milestone tercatat untuk Job ini.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* 2. Checklist Dokumen Berfase (§2.4) */}
                        <div className="bg-card space-y-4 rounded-xl border p-4">
                            <h3 className="border-b pb-2 text-sm font-medium">Checklist Dokumen Berfase</h3>

                            {/* Fase 1: Pra-Filing */}
                            <div className="space-y-1.5">
                                <h4 className="text-muted-foreground text-xs font-semibold uppercase">Fase 1: Pra-Filing (Syarat Maju PIB)</h4>
                                <table className="w-full overflow-hidden rounded-lg border text-xs">
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
                                <h4 className="text-muted-foreground text-xs font-semibold uppercase">Fase 2: Pasca-Filing (DO & SPPB)</h4>
                                <table className="w-full overflow-hidden rounded-lg border text-xs">
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
                                <h4 className="text-muted-foreground text-xs font-semibold uppercase">Fase 3: Pasca-Delivery (Bukti Gate & POD)</h4>
                                <table className="w-full overflow-hidden rounded-lg border text-xs">
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
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="bg-card w-full max-w-md space-y-4 rounded-xl border p-6 shadow-xl">
                            <h3 className="text-base font-bold">Catat Milestone Peristiwa</h3>

                            <form onSubmit={handleStoreMilestone} className="space-y-3 text-sm">
                                <div>
                                    <Label htmlFor="m_type">Jenis Milestone</Label>
                                    <select
                                        id="m_type"
                                        className="bg-background mt-1 w-full rounded-md border p-2"
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
                                            className="bg-background mt-1 w-full rounded-md border p-2 font-mono text-xs"
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

                                {['do_release', 'sppb', 'pib_filed'].includes(milestoneForm.data.milestone_type) && (
                                    <div>
                                        <Label htmlFor="m_proof_no">Nomor Dokumen (No DO / No SPPB / No PIB)</Label>
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

                                <div className="flex justify-end gap-2 border-t pt-2">
                                    <Button type="button" variant="outline" onClick={() => setShowMilestoneModal(false)}>
                                        Batal
                                    </Button>
                                    <Button type="submit">Simpan Milestone</Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
