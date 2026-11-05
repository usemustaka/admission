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
    is_alive: boolean;
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
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
