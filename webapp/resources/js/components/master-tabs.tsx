import { Link, usePage } from '@inertiajs/react';

const tabs = [
    { title: 'Importir', url: '/admin/master/importirs' },
    { title: 'Shipping Line', url: '/admin/master/shipping-lines' },
    { title: 'Tarif Line', url: '/admin/master/line-tariffs' },
    { title: 'Tarif MFN', url: '/admin/master/mfn-tariffs' },
    { title: 'Preferensi FTA', url: '/admin/master/fta-tariffs' },
    { title: 'PPh 22 (PMK 51)', url: '/admin/master/pph22-rules' },
    { title: 'Barang Mewah', url: '/admin/master/luxury-goods' },
    { title: 'Kurs Pajak (KMK)', url: '/admin/master/tax-rates' },
    { title: 'Vendor Trucking', url: '/admin/master/vendors' },
    { title: 'Lokasi', url: '/admin/master/locations' },
    { title: 'Jenis Kontainer', url: '/admin/master/container-types' },
    { title: 'Mata Uang', url: '/admin/master/currencies' },
];

export function MasterTabs() {
    const { url } = usePage();

    return (
        <nav className="flex flex-wrap gap-2 border-b pb-2 text-sm">
            {tabs.map((tab) => {
                const active = url.startsWith(tab.url);
                return (
                    <Link
                        key={tab.url}
                        href={tab.url}
                        className={
                            active
                                ? 'rounded-md bg-muted px-3 py-1.5 font-medium'
                                : 'rounded-md px-3 py-1.5 text-muted-foreground hover:bg-muted/60'
                        }
                    >
                        {tab.title}
                    </Link>
                );
            })}
        </nav>
    );
}
