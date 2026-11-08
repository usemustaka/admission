import { Link } from '@inertiajs/react';
import {
    BadgeCheck,
    BriefcaseBusiness,
    Check,
    ClipboardList,
    Circle,
    GraduationCap,
    IdCard,
    MailWarning,
    Receipt,
    ShieldCheck,
    UserRound,
    Wallet,
} from 'lucide-react';
import { DescriptionList } from '@/components/description-list';
import { PersonAvatar } from '@/components/person-avatar';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { formatDate, formatRupiah } from '@/lib/format';
import { show as showPayment } from '@/routes/admin/payments';
import { show as showPerson } from '@/routes/admin/people';
import { show as showRegistrant } from '@/routes/admin/registrants';
import { show as showUser } from '@/routes/admin/users';
import { receipt } from '@/routes/payments';
import type {
    PersonDetail,
    PersonFinance,
    PersonProfileData,
    PersonRegistration,
} from '@/types/people';

function age(birthDate: string | null): string | null {
    if (!birthDate) {
        return null;
    }

    const born = new Date(`${birthDate}T00:00:00`);
    const today = new Date();
    let years = today.getFullYear() - born.getFullYear();

    if (
        today.getMonth() < born.getMonth() ||
        (today.getMonth() === born.getMonth() &&
            today.getDate() < born.getDate())
    ) {
        years -= 1;
    }

    return years >= 0 ? `${years} tahun` : null;
}

export function birthLine(person: PersonDetail): string | null {
    const date = person.birth_date
        ? `${formatDate(person.birth_date, { day: 'numeric', month: 'long', year: 'numeric' })} (${age(person.birth_date)})`
        : null;

    return [person.birth_place, date].filter(Boolean).join(', ') || null;
}

/**
 * Every field of the profile in one card, so nobody has to open the edit form to read a number.
 */
export function ProfileCard({
    person,
    profile,
}: {
    person: PersonDetail;
    profile: PersonProfileData;
}) {
    const siblings =
        profile.child_order && profile.siblings_count
            ? `Anak ke-${profile.child_order} dari ${profile.siblings_count} bersaudara`
            : profile.child_order
              ? `Anak ke-${profile.child_order}`
              : profile.siblings_count
                ? `${profile.siblings_count} bersaudara`
                : null;

    return (
        <SectionCard
            title="Data diri"
            icon={UserRound}
            compact
            contentClassName="grid gap-3"
        >
            <DescriptionList
                compact
                items={[
                    { label: 'NISN', value: profile.nisn },
                    { label: 'Nomor KK', value: person.family_card_number },
                    { label: 'Agama', value: profile.religion },
                    { label: 'Golongan darah', value: profile.blood_type },
                    {
                        label: 'Urutan saudara',
                        value: siblings,
                        wide: true,
                    },
                    { label: 'Pekerjaan', value: profile.job },
                    {
                        label: 'Penghasilan per bulan',
                        value: profile.monthly_income,
                    },
                    {
                        label: 'Pendidikan terakhir',
                        value: profile.last_education,
                    },
                    {
                        label: 'Status',
                        value: person.is_alive
                            ? 'Masih hidup'
                            : 'Sudah meninggal',
                    },
                    { label: 'Dicatat', value: profile.created_at },
                    { label: 'Terakhir diubah', value: profile.updated_at },
                ]}
            />
        </SectionCard>
    );
}

export function ChecklistCard({
    items,
}: {
    items: { label: string; done: boolean }[];
}) {
    const completed = items.filter((item) => item.done).length;
    const allDone = completed === items.length;

    return (
        <SectionCard
            title="Kelengkapan data"
            description={
                allDone
                    ? 'Semua sudah lengkap'
                    : `${completed} dari ${items.length} sudah lengkap`
            }
            compact
            contentClassName="grid gap-3"
        >
            <Progress
                value={completed}
                max={items.length}
                aria-label="Kelengkapan data"
            />
            {!allDone && (
                <ul className="grid gap-2 text-sm">
                    {items.map((item) => (
                        <li
                            key={item.label}
                            className="flex items-center gap-2.5"
                        >
                            {item.done ? (
                                <Check className="size-4 text-emerald-600" />
                            ) : (
                                <Circle className="size-4 text-amber-600" />
                            )}
                            <span
                                className={
                                    item.done
                                        ? 'text-muted-foreground'
                                        : 'font-medium'
                                }
                            >
                                {item.label}
                                <span className="sr-only">
                                    {item.done ? ', sudah' : ', belum'}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}

export function SchoolsCard({ profile }: { profile: PersonProfileData }) {
    return (
        <SectionCard title="Riwayat pendidikan" icon={GraduationCap} compact>
            {profile.education.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada riwayat sekolah yang diisi.
                </p>
            ) : (
                <ul className="grid gap-3 divide-y divide-line">
                    {profile.education.map((school, index) => (
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
 * Where the person studies or works in the foundation: student records with their number and year, and positions.
 */
export function RolesCard({
    profile,
    canViewRegistrations,
}: {
    profile: PersonProfileData;
    canViewRegistrations: boolean;
}) {
    if (profile.students.length === 0 && profile.employees.length === 0) {
        return null;
    }

    return (
        <SectionCard
            title="Siswa dan pegawai"
            icon={BriefcaseBusiness}
            compact
            contentClassName="grid gap-3"
        >
            {profile.students.map((student) => (
                <p key={student.id} className="text-sm">
                    <span className="font-semibold">
                        Siswa {student.organization}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        · NIS {student.nis ?? '—'} ·{' '}
                        {student.year ?? 'tanpa tahun ajaran'}
                    </span>
                    {canViewRegistrations && student.registrant_id && (
                        <>
                            {' '}
                            <Link
                                href={showRegistrant(student.registrant_id)}
                                className="font-semibold underline underline-offset-4"
                            >
                                Lihat pendaftaran
                            </Link>
                        </>
                    )}
                </p>
            ))}
            {profile.employees.map((employee, index) => (
                <p key={index} className="text-sm">
                    <span className="font-semibold">
                        {employee.position ?? 'Pegawai'}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        · {employee.organization}
                    </span>
                </p>
            ))}
        </SectionCard>
    );
}

export function AccountCard({
    profile,
    canViewAccount,
}: {
    profile: PersonProfileData;
    canViewAccount: boolean;
}) {
    const account = profile.account;

    return (
        <SectionCard
            title="Akun masuk"
            icon={IdCard}
            compact
            contentClassName="grid gap-3"
            actions={
                canViewAccount &&
                account && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={showUser(account.id)}>Kelola akun</Link>
                    </Button>
                )
            }
        >
            {account === null ? (
                <p className="text-sm text-muted-foreground">
                    Orang ini belum punya akun untuk masuk ke aplikasi.
                </p>
            ) : (
                <div className="grid gap-2 text-sm">
                    <p className="font-medium break-all">{account.email}</p>
                    <div className="flex flex-wrap gap-1.5">
                        {account.verified ? (
                            <Badge variant="success">
                                <BadgeCheck />
                                Email terverifikasi
                            </Badge>
                        ) : (
                            <Badge variant="warning">
                                <MailWarning />
                                Email belum diverifikasi
                            </Badge>
                        )}
                        {!account.is_active && (
                            <Badge variant="danger">Dinonaktifkan</Badge>
                        )}
                        {account.two_factor && (
                            <Badge variant="info">
                                <ShieldCheck />
                                Dua langkah aktif
                            </Badge>
                        )}
                        {account.roles.map((role) => (
                            <Badge key={role} variant="muted">
                                {role}
                            </Badge>
                        ))}
                    </div>
                    {account.created_at && (
                        <p className="text-muted-foreground">
                            Akun dibuat {account.created_at}
                        </p>
                    )}
                </div>
            )}
        </SectionCard>
    );
}

const paymentLabel: Record<string, string> = {
    none: 'Tanpa tagihan',
    unpaid: 'Belum dibayar',
    partial: 'Dibayar sebagian',
    paid: 'Lunas',
};

/**
 * Own and children's registrations with where each stands: status, money, files, exam result and scholarship hints.
 */
export function RegistrationsCard({
    registrations,
}: {
    registrations: PersonRegistration[];
}) {
    return (
        <SectionCard
            title="Pendaftaran"
            icon={ClipboardList}
            compact
            contentClassName="grid gap-4"
        >
            {registrations.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada pendaftaran.
                </p>
            ) : (
                <ul className="grid gap-4 divide-y divide-line">
                    {registrations.map((registration) => (
                        <li
                            key={registration.id}
                            className="grid gap-2 pt-4 first:pt-0"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <Link
                                    href={showRegistrant(registration.id)}
                                    className="grid min-w-0 leading-tight hover:underline"
                                >
                                    <span className="truncate text-sm font-semibold">
                                        {registration.is_self
                                            ? registration.organization
                                            : `${registration.child_name} · ${registration.organization}`}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {registration.wave}
                                    </span>
                                </Link>
                                <StatusBadge
                                    status={registration.status}
                                    label={registration.status_label}
                                />
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {registration.admission_type && (
                                    <Badge variant="muted">
                                        {registration.admission_type}
                                    </Badge>
                                )}
                                {registration.payment && (
                                    <Badge
                                        variant={
                                            registration.payment === 'paid'
                                                ? 'success'
                                                : registration.payment ===
                                                    'none'
                                                  ? 'muted'
                                                  : 'warning'
                                        }
                                    >
                                        {paymentLabel[registration.payment]}
                                        {registration.outstanding
                                            ? ` · sisa ${formatRupiah(registration.outstanding)}`
                                            : ''}
                                    </Badge>
                                )}
                                {registration.exam && (
                                    <Badge
                                        variant={
                                            registration.exam === 'Tuntas'
                                                ? 'success'
                                                : 'muted'
                                        }
                                    >
                                        {registration.exam}
                                    </Badge>
                                )}
                                {(registration.documents_missing?.length ?? 0) >
                                0 ? (
                                    <Badge variant="warning">
                                        Berkas kurang{' '}
                                        {registration.documents_missing?.length}
                                    </Badge>
                                ) : (
                                    (registration.documents_uploaded ?? 0) >
                                        0 && (
                                        <Badge variant="muted">
                                            {registration.documents_uploaded}{' '}
                                            berkas
                                        </Badge>
                                    )
                                )}
                                {registration.scholarship?.map((kind) => (
                                    <Badge key={kind} variant="info">
                                        Potensi beasiswa {kind.toLowerCase()}
                                    </Badge>
                                ))}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}

export function FinanceCard({
    finance,
    canViewPayments,
}: {
    finance: PersonFinance;
    canViewPayments: boolean;
}) {
    return (
        <SectionCard
            title="Pembayaran"
            icon={Wallet}
            compact
            contentClassName="grid gap-3"
        >
            <p className="text-sm">
                {finance.owed > 0 ? (
                    <>
                        Masih harus dibayar{' '}
                        <span className="font-semibold tabular-nums">
                            {formatRupiah(finance.owed)}
                        </span>{' '}
                        untuk semua pendaftaran terkait.
                    </>
                ) : (
                    <span className="text-muted-foreground">
                        Tidak ada tagihan yang menunggu.
                    </span>
                )}
            </p>
            {finance.payments.length > 0 && (
                <ul className="grid gap-3 border-t border-line pt-3">
                    {finance.payments.map((payment) => (
                        <li key={payment.id} className="grid gap-0.5 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="flex items-center gap-2">
                                    <span className="font-semibold tabular-nums">
                                        {formatRupiah(payment.amount)}
                                    </span>
                                    <StatusBadge
                                        status={payment.status}
                                        label={payment.status_label}
                                    />
                                </span>
                                <span className="flex items-center gap-1">
                                    {payment.receipt && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            asChild
                                        >
                                            <a
                                                href={receipt(payment.id).url}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label="Cetak kwitansi"
                                                title="Kwitansi"
                                            >
                                                <Receipt />
                                            </a>
                                        </Button>
                                    )}
                                    {canViewPayments && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={showPayment(payment.id)}
                                            >
                                                Detail
                                            </Link>
                                        </Button>
                                    )}
                                </span>
                            </div>
                            <p className="text-muted-foreground">
                                {formatDate(payment.paid_on)} · {payment.method}
                                {payment.for.length > 0 &&
                                    ` · ${payment.for.join(', ')}`}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </SectionCard>
    );
}

export function SiblingsCard({ profile }: { profile: PersonProfileData }) {
    if (profile.siblings.length === 0) {
        return null;
    }

    return (
        <SectionCard title="Saudara" compact>
            <ul className="grid gap-1">
                {profile.siblings.map((sibling) => (
                    <li key={sibling.id}>
                        <Link
                            href={showPerson(sibling.id)}
                            className="-mx-2 flex min-h-12 items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-none"
                        >
                            <PersonAvatar
                                name={sibling.name}
                                src={sibling.photo_url}
                                size="sm"
                            />
                            <span className="min-w-0 flex-1 truncate text-sm font-semibold">
                                {sibling.name}
                            </span>
                            <Badge variant="muted">{sibling.relation}</Badge>
                        </Link>
                    </li>
                ))}
            </ul>
        </SectionCard>
    );
}
