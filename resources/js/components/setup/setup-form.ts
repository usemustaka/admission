import { isHexColor } from '@/lib/accent';

export type SetupCheck = {
    label: string;
    ok: boolean;
    hint: string | null;
    /** A suggestion rather than a requirement: shown, but it does not warn that the setup may fail. */
    soft?: boolean;
};

export type SetupExisting = {
    organizations: string[];
    years: string[];
};

export type SetupDefaults = {
    app_name: string;
    accent_color: string;
    year: { name: string; starts_on: string; ends_on: string };
};

export type SetupFormData = {
    setup_token: string;
    brand: { name: string; accent_color: string };
    logo: File | null;
    contact: {
        phone: string;
        whatsapp: string;
        email: string;
        address: string;
    };
    payment: {
        bank_name: string;
        account_number: string;
        account_holder: string;
    };
    admin: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
    };
    organizations: { name: string; category: string }[];
    year: {
        name: string;
        starts_on: string;
        ends_on: string;
        with_periods: boolean;
    };
    wave: { name: string; starts_on: string; ends_on: string };
};

export type SetupSection =
    | 'brand'
    | 'contact'
    | 'payment'
    | 'admin'
    | 'year'
    | 'wave';

export type SetSection = <K extends SetupSection>(
    section: K,
    values: Partial<SetupFormData[K]>,
) => void;

/** Errors keyed like the server sends them, for example `admin.email`. */
export type SetupErrors = Record<string, string | undefined>;

export type SetupContext = {
    requiresToken: boolean;
    hasExistingOrganizations: boolean;
    yearOpen: boolean;
    waveOpen: boolean;
};

export const setupSteps = [
    {
        label: 'Selamat datang',
        title: 'Selamat datang',
        description:
            'Atur aplikasi sekali saja. Semua ini bisa diubah nanti di menu Pengaturan.',
        blurb: '',
    },
    {
        label: 'Identitas',
        title: 'Identitas',
        description:
            'Nama, warna, dan logo yang dilihat orang tua dan panitia.',
        blurb: 'Nama aplikasi, warna utama, dan logo.',
    },
    {
        label: 'Super admin',
        title: 'Akun super admin',
        description: 'Akun pertama yang akan mengelola aplikasi ini.',
        blurb: 'Akun pertama untuk mengelola semuanya.',
    },
    {
        label: 'Lembaga',
        title: 'Lembaga dan tahun ajaran',
        description: 'Yang dibutuhkan sebelum pendaftaran bisa dibuka.',
        blurb: 'Lembaga, tahun ajaran, dan gelombang pertama.',
    },
    {
        label: 'Kontak',
        title: 'Kontak dan rekening',
        description: 'Boleh dikosongkan, isi nanti di Pengaturan.',
        blurb: 'Kontak sekolah dan rekening tujuan bayar (boleh dilewati).',
    },
] as const;

export const lastStep = setupSteps.length - 1;

/** The wizard step that shows the field behind a server error key. */
export function stepOfError(key: string): number | null {
    if (key === 'setup_token') {
        return 0;
    }

    if (key === 'logo' || key.startsWith('brand')) {
        return 1;
    }

    if (key.startsWith('admin')) {
        return 2;
    }

    if (
        key.startsWith('organizations') ||
        key.startsWith('year') ||
        key.startsWith('wave')
    ) {
        return 3;
    }

    if (key.startsWith('contact') || key.startsWith('payment')) {
        return 4;
    }

    return null;
}

/** Required-field checks only; the server stays the source of truth. */
export function validateStep(
    step: number,
    data: SetupFormData,
    context: SetupContext,
): SetupErrors {
    const errors: SetupErrors = {};
    const empty = (value: string) => value.trim() === '';

    if (step === 0 && context.requiresToken && empty(data.setup_token)) {
        errors.setup_token = 'Isi kode setup dari SETUP_TOKEN di file .env.';
    }

    if (step === 1) {
        if (empty(data.brand.name)) {
            errors['brand.name'] = 'Nama aplikasi wajib diisi.';
        }

        if (!isHexColor(data.brand.accent_color)) {
            errors['brand.accent_color'] =
                'Kode warna harus berbentuk #rrggbb, contoh #a3e635.';
        }
    }

    if (step === 2) {
        if (empty(data.admin.name)) {
            errors['admin.name'] = 'Nama wajib diisi.';
        }

        if (empty(data.admin.email)) {
            errors['admin.email'] = 'Email wajib diisi.';
        }

        if (data.admin.password === '') {
            errors['admin.password'] = 'Kata sandi wajib diisi.';
        }

        if (data.admin.password_confirmation === '') {
            errors['admin.password_confirmation'] =
                'Ulangi kata sandi supaya tidak salah ketik.';
        } else if (data.admin.password !== data.admin.password_confirmation) {
            errors['admin.password_confirmation'] =
                'Kata sandi dan ulangannya belum sama.';
        }
    }

    if (step === 3) {
        const filled = data.organizations.filter(
            (row) => !empty(row.name) || !empty(row.category),
        );

        if (!context.hasExistingOrganizations && filled.length === 0) {
            errors['organizations.0.name'] =
                'Tambahkan minimal satu lembaga, misalnya nama sekolahnya.';
        }

        data.organizations.forEach((row, index) => {
            if (empty(row.name) && !empty(row.category)) {
                errors[`organizations.${index}.name`] =
                    'Isi nama lembaga, atau hapus baris ini.';
            }
        });

        if (context.yearOpen) {
            if (empty(data.year.name)) {
                errors['year.name'] = 'Nama tahun ajaran wajib diisi.';
            }

            if (empty(data.year.starts_on)) {
                errors['year.starts_on'] = 'Tanggal mulai wajib diisi.';
            }

            if (empty(data.year.ends_on)) {
                errors['year.ends_on'] = 'Tanggal selesai wajib diisi.';
            } else if (
                !empty(data.year.starts_on) &&
                data.year.ends_on <= data.year.starts_on
            ) {
                errors['year.ends_on'] =
                    'Tanggal selesai harus sesudah tanggal mulai.';
            }
        }

        if (context.waveOpen && !empty(data.wave.name)) {
            if (empty(data.wave.starts_on)) {
                errors['wave.starts_on'] = 'Tanggal mulai wajib diisi.';
            }

            if (empty(data.wave.ends_on)) {
                errors['wave.ends_on'] = 'Tanggal selesai wajib diisi.';
            } else if (
                !empty(data.wave.starts_on) &&
                data.wave.ends_on < data.wave.starts_on
            ) {
                errors['wave.ends_on'] =
                    'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            }
        }
    }

    return errors;
}

/** What actually goes to the server: closed sections and empty files are left out. */
export function buildPayload(
    data: SetupFormData,
    context: SetupContext,
): Record<string, unknown> {
    const { logo, wave, year, setup_token: token, ...rest } = data;
    const payload: Record<string, unknown> = { ...rest };

    if (context.requiresToken) {
        payload.setup_token = token;
    }

    if (logo) {
        payload.logo = logo;
    }

    if (context.yearOpen) {
        payload.year = year;
    }

    if (context.waveOpen && wave.name.trim() !== '') {
        payload.wave = wave;
    }

    return payload;
}
