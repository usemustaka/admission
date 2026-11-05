import { ShieldCheck } from 'lucide-react';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { SetupNote } from '@/components/setup/setup-note';
import type {
    SetSection,
    SetupErrors,
    SetupFormData,
} from '@/components/setup/setup-form';
import { Input } from '@/components/ui/input';

export function StepAdmin({
    data,
    errors,
    setSection,
}: {
    data: SetupFormData;
    errors: SetupErrors;
    setSection: SetSection;
}) {
    return (
        <div className="grid gap-5">
            <SetupNote icon={<ShieldCheck className="size-5" />}>
                <p>
                    Akun ini bisa mengelola semuanya. Email langsung dianggap
                    terverifikasi.
                </p>
            </SetupNote>

            <FormField
                label="Nama lengkap"
                required
                error={errors['admin.name']}
            >
                {(control) => (
                    <Input
                        {...control}
                        name="admin[name]"
                        autoComplete="name"
                        value={data.admin.name}
                        onChange={(event) =>
                            setSection('admin', { name: event.target.value })
                        }
                    />
                )}
            </FormField>

            <FormField
                label="Alamat email"
                required
                error={errors['admin.email']}
                hint="Dipakai untuk masuk."
            >
                {(control) => (
                    <Input
                        {...control}
                        type="email"
                        name="admin[email]"
                        autoComplete="email"
                        placeholder="email@contoh.com"
                        value={data.admin.email}
                        onChange={(event) =>
                            setSection('admin', { email: event.target.value })
                        }
                    />
                )}
            </FormField>

            <FormField
                label="Kata sandi"
                required
                error={errors['admin.password']}
                hint="Minimal 12 karakter, campur huruf besar-kecil, angka, dan simbol."
            >
                {(control) => (
                    <PasswordInput
                        {...control}
                        name="admin[password]"
                        autoComplete="new-password"
                        value={data.admin.password}
                        onChange={(event) =>
                            setSection('admin', {
                                password: event.target.value,
                            })
                        }
                    />
                )}
            </FormField>

            <FormField
                label="Ulangi kata sandi"
                required
                error={errors['admin.password_confirmation']}
            >
                {(control) => (
                    <PasswordInput
                        {...control}
                        name="admin[password_confirmation]"
                        autoComplete="new-password"
                        value={data.admin.password_confirmation}
                        onChange={(event) =>
                            setSection('admin', {
                                password_confirmation: event.target.value,
                            })
                        }
                    />
                )}
            </FormField>
        </div>
    );
}
