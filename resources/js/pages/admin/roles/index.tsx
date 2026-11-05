import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Copy, Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, destroy, duplicate, edit, index } from '@/routes/admin/roles';
import type { RoleRow } from '@/types/access';

const noSorting: SortingState = [];

export default function RolesIndex({ roles }: { roles: RoleRow[] }) {
    const [deleting, setDeleting] = useState<RoleRow | null>(null);
    const [busy, setBusy] = useState(false);

    function copy(role: RoleRow) {
        router.post(
            duplicate(String(role.id)).url,
            {},
            {
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'Peran belum bisa digandakan.',
                    ),
            },
        );
    }

    function remove(role: RoleRow) {
        router.delete(destroy(String(role.id)).url, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onSuccess: () => setDeleting(null),
            onError: (errors) => {
                setDeleting(null);
                toast.error(
                    Object.values(errors)[0] ?? 'Peran belum bisa dihapus.',
                );
            },
            onFinish: () => setBusy(false),
        });
    }

    const columns = useMemo<ColumnDef<RoleRow>[]>(
        () => [
            {
                id: 'name',
                header: 'Peran',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold">
                            {row.original.name}
                        </span>
                        {row.original.is_locked && (
                            <Badge variant="muted">
                                <Lock />
                                Terkunci
                            </Badge>
                        )}
                        {row.original.is_built_in && (
                            <Badge variant="info">Bawaan</Badge>
                        )}
                    </span>
                ),
            },
            {
                id: 'users',
                header: 'Akun',
                enableSorting: false,
                meta: { className: 'tabular-nums' },
                cell: ({ row }) => row.original.users_count,
            },
            {
                id: 'abilities',
                header: 'Izin',
                enableSorting: false,
                meta: { className: 'tabular-nums' },
                cell: ({ row }) =>
                    row.original.name === 'super-admin'
                        ? `${row.original.abilities_count} (semua)`
                        : row.original.abilities_count,
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                enableSorting: false,
                meta: { className: 'w-40 text-right' },
                cell: ({ row }) => {
                    const role = row.original;
                    const deletable =
                        !role.is_locked &&
                        !role.is_built_in &&
                        role.users_count === 0;

                    return (
                        <span className="flex justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                className="max-md:size-10"
                            >
                                <Link
                                    href={edit(String(role.id))}
                                    aria-label={`${role.is_locked ? 'Lihat' : 'Ubah'} peran ${role.name}`}
                                >
                                    <Pencil />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="max-md:size-10"
                                aria-label={`Gandakan peran ${role.name}`}
                                onClick={() => copy(role)}
                            >
                                <Copy />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="max-md:size-10"
                                aria-label={`Hapus peran ${role.name}`}
                                title={
                                    deletable
                                        ? undefined
                                        : 'Peran bawaan atau yang masih dipakai akun tidak bisa dihapus'
                                }
                                disabled={!deletable}
                                onClick={() => setDeleting(role)}
                            >
                                <Trash2 />
                            </Button>
                        </span>
                    );
                },
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Peran" />
            <div className="grid gap-6">
                <PageHeader
                    icon={ShieldCheck}
                    title="Peran"
                    description="Peran mengelompokkan izin. Buat peran sendiri untuk kebutuhan sekolahmu, lalu pasang ke staf."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Buat peran
                            </Link>
                        </Button>
                    }
                />

                <DataTable
                    columns={columns}
                    data={roles}
                    getRowId={(row) => String(row.id)}
                    sorting={noSorting}
                    onSortingChange={() => {}}
                />
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && !busy && setDeleting(null)}
                title={`Hapus peran ${deleting?.name ?? ''}?`}
                description="Peran ini hilang dari daftar. Catatan yang dibagikan ke peran ini tidak lagi terlihat oleh siapa pun lewat peran itu."
                confirmLabel="Hapus peran"
                tone="danger"
                processing={busy}
                onConfirm={() => deleting && remove(deleting)}
            />
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Peran', href: index() },
    ],
};
