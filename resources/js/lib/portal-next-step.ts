import { bills, show } from '@/routes/portal/children';
import { edit as editData } from '@/routes/portal/children/data';
import type { PortalRegistrant } from '@/types/portal';

/**
 * Who has to move next: the parent, the committee, or nobody (finished).
 */
export type Turn = 'parent' | 'committee' | 'done';

export type NextAction = {
    kind:
        | 'identity'
        | 'pay-registration'
        | 'fill'
        | 'fix'
        | 'submit'
        | 'review'
        | 'pay-settlement'
        | 'announcement'
        | 'result'
        | 'placed';
    turn: Turn;
    /** Short label that says whose turn it is. Never colour alone. */
    turnLabel: string;
    title: string;
    description: string;
    /** The one button that moves things forward. Null while waiting. */
    cta: { label: string; href: string } | null;
};

/**
 * What a parent should do next for one registration, in plain words. It only
 * uses what the server already sends about the registration. `readiness` is
 * the list of things still missing before the data can be sent; when it is
 * left out (on the home page) a draft is treated as "fill in the data".
 */
export function nextAction(
    registrant: PortalRegistrant,
    readiness?: string[],
): NextAction {
    const waiting = (
        kind: NextAction['kind'],
        title: string,
        description: string,
    ): NextAction => ({
        kind,
        turn: 'committee',
        turnLabel: 'Menunggu panitia',
        title,
        description,
        cta: null,
    });

    if (registrant.identity_pending) {
        return waiting(
            'identity',
            'Panitia sedang mencocokkan data anak',
            'Belum ada yang perlu kamu lakukan. Kamu akan dikabari kalau sudah selesai.',
        );
    }

    const yourTurn = (
        kind: NextAction['kind'],
        title: string,
        description: string,
        label: string,
        href: string,
    ): NextAction => ({
        kind,
        turn: 'parent',
        turnLabel: 'Giliran kamu',
        title,
        description,
        cta: { label, href },
    });

    switch (registrant.status) {
        case 'revision':
            return yourTurn(
                'fix',
                'Perbaiki data anak',
                'Panitia meminta ada yang diperbaiki. Baca catatannya, ubah datanya, lalu kirim lagi.',
                'Perbaiki data',
                editData(registrant.id).url,
            );

        case 'draft':
            if (registrant.current_step === 'registration-bill') {
                return yourTurn(
                    'pay-registration',
                    'Bayar tagihan pendaftaran',
                    'Selesaikan dulu supaya pendaftaran bisa lanjut. Datanya boleh diisi sambil menunggu.',
                    'Bayar tagihan',
                    bills(registrant.id).url,
                );
            }

            if (readiness !== undefined && readiness.length === 0) {
                return yourTurn(
                    'submit',
                    'Kirim data untuk diperiksa',
                    'Semua sudah lengkap. Setelah dikirim, data tidak bisa diubah sampai panitia selesai memeriksa.',
                    'Kirim data',
                    show(registrant.id).url,
                );
            }

            return yourTurn(
                'fill',
                'Lengkapi data anak',
                'Isi data anak dan orang tua, lalu pilih jadwal ujian. Disimpan sebagai draf, jadi boleh dilanjutkan kapan saja.',
                'Lengkapi data',
                editData(registrant.id).url,
            );

        case 'review':
            return waiting(
                'review',
                'Panitia sedang memeriksa data',
                'Tunggu ya. Kamu akan dikabari kalau ada yang perlu diperbaiki atau kalau datanya sudah valid.',
            );

        case 'validated':
            if (registrant.current_step === 'settlement-bill') {
                return yourTurn(
                    'pay-settlement',
                    'Bayar tagihan pelunasan',
                    'Datanya sudah valid. Selesaikan pelunasan supaya bisa lanjut ke pengumuman.',
                    'Bayar tagihan',
                    bills(registrant.id).url,
                );
            }

            return waiting(
                'announcement',
                'Data sudah valid',
                'Tinggal menunggu pengumuman hasil dari panitia.',
            );

        case 'announced':
            return {
                kind: 'result',
                turn: 'done',
                turnLabel: 'Hasil sudah keluar',
                title: 'Pengumuman sudah keluar',
                description:
                    'Lihat hasilnya di bawah. Panitia akan menempatkan anak di kelas berikutnya.',
                cta: null,
            };

        case 'placed':
        default:
            return {
                kind: 'placed',
                turn: 'done',
                turnLabel: 'Selesai',
                title: 'Anak sudah ditempatkan',
                description:
                    'Semua tahap pendaftaran sudah selesai. Terima kasih sudah mendaftar.',
                cta: null,
            };
    }
}
