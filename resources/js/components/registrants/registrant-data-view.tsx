import { Link } from '@inertiajs/react';
import {
    GraduationCap,
    MessageCircle,
    PencilLine,
    Phone,
    ShieldAlert,
    UserRound,
    Users,
} from 'lucide-react';
import { DescriptionList } from '@/components/description-list';
import type { DescriptionItem } from '@/components/description-list';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { whatsappUrl } from '@/lib/format';
import { show as showPerson } from '@/routes/admin/people';

export type RegistrantDetailData = {
    /** True while the NIK is unconfirmed: nothing of the person on record is sent, only the typed name. */
    restricted: boolean;
    admission_type_label?: string;
    education?: {
        level_label: string;
        school_name: string;
        npsn: string | null;
        school_address: string | null;
    }[];
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
 * Everything the child's data form holds, read-only, so the committee can check it without opening the form.
 * While the NIK is unconfirmed only the name the parent typed is shown.
 */
export function ChildDataCard({
    data,
    editHref,
    canEdit,
}: {
    data: RegistrantDetailData;
    editHref: string;
    canEdit: boolean;
}) {
    const { child } = data;

    const items: DescriptionItem[] = data.restricted
        ? [{ label: 'Nama yang diketik', value: child.name, wide: true }]
        : [
              { label: 'NIK', value: child.nik },
              { label: 'Jenis kelamin', value: child.gender_label },
              {
                  label: 'Tempat, tanggal lahir',
                  value: child.birth,
                  wide: true,
              },
              { label: 'Agama', value: child.religion },
              { label: 'Golongan darah', value: child.blood_type },
              { label: 'NISN', value: child.nisn },
              { label: 'Nomor KK', value: child.family_card_number },
              {
                  label: 'Urutan saudara',
                  value: siblings(child.child_order, child.siblings_count),
                  wide: true,
              },
              { label: 'Alamat', value: child.address, wide: true },
          ];

    return (
        <SectionCard
            title="Data anak"
            icon={UserRound}
            compact
            actions={
                canEdit && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={editHref}>
                            <PencilLine />
                            Ubah data
                        </Link>
                    </Button>
                )
            }
            contentClassName="grid gap-3"
        >
            {data.restricted && (
                <Alert variant="warning">
                    <ShieldAlert />
                    <AlertDescription>
                        Data lain disembunyikan selama NIK belum dicocokkan,
                        supaya data anak orang lain tidak terbawa.
                    </AlertDescription>
                </Alert>
            )}
            <DescriptionList compact items={items} />
        </SectionCard>
    );
}

export function EducationCard({ data }: { data: RegistrantDetailData }) {
    if (data.restricted) {
        return null;
    }

    const schools = data.education ?? [];

    return (
        <SectionCard
            title="Riwayat pendidikan"
            icon={GraduationCap}
            compact
            contentClassName="grid gap-3"
        >
            {schools.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada riwayat sekolah yang diisi.
                </p>
            ) : (
                <ul className="grid gap-3 divide-y divide-line">
                    {schools.map((school, index) => (
                        <li key={index} className="pt-3 first:pt-0">
                            <p className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                {school.school_name}
                                <Badge variant="muted">
                                    {school.level_label}
                                </Badge>
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {[
                                    school.npsn && `NPSN ${school.npsn}`,
                                    school.school_address,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || 'Tanpa NPSN dan alamat'}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}

/**
 * Father, mother and guardian in one card, each with the number to call or message.
 */
export function ParentsCard({
    data,
    canViewPerson,
}: {
    data: RegistrantDetailData;
    canViewPerson: boolean;
}) {
    if (data.restricted) {
        return null;
    }

    return (
        <SectionCard
            title="Orang tua dan wali"
            icon={Users}
            compact
            contentClassName="grid gap-4"
        >
            {data.parents.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Belum ada data orang tua atau wali.
                </p>
            )}

            {data.parents.map((parent) => (
                <section
                    key={parent.relation}
                    className="grid gap-3 border-t border-line pt-4 first:border-t-0 first:pt-0"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        {canViewPerson ? (
                            <Link
                                href={showPerson(parent.person_id)}
                                className="text-sm font-semibold hover:underline"
                            >
                                {parent.name}
                            </Link>
                        ) : (
                            <span className="text-sm font-semibold">
                                {parent.name}
                            </span>
                        )}
                        <Badge variant="muted">{parent.relation_label}</Badge>
                        {parent.kind_label && (
                            <Badge variant="muted">{parent.kind_label}</Badge>
                        )}
                        {parent.is_primary_contact && (
                            <Badge variant="info">Kontak utama</Badge>
                        )}
                        {!parent.is_alive && (
                            <Badge variant="muted">Sudah meninggal</Badge>
                        )}
                    </div>
                    <DescriptionList
                        compact
                        items={[
                            { label: 'NIK', value: parent.nik },
                            {
                                label: 'Nomor HP',
                                value: parent.phone ? (
                                    <span className="flex items-center gap-1.5">
                                        <span className="tabular-nums">
                                            {parent.phone}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            asChild
                                        >
                                            <a
                                                href={`tel:${parent.phone}`}
                                                aria-label={`Telepon ${parent.name}`}
                                            >
                                                <Phone />
                                            </a>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8"
                                            asChild
                                        >
                                            <a
                                                href={whatsappUrl(parent.phone)}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label={`WhatsApp ${parent.name}`}
                                            >
                                                <MessageCircle />
                                            </a>
                                        </Button>
                                    </span>
                                ) : null,
                            },
                            { label: 'Pekerjaan', value: parent.job },
                            {
                                label: 'Penghasilan per bulan',
                                value: parent.monthly_income,
                            },
                            {
                                label: 'Pendidikan terakhir',
                                value: parent.last_education,
                                wide: true,
                            },
                            {
                                label: 'Alamat',
                                value: parent.address,
                                wide: true,
                            },
                        ]}
                    />
                </section>
            ))}
        </SectionCard>
    );
}
