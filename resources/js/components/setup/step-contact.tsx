import { FormField } from '@/components/form-field';
import type {
    SetSection,
    SetupErrors,
    SetupFormData,
} from '@/components/setup/setup-form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export function StepContact({
    data,
    errors,
    setSection,
}: {
    data: SetupFormData;
    errors: SetupErrors;
    setSection: SetSection;
}) {
    return (
        <div className="grid gap-8">
            <fieldset className="grid gap-5">
                <legend className="mb-1 text-base font-semibold">
                    Kontak sekolah
                </legend>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField label="Telepon" error={errors['contact.phone']}>
                        {(control) => (
                            <Input
                                {...control}
                                type="tel"
                                name="contact[phone]"
                                autoComplete="tel"
                                placeholder="021 555 0100"
                                value={data.contact.phone}
                                onChange={(event) =>
                                    setSection('contact', {
                                        phone: event.target.value,
                                    })
                                }
                            />
                        )}
                    </FormField>
                    <FormField
                        label="WhatsApp"
                        error={errors['contact.whatsapp']}
                        hint="Angka saja, boleh diawali +."
                    >
                        {(control) => (
                            <Input
                                {...control}
                                type="tel"
                                name="contact[whatsapp]"
                                placeholder="0812 3456 7890"
                                value={data.contact.whatsapp}
                                onChange={(event) =>
                                    setSection('contact', {
                                        whatsapp: event.target.value,
                                    })
                                }
                            />
                        )}
                    </FormField>
                </div>
                <FormField label="Email" error={errors['contact.email']}>
                    {(control) => (
                        <Input
                            {...control}
                            type="email"
                            name="contact[email]"
                            placeholder="admin@sekolah.sch.id"
                            value={data.contact.email}
                            onChange={(event) =>
                                setSection('contact', {
                                    email: event.target.value,
                                })
                            }
                        />
                    )}
                </FormField>
                <FormField label="Alamat" error={errors['contact.address']}>
                    {(control) => (
                        <Textarea
                            {...control}
                            name="contact[address]"
                            rows={3}
                            autoComplete="street-address"
                            value={data.contact.address}
                            onChange={(event) =>
                                setSection('contact', {
                                    address: event.target.value,
                                })
                            }
                        />
                    )}
                </FormField>
            </fieldset>

            <fieldset className="grid gap-5">
                <legend className="mb-1 text-base font-semibold">
                    Rekening tujuan pembayaran
                </legend>
                <p className="text-[15px] text-muted-foreground">
                    Tampil di halaman pembayaran orang tua.
                </p>
                <FormField
                    label="Nama bank"
                    error={errors['payment.bank_name']}
                >
                    {(control) => (
                        <Input
                            {...control}
                            name="payment[bank_name]"
                            placeholder="Bank Syariah Indonesia"
                            value={data.payment.bank_name}
                            onChange={(event) =>
                                setSection('payment', {
                                    bank_name: event.target.value,
                                })
                            }
                        />
                    )}
                </FormField>
                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        label="Nomor rekening"
                        error={errors['payment.account_number']}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                name="payment[account_number]"
                                inputMode="numeric"
                                autoComplete="off"
                                value={data.payment.account_number}
                                onChange={(event) =>
                                    setSection('payment', {
                                        account_number: event.target.value,
                                    })
                                }
                            />
                        )}
                    </FormField>
                    <FormField
                        label="Atas nama"
                        error={errors['payment.account_holder']}
                    >
                        {(control) => (
                            <Input
                                {...control}
                                name="payment[account_holder]"
                                autoComplete="off"
                                value={data.payment.account_holder}
                                onChange={(event) =>
                                    setSection('payment', {
                                        account_holder: event.target.value,
                                    })
                                }
                            />
                        )}
                    </FormField>
                </div>
            </fieldset>
        </div>
    );
}
