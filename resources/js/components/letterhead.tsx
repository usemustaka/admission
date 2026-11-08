import { usePage } from '@inertiajs/react';

/**
 * The school's letterhead for printed papers: the logo and name from the settings with the address and contact
 * under them, over a heavy rule. The paper is fixed to black on white so it prints the same in dark mode.
 */
export function Letterhead() {
    const { brand } = usePage().props;
    const contact = [
        brand.contact.address,
        [brand.contact.phone, brand.contact.email].filter(Boolean).join(' · '),
        brand.contact.website,
    ].filter(Boolean);

    return (
        <header className="flex items-center gap-4 border-b-2 border-neutral-900 pb-4">
            {brand.logo_url && (
                <img
                    src={brand.logo_url}
                    alt=""
                    className="h-16 w-auto max-w-40 object-contain"
                />
            )}
            <div className="grid flex-1 gap-0.5 text-center">
                <p className="text-xl leading-tight font-bold tracking-wide uppercase">
                    {brand.organization ?? brand.name}
                </p>
                {brand.organization && brand.organization !== brand.name && (
                    <p className="text-base font-semibold">{brand.name}</p>
                )}
                {contact.map((line) => (
                    <p key={line} className="text-sm text-neutral-700">
                        {line}
                    </p>
                ))}
            </div>
        </header>
    );
}
