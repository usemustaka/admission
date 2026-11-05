import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { PageTransition } from '@/components/page-transition';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const footerText = usePage().props.brand.public.footer_text;

    return (
        <div className="grid min-h-svh place-items-center bg-neutral-50 px-4 py-8 dark:bg-background">
            <div className="relative w-full max-w-[480px]">
                <div className="mb-6 flex justify-center">
                    <Link
                        href={home()}
                        className="rounded-lg outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <AppLogo />
                    </Link>
                </div>

                <div className="overflow-hidden rounded-3xl border border-line bg-card">
                    <div className="space-y-6 p-6 sm:p-8">
                        {(title || description) && (
                            <div className="space-y-2">
                                {title && (
                                    <h1 className="text-[26px] leading-tight font-semibold tracking-tight">
                                        {title}
                                    </h1>
                                )}
                                {description && (
                                    <p className="text-[15px] text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                        )}
                        <PageTransition>{children}</PageTransition>
                    </div>
                </div>

                {footerText && (
                    <p className="mt-6 text-center text-xs text-muted-foreground">
                        {footerText}
                    </p>
                )}
            </div>
        </div>
    );
}
