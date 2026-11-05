import { Link, usePage } from '@inertiajs/react';
import { House, UserRound, UserRoundPlus, Wallet } from 'lucide-react';
import { useState } from 'react';
import { AccountSheet } from '@/components/account-sheet';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { portal } from '@/routes';
import { index as bills } from '@/routes/portal/bills';
import { create as registerChild } from '@/routes/portal/children';

type Tab = {
    key: string;
    title: string;
    icon: LucideIcon;
    href: ReturnType<typeof portal>;
};

const tabs: Tab[] = [
    { key: 'home', title: 'Beranda', icon: House, href: portal() },
    { key: 'bills', title: 'Tagihan', icon: Wallet, href: bills() },
    {
        key: 'register',
        title: 'Daftar',
        icon: UserRoundPlus,
        href: registerChild(),
    },
];

function activeTab(path: string): string {
    if (path.startsWith('/settings')) {
        return 'account';
    }

    if (
        path.startsWith('/portal/tagihan') ||
        path.startsWith('/portal/pembayaran')
    ) {
        return 'bills';
    }

    if (path.startsWith('/portal/anak/daftar')) {
        return 'register';
    }

    if (/^\/portal\/anak\/\d+\/tagihan/u.test(path)) {
        return 'bills';
    }

    return 'home';
}

/**
 * Bottom navigation of the parent portal. The portal is built for phones, so
 * this bar shows at every window size, pinned to the bottom of the phone-wide
 * column.
 */
export function PortalTabBar() {
    const path = new URL(usePage().url, 'http://localhost').pathname;
    const current = activeTab(path);
    const [accountOpen, setAccountOpen] = useState(false);
    const onAccount = current === 'account';

    return (
        <>
            <nav
                aria-label="Menu utama"
                className="fixed inset-x-0 bottom-0 z-40 mx-auto grid max-w-[480px] grid-cols-4 border-t border-line bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur"
            >
                {tabs.map((tab) => {
                    const selected = tab.key === current;

                    return (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            aria-current={selected ? 'page' : undefined}
                            className={cn(
                                'flex min-h-14 flex-col items-center gap-0.5 pt-2 pb-1.5 text-xs font-semibold outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                                selected
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <span
                                className={cn(
                                    'grid h-7 w-14 place-items-center rounded-full transition-colors',
                                    selected && 'bg-lime-300 text-neutral-900',
                                )}
                            >
                                <tab.icon
                                    className="size-6"
                                    strokeWidth={selected ? 2.25 : 1.75}
                                />
                            </span>
                            {tab.title}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setAccountOpen(true)}
                    aria-current={onAccount ? 'page' : undefined}
                    className={cn(
                        'flex min-h-14 flex-col items-center gap-0.5 pt-2 pb-1.5 text-xs font-semibold outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                        onAccount ? 'text-foreground' : 'text-muted-foreground',
                    )}
                >
                    <span
                        className={cn(
                            'grid h-7 w-14 place-items-center rounded-full transition-colors',
                            onAccount && 'bg-lime-300 text-neutral-900',
                        )}
                    >
                        <UserRound
                            className="size-6"
                            strokeWidth={onAccount ? 2.25 : 1.75}
                        />
                    </span>
                    Akun
                </button>
            </nav>
            <AccountSheet open={accountOpen} onOpenChange={setAccountOpen} />
        </>
    );
}
