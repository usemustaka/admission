/**
 * The framework hands back its status messages in English. These are the
 * ones the auth pages can show, in the words the app uses.
 */
const messages: Record<string, string> = {
    'verification-link-sent':
        'Tautan verifikasi baru sudah kami kirim ke emailmu.',
    'We have emailed your password reset link.':
        'Tautan untuk mengatur ulang kata sandi sudah kami kirim ke emailmu.',
    'passwords.sent':
        'Tautan untuk mengatur ulang kata sandi sudah kami kirim ke emailmu.',
    'Your password has been reset.':
        'Kata sandimu sudah diubah. Silakan masuk.',
    'passwords.reset': 'Kata sandimu sudah diubah. Silakan masuk.',
};

export function translateStatus(status: string): string {
    return messages[status] ?? status;
}
