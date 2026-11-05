export type PortalRegistrant = {
    id: number;
    child: { id: number; name: string; photo_url: string | null };
    organization: string;
    wave: string;
    status: string;
    status_label: string;
    steps: string[];
    current_step: string;
    can_edit: boolean;
    /** Why the committee sent the data back, while it waits for the parent. */
    revision_reason: string | null;
    /** The committee is still confirming the child's NIK, so no details are shown. */
    identity_pending: boolean;
};

export type Completeness = { filled: number; total: number };

export type ParentFieldsData = {
    name: string;
    nik: string;
    phone: string;
    job: string;
    last_education: string;
    monthly_income: string;
    address: string;
    is_alive: boolean;
    kind: string;
};

export type ParentRelationKey = 'father' | 'mother' | 'guardian';
