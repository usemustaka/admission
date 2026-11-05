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
    parents,
    options,
    canEdit,
}: {
    registrant: PortalRegistrant;
    child: ChildData;
    parents: Record<ParentRelationKey, ParentFromServer>;
    options: DataFormOptions;
    canEdit: boolean;
}) {
    return (
        <>
            <Head title={`Data ${registrant.child.name}`} />
            <div className="grid gap-4">
                <PageHeader
                    title="Lengkapi data anak"
                    description="Boleh diisi sebagian dulu. Datanya disimpan sebagai draf dan bisa dilanjutkan kapan saja."
                />
                <RegistrantDataForm
                    action={update(registrant.id).url}
                    backHref={show(registrant.id).url}
                    identityNote="Nama dan NIK tidak bisa diubah di sini. Kalau ada yang keliru, hubungi sekolah ya."
                    child={child}
                    parents={parents}
                    options={options}
                    canEdit={canEdit}
                    stickyActions
                />
            </div>
        </>
    );
}
