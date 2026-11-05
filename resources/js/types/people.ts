export type PersonSummary = {
    id: number;
    name: string;
    nik: string | null;
    gender: string | null;
    phone: string | null;
    photo_url: string | null;
};

export type PersonDetail = PersonSummary & {
    gender_value: string | null;
    birth_place: string | null;
    birth_date: string | null;
    email: string | null;
    family_card_number: string | null;
    is_alive: boolean;
};

/** One row of the people list. */
export type PersonRow = PersonSummary & {
    roles: string[];
    nik_complete: boolean;
    has_contact: boolean;
    created_at: string | null;
};

export type PersonRegistration = {
    id: number;
    child_name: string;
    is_self: boolean;
    organization: string;
    wave: string;
    status: string;
    status_label: string;
};

export type PersonQuickViewData = {
    person: {
        id: number;
        name: string;
        nik: string | null;
        gender: string | null;
        birth: string | null;
        phone: string | null;
        email: string | null;
        is_alive: boolean;
        photo_url: string | null;
    };
    roles: string[];
    primary_contact: {
        id: number;
        name: string;
        relation: string;
        phone: string | null;
    } | null;
    parents: {
        id: number;
        name: string;
        photo_url: string | null;
        phone: string | null;
        relation: string;
        is_primary_contact: boolean;
    }[];
    dependents: {
        id: number;
        name: string;
        photo_url: string | null;
        relation: string;
    }[];
    notes: {
        id: number;
        body: string;
        urgency: NoteItem['urgency'];
        urgency_label: string;
        author: string;
        created_at: string | null;
    }[];
    registrations: PersonRegistration[];
    can_view_registrations: boolean;
};

export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type ParentLink = {
    id: number;
    person: PersonSummary;
    relation: string;
    relation_value: string;
    kind: string | null;
    is_primary_contact: boolean;
};

export type ChildLink = {
    id: number;
    person: PersonSummary;
    relation: string;
};

export type NoteItem = {
    id: number;
    body: string;
    urgency: 'low' | 'normal' | 'high' | 'critical';
    visibility: 'public' | 'private';
    shared_with_family: boolean;
    created_at: string | null;
    author: { name: string; photo_url: string | null };
    is_mine: boolean;
    shared_with: { type: string; id: number; name: string }[];
    user_ids: number[];
    role_ids: number[];
    can_update: boolean;
    can_delete: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** Someone who looks like the person being added or linked, shown for the "Kemungkinan sudah ada" check. */
export type DuplicateCandidate = {
    id: number;
    name: string;
    birth_date: string | null;
    phone_masked: string | null;
    has_nik: boolean;
    has_family_card: boolean;
    reasons: string[];
};
