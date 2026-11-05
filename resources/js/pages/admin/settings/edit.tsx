import { Head, useForm, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { PhotoUpload } from '@/components/photo-upload';
import { BrandPreview } from '@/components/settings/brand-preview';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { isHexColor } from '@/lib/accent';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit, update } from '@/routes/admin/settings';

/** Nested by the dots in the setting keys, for example `{ brand: { name } }`. */
type Values = { [key: string]: string | boolean | null | Values };

type SettingsFormData = Record<
    string,
    string | boolean | File | null | Record<string, string | boolean>
>;

type Props = {
    group: string;
    groups: { value: string; label: string; description: string }[];
    values: Values;
    files: { logo_url: string | null; favicon_url: string | null };
    defaults: Record<string, string>;
};

const swatches = [
    { name: 'Lime', value: '#a3e635' },
    { name: 'Hijau', value: '#34d399' },
    { name: 'Teal', value: '#2dd4bf' },
    { name: 'Langit', value: '#38bdf8' },
    { name: 'Biru', value: '#60a5fa' },
    { name: 'Ungu', value: '#a78bfa' },
    { name: 'Merah muda', value: '#f472b6' },
    { name: 'Merah', value: '#fb7185' },
    { name: 'Jingga', value: '#fb923c' },
    { name: 'Kuning', value: '#fbbf24' },
];

export default function SettingsEdit({
    group,
    groups,
    values,
    files,
    defaults,
}: Props) {
    const current = groups.find((item) => item.value === group) ?? groups[0];

    const form = useForm<SettingsFormData>({
        ...Object.fromEntries(
            Object.entries(values).map(([namespace, fields]) => [
                namespace,
                Object.fromEntries(
                    Object.entries(fields as Values).map(([key, value]) => [
                        key,
                        typeof value === 'boolean' ? value : (value ?? ''),
                    ]),
                ),
            ]),
        ),
        logo: null as File | null,
        remove_logo: false,
        favicon: null as File | null,
        remove_favicon: false,
    });
    const errors = form.errors as Record<string, string>;
    const read = (key: string): unknown =>
        key
            .split('.')
            .reduce<unknown>(
                (node, part) =>
                    node !== null && typeof node === 'object'
                        ? (node as Record<string, unknown>)[part]
                        : undefined,
                form.data,
            );
    const text = (key: string) => {
        const value = read(key);

        return typeof value === 'string' ? value : '';
    };
    const set = (key: string, value: string | boolean) =>
        form.setData(key as never, value as never);

    function submit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) =>
            group === 'identitas'
                ? data
                : Object.fromEntries(
                      Object.entries(data).filter(
                          ([key]) =>
                              ![
                                  'logo',
                                  'remove_logo',
                                  'favicon',
                                  'remove_favicon',
                              ].includes(key),
                      ),
                  ),
        );
        form.post(update({ group }).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                form.setData((data) => ({
                    ...data,
                    logo: null,
                    remove_logo: false,
                    favicon: null,
                    remove_favicon: false,
                })),
        });
    }

    function field(
        key: string,
        label: string,
        options: {
            hint?: string;
            placeholder?: string;
            required?: boolean;
            type?: string;
            multiline?: boolean;
        } = {},
    ) {
        return (
            <FormField
                label={label}
                hint={options.hint}
                required={options.required}
                error={errors[key]}
            >
                {(control) =>
                    options.multiline ? (
                        <Textarea
                            {...control}
                            value={text(key)}
                            placeholder={options.placeholder}
                            onChange={(event) => set(key, event.target.value)}
                        />
                    ) : (
                        <Input
                            {...control}
                            type={options.type ?? 'text'}
                            value={text(key)}
                            placeholder={options.placeholder}
                            onChange={(event) => set(key, event.target.value)}
                        />
                    )
                }
            </FormField>
        );
    }

    const logoShown = form.data.remove_logo ? null : files.logo_url;
    const savedAccent = usePage().props.brand.accent;
    const accent =
        group === 'tampilan'
            ? text('brand.accent_color') || defaults['brand.accent_color']
            : savedAccent;
    const logoFile = form.data.logo as File | null;
    const [logoObjectUrl, setLogoObjectUrl] = useState<string | null>(null);
    const previewLogo = logoObjectUrl ?? logoShown;

    useEffect(() => {
        if (!logoFile) {
            setLogoObjectUrl(null);

            return;
        }

        const url = URL.createObjectURL(logoFile);
        setLogoObjectUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [logoFile]);

    return (
        <>
            <Head title={`Pengaturan ${current.label}`} />
            <div className="grid gap-6">
                <PageHeader
                    title="Pengaturan aplikasi"
                    description="Sesuaikan aplikasi dengan identitas sekolah atau yayasan Anda."
                />

                <form
                    onSubmit={submit}
                    className="grid items-start gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem]"
                >
                    <SectionCard
                        title={current.label}
                        description={current.description}
                        contentClassName="grid gap-5"
                    >
                        {group === 'identitas' && (
                            <>
                                {field('brand.name', 'Nama aplikasi', {
                                    required: true,
                                    placeholder: defaults['brand.name'],
                                    hint: 'Tampil di header, judul tab, dan pengirim email.',
                                })}
                                {field('foundation.name', 'Nama yayasan', {
                                    placeholder: 'Yayasan Pendidikan Harapan',
                                })}
                                {field('brand.tagline', 'Slogan', {
                                    placeholder: 'Mendidik dengan hati',
                                    hint: 'Satu kalimat singkat. Dipakai juga untuk deskripsi halaman di mesin pencari.',
                                })}

                                <div className="grid gap-3">
                                    <PhotoUpload
                                        label="Logo"
                                        hint="PNG, JPG, atau WebP, maksimal 1 MB. Latar transparan paling cocok."
                                        maxSizeMb={1}
                                        accept="image/png,image/jpeg,image/webp"
                                        fit="contain"
                                        previewClassName="h-24 w-40"
                                        value={logoShown}
                                        onChange={(file) =>
                                            form.setData((data) => ({
                                                ...data,
                                                logo: file,
                                                remove_logo: file === null,
                                            }))
                                        }
                                    />
                                    {errors.logo && (
                                        <p className="text-sm font-medium text-destructive-foreground">
                                            {errors.logo}
                                        </p>
                                    )}
                                    <label className="flex min-h-11 items-center justify-between gap-4 text-[15px] font-medium">
                                        Tampilkan nama di samping logo
                                        <Switch
                                            checked={
                                                read('brand.show_name') !==
                                                false
                                            }
                                            onCheckedChange={(checked) =>
                                                set('brand.show_name', checked)
                                            }
                                        />
                                    </label>
                                </div>

                                <div className="grid gap-3">
                                    <PhotoUpload
                                        label="Ikon tab"
                                        hint="Gambar persegi (PNG, ICO, WebP, atau JPG), maksimal 512 KB."
                                        maxSizeMb={0.5}
                                        accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/webp,image/jpeg,.ico"
                                        fit="contain"
                                        previewClassName="h-24 w-24"
                                        value={
                                            form.data.remove_favicon
                                                ? null
                                                : files.favicon_url
                                        }
                                        onChange={(file) =>
                                            form.setData((data) => ({
                                                ...data,
                                                favicon: file,
                                                remove_favicon: file === null,
                                            }))
                                        }
                                    />
                                    {errors.favicon && (
                                        <p className="text-sm font-medium text-destructive-foreground">
                                            {errors.favicon}
                                        </p>
                                    )}
                                </div>
                            </>
                        )}

                        {group === 'tampilan' && (
                            <>
                                <fieldset className="grid gap-3">
                                    <legend className="mb-1 text-sm font-semibold">
                                        Warna utama
                                    </legend>
                                    <div className="flex flex-wrap gap-2.5">
                                        {swatches.map((swatch) => {
                                            const selected =
                                                accent.toLowerCase() ===
                                                swatch.value;

                                            return (
                                                <button
                                                    key={swatch.value}
                                                    type="button"
                                                    aria-label={swatch.name}
                                                    aria-pressed={selected}
                                                    onClick={() =>
                                                        set(
                                                            'brand.accent_color',
                                                            swatch.value,
                                                        )
                                                    }
                                                    style={{
                                                        backgroundColor:
                                                            swatch.value,
                                                    }}
                                                    className={cn(
                                                        'grid size-11 place-items-center rounded-full text-neutral-900 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                                        selected &&
                                                            'ring-2 ring-neutral-900 ring-offset-2 ring-offset-background dark:ring-white',
                                                    )}
                                                >
                                                    {selected && (
                                                        <Check className="size-5" />
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </fieldset>

                                <div className="grid gap-4 @2xl:grid-cols-2">
                                    <FormField
                                        label="Kode warna"
                                        error={errors['brand.accent_color']}
                                        hint="Contoh: #a3e635. Warna ini jadi warna tombol utama; warna lembut diturunkan otomatis."
                                    >
                                        {(control) => (
                                            <Input
                                                {...control}
                                                value={text(
                                                    'brand.accent_color',
                                                )}
                                                onChange={(event) =>
                                                    set(
                                                        'brand.accent_color',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={
                                                    defaults[
                                                        'brand.accent_color'
                                                    ]
                                                }
                                            />
                                        )}
                                    </FormField>
                                    <FormField label="Pilih bebas">
                                        {(control) => (
                                            <input
                                                {...control}
                                                type="color"
                                                value={
                                                    isHexColor(accent)
                                                        ? accent
                                                        : defaults[
                                                              'brand.accent_color'
                                                          ]
                                                }
                                                onChange={(event) =>
                                                    set(
                                                        'brand.accent_color',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-11 w-full cursor-pointer rounded-xl border border-input bg-card p-1"
                                            />
                                        )}
                                    </FormField>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="w-fit"
                                    onClick={() =>
                                        set(
                                            'brand.accent_color',
                                            defaults['brand.accent_color'],
                                        )
                                    }
                                >
                                    Kembalikan ke warna bawaan
                                </Button>
                            </>
                        )}

                        {group === 'kontak' && (
                            <>
                                <div className="grid gap-5 @2xl:grid-cols-2">
                                    {field('contact.phone', 'Telepon', {
                                        placeholder: '021 555 0100',
                                        type: 'tel',
                                    })}
                                    {field('contact.whatsapp', 'WhatsApp', {
                                        placeholder: '0812 3456 7890',
                                        type: 'tel',
                                        hint: 'Angka saja, boleh diawali +. Orang tua bisa langsung mengetuk untuk chat.',
                                    })}
                                </div>
                                {field('contact.email', 'Email', {
                                    placeholder: 'admin@sekolah.sch.id',
                                    type: 'email',
                                })}
                                {field('contact.website', 'Situs web', {
                                    placeholder: 'https://sekolah.sch.id',
                                    type: 'url',
                                })}
                                {field('contact.address', 'Alamat', {
                                    multiline: true,
                                    placeholder:
                                        'Jl. Pendidikan No. 1, Bandung',
                                })}
                            </>
                        )}

                        {group === 'pembayaran' && (
                            <>
                                <div className="grid gap-5 @2xl:grid-cols-2">
                                    {field('payment.bank_name', 'Nama bank', {
                                        placeholder: 'Bank Syariah Indonesia',
                                    })}
                                    {field(
                                        'payment.account_number',
                                        'Nomor rekening',
                                        { placeholder: '7001234567' },
                                    )}
                                </div>
                                {field('payment.account_holder', 'Atas nama', {
                                    placeholder: 'Yayasan Pendidikan Harapan',
                                })}
                                {field(
                                    'payment.instructions',
                                    'Petunjuk transfer',
                                    {
                                        multiline: true,
                                        placeholder:
                                            'Transfer sesuai nominal tagihan, lalu unggah bukti transfernya.',
                                        hint: 'Tampil di halaman pembayaran orang tua.',
                                    },
                                )}
                            </>
                        )}

                        {group === 'halaman-depan' && (
                            <>
                                {field(
                                    'brand.welcome_title',
                                    'Judul sambutan',
                                    {
                                        placeholder:
                                            defaults['brand.welcome_title'],
                                    },
                                )}
                                {field(
                                    'brand.welcome_description',
                                    'Teks sambutan',
                                    {
                                        multiline: true,
                                        placeholder:
                                            defaults[
                                                'brand.welcome_description'
                                            ],
                                    },
                                )}
                                {field(
                                    'brand.footer_text',
                                    'Teks kaki halaman',
                                    {
                                        placeholder:
                                            '© 2026 Yayasan Pendidikan Harapan',
                                        hint: 'Tampil di bawah halaman depan dan halaman masuk.',
                                    },
                                )}
                            </>
                        )}

                        {group === 'keamanan' && (
                            <label className="flex min-h-11 items-start justify-between gap-4 text-[15px]">
                                <span className="grid gap-1">
                                    <span className="font-medium">
                                        Wajib autentikasi dua langkah untuk staf
                                    </span>
                                    <span className="text-muted-foreground">
                                        Staf yang belum mengaktifkannya
                                        diarahkan ke pengaturan keamanan sampai
                                        selesai. Orang tua tidak terpengaruh.
                                    </span>
                                    {errors[
                                        'security.require_two_factor_staff'
                                    ] && (
                                        <span className="font-medium text-destructive-foreground">
                                            {
                                                errors[
                                                    'security.require_two_factor_staff'
                                                ]
                                            }
                                        </span>
                                    )}
                                </span>
                                <Switch
                                    checked={
                                        read(
                                            'security.require_two_factor_staff',
                                        ) === true
                                    }
                                    onCheckedChange={(checked) =>
                                        set(
                                            'security.require_two_factor_staff',
                                            checked,
                                        )
                                    }
                                />
                            </label>
                        )}

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Simpan pengaturan
                            </Button>
                        </div>
                    </SectionCard>

                    {(group === 'identitas' || group === 'tampilan') && (
                        <div className="grid gap-3 @5xl:sticky @5xl:top-24">
                            <p className="text-sm font-semibold">
                                Contoh tampilan
                            </p>
                            <BrandPreview
                                name={
                                    text('brand.name') || defaults['brand.name']
                                }
                                logoUrl={previewLogo}
                                showName={read('brand.show_name') !== false}
                                accent={accent}
                            />
                            <p className="text-sm text-muted-foreground">
                                Perubahan baru dipakai seluruh aplikasi setelah
                                disimpan.
                            </p>
                        </div>
                    )}
                </form>
            </div>
        </>
    );
}

SettingsEdit.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pengaturan', href: edit({ group: 'identitas' }) },
    ],
};
