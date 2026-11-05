import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

type ItemRow = {
    hs_code: string;
    description: string;
    currency_code: string;
    fob_valas: string;
    freight_valas: string;
    insurance_valas: string;
    insurance_basis: string;
    kurs_pajak_rate: string;
    is_ppn_luxury: boolean;
    is_pph22_exempt: boolean;
    exemption_reason: string;
    pungutan_lain_amount: string;
};

type DeclarationItem = {
    id: number;
    item_number: number;
    hs_code: string;
    description: string | null;
    currency_code: string;
    fob_valas: string;
    freight_valas: string;
    insurance_valas: string;
    cif_valas: string;
    cif_rupiah: string;
    bm_rate: string;
    bm_amount: string;
    dpp_ppn: string;
    ppn_amount: string;
    pph22_rate: string;
    pph22_amount: string;
    ppnbm_rate: string;
    ppnbm_amount: string;
    is_unverified_warning: boolean;
};

type Declaration = {
    id: number;
    version: number;
    status: string;
    incoterm: string | null;
    kmk_number: string | null;
    total_cif_valas: string;
    total_cif_idr: string;
    total_bm: string;
    total_ppn: string;
    total_pph22: string;
    total_ppnbm: string;
    total_pungutan_lain: string;
    total_pungutan: string;
    created_by_user_id: number | null;
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    items: DeclarationItem[];
};

const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

type FtaForm = {
    id: number;
    scheme_code: string;
    form_number: string;
    hs_code: string;
    origin_country: string;
    port_of_loading: string;
    issued_at: string;
    valid_until: string;
    file_path: string | null;
    file_name: string | null;
    created_at: string;
    creator: { id: number; name: string } | null;
};

type FtaGateItem = {
    customs_item_id: number;
    item_number: number;
    hs_code: string;
    bm_type: string;
    status: 'lolos' | 'peringatan' | 'diblokir' | 'mfn' | 'override';
    form_id: number | null;
    checks: string[];
    reasons: string[];
};

type FtaGateEvaluation = {
    claim_fta: boolean;
    mfn_decided: boolean;
    overall: 'lolos' | 'peringatan' | 'diblokir' | 'mfn';
    items: FtaGateItem[];
};

type FtaDecision = {
    id: number;
    decision_type: 'mfn_now' | 'delay' | 'warning_8digit';
    reason: string | null;
    risk_note: string | null;
    refund_reference: string | null;
    customs_item_id: number | null;
    fta_form_id: number | null;
    created_at: string;
    creator: { id: number; name: string } | null;
};

type FtaOverride = {
    id: number;
    customs_declaration_id: number;
    customs_item_id: number;
    reason: string;
    attachment_path: string;
    attachment_name: string | null;
    created_at: string;
    creator: { id: number; name: string } | null;
};

const GATE_BADGE: Record<string, { label: string; className: string }> = {
    lolos: { label: 'Lolos', className: 'bg-emerald-100 text-emerald-800' },
    peringatan: { label: 'Peringatan', className: 'bg-amber-100 text-amber-800' },
    diblokir: { label: 'Diblokir', className: 'bg-red-100 text-red-800' },
    mfn: { label: 'MFN', className: 'bg-slate-100 text-slate-700' },
    override: { label: 'Override', className: 'bg-violet-100 text-violet-800' },
};

const DECISION_LABEL: Record<string, string> = {
    mfn_now: 'File MFN sekarang',
    delay: 'Tunda filing',
    warning_8digit: 'Peringatan digit 7–8 (T5)',
};
const INSURANCE_BASES = [
    ['input_riil', 'Asuransi riil (diinput)'],
    ['fallback_fob_0.5', 'Fallback 0.5% × FOB'],
    ['fallback_cfr_0.5', 'Fallback 0.5% × CFR'],
    ['dalam_negeri_0', 'Dalam negeri (0)'],
] as const;

const rupiah = (v: string | number) => `Rp ${Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })}`;

const emptyItem = (kurs: string): ItemRow => ({
    hs_code: '',
    description: '',
    currency_code: 'USD',
    fob_valas: '',
    freight_valas: '0',
    insurance_valas: '',
    insurance_basis: 'input_riil',
    kurs_pajak_rate: kurs,
    is_ppn_luxury: false,
    is_pph22_exempt: false,
    exemption_reason: '',
    pungutan_lain_amount: '0',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Daftar Job', href: '/jobs' },
    { title: 'Kalkulator PIB', href: '#' },
];

export default function CalculatorPib({
    job,
    declaration,
    activeTaxRate,
    canCalculate,
    canApprove,
    ftaSchemes,
    ftaForms,
    ftaGate,
    ftaDecisions,
    ftaOverrides,
    canOverride,
    canDecide,
    canStoreForm,
}: {
    job: {
        id: number;
        job_number: string;
        incoterm?: string | null;
        importir: { name: string; status_api: string } | null;
        shippingLine: { name: string } | null;
        pol: { code: string; name: string } | null;
        pod: { code: string; name: string } | null;
        claim_fta: boolean;
        fta_scheme: string | null;
    };
    declaration: (Declaration & { items: DeclarationItem[] }) | null;
    activeTaxRate: { rate_to_idr: string; valid_from: string; valid_to: string } | null;
    ftaSchemes: string[];
    canCalculate: boolean;
    canApprove: boolean;
    ftaForms: FtaForm[];
    ftaGate: FtaGateEvaluation | null;
    ftaDecisions: FtaDecision[];
    ftaOverrides: FtaOverride[];
    canOverride: boolean;
    canDecide: boolean;
    canStoreForm: boolean;
}) {
    const authUserId = usePage<SharedData>().props.auth.user.id;
    // TEMUAN LANJUTAN (2026-11-05): kurs TIDAK BOLEH default 1 — nilai 1
    // untuk valas meruntuhkan CIF Rupiah. Kosong bila tidak ada Kurs Pajak
    // aktif; server selalu resolve dari baris KMK dan gagal tertutup bila
    // tidak ada (input ini hanya tampilan, bukan sumber hitung).
    const defaultKurs = activeTaxRate ? String(activeTaxRate.rate_to_idr) : '';

    const { data, setData, post, processing, errors, reset } = useForm<{
        incoterm: string;
        items: ItemRow[];
    }>({
        incoterm: declaration?.incoterm ?? '',
        items: [emptyItem(defaultKurs)],
    });

    // Alias agar akses error per-baris dinamis (items.0.hs_code, containers.0.type, ...) typecheck.
    const errs = errors as Record<string, string | undefined>;

    const addItem = () => setData('items', [...data.items, emptyItem(defaultKurs)]);
    const removeItem = (i: number) => {
        if (data.items.length <= 1) return;
        setData(
            'items',
            data.items.filter((_, idx) => idx !== i),
        );
    };
    const updateItem = (i: number, field: keyof ItemRow, value: string | boolean) => {
        const next = [...data.items];
        next[i] = { ...next[i], [field]: value };
        setData('items', next);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('jobs.calculator.draft', job.id), {
            onSuccess: () => reset(),
        });
    };

    const canApproveThis = declaration && canApprove && declaration.status === 'draft' && declaration.created_by_user_id !== authUserId;

    // Decision log: gabungan keputusan MFN + override, terbaru dulu.
    const logEntries = [
        ...ftaDecisions.map((d) => ({ key: `d-${d.id}`, at: d.created_at, kind: 'decision' as const, decision: d })),
        ...ftaOverrides.map((o) => ({ key: `o-${o.id}`, at: o.created_at, kind: 'override' as const, override: o })),
    ].sort((a, b) => (a.at < b.at ? 1 : -1));

    const blockedItems = (ftaGate?.items ?? []).filter((it) => it.status === 'diblokir');
    const mfnDecided = ftaGate?.mfn_decided ?? false;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kalkulator PIB v2" />
            <div className="flex h-full max-w-6xl flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-medium">Kalkulator PIB v2 — Job {job.job_number}</h2>
                        <p className="text-muted-foreground mt-1 text-xs">
                            {job.importir?.name ?? '-'} ({job.importir?.status_api ?? '-'}) · {job.shippingLine?.name ?? '-'}
                            {job.claim_fta ? ` · Klaim FTA ${job.fta_scheme ?? ''}` : ''}
                        </p>
                    </div>
                    {activeTaxRate && (
                        <div className="text-muted-foreground text-right text-xs">
                            <div>Kurs Pajak aktif (NDPBM):</div>
                            <div className="text-foreground font-mono text-sm">
                                USD {Number(activeTaxRate.rate_to_idr).toLocaleString('id-ID', { maximumFractionDigits: 4 })}
                            </div>
                            <div>
                                {activeTaxRate.valid_from.slice(0, 10)} → {activeTaxRate.valid_to.slice(0, 10)}
                            </div>
                        </div>
                    )}
                </div>

                {/* Snapshot tersimpan */}
                {declaration && (
                    <div className="bg-card space-y-3 rounded-xl border p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-2">
                            <div className="flex items-center gap-2">
                                <h3 className="text-sm font-medium">Draf PIB v{declaration.version}</h3>
                                <Badge
                                    variant={declaration.status === 'approved' ? 'default' : declaration.status === 'filed' ? 'secondary' : 'outline'}
                                >
                                    {declaration.status}
                                </Badge>
                            </div>
                            <div className="text-muted-foreground text-xs">
                                Dibuat {declaration.creator?.name ?? '-'}
                                {declaration.approver ? ` · Disetujui ${declaration.approver.name}` : ''}
                                {declaration.incoterm ? ` · Incoterm ${declaration.incoterm}` : ''}
                            </div>
                        </div>

                        <div className="grid gap-2 text-sm sm:grid-cols-4">
                            <div>
                                <span className="text-muted-foreground block text-xs">CIF (Valas)</span>
                                <span className="font-mono">USD {Number(declaration.total_cif_valas).toLocaleString()}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">CIF (Rp)</span>
                                <span className="font-mono">{rupiah(declaration.total_cif_idr)}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">Bea Masuk</span>
                                <span className="font-mono">{rupiah(declaration.total_bm)}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">PPN Impor</span>
                                <span className="font-mono">{rupiah(declaration.total_ppn)}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">PPh 22</span>
                                <span className="font-mono">{rupiah(declaration.total_pph22)}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">PPnBM</span>
                                <span className="font-mono">{rupiah(declaration.total_ppnbm)}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">Pungutan Lain</span>
                                <span className="font-mono">{rupiah(declaration.total_pungutan_lain)}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block text-xs">Total Pungutan</span>
                                <span className="font-mono font-medium">{rupiah(declaration.total_pungutan)}</span>
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="bg-muted/50 border-b text-left">
                                        <th className="px-2 py-1.5">Seri</th>
                                        <th className="px-2 py-1.5">HS</th>
                                        <th className="px-2 py-1.5">CIF (Rp)</th>
                                        <th className="px-2 py-1.5 text-right">BM</th>
                                        <th className="px-2 py-1.5 text-right">PPN</th>
                                        <th className="px-2 py-1.5 text-right">PPh 22</th>
                                        <th className="px-2 py-1.5 text-right">PPnBM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {declaration.items.map((it) => (
                                        <tr key={it.id} className="border-b last:border-b-0">
                                            <td className="px-2 py-1.5">{it.item_number}</td>
                                            <td className="px-2 py-1.5 font-mono">
                                                {it.hs_code}
                                                {it.is_unverified_warning && (
                                                    <span className="text-amber-600" title="Tarif perlu verifikasi hukum">
                                                        {' '}
                                                        ⚠
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-2 py-1.5 font-mono">{Number(it.cif_rupiah).toLocaleString()}</td>
                                            <td className="px-2 py-1.5 text-right font-mono">{Number(it.bm_amount).toLocaleString()}</td>
                                            <td className="px-2 py-1.5 text-right font-mono">{Number(it.ppn_amount).toLocaleString()}</td>
                                            <td className="px-2 py-1.5 text-right font-mono">{Number(it.pph22_amount).toLocaleString()}</td>
                                            <td className="px-2 py-1.5 text-right font-mono">{Number(it.ppnbm_amount).toLocaleString()}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {canApproveThis && (
                            <div className="pt-1">
                                <Button
                                    size="sm"
                                    onClick={() => router.post(route('jobs.calculator.approve', { job: job.id, declaration: declaration.id }))}
                                >
                                    Setujui sebagai Snapshot PIB (Maker-Checker)
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {/* Gerbang FTA (Tiket 10, Q19) — Form FTA, status gerbang, override, decision log */}
                <div className="bg-card space-y-3 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-2">
                        <h3 className="text-sm font-medium">Gerbang FTA — Form FTA & Validasi</h3>
                        {ftaGate && (
                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${GATE_BADGE[ftaGate.overall].className}`}>
                                {GATE_BADGE[ftaGate.overall].label}
                            </span>
                        )}
                    </div>

                    {!job.claim_fta && !mfnDecided && (
                        <p className="text-muted-foreground text-xs">
                            Job ini tidak klaim FTA — filing memakai tarif MFN, gerbang FTA tidak berlaku.
                        </p>
                    )}
                    {mfnDecided && (
                        <p className="rounded-md bg-slate-100 px-2 py-1.5 text-xs font-medium text-slate-700">
                            FTA tidak diklaim — MFN (keputusan tercatat di decision log). Versi PIB ini tidak dapat diam-diam diubah kembali ke FTA;
                            klaim ulang MFN→FTA adalah refund manual di luar sistem.
                        </p>
                    )}
                    {job.claim_fta && job.pol && (
                        <p className="text-muted-foreground text-xs">
                            POL Job: {job.pol.code} — {job.pol.name} · Skema: {job.fta_scheme ?? '-'}
                        </p>
                    )}

                    {ftaGate && declaration && ftaGate.items.length > 0 && (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="bg-muted/50 border-b text-left">
                                        <th className="px-2 py-1.5">Seri</th>
                                        <th className="px-2 py-1.5">HS PIB</th>
                                        <th className="px-2 py-1.5">Tarif</th>
                                        <th className="px-2 py-1.5">Status</th>
                                        <th className="px-2 py-1.5">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ftaGate.items.map((it) => (
                                        <tr key={it.customs_item_id} className="border-b last:border-b-0">
                                            <td className="px-2 py-1.5">{it.item_number}</td>
                                            <td className="px-2 py-1.5 font-mono">{it.hs_code}</td>
                                            <td className="px-2 py-1.5">{it.bm_type === 'fta' ? 'FTA' : 'MFN'}</td>
                                            <td className="px-2 py-1.5">
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${GATE_BADGE[it.status].className}`}>
                                                    {GATE_BADGE[it.status].label}
                                                </span>
                                            </td>
                                            <td className="px-2 py-1.5">
                                                {it.reasons.map((r, i) => (
                                                    <div key={i}>{r}</div>
                                                ))}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <div className="space-y-1">
                        <h4 className="text-xs font-medium">Form FTA tercatat ({ftaForms.length})</h4>
                        {ftaForms.length === 0 && <p className="text-muted-foreground text-xs">Belum ada Form FTA untuk Job ini.</p>}
                        <ul className="space-y-1">
                            {ftaForms.map((f) => (
                                <li key={f.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-2 py-1.5 text-xs">
                                    <span>
                                        <strong>
                                            {f.scheme_code} — {f.form_number}
                                        </strong>{' '}
                                        · HS <span className="font-mono">{f.hs_code}</span> · Negara asal {f.origin_country} · POL {f.port_of_loading}{' '}
                                        · Berlaku {new Date(f.issued_at).toLocaleDateString('id-ID')} s.d.{' '}
                                        {new Date(f.valid_until).toLocaleDateString('id-ID')}
                                        {f.creator ? ` · oleh ${f.creator.name}` : ''}
                                    </span>
                                    {f.file_path && (
                                        <a className="text-primary underline" href={route('jobs.fta-forms.download', { job: job.id, form: f.id })}>
                                            {f.file_name ?? 'Unduh lampiran'}
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>

                    {canStoreForm && <FtaFormForm jobId={job.id} ftaSchemes={ftaSchemes} declarationItems={declaration?.items ?? []} />}

                    {canOverride && declaration && declaration.status === 'draft' && blockedItems.length > 0 && (
                        <div className="space-y-2 rounded-lg border border-red-200 bg-red-50/50 p-3">
                            <h4 className="text-xs font-medium text-red-800">Override gerbang FTA (Customs Specialist — bukan pembuat draf)</h4>
                            {declaration.created_by_user_id === authUserId && (
                                <p className="text-xs text-red-700">
                                    Anda adalah pembuat draf PIB ini — override harus dilakukan Customs Specialist lain (maker-checker, ADR 0006).
                                </p>
                            )}
                            {declaration.created_by_user_id !== authUserId &&
                                blockedItems.map((it) => (
                                    <OverrideForm key={it.customs_item_id} jobId={job.id} declarationId={declaration.id} item={it} />
                                ))}
                        </div>
                    )}

                    <div className="space-y-1">
                        <h4 className="text-xs font-medium">Decision log ({logEntries.length})</h4>
                        {logEntries.length === 0 && <p className="text-muted-foreground text-xs">Belum ada keputusan atau override tercatat.</p>}
                        <ul className="space-y-1">
                            {logEntries.map((entry) =>
                                entry.kind === 'decision' ? (
                                    <li key={entry.key} className="rounded-md border px-2 py-1.5 text-xs">
                                        <strong>{DECISION_LABEL[entry.decision.decision_type] ?? entry.decision.decision_type}</strong>
                                        {entry.decision.reason ? ` — ${entry.decision.reason}` : ''}
                                        {entry.decision.risk_note ? ` (Risiko: ${entry.decision.risk_note})` : ''}
                                        {entry.decision.refund_reference ? ` · Refund ref: ${entry.decision.refund_reference}` : ''}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {new Date(entry.decision.created_at).toLocaleString('id-ID')}
                                            {entry.decision.creator ? ` oleh ${entry.decision.creator.name}` : ''}
                                        </span>
                                        {entry.decision.decision_type === 'mfn_now' && !entry.decision.refund_reference && canDecide && (
                                            <div className="mt-1">
                                                <RefundReferenceForm jobId={job.id} decisionId={entry.decision.id} />
                                            </div>
                                        )}
                                    </li>
                                ) : (
                                    <li key={entry.key} className="rounded-md border px-2 py-1.5 text-xs">
                                        <strong>Override gerbang FTA</strong>
                                        {entry.override.reason ? ` — ${entry.override.reason}` : ''}
                                        {entry.override.attachment_name ? ` · Lampiran: ${entry.override.attachment_name}` : ''}
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {new Date(entry.override.created_at).toLocaleString('id-ID')}
                                            {entry.override.creator ? ` oleh ${entry.override.creator.name}` : ''}
                                        </span>
                                    </li>
                                ),
                            )}
                        </ul>
                    </div>

                    {canDecide && declaration && declaration.status === 'draft' && job.claim_fta && !mfnDecided && (
                        <DecisionForm jobId={job.id} declarationId={declaration.id} />
                    )}
                </div>

                {/* Form draf baru */}
                {!activeTaxRate && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                        Kurs Pajak (NDPBM) belum diatur untuk periode ini — hitung PIB akan ditolak sampai Kurs Pajak diisi di master Kurs Pajak.
                        Jangan isi kurs manual: server hanya memakai baris KMK resmi.
                    </div>
                )}
                {canCalculate ? (
                    <form onSubmit={submit} className="space-y-4">
                        <div className="bg-card space-y-3 rounded-xl border p-4">
                            <div className="flex flex-wrap items-end gap-3">
                                <div>
                                    <Label htmlFor="incoterm">Incoterm (data keterangan PIB)</Label>
                                    <select
                                        id="incoterm"
                                        className="bg-background mt-1 rounded-md border p-2 text-sm"
                                        value={data.incoterm}
                                        onChange={(e) => setData('incoterm', e.target.value)}
                                    >
                                        <option value="">—</option>
                                        {INCOTERMS.map((ic) => (
                                            <option key={ic} value={ic}>
                                                {ic}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.incoterm && <p className="text-xs text-red-600">{errors.incoterm}</p>}
                                </div>
                                <Button type="button" variant="outline" size="sm" onClick={addItem} className="mt-5">
                                    + Seri Barang
                                </Button>
                            </div>

                            <div className="space-y-3">
                                {data.items.map((item, i) => (
                                    <div key={i} className="bg-muted/20 space-y-3 rounded-lg border p-3">
                                        <div className="grid items-end gap-3 sm:grid-cols-6">
                                            <div className="sm:col-span-2">
                                                <Label className="text-xs">Kode HS (8/10 digit)</Label>
                                                <Input
                                                    value={item.hs_code}
                                                    onChange={(e) => updateItem(i, 'hs_code', e.target.value)}
                                                    placeholder="0301.11.00.00"
                                                    required
                                                />
                                                {errs[`items.${i}.hs_code`] && <p className="text-xs text-red-600">{errs[`items.${i}.hs_code`]}</p>}
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label className="text-xs">Uraian</Label>
                                                <Input value={item.description} onChange={(e) => updateItem(i, 'description', e.target.value)} />
                                            </div>
                                            <div>
                                                <Label className="text-xs">Valas</Label>
                                                <Input
                                                    value={item.currency_code}
                                                    onChange={(e) => updateItem(i, 'currency_code', e.target.value.toUpperCase())}
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-xs">Kurs Pajak</Label>
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    value={item.currency_code === 'USD' ? item.kurs_pajak_rate : ''}
                                                    placeholder="KMK (otomatis)"
                                                    title="Kurs diambil dari Kurs Pajak (KMK) yang berlaku — bukan input manual"
                                                    readOnly
                                                />
                                                <p className="text-muted-foreground mt-0.5 text-[11px]">KMK resmi, bukan input manual.</p>
                                            </div>
                                        </div>
                                        <div className="grid items-end gap-3 sm:grid-cols-6">
                                            <div>
                                                <Label className="text-xs">FOB (valas)</Label>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={item.fob_valas}
                                                    onChange={(e) => updateItem(i, 'fob_valas', e.target.value)}
                                                    required
                                                />
                                                {errs[`items.${i}.fob_valas`] && (
                                                    <p className="text-xs text-red-600">{errs[`items.${i}.fob_valas`]}</p>
                                                )}
                                            </div>
                                            <div>
                                                <Label className="text-xs">Freight (valas)</Label>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={item.freight_valas}
                                                    onChange={(e) => updateItem(i, 'freight_valas', e.target.value)}
                                                    required
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-xs">Asuransi</Label>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={item.insurance_valas}
                                                    onChange={(e) => updateItem(i, 'insurance_valas', e.target.value)}
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label className="text-xs">Dasar Asuransi</Label>
                                                <select
                                                    className="bg-background mt-1 w-full rounded-md border p-2 text-sm"
                                                    value={item.insurance_basis}
                                                    onChange={(e) => updateItem(i, 'insurance_basis', e.target.value)}
                                                >
                                                    {INSURANCE_BASES.map(([v, l]) => (
                                                        <option key={v} value={v}>
                                                            {l}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <div className="flex-1">
                                                    <Label className="text-xs">Pungutan Lain (Rp)</Label>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={item.pungutan_lain_amount}
                                                        onChange={(e) => updateItem(i, 'pungutan_lain_amount', e.target.value)}
                                                    />
                                                </div>
                                                {data.items.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="mt-5 text-red-600"
                                                        onClick={() => removeItem(i)}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-4 text-xs">
                                            <label className="flex cursor-pointer items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={item.is_ppn_luxury}
                                                    onChange={(e) => updateItem(i, 'is_ppn_luxury', e.target.checked)}
                                                />
                                                Barang mewah (PPnBM)
                                            </label>
                                            <label className="flex cursor-pointer items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={item.is_pph22_exempt}
                                                    onChange={(e) => updateItem(i, 'is_pph22_exempt', e.target.checked)}
                                                />
                                                Bebas PPh 22 (Ps. 4)
                                            </label>
                                            {item.is_pph22_exempt && (
                                                <Input
                                                    className="h-7 max-w-xs text-xs"
                                                    placeholder="Alasan pengecualian"
                                                    value={item.exemption_reason}
                                                    onChange={(e) => updateItem(i, 'exemption_reason', e.target.value)}
                                                />
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing} className="px-8">
                                Simpan Draf & Hitung
                            </Button>
                        </div>
                    </form>
                ) : (
                    <p className="text-muted-foreground text-sm">Hanya Customs Specialist yang dapat menghitung PIB (§2.5).</p>
                )}
            </div>
        </AppLayout>
    );
}

/** Form tambah Form FTA (CS / Customs Specialist). */
function FtaFormForm({ jobId, ftaSchemes, declarationItems }: { jobId: number; ftaSchemes: string[]; declarationItems: DeclarationItem[] }) {
    const form = useForm<{
        scheme_code: string;
        form_number: string;
        hs_code: string;
        origin_country: string;
        port_of_loading: string;
        issued_at: string;
        valid_until: string;
        customs_item_id: string;
        file: File | null;
    }>({
        scheme_code: ftaSchemes[0] ?? 'ACFTA',
        form_number: '',
        hs_code: '',
        origin_country: '',
        port_of_loading: '',
        issued_at: '',
        valid_until: '',
        customs_item_id: '',
        file: null,
    });
    const ferrs = form.errors as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('jobs.fta-forms.store', jobId), {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="bg-muted/30 space-y-2 rounded-lg border p-3">
            <h4 className="text-xs font-medium">Tambah Form FTA</h4>
            <div className="grid gap-2 md:grid-cols-3">
                <label className="grid gap-1 text-xs">
                    Skema FTA
                    <select
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.scheme_code}
                        onChange={(e) => form.setData('scheme_code', e.target.value)}
                    >
                        {ftaSchemes.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-1 text-xs">
                    Nomor Form
                    <input
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.form_number}
                        onChange={(e) => form.setData('form_number', e.target.value)}
                        placeholder="cth. E24123456789"
                    />
                    {ferrs.form_number && <span className="text-[11px] text-red-600">{ferrs.form_number}</span>}
                </label>
                <label className="grid gap-1 text-xs">
                    HS pada Form (6–10 digit)
                    <input
                        className="bg-background h-9 rounded-md border px-2 font-mono"
                        value={form.data.hs_code}
                        onChange={(e) => form.setData('hs_code', e.target.value)}
                        placeholder="cth. 390110"
                    />
                    {ferrs.hs_code && <span className="text-[11px] text-red-600">{ferrs.hs_code}</span>}
                </label>
                <label className="grid gap-1 text-xs">
                    Negara asal (2 huruf)
                    <input
                        className="bg-background h-9 rounded-md border px-2 uppercase"
                        value={form.data.origin_country}
                        onChange={(e) => form.setData('origin_country', e.target.value.toUpperCase())}
                        placeholder="CN"
                        maxLength={2}
                    />
                    {ferrs.origin_country && <span className="text-[11px] text-red-600">{ferrs.origin_country}</span>}
                </label>
                <label className="grid gap-1 text-xs">
                    Port of loading
                    <input
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.port_of_loading}
                        onChange={(e) => form.setData('port_of_loading', e.target.value)}
                        placeholder="cth. CNSHA / Shanghai"
                    />
                    {ferrs.port_of_loading && <span className="text-[11px] text-red-600">{ferrs.port_of_loading}</span>}
                </label>
                <label className="grid gap-1 text-xs">
                    Seri PIB yang diikat (opsional)
                    <select
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.customs_item_id}
                        onChange={(e) => form.setData('customs_item_id', e.target.value)}
                    >
                        <option value="">— cocok otomatis via prefix 6 digit —</option>
                        {declarationItems.map((it) => (
                            <option key={it.id} value={it.id}>
                                Seri {it.item_number} — {it.hs_code}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="grid gap-1 text-xs">
                    Tanggal terbit
                    <input
                        type="date"
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.issued_at}
                        onChange={(e) => form.setData('issued_at', e.target.value)}
                    />
                    {ferrs.issued_at && <span className="text-[11px] text-red-600">{ferrs.issued_at}</span>}
                </label>
                <label className="grid gap-1 text-xs">
                    Berlaku sampai
                    <input
                        type="date"
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.valid_until}
                        onChange={(e) => form.setData('valid_until', e.target.value)}
                    />
                    {ferrs.valid_until && <span className="text-[11px] text-red-600">{ferrs.valid_until}</span>}
                </label>
                <label className="grid gap-1 text-xs">
                    Lampiran Form (opsional, maks 20 MB)
                    <input
                        type="file"
                        className="bg-background h-9 rounded-md border px-2 py-1.5"
                        onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                    />
                    {ferrs.file && <span className="text-[11px] text-red-600">{ferrs.file}</span>}
                </label>
            </div>
            <button
                type="submit"
                disabled={form.processing}
                className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-xs font-medium disabled:opacity-50"
            >
                {form.processing ? 'Menyimpan…' : 'Simpan Form FTA'}
            </button>
        </form>
    );
}

/** Override atas satu seri yang diblokir gerbang FTA — alasan + lampiran wajib. */
function OverrideForm({ jobId, declarationId, item }: { jobId: number; declarationId: number; item: FtaGateItem }) {
    const form = useForm<{
        customs_item_id: number;
        reason: string;
        attachment: File | null;
    }>({ customs_item_id: item.customs_item_id, reason: '', attachment: null });
    const ferrs = form.errors as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('jobs.calculator.fta-override', { job: jobId, declaration: declarationId }), {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="bg-background space-y-2 rounded-lg border p-3">
            <p className="text-xs font-medium">
                Seri {item.item_number} — HS <span className="font-mono">{item.hs_code}</span>
            </p>
            {item.reasons.map((r, i) => (
                <p key={i} className="text-[11px] text-red-700">
                    {r}
                </p>
            ))}
            <label className="grid gap-1 text-xs">
                Alasan override (wajib)
                <textarea
                    className="bg-background min-h-16 rounded-md border px-2 py-1.5"
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    placeholder="Jelaskan mengapa blokir ini di-override — tercatat di audit trail."
                />
                {ferrs.reason && <span className="text-[11px] text-red-600">{ferrs.reason}</span>}
            </label>
            <label className="grid gap-1 text-xs">
                Lampiran pendukung (wajib, maks 20 MB)
                <input
                    type="file"
                    className="bg-background h-9 rounded-md border px-2 py-1.5"
                    onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)}
                />
                {ferrs.attachment && <span className="text-[11px] text-red-600">{ferrs.attachment}</span>}
            </label>
            {ferrs.override && <p className="text-[11px] text-red-600">{ferrs.override}</p>}
            <button
                type="submit"
                disabled={form.processing}
                className="h-9 rounded-md bg-violet-600 px-3 text-xs font-medium text-white disabled:opacity-50"
            >
                {form.processing ? 'Menyimpan…' : 'Simpan Override'}
            </button>
        </form>
    );
}

/** Keputusan filing bila Form belum tersedia: file MFN sekarang / tunda. */
function DecisionForm({ jobId, declarationId }: { jobId: number; declarationId: number }) {
    const form = useForm<{
        decision_type: string;
        reason: string;
        risk_note: string;
        refund_reference: string;
    }>({ decision_type: 'mfn_now', reason: '', risk_note: '', refund_reference: '' });
    const ferrs = form.errors as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('jobs.calculator.fta-decision', { job: jobId, declaration: declarationId }), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-2 rounded-lg border bg-amber-50/60 p-3">
            <h4 className="text-xs font-medium">Form belum tersedia? Catat keputusan filing</h4>
            <div className="grid gap-2 md:grid-cols-2">
                <label className="grid gap-1 text-xs">
                    Keputusan
                    <select
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.decision_type}
                        onChange={(e) => form.setData('decision_type', e.target.value)}
                    >
                        <option value="mfn_now">File MFN sekarang (tanpa klaim FTA)</option>
                        <option value="delay">Tunda filing menunggu Form FTA</option>
                    </select>
                    {ferrs.decision_type && <span className="text-[11px] text-red-600">{ferrs.decision_type}</span>}
                </label>
                <label className="grid gap-1 text-xs">
                    Referensi refund eksternal (opsional — untuk reclaim MFN→FTA manual)
                    <input
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.refund_reference}
                        onChange={(e) => form.setData('refund_reference', e.target.value)}
                        placeholder="No. referensi refund (bila sudah ada)"
                    />
                </label>
                <label className="grid gap-1 text-xs md:col-span-2">
                    Alasan (wajib)
                    <textarea
                        className="bg-background min-h-16 rounded-md border px-2 py-1.5"
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        placeholder="cth. Form E belum terbit dari eksportir; filing tidak bisa menunggu karena free time habis."
                    />
                    {ferrs.reason && <span className="text-[11px] text-red-600">{ferrs.reason}</span>}
                </label>
                <label className="grid gap-1 text-xs md:col-span-2">
                    Catatan risiko (opsional)
                    <input
                        className="bg-background h-9 rounded-md border px-2"
                        value={form.data.risk_note}
                        onChange={(e) => form.setData('risk_note', e.target.value)}
                        placeholder="cth. Selisih BM MFN vs FTA ± Rp42 jt — reclaim manual setelah Form terbit."
                    />
                </label>
            </div>
            <button
                type="submit"
                disabled={form.processing}
                className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-xs font-medium disabled:opacity-50"
            >
                {form.processing ? 'Menyimpan…' : 'Catat Keputusan'}
            </button>
        </form>
    );
}

/** Catat nomor referensi refund eksternal pada keputusan MFN yang sudah ada. */
function RefundReferenceForm({ jobId, decisionId }: { jobId: number; decisionId: number }) {
    const form = useForm<{ refund_reference: string }>({ refund_reference: '' });
    const ferrs = form.errors as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.patch(route('jobs.fta-decisions.refund-reference', { job: jobId, decision: decisionId }), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-center gap-2">
            <input
                className="bg-background h-8 w-56 rounded-md border px-2 text-xs"
                value={form.data.refund_reference}
                onChange={(e) => form.setData('refund_reference', e.target.value)}
                placeholder="No. referensi refund MFN→FTA"
            />
            <button
                type="submit"
                disabled={form.processing}
                className="bg-primary text-primary-foreground h-8 rounded-md px-2 text-xs font-medium disabled:opacity-50"
            >
                Simpan Referensi Refund
            </button>
            {ferrs.refund_reference && <span className="text-[11px] text-red-600">{ferrs.refund_reference}</span>}
        </form>
    );
}
