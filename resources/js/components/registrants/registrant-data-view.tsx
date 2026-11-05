import { Link } from '@inertiajs/react';
import { ChevronDown, PencilLine, ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { DescriptionList } from '@/components/description-list';
import type { DescriptionItem } from '@/components/description-list';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { show as showPerson } from '@/routes/admin/people';

export type RegistrantDetailData = {
    /** True while the NIK is unconfirmed: nothing of the person on record is sent, only the typed name. */
    restricted: boolean;
    child: {
        name: string;
        person_id?: number;
        nik?: string | null;
        gender_label?: string | null;
        birth?: string | null;
        religion?: string | null;
        nisn?: string | null;
        blood_type?: string | null;
        child_order?: number | null;
        siblings_count?: number | null;
        family_card_number?: string | null;
        address?: string | null;
        photo_url?: string | null;
    };
    parents: {
        person_id: number;
        name: string;
        nik: string | null;
        phone: string | null;
        job: string | null;
        last_education: string | null;
        monthly_income: string | null;
        address: string | null;
        is_alive: boolean;
        relation: string;
        relation_label: string;
        kind_label: string | null;
        is_primary_contact: boolean;
    }[];
};

function CollapsibleCard({
    title,
    badges,
    children,
}: {
    title: string;
    badges?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Collapsible defaultOpen asChild>
            <Card className="gap-0 py-0 @2xl:gap-0 @2xl:py-0">
                <CardHeader className="px-0 @2xl:px-0">
                    <CollapsibleTrigger className="group flex min-h-14 w-full items-center justify-between gap-3 rounded-2xl px-4 text-left outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring @2xl:px-6">
                        <span className="flex flex-wrap items-center gap-2 text-base font-semibold">
                            {title}
                            {badges}
                        </span>
                        <ChevronDown className="size-5 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent className="border-t border-line py-4 @2xl:py-5">
                        {children}
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>
    );
}

function siblings(order?: number | null, count?: number | null): string | null {
    if (order && count) {
        return `Anak ke-${order} dari ${count} bersaudara`;
    }

    if (order) {
        return `Anak ke-${order}`;
    }

    return count ? `${count} bersaudara` : null;
}

/**
 * Everything the data form holds, read-only, so the committee can check it without opening the form.
 */
export function RegistrantDataView({
    data,
    editHref,
    canEdit,
    canViewPerson,
}: {
    data: RegistrantDetailData;
    editHref: string;
    canEdit: boolean;
    canViewPerson: boolean;
}) {
    const { child } = data;

    const childItems: DescriptionItem[] = [
        { label: 'Nama', value: child.name },
        { label: 'NIK', value: child.nik },
        { label: 'Jenis kelamin', value: child.gender_label },
        { label: 'Tempat, tanggal lahir', value: child.birth },
        { label: 'Agama', value: child.religion },
        { label: 'NISN', value: child.nisn },
        { label: 'Golongan darah', value: child.blood_type },
        {
            label: 'Urutan saudara',
            value: siblings(child.child_order, child.siblings_count),
        },
        { label: 'Nomor KK', value: child.family_card_number },
        { label: 'Alamat', value: child.address, wide: true },
    ];

    return (
        <section className="grid gap-4" aria-labelledby="data-lengkap">
            <div className="flex items-start justify-between gap-4">
                <div className="grid gap-1">
                    <h2
                        id="data-lengkap"
                        className="text-base font-semibold @2xl:text-lg"
                    >
                        Data lengkap
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Seluruh data yang diisi orang tua, hanya untuk dibaca.
                    </p>
                </div>
                {canEdit && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={editHref}>
                            <PencilLine />
                            Ubah data
                        </Link>
                    </Button>
                )}
            </div>
            {data.restricted ? (
                <>
                    <Alert variant="warning">
                        <ShieldAlert />
                        <AlertDescription>
                            Data lain disembunyikan selama NIK belum dicocokkan,
                            supaya data anak orang lain tidak terbawa. Yang
                            tampil hanya nama yang diketik orang tua.
                        </AlertDescription>
                    </Alert>
                    <CollapsibleCard title="Data anak">
                        <DescriptionList
                            items={[
                                {
                                    label: 'Nama yang diketik',
                                    value: child.name,
                                },
                            ]}
                        />
                    </CollapsibleCard>
                </>
            ) : (
                <>
                    <CollapsibleCard title="Data anak">
                        <div className="grid gap-6 sm:grid-cols-[auto_1fr]">
                            {child.photo_url && (
                                <img
                                    src={child.photo_url}
                                    alt={`Foto ${child.name}`}
                                    className="h-40 w-30 rounded-xl border object-cover"
                                />
                            )}
                            <DescriptionList items={childItems} />
                        </div>
                    </CollapsibleCard>

                    {data.parents.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada data orang tua atau wali.
                        </p>
                    )}

                    {data.parents.map((parent) => (
                        <CollapsibleCard
                            key={parent.relation}
                            title={`Data ${parent.relation_label.toLowerCase()}`}
                            badges={
                                <>
                                    {parent.kind_label && (
                                        <Badge variant="muted">
                                            {parent.kind_label}
                                        </Badge>
                                    )}
                                    {parent.is_primary_contact && (
                                        <Badge variant="info">
                                            Kontak utama
                                        </Badge>
                                    )}
                                    {!parent.is_alive && (
                                        <Badge variant="muted">
                                            Sudah meninggal
                                        </Badge>
                                    )}
                                </>
                            }
                        >
                            <DescriptionList
                                items={[
                                    {
                                        label: 'Nama',
                                        value: canViewPerson ? (
                                            <Link
                                                href={showPerson(
                                                    parent.person_id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {parent.name}
                                            </Link>
                                        ) : (
                                            parent.name
                                        ),
                                    },
                                    { label: 'NIK', value: parent.nik },
                                    { label: 'Nomor HP', value: parent.phone },
                                    {
                                        label: 'Pekerjaan',
                                        value: parent.job,
                                    },
                                    {
                                        label: 'Penghasilan per bulan',
                                        value: parent.monthly_income,
                                    },
                                    {
                                        label: 'Pendidikan terakhir',
                                        value: parent.last_education,
                                    },
                                    {
                                        label: 'Alamat',
                                        value: parent.address,
                                        wide: true,
                                    },
                                ]}
                            />
                        </CollapsibleCard>
                    ))}
                </>
            )}
        </section>
    );
}
