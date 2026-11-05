import { Check } from 'lucide-react';
import { FormField } from '@/components/form-field';
import { PhotoUpload } from '@/components/photo-upload';
import { BrandPreview } from '@/components/settings/brand-preview';
import type {
    SetSection,
    SetupErrors,
    SetupFormData,
} from '@/components/setup/setup-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { accentSwatches, isHexColor } from '@/lib/accent';
import { cn } from '@/lib/utils';

export function StepIdentity({
    data,
    errors,
    setSection,
    logoUrl,
    onLogoChange,
    defaultAccent,
}: {
    data: SetupFormData;
    errors: SetupErrors;
    setSection: SetSection;
    logoUrl: string | null;
    onLogoChange: (file: File | null) => void;
    defaultAccent: string;
}) {
    const accent = data.brand.accent_color;

    return (
        <div className="grid gap-6">
            <FormField
                label="Nama aplikasi"
                required
                error={errors['brand.name']}
                hint="Tampil di header, judul tab, dan pengirim email. Boleh nama sekolah atau yayasan."
            >
                {(control) => (
                    <Input
                        {...control}
                        name="brand[name]"
                        maxLength={60}
                        autoComplete="organization"
                        value={data.brand.name}
                        onChange={(event) =>
                            setSection('brand', { name: event.target.value })
                        }
                    />
                )}
            </FormField>

            <div className="grid gap-2">
                <PhotoUpload
                    label="Logo (opsional)"
                    hint="PNG, JPG, atau WebP, maksimal 1 MB. Latar transparan paling cocok. Tanpa logo, huruf pertama nama aplikasi dipakai."
                    maxSizeMb={1}
                    accept="image/png,image/jpeg,image/webp"
                    fit="contain"
                    previewClassName="h-24 w-32 sm:w-40"
                    value={logoUrl}
                    onChange={onLogoChange}
                />
                {errors.logo && (
                    <p className="text-[15px] font-medium text-destructive-foreground">
                        {errors.logo}
                    </p>
                )}
            </div>

            <fieldset className="grid gap-3">
                <legend className="mb-1 text-[15px] font-semibold">
                    Warna utama
                </legend>
                <div className="flex flex-wrap gap-2.5">
                    {accentSwatches.map((swatch) => {
                        const selected = accent.toLowerCase() === swatch.value;

                        return (
                            <button
                                key={swatch.value}
                                type="button"
                                aria-label={swatch.name}
                                aria-pressed={selected}
                                onClick={() =>
                                    setSection('brand', {
                                        accent_color: swatch.value,
                                    })
                                }
                                style={{ backgroundColor: swatch.value }}
                                className={cn(
                                    'grid size-11 place-items-center rounded-full text-neutral-900 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                    selected &&
                                        'ring-2 ring-neutral-900 ring-offset-2 ring-offset-background dark:ring-white',
                                )}
                            >
                                {selected && <Check className="size-5" />}
                            </button>
                        );
                    })}
                </div>

                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_9rem]">
                    <FormField
                        label="Kode warna"
                        error={errors['brand.accent_color']}
                        hint="Contoh: #a3e635. Warna lembut diturunkan otomatis."
                    >
                        {(control) => (
                            <Input
                                {...control}
                                name="brand[accent_color]"
                                maxLength={7}
                                autoComplete="off"
                                spellCheck={false}
                                value={accent}
                                placeholder={defaultAccent}
                                onChange={(event) =>
                                    setSection('brand', {
                                        accent_color: event.target.value,
                                    })
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
                                    isHexColor(accent) ? accent : defaultAccent
                                }
                                onChange={(event) =>
                                    setSection('brand', {
                                        accent_color: event.target.value,
                                    })
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
                        setSection('brand', { accent_color: defaultAccent })
                    }
                >
                    Kembalikan ke warna bawaan
                </Button>
            </fieldset>

            <div className="grid gap-2">
                <p className="text-[15px] font-semibold">Contoh tampilan</p>
                <BrandPreview
                    name={data.brand.name}
                    logoUrl={logoUrl}
                    showName
                    accent={accent}
                />
            </div>
        </div>
    );
}
