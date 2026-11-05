import { Head, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, Pencil, Plus, School, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { OrganizationFormDialog } from '@/components/academic/organization-form-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { dashboard } from '@/routes';
import { destroy, index, move, update } from '@/routes/admin/organizations';
import type { CategoryOption, OrganizationRow } from '@/types/academic';

const noSorting: SortingState = [];

type FormTarget = { organization?: OrganizationRow };

export default function OrganizationsIndex({
    organizations,
    categories,
}: {
    organizations: OrganizationRow[];
    categories: CategoryOption[];
}) {
    const [form, setForm] = useState<FormTarget | null>(null);
    const [deleting, setDeleting] = useState<OrganizationRow | null>(null);
    const [busy, setBusy] = useState<string | null>(null);

    function run(
        key: string,
        send: (options: Parameters<typeof router.post>[2]) => void,
        failure: string,
        done?: () => void,
    ) {
        send({
            preserveScroll: true,
            onStart: () => setBusy(key),
            onFinish: () => setBusy(null),
            onSuccess: done,
            onError: (errors) => {
                done?.();
                toast.error(Object.values(errors)[0] ?? failure);
            },
            onHttpException: () => {
                toast.error(failure);
            },
            onNetworkError: () => {
                toast.error(failure);
            },
        });
    }

    function toggle(organization: OrganizationRow, active: boolean) {
        run(
            `toggle-${organization.id}`,
            (options) =>
                router.put(
                    update(organization.id).url,
                    {
                        name: organization.name,
                        code: organization.code,
                        category: organization.category ?? '',
                        is_active: active,
                    },
                    options,
                ),
            `Status ${organization.name} belum bisa diubah. Coba lagi ya.`,
        );
    }

    function reorder(organization: OrganizationRow, direction: 'up' | 'down') {
        run(
            `move-${organization.id}`,
            (options) =>
                router.patch(move(organization.id).url, { direction }, options),
            'Urutan belum bisa diubah. Coba lagi ya.',
        );
    }

    function remove(organization: OrganizationRow) {
        run(
            `delete-${organization.id}`,
            (options) => router.delete(destroy(organization.id).url, options),
            'Lembaga belum bisa dihapus.',
            () => setDeleting(null),
        );
    }

    const columns = useMemo<ColumnDef<OrganizationRow>[]>(
        () => [
            {
                id: 'order',
                header: () => <span className="sr-only">Urutan</span>,
                enableSorting: false,
                meta: { className: 'w-24' },
                cell: ({ row, table }) => {
                    const position = table
                        .getRowModel()
                        .rows.findIndex((item) => item.id === row.id);
                    const last = table.getRowModel().rows.length - 1;

                    return (
                        <span className="flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                className="max-md:size-10"
                                disabled={position === 0 || busy !== null}
                                aria-label={`Naikkan ${row.original.name}`}
                                onClick={() => reorder(row.original, 'up')}
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                className="max-md:size-10"
                                disabled={position === last || busy !== null}
                                aria-label={`Turunkan ${row.original.name}`}
                                onClick={() => reorder(row.original, 'down')}
                            >
                                <ArrowDown />
                            </Button>
                        </span>
                    );
                },
            },
            {
                id: 'name',
                header: 'Lembaga',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="grid">
                        <span className="font-semibold">
                            {row.original.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.code}
                            {row.original.category_label
                                ? ` · ${row.original.category_label}`
                                : ''}
                        </span>
                    </span>
                ),
            },
            {
                id: 'usage',
                header: 'Dipakai',
                enableSorting: false,
                meta: {
                    className: 'hidden text-sm tabular-nums md:table-cell',
                },
                cell: ({ row }) => (
                    <span className="grid gap-0.5 text-muted-foreground">
                        <span>{row.original.rulesets_count} aturan</span>
                        <span>{row.original.registrants_count} pendaftar</span>
                        <span>{row.original.students_count} siswa</span>
                    </span>
                ),
            },
            {
                id: 'active',
                header: 'Aktif',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="flex items-center gap-2">
                        <Switch
                            checked={row.original.is_active}
                            disabled={busy === `toggle-${row.original.id}`}
                            onCheckedChange={(checked) =>
                                toggle(row.original, checked)
                            }
                            aria-label={`Aktifkan ${row.original.name}`}
                        />
                        {!row.original.is_active && (
                            <Badge variant="muted">Nonaktif</Badge>
                        )}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                enableSorting: false,
                meta: { className: 'text-right' },
                cell: ({ row }) => (
                    <span className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            className="max-md:h-10"
                            onClick={() =>
                                setForm({ organization: row.original })
                            }
                        >
                            <Pencil />
                            Ubah
                        </Button>
                        <Button
                            variant="outline"
                            size="icon-sm"
                            className="max-md:size-10"
                            disabled={row.original.in_use}
                            title={
                                row.original.in_use
                                    ? 'Sudah dipakai. Nonaktifkan saja.'
                                    : undefined
                            }
                            aria-label={`Hapus ${row.original.name}`}
                            onClick={() => setDeleting(row.original)}
                        >
                            <Trash2 />
                        </Button>
                    </span>
                ),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [busy],
    );

    const newButton = (
        <Button onClick={() => setForm({})}>
            <Plus />
            Lembaga baru
        </Button>
    );

    return (
        <>
            <Head title="Lembaga" />
            <div className="grid gap-6">
                <PageHeader
                    icon={School}
                    title="Lembaga"
                    description="Sekolah dan pesantren di bawah yayasan. Lembaga yang sudah dipakai tidak dihapus, cukup dinonaktifkan."
                    actions={newButton}
                />

                {organizations.length === 0 ? (
                    <EmptyState
                        icon={School}
                        title="Belum ada lembaga"
                        description="Tambahkan lembaga pertama supaya aturan dan pendaftaran bisa dibuat."
                        action={newButton}
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={organizations}
                        getRowId={(row) => String(row.id)}
                        sorting={noSorting}
                        onSortingChange={() => {}}
                    />
                )}
            </div>

            {form && (
                <OrganizationFormDialog
                    organization={form.organization}
                    categories={categories}
                    onClose={() => setForm(null)}
                />
            )}
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Hapus lembaga ${deleting?.name ?? ''}?`}
                description="Lembaga ini belum dipakai di mana pun. Tindakan ini tidak bisa dibatalkan."
                confirmLabel="Ya, hapus"
                tone="danger"
                processing={busy?.startsWith('delete') ?? false}
                onConfirm={() => deleting && remove(deleting)}
            />
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Lembaga', href: index() },
    ],
};
