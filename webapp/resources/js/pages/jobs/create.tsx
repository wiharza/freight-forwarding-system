import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import React, { useState } from 'react';

type ImportirItem = { id: number; name: string; npwp: string; status_api: string };
type ShippingLineItem = { id: number; code: string; name: string };
type PortItem = { id: number; code: string; name: string };
type ContainerTypeItem = { id: number; size: string; type: string };

type ContainerRow = {
    container_number: string;
    size: string;
    type: string;
    seal_number: string;
    gross_weight_kg: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Daftar Job', href: '/jobs' },
    { title: 'Entri Job Baru', href: '/jobs/create' },
];

export default function JobsCreate({
    importirs,
    shippingLines,
    ports,
    containerTypes,
    ftaSchemes,
}: {
    importirs: ImportirItem[];
    shippingLines: ShippingLineItem[];
    ports: PortItem[];
    containerTypes: ContainerTypeItem[];
    ftaSchemes: string[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        importir_id: importirs[0]?.id ?? '',
        shipping_line_id: shippingLines[0]?.id ?? '',
        bl_number: '',
        bl_date: new Date().toISOString().split('T')[0],
        pol_id: ports[0]?.id ?? '',
        pod_id: ports[0]?.id ?? '',
        vessel_name: '',
        voyage_number: '',
        eta: '',
        ata: '',
        is_fcl: true, // v1 hanya FCL (§1.2, Q2, Q10)
        claim_fta: false,
        fta_scheme: 'ACFTA',
        referensi_excel_lama: '',
        tanggal_cutover: '',
        containers: [
            { container_number: '', size: '20', type: 'Dry', seal_number: '', gross_weight_kg: '20000' },
        ] as ContainerRow[],
    });

    const addContainer = () => {
        setData('containers', [
            ...data.containers,
            { container_number: '', size: '20', type: 'Dry', seal_number: '', gross_weight_kg: '20000' },
        ]);
    };

    const removeContainer = (index: number) => {
        if (data.containers.length <= 1) return;
        setData('containers', data.containers.filter((_, i) => i !== index));
    };

    const updateContainer = (index: number, field: keyof ContainerRow, value: string) => {
        const next = [...data.containers];
        next[index][field] = value;
        setData('containers', next);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('jobs.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Entri Job Baru" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4 max-w-5xl">
                <div>
                    <h2 className="text-lg font-medium">Entri Order Impor Baru (1 Job = 1 Importir + 1 B/L)</h2>
                    <p className="text-xs text-muted-foreground mt-1">
                        Sistem versi 1 melayani Impor Laut FCL + Trucking (§1.2, Q2, Q10).
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* 1. Header Job */}
                    <div className="rounded-xl border p-4 space-y-4 bg-card">
                        <h3 className="font-medium text-sm border-b pb-2">Informasi B/L & Pihak Terkait</h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="importir_id">Importir (Pemilik Barang / Klien)</Label>
                                <select
                                    id="importir_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.importir_id}
                                    onChange={(e) => setData('importir_id', parseInt(e.target.value))}
                                    required
                                >
                                    {importirs.map((imp) => (
                                        <option key={imp.id} value={imp.id}>
                                            {imp.name} (NPWP: {imp.npwp} - {imp.status_api})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="shipping_line_id">Shipping Line (Pelayaran)</Label>
                                <select
                                    id="shipping_line_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.shipping_line_id}
                                    onChange={(e) => setData('shipping_line_id', parseInt(e.target.value))}
                                    required
                                >
                                    {shippingLines.map((line) => (
                                        <option key={line.id} value={line.id}>
                                            {line.code} - {line.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <Label htmlFor="bl_number">Nomor Bill of Lading (B/L)</Label>
                                <Input
                                    id="bl_number"
                                    value={data.bl_number}
                                    onChange={(e) => setData('bl_number', e.target.value)}
                                    placeholder="Contoh: SITC202611001"
                                    required
                                />
                                {errors.bl_number && <p className="text-xs text-red-600 mt-1">{errors.bl_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="bl_date">Tanggal B/L</Label>
                                <Input
                                    id="bl_date"
                                    type="date"
                                    value={data.bl_date}
                                    onChange={(e) => setData('bl_date', e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="pod_id">Pelabuhan Bongkar (POD)</Label>
                                <select
                                    id="pod_id"
                                    className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                    value={data.pod_id}
                                    onChange={(e) => setData('pod_id', parseInt(e.target.value))}
                                    required
                                >
                                    {ports.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} ({p.code})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-4">
                            <div>
                                <Label htmlFor="vessel_name">Nama Kapal (Vessel)</Label>
                                <Input
                                    id="vessel_name"
                                    value={data.vessel_name}
                                    onChange={(e) => setData('vessel_name', e.target.value)}
                                    placeholder="Contoh: SITC SHANGHAI"
                                />
                            </div>
                            <div>
                                <Label htmlFor="voyage_number">Voyage No</Label>
                                <Input
                                    id="voyage_number"
                                    value={data.voyage_number}
                                    onChange={(e) => setData('voyage_number', e.target.value)}
                                    placeholder="Contoh: 2611N"
                                />
                            </div>
                            <div>
                                <Label htmlFor="eta">ETA (Perkiraan Tiba)</Label>
                                <Input
                                    id="eta"
                                    type="date"
                                    value={data.eta}
                                    onChange={(e) => setData('eta', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="ata">ATA (Aktual Tiba)</Label>
                                <Input
                                    id="ata"
                                    type="date"
                                    value={data.ata}
                                    onChange={(e) => setData('ata', e.target.value)}
                                />
                            </div>
                        </div>

                        {/* Klaim FTA */}
                        <div className="border-t pt-3 flex flex-wrap items-center gap-4">
                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                <Checkbox
                                    id="claim_fta"
                                    checked={data.claim_fta}
                                    onCheckedChange={(c) => setData('claim_fta', c === true)}
                                />
                                <span className="font-medium">Klaim Skema Tarif Preferensi (Form FTA)</span>
                            </label>

                            {data.claim_fta && (
                                <div className="flex items-center gap-2">
                                    <Label htmlFor="fta_scheme" className="text-xs">Skema FTA:</Label>
                                    <select
                                        id="fta_scheme"
                                        className="border rounded-md p-1.5 text-xs bg-background"
                                        value={data.fta_scheme}
                                        onChange={(e) => setData('fta_scheme', e.target.value)}
                                    >
                                        {ftaSchemes.map((s) => (
                                            <option key={s} value={s}>{s}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* 2. Daftar Kontainer 1..N (§2.1, Q10) */}
                    <div className="rounded-xl border p-4 space-y-4 bg-card">
                        <div className="flex items-center justify-between border-b pb-2">
                            <div>
                                <h3 className="font-medium text-sm">Daftar Kontainer Fisik (1..N Kontainer)</h3>
                                <p className="text-xs text-muted-foreground">Demurrage/Detention dihitung per Kontainer.</p>
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={addContainer}>
                                + Tambah Kontainer
                            </Button>
                        </div>

                        {errors.containers && <p className="text-xs text-red-600">{errors.containers}</p>}

                        <div className="space-y-3">
                            {data.containers.map((c, idx) => (
                                <div key={idx} className="grid gap-3 sm:grid-cols-6 items-end rounded-lg border p-3 bg-muted/20">
                                    <div className="sm:col-span-2">
                                        <Label className="text-xs">Nomor Kontainer</Label>
                                        <Input
                                            value={c.container_number}
                                            onChange={(e) => updateContainer(idx, 'container_number', e.target.value)}
                                            placeholder="Contoh: MSKU1234567"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-xs">Ukuran</Label>
                                        <select
                                            className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                            value={c.size}
                                            onChange={(e) => updateContainer(idx, 'size', e.target.value)}
                                        >
                                            <option value="20">20'</option>
                                            <option value="40">40'</option>
                                            <option value="45">45'</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label className="text-xs">Tipe</Label>
                                        <select
                                            className="w-full border rounded-md p-2 text-sm bg-background mt-1"
                                            value={c.type}
                                            onChange={(e) => updateContainer(idx, 'type', e.target.value)}
                                        >
                                            <option value="Dry">Dry</option>
                                            <option value="Reefer">Reefer</option>
                                            <option value="High Cube">High Cube</option>
                                            <option value="Open Top">Open Top</option>
                                        </select>
                                    </div>
                                    <div>
                                        <Label className="text-xs">No. Seal</Label>
                                        <Input
                                            value={c.seal_number}
                                            onChange={(e) => updateContainer(idx, 'seal_number', e.target.value)}
                                            placeholder="Seal No."
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="flex-1">
                                            <Label className="text-xs">Berat (KG)</Label>
                                            <Input
                                                type="number"
                                                value={c.gross_weight_kg}
                                                onChange={(e) => updateContainer(idx, 'gross_weight_kg', e.target.value)}
                                                required
                                            />
                                        </div>
                                        {data.containers.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="text-red-600 mt-5"
                                                onClick={() => removeContainer(idx)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button type="submit" disabled={processing} className="px-8">
                            Simpan & Buat Job
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
