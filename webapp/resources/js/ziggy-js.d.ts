// Deklarasi tipe minimal untuk 'ziggy-js' (route helper dari vendor/tightenco/ziggy).
// Dipakai tsc saat folder vendor/ belum ter-install (mis. VM ini); di runtime
// Vite/Laravel tetap memakai implementasi asli dari vendor.
declare module 'ziggy-js' {
    export function route(name: string, params?: string | number | Record<string, unknown> | Array<string | number>, absolute?: boolean): string;
}
