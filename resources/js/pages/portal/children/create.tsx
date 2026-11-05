import { Head, Link, useForm } from '@inertiajs/react';
import { Baby, UserRound, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { NikInput } from '@/components/nik-input';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { StickyActions } from '@/components/portal/sticky-actions';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { portal } from '@/routes';
import { store } from '@/routes/portal/children';
import type { Option } from '@/types/people';

type Choice = { value: string | number; label: string };

export default function RegisterChild({
    waves,
    organizations,
    genders,
    relations,
    kinds,
    suggestedRelation,
}: {
    waves: Choice[];
    organizations: Choice[];
    genders: Option[];
    relations: Option[];
    kinds: Option[];
    suggestedRelation: string | null;
}) {
    const form = useForm({
        name: '',
        nik: '',
        gender: '',
        birth_date: '',
        organization_id: '',
        wave_id: waves.length === 1 ? String(waves[0].value) : '',
        relation: suggestedRelation ?? '',
        kind: 'biological',
    });

    if (waves.length === 0) {
        return (
            <>
                <Head title="Daftarkan anak" />
                <div className="grid gap-4">
                    <PageHeader title="Daftarkan anak" />
                    <EmptyState
                        icon={Baby}
                        title="Pendaftaran sedang ditutup"
                        description="Belum ada gelombang yang menerima pendaftar. Coba lagi nanti atau hubungi sekolah ya."
                        action={
                            <Button variant="outline" size="lg" asChild>
                                <Link href={portal()}>Kembali ke beranda</Link>
                            </Button>
                        }
                    />
                </div>
            </>
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store().url);
    }

    const needsKind =
        form.data.relation === 'father' || form.data.relation === 'mother';

    return (
        <>
            <Head title="Daftarkan anak" />
            <form onSubmit={submit} className="grid gap-4">
                <PageHeader
                    title="Daftarkan anak"
                    description="Isi data awalnya dulu. Data lengkap dan foto bisa diisi setelah ini, dan disimpan sebagai draf."
                />

                <SectionCard title="Tentang anak" icon={UserRound}>
                    <div className="grid gap-4">
                        <FormField
                            label="Nama lengkap anak"
                            required
                            hint="Sesuai akta kelahiran"
                            error={form.errors.name}
                        >
                            {(control) => (
                                <Input
                                    autoComplete="off"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="NIK anak"
                            required
                            hint="16 digit angka, lihat di Kartu Keluarga"
                            error={form.errors.nik}
                        >
                            {(control) => (
                                <NikInput
                                    value={form.data.nik}
                                    onValueChange={(nik) =>
                                        form.setData('nik', nik)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Tanggal lahir"
                            error={form.errors.birth_date}
                        >
                            {(control) => (
                                <Input
                                    type="date"
                                    value={form.data.birth_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'birth_date',
                                            e.target.value,
                                        )
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Jenis kelamin"
                            required
                            error={form.errors.gender}
                        >
                            {() => (
                                <ChoiceCardGroup
                                    columns={2}
                                    value={form.data.gender}
                                    onValueChange={(gender) =>
                                        form.setData('gender', gender)
                                    }
                                    options={genders}
                                />
                            )}
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard title="Mau mendaftar ke mana?">
                    <div className="grid gap-4">
                        <FormField
                            label="Lembaga tujuan"
                            required
                            error={form.errors.organization_id}
                        >
                            {(control) => (
                                <OptionSelect
                                    value={form.data.organization_id}
                                    onValueChange={(value) =>
                                        form.setData('organization_id', value)
                                    }
                                    options={organizations}
                                    placeholder="Pilih lembaga"
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Gelombang"
                            required
                            error={form.errors.wave_id}
                        >
                            {(control) => (
                                <OptionSelect
                                    value={form.data.wave_id}
                                    onValueChange={(value) =>
                                        form.setData('wave_id', value)
                                    }
                                    options={waves}
                                    placeholder="Pilih gelombang"
                                    {...control}
                                />
                            )}
                        </FormField>
                    </div>
                </SectionCard>

                <SectionCard title="Kamu sebagai apa?" icon={Users}>
                    <div className="grid gap-4">
                        <FormField
                            label="Hubunganmu dengan anak"
                            required
                            error={form.errors.relation}
                        >
                            {() => (
                                <ChoiceCardGroup
                                    columns={3}
                                    value={form.data.relation}
                                    onValueChange={(relation) =>
                                        form.setData('relation', relation)
                                    }
                                    options={relations}
                                />
                            )}
                        </FormField>
                        {needsKind && (
                            <FormField
                                label="Kandung atau tiri?"
                                required
                                error={form.errors.kind}
                            >
                                {() => (
                                    <ChoiceCardGroup
                                        columns={2}
                                        value={form.data.kind}
                                        onValueChange={(kind) =>
                                            form.setData('kind', kind)
                                        }
                                        options={kinds}
                                    />
                                )}
                            </FormField>
                        )}
                    </div>
                </SectionCard>

                <StickyActions className="flex gap-3">
                    <Button variant="outline" size="lg" asChild>
                        <Link href={portal()}>Batal</Link>
                    </Button>
                    <Button
                        type="submit"
                        size="lg"
                        className="flex-1"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        Daftarkan
                    </Button>
                </StickyActions>
            </form>
        </>
    );
}
