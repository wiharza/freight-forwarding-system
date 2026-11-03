import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type RoleRow = { id: number; name: string; slug: string };
type UserRow = { id: number; name: string; email: string; roleIds: number[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manajemen User', href: '/admin/users' },
    { title: 'Ubah User', href: '' },
];

export default function UsersEdit({ user, roles }: { user: UserRow; roles: RoleRow[] }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        password: '',
        roles: user.roleIds as number[],
    });

    const toggleRole = (id: number, checked: boolean) => {
        setData('roles', checked ? [...data.roles, id] : data.roles.filter((roleId) => roleId !== id));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('admin.users.update', user.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ubah User" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <h2 className="text-lg font-medium">Ubah User: {user.email}</h2>

                <form onSubmit={submit} className="max-w-xl space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Nama</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password">Password baru (kosongkan bila tidak diubah)</Label>
                        <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                        {errors.password && <p className="text-sm text-red-600">{errors.password}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label>Peran (boleh lebih dari satu)</Label>
                        <div className="grid gap-2 rounded-xl border p-4 md:grid-cols-2">
                            {roles.map((role) => (
                                <label key={role.id} className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        id={`role-${role.id}`}
                                        checked={data.roles.includes(role.id)}
                                        onCheckedChange={(checked) => toggleRole(role.id, checked === true)}
                                    />
                                    {role.name}
                                </label>
                            ))}
                        </div>
                        {errors.roles && <p className="text-sm text-red-600">{errors.roles}</p>}
                    </div>

                    <Button type="submit" disabled={processing}>
                        Simpan
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
