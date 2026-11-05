import type {
    SetupExisting,
    SetupFormData,
} from '@/components/setup/setup-form';
import { isHexColor } from '@/lib/accent';

/** A flat summary of the key choices, shown right above the final button. */
export function SetupReview({
    data,
    existing,
    yearOpen,
    waveOpen,
}: {
    data: SetupFormData;
    existing: SetupExisting;
    yearOpen: boolean;
    waveOpen: boolean;
}) {
    const newOrganizations = data.organizations.filter(
        (row) => row.name.trim() !== '',
    ).length;
    const organizationParts = [
        newOrganizations > 0 ? `${newOrganizations} baru` : null,
        existing.organizations.length > 0
            ? `${existing.organizations.length} sudah ada`
            : null,
    ].filter(Boolean);

    const yearParts = [
        yearOpen && data.year.name.trim() !== '' ? data.year.name : null,
        existing.years.length > 0
            ? `${existing.years.join(', ')} (sudah ada)`
            : null,
    ].filter(Boolean);

    const items: { label: string; value: React.ReactNode }[] = [
        { label: 'Nama aplikasi', value: data.brand.name || '—' },
        {
            label: 'Warna utama',
            value: (
                <span className="inline-flex items-center gap-2">
                    <span
                        aria-hidden="true"
                        className="size-4 rounded-full border border-line"
                        style={
                            isHexColor(data.brand.accent_color)
                                ? { backgroundColor: data.brand.accent_color }
                                : undefined
                        }
                    />
                    {data.brand.accent_color}
                </span>
            ),
        },
        { label: 'Logo', value: data.logo?.name ?? 'Belum ada' },
        {
            label: 'Super admin',
            value: (
                <>
                    {data.admin.name || '—'}
                    <span className="block text-muted-foreground">
                        {data.admin.email}
                    </span>
                </>
            ),
        },
        {
            label: 'Lembaga',
            value:
                organizationParts.length > 0
                    ? organizationParts.join(', ')
                    : 'Belum ada',
        },
        {
            label: 'Tahun ajaran',
            value: yearParts.length > 0 ? yearParts.join(', ') : 'Belum ada',
        },
        {
            label: 'Gelombang pertama',
            value:
                waveOpen && data.wave.name.trim() !== ''
                    ? data.wave.name
                    : 'Tidak ditambahkan',
        },
    ];

    return (
        <section aria-labelledby="setup-review" className="grid gap-4">
            <h2 id="setup-review" className="text-base font-semibold">
                Periksa dulu sebelum selesai
            </h2>
            <dl className="grid gap-x-8 gap-y-4 border-t border-line pt-4 sm:grid-cols-2">
                {items.map((item) => (
                    <div key={item.label} className="grid gap-1">
                        <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {item.label}
                        </dt>
                        <dd className="text-[15px] font-medium break-words">
                            {item.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
