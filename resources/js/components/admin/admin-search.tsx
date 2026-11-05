import { router } from '@inertiajs/react';
import { CornerDownLeft, Search, UserRound } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { visibleAdminSections } from '@/components/admin/admin-nav';
import { PersonAvatar } from '@/components/person-avatar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate } from '@/lib/format';
import { cn, toUrl } from '@/lib/utils';
import {
    search as searchPeople,
    show as showPerson,
} from '@/routes/admin/people';

type Result = {
    key: string;
    kind: string;
    label: string;
    hint?: string;
    url: string;
    leading: ReactNode;
};

type PersonHit = {
    id: number;
    name: string;
    nik: string | null;
    birth_date: string | null;
    phone_masked: string | null;
};

/**
 * Quick jump for staff: type a page name, a child's name or a NIK, press
 * Enter. Opens from the header button or with Ctrl/Cmd + K.
 */
export function AdminSearch({
    open,
    onOpenChange,
    abilities,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    abilities: string[];
}) {
    const [term, setTerm] = useState('');
    const [people, setPeople] = useState<PersonHit[]>([]);
    const [cursor, setCursor] = useState(0);
    const requestId = useRef(0);

    const canSearchPeople = abilities.includes('core::person.view');

    useEffect(() => {
        if (!open) {
            return;
        }

        const trimmed = term.trim();

        if (!canSearchPeople || trimmed.length < 2) {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setPeople([]);

            return;
        }

        const current = ++requestId.current;
        const timeout = setTimeout(async () => {
            const response = await fetch(
                searchPeople({ query: { q: trimmed } }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok || current !== requestId.current) {
                return;
            }

            const body = (await response.json()) as { data: PersonHit[] };

            setPeople(body.data);
        }, 200);

        return () => clearTimeout(timeout);
    }, [term, open, canSearchPeople]);

    const results = useMemo<Result[]>(() => {
        const needle = term.trim().toLowerCase();
        const pages = visibleAdminSections(abilities).flatMap((section) =>
            section.pages.map((page) => ({
                key: `page-${page.title}`,
                kind: 'Halaman',
                label: page.title,
                hint: section.title === page.title ? undefined : section.title,
                url: toUrl(page.href),
                leading: <page.icon className="size-4" />,
            })),
        );
        const matchedPages = needle
            ? pages.filter((page) =>
                  `${page.label} ${page.hint ?? ''}`
                      .toLowerCase()
                      .includes(needle),
              )
            : pages;
        const hits = people.map((person) => ({
            key: `person-${person.id}`,
            kind: 'Orang',
            label: person.name,
            hint:
                [
                    person.nik,
                    person.birth_date
                        ? `Lahir ${formatDate(new Date(`${person.birth_date}T00:00:00`))}`
                        : null,
                    person.phone_masked,
                ]
                    .filter(Boolean)
                    .join(' · ') || undefined,
            url: showPerson(person.id).url,
            leading: <PersonAvatar name={person.name} size="sm" />,
        }));

        return [...hits, ...matchedPages].slice(0, 10);
    }, [term, people, abilities]);

    function go(result: Result | undefined) {
        if (!result) {
            return;
        }

        onOpenChange(false);
        router.visit(result.url);
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);

                if (!next) {
                    setTerm('');
                    setPeople([]);
                    setCursor(0);
                }
            }}
        >
            <DialogContent
                className="top-[12%] translate-y-0 gap-0 p-0 sm:max-w-xl"
                showCloseButton={false}
            >
                <DialogTitle className="sr-only">Cari</DialogTitle>
                <DialogDescription className="sr-only">
                    Cari halaman, nama, NIK, atau nomor HP
                </DialogDescription>

                <div className="p-4">
                    <div className="flex h-12 items-center gap-3 rounded-full border border-neutral-300 px-5 transition-colors focus-within:border-foreground focus-within:ring-2 focus-within:ring-ring">
                        <Search className="size-4 shrink-0 text-muted-foreground" />
                        <input
                            autoFocus
                            value={term}
                            onChange={(event) => {
                                setTerm(event.target.value);
                                setCursor(0);
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    setCursor((value) =>
                                        Math.min(value + 1, results.length - 1),
                                    );
                                } else if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    setCursor((value) =>
                                        Math.max(value - 1, 0),
                                    );
                                } else if (event.key === 'Enter') {
                                    event.preventDefault();
                                    go(results[cursor]);
                                }
                            }}
                            placeholder="Cari halaman, nama, NIK, atau nomor HP…"
                            aria-label="Pencarian cepat"
                            className="w-full bg-transparent text-base outline-none placeholder:text-neutral-500"
                        />
                        <kbd className="rounded-md bg-neutral-100 px-2 py-1 text-xs font-semibold text-muted-foreground dark:bg-neutral-700/50">
                            ESC
                        </kbd>
                    </div>
                </div>

                <div className="max-h-[50vh] overflow-y-auto px-2 pb-3">
                    {results.length === 0 && (
                        <p className="py-10 text-center text-[15px] text-muted-foreground">
                            Tidak ada hasil untuk “{term}”
                        </p>
                    )}
                    {results.map((result, index) => (
                        <button
                            key={result.key}
                            type="button"
                            onMouseEnter={() => setCursor(index)}
                            onClick={() => go(result)}
                            className={cn(
                                'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left outline-none',
                                index === cursor
                                    ? 'bg-lime-100 dark:bg-lime-400/15'
                                    : 'hover:bg-accent',
                            )}
                        >
                            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-neutral-100 dark:bg-neutral-700/50">
                                {result.leading ?? (
                                    <UserRound className="size-4" />
                                )}
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                <span className="font-semibold">
                                    {result.label}
                                </span>
                                {result.hint && (
                                    <span className="ml-2 text-sm text-muted-foreground tabular-nums">
                                        {result.hint}
                                    </span>
                                )}
                            </span>
                            <span className="text-xs font-semibold text-muted-foreground">
                                {result.kind}
                            </span>
                            {index === cursor && (
                                <CornerDownLeft className="size-3.5 text-muted-foreground" />
                            )}
                        </button>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
