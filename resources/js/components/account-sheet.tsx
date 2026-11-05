import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    LogOut,
    School,
    Settings,
    SlidersHorizontal,
    UserCog,
} from 'lucide-react';
import { PersonAvatar } from '@/components/person-avatar';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { logout } from '@/routes';
import { index as organizations } from '@/routes/admin/organizations';
import { edit as editSetting } from '@/routes/admin/settings';
import { index as staff } from '@/routes/admin/users';
import { index as years } from '@/routes/admin/years';
import { edit as editProfile } from '@/routes/profile';

/**
 * Bottom sheet behind the "Akun" tab on phones: who is signed in, account
 * settings, and sign out.
 */
export function AccountSheet({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { auth } = usePage().props;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="mx-auto max-w-[480px] gap-0 p-4 pb-6"
            >
                <SheetTitle className="sr-only">Akun</SheetTitle>
                <SheetDescription className="sr-only">
                    Pengaturan akun dan keluar
                </SheetDescription>

                {auth.user && (
                    <div className="mb-2 flex items-center gap-3 pr-10">
                        <PersonAvatar name={auth.user.name} size="lg" />
                        <div className="min-w-0">
                            <p className="truncate font-semibold">
                                {auth.user.name}
                            </p>
                            <p className="truncate text-sm text-muted-foreground">
                                {auth.user.email}
                            </p>
                        </div>
                    </div>
                )}

                <Link
                    href={editProfile()}
                    onClick={() => onOpenChange(false)}
                    className="flex items-center gap-4 rounded-xl px-2 py-3 text-base font-semibold active:bg-accent"
                >
                    <Settings className="size-6 text-muted-foreground" />
                    Pengaturan akun
                </Link>
                {auth.abilities.includes('core::setting.manage') && (
                    <Link
                        href={editSetting({ group: 'identitas' })}
                        onClick={() => onOpenChange(false)}
                        className="flex items-center gap-4 rounded-xl px-2 py-3 text-base font-semibold active:bg-accent"
                    >
                        <SlidersHorizontal className="size-6 text-muted-foreground" />
                        Pengaturan aplikasi
                    </Link>
                )}
                {auth.abilities.includes('core::year.manage') && (
                    <Link
                        href={years()}
                        onClick={() => onOpenChange(false)}
                        className="flex items-center gap-4 rounded-xl px-2 py-3 text-base font-semibold active:bg-accent"
                    >
                        <CalendarDays className="size-6 text-muted-foreground" />
                        Tahun ajaran
                    </Link>
                )}
                {auth.abilities.includes('core::organization.manage') && (
                    <Link
                        href={organizations()}
                        onClick={() => onOpenChange(false)}
                        className="flex items-center gap-4 rounded-xl px-2 py-3 text-base font-semibold active:bg-accent"
                    >
                        <School className="size-6 text-muted-foreground" />
                        Lembaga
                    </Link>
                )}
                {auth.abilities.includes('core::user.manage') && (
                    <Link
                        href={staff()}
                        onClick={() => onOpenChange(false)}
                        className="flex items-center gap-4 rounded-xl px-2 py-3 text-base font-semibold active:bg-accent"
                    >
                        <UserCog className="size-6 text-muted-foreground" />
                        Staf dan peran
                    </Link>
                )}
                <Link
                    href={logout()}
                    as="button"
                    onClick={() => {
                        onOpenChange(false);
                        router.flushAll();
                    }}
                    data-test="logout-button"
                    className="flex items-center gap-4 rounded-xl px-2 py-3 text-left text-base font-semibold active:bg-accent"
                >
                    <LogOut className="size-6 text-muted-foreground" />
                    Keluar
                </Link>
            </SheetContent>
        </Sheet>
    );
}
