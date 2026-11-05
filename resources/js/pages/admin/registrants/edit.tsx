import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { RegistrantDataForm } from '@/components/registrants/data-form';
import type {
    ChildData,
    DataFormOptions,
    ParentFromServer,
} from '@/components/registrants/data-form';
import { dashboard } from '@/routes';
import { edit as editPerson } from '@/routes/admin/people';
import { index, show } from '@/routes/admin/registrants';
import { update } from '@/routes/admin/registrants/data';
import type { ParentRelationKey } from '@/types/portal';

export default function RegistrantEdit({
    registrant,
    child,
    parents,
    options,
}: {
    registrant: { id: number; child_name: string; person_id: number };
    child: ChildData;
    parents: Record<ParentRelationKey, ParentFromServer>;
    options: DataFormOptions;
}) {
    return (
        <>
            <Head title={`Ubah data ${registrant.child_name}`} />
            <div className="mx-auto grid w-full max-w-3xl gap-6">
                <PageHeader
                    title={`Ubah data ${registrant.child_name}`}
                    description="Panitia bisa melengkapi data yang sama dengan yang diisi orang tua, kapan pun statusnya."
                />
                <RegistrantDataForm
                    action={update(registrant.id).url}
                    backHref={show(registrant.id).url}
                    identityNote="Nama dan NIK anak diubah lewat halaman data orang."
                    identityHref={editPerson(registrant.person_id).url}
                    child={child}
                    parents={parents}
                    options={options}
                    canEdit
                />
            </div>
        </>
    );
}

RegistrantEdit.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pendaftar', href: index() },
        { title: 'Ubah data', href: '#' },
    ],
};
