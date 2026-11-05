export type AdmissionStep = {
    key: string;
    label: string;
    description: string;
};

/**
 * Default admission journey shown to parents. Steps for bills are skipped
 * automatically when the organization has not configured that bill type.
 */
export const admissionSteps: AdmissionStep[] = [
    {
        key: 'registered',
        label: 'Daftar',
        description: 'Data awal anak sudah tercatat',
    },
    {
        key: 'registration-bill',
        label: 'Bayar pendaftaran',
        description: 'Selesaikan tagihan pendaftaran',
    },
    {
        key: 'data',
        label: 'Lengkapi data',
        description: 'Isi data anak & pilih jadwal ujian',
    },
    {
        key: 'validation',
        label: 'Validasi',
        description: 'Panitia memeriksa data yang dikirim',
    },
    {
        key: 'settlement-bill',
        label: 'Pelunasan',
        description: 'Selesaikan tagihan pelunasan',
    },
    {
        key: 'announcement',
        label: 'Pengumuman',
        description: 'Hasil seleksi diumumkan',
    },
    {
        key: 'placement',
        label: 'Penempatan',
        description: 'Ditempatkan di tahun ajaran & kelas',
    },
];

/**
 * The steps that apply to one registration, in order. Bill steps are left out
 * when the ruleset has no bill of that type.
 */
export function stepsFor(keys: string[]): AdmissionStep[] {
    return keys
        .map((key) => admissionSteps.find((step) => step.key === key))
        .filter((step): step is AdmissionStep => step !== undefined);
}

export function currentStepIndex(keys: string[], current: string): number {
    return Math.max(keys.indexOf(current), 0);
}
