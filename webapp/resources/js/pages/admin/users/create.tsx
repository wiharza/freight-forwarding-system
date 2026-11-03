import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type RoleRow = { id: number; name: string; slug: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manajemen User', href: '/admin/users' },
    { title: 'User Baru', href: '/admin/users/create' },
];

export default function UsersCreate({ roles }: { roles: RoleRow[] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        roles: [] as number[],
    });

    const toggleRole = (id: number, checked: boolean) => {
        setData('roles', checked ? [...data.roles, id] : data.roles.filter((roleId) => roleId !== id));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.users.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Baru" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <h2 className="text-lg font-medium">Buat User Baru</h2>

                <form onSubmit={submit} className="max-w-xl space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Nama</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="password">Password</Label>
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
