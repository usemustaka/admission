import { Head, Link, usePage } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { show as showBrochure } from '@/routes/brochures';

/**
 * Front door of the site: says what it is for and sends people to sign in or
 * sign up, or straight to their page when they already are.
 */
export default function Welcome({
    brochures,
}: {
    brochures: { id: number; name: string }[];
}) {
    const { auth, brand } = usePage().props;

    return (
        <>
            <Head title={brand.public.welcome_title} />
            <div className="grid min-h-svh place-items-center bg-neutral-50 px-4 py-8 dark:bg-background">
                <div className="relative grid w-full max-w-[480px] gap-6">
                    <div className="flex justify-center">
                        <AppLogo />
                    </div>

                    <div className="grid gap-6 rounded-3xl border border-line bg-card p-6 text-center">
                        <div className="grid gap-2">
                            <h1 className="text-[26px] leading-tight font-semibold tracking-tight text-balance">
                                {brand.public.welcome_title}
                            </h1>
                            <p className="text-[15px] text-muted-foreground">
                                {brand.public.welcome_description}
                            </p>
                            {brand.tagline && (
                                <p className="text-sm font-semibold">
                                    {brand.tagline}
                                </p>
                            )}
                        </div>

                        {auth.user ? (
                            <Button asChild size="lg" className="w-full">
                                <Link href={dashboard()}>
                                    Lanjut ke beranda
                                </Link>
                            </Button>
                        ) : (
                            <div className="grid gap-3">
                                <Button asChild size="lg" className="w-full">
                                    <Link href={login()}>Masuk</Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link href={register()}>Daftar</Link>
                                </Button>
                            </div>
                        )}
                    </div>

                    {brochures.length > 0 && (
                        <div className="grid gap-3 rounded-3xl border border-line bg-card p-6">
                            <h2 className="text-center text-[15px] font-semibold">
                                Unduh brosur
                            </h2>
                            <div className="grid gap-2">
                                {brochures.map((brochure) => (
                                    <Button
                                        key={brochure.id}
                                        asChild
                                        variant="outline"
                                        className="justify-start"
                                    >
                                        <a
                                            href={showBrochure(brochure.id).url}
                                            download
                                        >
                                            <FileDown />
                                            {brochure.name}
                                        </a>
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}

                    {brand.public.footer_text && (
                        <p className="text-center text-xs text-muted-foreground">
                            {brand.public.footer_text}
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
