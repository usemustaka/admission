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
