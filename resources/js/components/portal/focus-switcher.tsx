import { Link, usePage } from '@inertiajs/react';
import { Baby, Check, ChevronsUpDown, Plus, Users } from 'lucide-react';
import { PersonAvatar } from '@/components/person-avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { portal } from '@/routes';
import { create, show } from '@/routes/portal/children';

/**
 * Lets a parent look at one child at a time ("fokus") or at all of them.
 * It only changes what the portal shows, not who is signed in.
 */
export function FocusSwitcher() {
    const page = usePage();
    const children = page.props.portal?.children ?? [];

    if (children.length === 0) {
        return null;
    }

    const focusedId = Number(
        /\/portal\/anak\/(\d+)/.exec(page.url)?.[1] ?? Number.NaN,
    );
    const focused = children.find((child) => child.id === focusedId);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="gap-2 rounded-full">
                    {focused ? (
                        <PersonAvatar
                            name={focused.name}
                            src={focused.photo_url}
                            size="sm"
                            className="size-6 text-[10px]"
                        />
                    ) : (
                        <Users />
                    )}
                    <span className="max-w-32 truncate">
                        {focused ? focused.name : 'Semua anak'}
                    </span>
                    <ChevronsUpDown className="opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuLabel>Lihat sebagai</DropdownMenuLabel>
                <DropdownMenuItem asChild>
                    <Link href={portal()} className="cursor-pointer">
                        <Users />
                        <span className="flex-1">Semua anak</span>
                        {!focused && <Check />}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                {children.map((child) => (
                    <DropdownMenuItem key={child.id} asChild>
                        <Link href={show(child.id)} className="cursor-pointer">
                            <Baby />
                            <span className="flex-1 truncate">
                                {child.name}
                            </span>
                            {focused?.id === child.id && <Check />}
                        </Link>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={create()} className="cursor-pointer">
                        <Plus />
                        Daftarkan anak lain
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
