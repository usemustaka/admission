import { Head, router } from '@inertiajs/react';
import { CalendarRange, Pencil, Plus } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { WaveFormDialog } from '@/components/admission/wave-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { index, update } from '@/routes/admin/waves';
import type { Wave } from '@/types/admission';

function period(wave: Wave): string {
    if (!wave.starts_on && !wave.ends_on) {
        return 'Tanpa batas tanggal';
    }

    return [
        wave.starts_on ? formatDate(wave.starts_on) : 'Sejak awal',
        wave.ends_on ? formatDate(wave.ends_on) : 'tanpa batas akhir',
    ].join(' s.d. ');
}

export default function WavesIndex({
    waves,
    can,
}: {
    waves: Wave[];
    can: { manage: boolean };
}) {
    function toggle(wave: Wave, isOpen: boolean) {
        router.put(
            update(wave.id).url,
            {
                name: wave.name,
                starts_on: wave.starts_on ?? '',
                ends_on: wave.ends_on ?? '',
                is_open: isOpen,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Gelombang" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <PageHeader
                    icon={CalendarRange}
                    title="Gelombang pendaftaran"
                    description="Kelompok pendaftar. Pendaftaran dibuka sepanjang tahun, jadi gelombang tidak terikat tahun ajaran."
                    actions={
                        can.manage && (
                            <WaveFormDialog
                                trigger={
                                    <Button>
                                        <Plus />
                                        Gelombang baru
                                    </Button>
                                }
                            />
                        )
                    }
                />

                {waves.length === 0 ? (
                    <EmptyState
                        icon={CalendarRange}
                        title="Belum ada gelombang"
                        description="Buat gelombang pertama supaya orang tua bisa mulai mendaftarkan anak."
                    />
                ) : (
                    <ul className="grid gap-3">
                        {waves.map((wave) => (
                            <li
                                key={wave.id}
                                className="flex flex-wrap items-center gap-4 rounded-2xl border bg-card p-4 shadow-xs"
                            >
                                <div className="grid min-w-0 flex-1 gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-lg font-semibold tracking-tight">
                                            {wave.name}
                                        </h2>
                                        {wave.is_accepting ? (
                                            <Badge variant="success">
                                                Sedang menerima pendaftar
                                            </Badge>
                                        ) : wave.is_open ? (
                                            <Badge variant="warning">
                                                Terbuka, di luar tanggalnya
                                            </Badge>
                                        ) : (
                                            <Badge variant="muted">
                                                Ditutup
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {period(wave)} · {wave.rulesets_count}{' '}
                                        aturan
                                    </p>
                                </div>

                                {can.manage && (
                                    <div className="flex items-center gap-3">
                                        <label className="flex items-center gap-2 text-sm font-medium">
                                            <Switch
                                                checked={wave.is_open}
                                                onCheckedChange={(checked) =>
                                                    toggle(wave, checked)
                                                }
                                                aria-label={`Buka ${wave.name}`}
                                            />
                                            Buka
                                        </label>
                                        <WaveFormDialog
                                            wave={wave}
                                            trigger={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Pencil />
                                                    Ubah
                                                </Button>
                                            }
                                        />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

WavesIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Gelombang', href: index() },
    ],
};
