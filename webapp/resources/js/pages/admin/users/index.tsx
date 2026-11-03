import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type RoleRow = { id: number; name: string };
type UserRow = { id: number; name: string; email: string; roles: RoleRow[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manajemen User', href: '/admin/users' },
];

export default function UsersIndex({ users }: { users: UserRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manajemen User" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium">Daftar User</h2>
                    <Link href={route('admin.users.create')}>
                        <Button>+ User Baru</Button>
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left">
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Peran</th>
                                <th className="px-4 py-3 font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-b last:border-b-0">
                                    <td className="px-4 py-3">{user.name}</td>
                                    <td className="px-4 py-3">{user.email}</td>
                                    <td className="flex flex-wrap gap-1 px-4 py-3">
                                        {user.roles.map((role) => (
                                            <Badge key={role.id} variant="secondary">
                                                {role.name}
                                            </Badge>
                                        ))}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={route('admin.users.edit', user.id)}>
                                            <Button variant="outline" size="sm">
                                                Ubah
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
