import { Head } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { RegistrantDataForm } from '@/components/registrants/data-form';
import type {
    ChildData,
    DataFormOptions,
    ParentFromServer,
} from '@/components/registrants/data-form';
import { show } from '@/routes/portal/children';
import { update } from '@/routes/portal/children/data';
import type { ParentRelationKey, PortalRegistrant } from '@/types/portal';

export default function ChildDataPage({
    registrant,
    child,
    formalPhotoUrl,
    parents,
    options,
    canEdit,
}: {
    registrant: PortalRegistrant;
    child: ChildData;
    formalPhotoUrl: string | null;
    parents: Record<ParentRelationKey, ParentFromServer>;
    options: DataFormOptions;
    canEdit: boolean;
}) {
    return (
        <>
            <Head title={`Data ${registrant.child.name}`} />
            <div className="mx-auto grid w-full max-w-3xl gap-6 px-4 py-8">
                <PageHeader
                    title="Lengkapi data anak"
                    description="Boleh diisi sebagian dulu, datanya disimpan sebagai draf dan bisa dilanjutkan kapan saja."
                />
                <RegistrantDataForm
                    action={update(registrant.id).url}
                    backHref={show(registrant.id).url}
                    identityNote="Nama dan NIK tidak bisa diubah di sini. Kalau ada yang keliru, hubungi sekolah ya."
                    child={child}
                    formalPhotoUrl={formalPhotoUrl}
                    parents={parents}
                    options={options}
                    canEdit={canEdit}
                />
            </div>
        </>
    );
}
