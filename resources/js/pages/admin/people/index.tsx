import { Head, Link, router } from '@inertiajs/react';
import { Plus, SearchX, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/admin/people';
import type { Paginated, PersonSummary } from '@/types/people';

export default function PeopleIndex({
    people,
    filters,
}: {
    people: Paginated<PersonSummary>;
    filters: { cari: string };
}) {
    const [term, setTerm] = useState(filters.cari);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                index().url,
                { cari: term || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [term]);

    return (
        <>
            <Head title="Orang" />
            <div className="mx-auto grid w-full max-w-5xl gap-6 p-4 md:p-6">
                <PageHeader
                    icon={Users}
                    title="Orang"
                    description="Semua orang yang tercatat: siswa, orang tua, wali, dan pegawai."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Tambah orang
                            </Link>
                        </Button>
                    }
                />

                <SearchInput
                    value={term}
                    onValueChange={setTerm}
                    placeholder="Cari nama atau NIK…"
                    className="max-w-md"
                />

                {people.data.length === 0 ? (
                    <EmptyState
                        icon={filters.cari ? SearchX : Users}
                        title={
                            filters.cari
                                ? 'Tidak ada yang cocok'
                                : 'Belum ada orang tercatat'
                        }
                        description={
                            filters.cari
                                ? 'Coba kata kunci lain, atau tambahkan sebagai orang baru.'
                                : 'Mulai dengan menambahkan orang pertama.'
                        }
                        action={
                            <Button asChild>
                                <Link href={create()}>Tambah orang</Link>
                            </Button>
                        }
                    />
                ) : (
                    <Card className="gap-0 overflow-hidden py-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>NIK</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Jenis kelamin
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Nomor HP
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {people.data.map((person) => (
                                    <TableRow key={person.id}>
                                        <TableCell>
                                            <Link
                                                href={show(person.id)}
                                                className="flex items-center gap-3 font-semibold hover:text-primary"
                                            >
                                                <PersonAvatar
                                                    name={person.name}
                                                    src={person.photo_url}
                                                    size="sm"
                                                />
                                                {person.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {person.nik ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            {person.gender ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {person.phone ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                )}

                {people.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-4"
                        aria-label="Halaman"
                    >
                        <p className="text-sm text-muted-foreground">
                            Halaman {people.current_page} dari{' '}
                            {people.last_page} · {people.total} orang
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                asChild={Boolean(people.prev_page_url)}
                                disabled={!people.prev_page_url}
                            >
                                {people.prev_page_url ? (
                                    <Link href={people.prev_page_url}>
                                        Sebelumnya
                                    </Link>
                                ) : (
                                    'Sebelumnya'
                                )}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                asChild={Boolean(people.next_page_url)}
                                disabled={!people.next_page_url}
                            >
                                {people.next_page_url ? (
                                    <Link href={people.next_page_url}>
                                        Berikutnya
                                    </Link>
                                ) : (
                                    'Berikutnya'
                                )}
                            </Button>
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

PeopleIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Orang', href: index() },
    ],
};
