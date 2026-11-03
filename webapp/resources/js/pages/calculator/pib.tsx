import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
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
}: {
    job: {
        id: number;
        job_number: string;
        incoterm?: string | null;
        importir: { name: string; status_api: string } | null;
        shippingLine: { name: string } | null;
        claim_fta: boolean;
        fta_scheme: string | null;
    };
    declaration: (Declaration & { items: DeclarationItem[] }) | null;
    activeTaxRate: { rate_to_idr: string; valid_from: string; valid_to: string } | null;
    ftaSchemes: string[];
    canCalculate: boolean;
    canApprove: boolean;
}) {
    const authUserId = usePage().props.auth.user.id;
    const defaultKurs = activeTaxRate ? String(activeTaxRate.rate_to_idr) : '1';

    const { data, setData, post, processing, errors, reset } = useForm<{
        incoterm: string;
        items: ItemRow[];
    }>({
        incoterm: declaration?.incoterm ?? '',
        items: [emptyItem(defaultKurs)],
    });

    const addItem = () => setData('items', [...data.items, emptyItem(defaultKurs)]);
    const removeItem = (i: number) => {
        if (data.items.length <= 1) return;
        setData('items', data.items.filter((_, idx) => idx !== i));
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

    const canApproveThis =
        declaration && canApprove && declaration.status === 'draft' && declaration.created_by_user_id !== authUserId;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kalkulator PIB v2" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-6xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-medium">Kalkulator PIB v2 — Job {job.job_number}</h2>
                        <p className="text-xs text-muted-foreground mt-1">
                            {job.importir?.name ?? '-'} ({job.importir?.status_api ?? '-'}) · {job.shippingLine?.name ?? '-'}
                            {job.claim_fta ? ` · Klaim FTA ${job.fta_scheme ?? ''}` : ''}
                        </p>
                    </div>
                    {activeTaxRate && (
                        <div className="text-xs text-muted-foreground text-right">
                            <div>Kurs Pajak aktif (NDPBM):</div>
                            <div className="font-mono text-sm text-foreground">
                                USD {Number(activeTaxRate.rate_to_idr).toLocaleString('id-ID', { maximumFractionDigits: 4 })}
                            </div>
                            <div>{activeTaxRate.valid_from.slice(0, 10)} → {activeTaxRate.valid_to.slice(0, 10)}</div>
                        </div>
                    )}
                </div>

                {/* Snapshot tersimpan */}
                {declaration && (
                    <div className="rounded-xl border p-4 space-y-3 bg-card">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-2">
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium text-sm">Draf PIB v{declaration.version}</h3>
                                <Badge variant={declaration.status === 'approved' ? 'default' : declaration.status === 'filed' ? 'secondary' : 'outline'}>
                                    {declaration.status}
                                </Badge>
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Dibuat {declaration.creator?.name ?? '-'}
                                {declaration.approver ? ` · Disetujui ${declaration.approver.name}` : ''}
                                {declaration.incoterm ? ` · Incoterm ${declaration.incoterm}` : ''}
                            </div>
                        </div>

                        <div className="grid gap-2 text-sm sm:grid-cols-4">
                            <div><span className="text-muted-foreground text-xs block">CIF (Valas)</span><span className="font-mono">USD {Number(declaration.total_cif_valas).toLocaleString()}</span></div>
                            <div><span className="text-muted-foreground text-xs block">CIF (Rp)</span><span className="font-mono">{rupiah(declaration.total_cif_idr)}</span></div>
                            <div><span className="text-muted-foreground text-xs block">Bea Masuk</span><span className="font-mono">{rupiah(declaration.total_bm)}</span></div>
                            <div><span className="text-muted-foreground text-xs block">PPN Impor</span><span className="font-mono">{rupiah(declaration.total_ppn)}</span></div>
                            <div><span className="text-muted-foreground text-xs block">PPh 22</span><span className="font-mono">{rupiah(declaration.total_pph22)}</span></div>
                            <div><span className="text-muted-foreground text-xs block">PPnBM</span><span className="font-mono">{rupiah(declaration.total_ppnbm)}</span></div>
                            <div><span className="text-muted-foreground text-xs block">Pungutan Lain</span><span className="font-mono">{rupiah(declaration.total_pungutan_lain)}</span></div>
                            <div><span className="text-muted-foreground text-xs block">Total Pungutan</span><span className="font-mono font-medium">{rupiah(declaration.total_pungutan)}</span></div>
                        </div>

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="border-b bg-muted/50 text-left">
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
                                                {it.is_unverified_warning && <span className="text-amber-600" title="Tarif perlu verifikasi hukum"> ⚠</span>}
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

                {/* Form draf baru */}
                {canCalculate ? (
                    <form onSubmit={submit} className="space-y-4">
                        <div className="rounded-xl border p-4 space-y-3 bg-card">
                            <div className="flex flex-wrap items-end gap-3">
                                <div>
                                    <Label htmlFor="incoterm">Incoterm (data keterangan PIB)</Label>
                                    <select
                                        id="incoterm"
                                        className="border rounded-md p-2 text-sm bg-background mt-1"
                                        value={data.incoterm}
                                        onChange={(e) => setData('incoterm', e.target.value)}
                                    >
                                        <option value="">—</option>
                                        {INCOTERMS.map((ic) => (
                                            <option key={ic} value={ic}>{ic}</option>
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
                                    <div key={i} className="rounded-lg border p-3 bg-muted/20 space-y-3">
                                        <div className="grid gap-3 sm:grid-cols-6 items-end">
                                            <div className="sm:col-span-2">
                                                <Label className="text-xs">Kode HS (8/10 digit)</Label>
                                                <Input
                                                    value={item.hs_code}
                                                    onChange={(e) => updateItem(i, 'hs_code', e.target.value)}
                                                    placeholder="0301.11.00.00"
                                                    required
                                                />
                                                {errors[`items.${i}.hs_code`] && (
                                                    <p className="text-xs text-red-600">{errors[`items.${i}.hs_code`]}</p>
                                                )}
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label className="text-xs">Uraian</Label>
                                                <Input value={item.description} onChange={(e) => updateItem(i, 'description', e.target.value)} />
                                            </div>
                                            <div>
                                                <Label className="text-xs">Valas</Label>
                                                <Input value={item.currency_code} onChange={(e) => updateItem(i, 'currency_code', e.target.value.toUpperCase())} />
                                            </div>
                                            <div>
                                                <Label className="text-xs">Kurs Pajak</Label>
                                                <Input type="number" step="0.0001" value={item.kurs_pajak_rate} onChange={(e) => updateItem(i, 'kurs_pajak_rate', e.target.value)} required />
                                            </div>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-6 items-end">
                                            <div>
                                                <Label className="text-xs">FOB (valas)</Label>
                                                <Input type="number" step="0.01" min="0" value={item.fob_valas} onChange={(e) => updateItem(i, 'fob_valas', e.target.value)} required />
                                                {errors[`items.${i}.fob_valas`] && (
                                                    <p className="text-xs text-red-600">{errors[`items.${i}.fob_valas`]}</p>
                                                )}
                                            </div>
                                            <div>
                                                <Label className="text-xs">Freight (valas)</Label>
                                                <Input type="number" step="0.01" min="0" value={item.freight_valas} onChange={(e) => updateItem(i, 'freight_valas', e.target.value)} required />
                                            </div>
                                            <div>
                                                <Label className="text-xs">Asuransi</Label>
                                                <Input type="number" step="0.01" min="0" value={item.insurance_valas} onChange={(e) => updateItem(i, 'insurance_valas', e.target.value)} />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label className="text-xs">Dasar Asuransi</Label>
                                                <select
                                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                                    value={item.insurance_basis}
                                                    onChange={(e) => updateItem(i, 'insurance_basis', e.target.value)}
                                                >
                                                    {INSURANCE_BASES.map(([v, l]) => (
                                                        <option key={v} value={v}>{l}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <div className="flex-1">
                                                    <Label className="text-xs">Pungutan Lain (Rp)</Label>
                                                    <Input type="number" step="0.01" min="0" value={item.pungutan_lain_amount} onChange={(e) => updateItem(i, 'pungutan_lain_amount', e.target.value)} />
                                                </div>
                                                {data.items.length > 1 && (
                                                    <Button type="button" variant="ghost" size="icon" className="text-red-600 mt-5" onClick={() => removeItem(i)}>
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-4 text-xs">
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" checked={item.is_ppn_luxury} onChange={(e) => updateItem(i, 'is_ppn_luxury', e.target.checked)} />
                                                Barang mewah (PPnBM)
                                            </label>
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" checked={item.is_pph22_exempt} onChange={(e) => updateItem(i, 'is_pph22_exempt', e.target.checked)} />
                                                Bebas PPh 22 (Ps. 4)
                                            </label>
                                            {item.is_pph22_exempt && (
                                                <Input
                                                    className="max-w-xs h-7 text-xs"
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
                    <p className="text-sm text-muted-foreground">
                        Hanya Customs Specialist yang dapat menghitung PIB (§2.5).
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
