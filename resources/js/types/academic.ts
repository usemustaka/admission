export type PeriodRow = {
    id: number;
    name: string;
    sequence: number;
    starts_on: string;
    ends_on: string;
    is_active: boolean;
};

export type YearRow = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_active: boolean;
    students_count: number;
    /** Why the year cannot be deleted right now, or null when it can. */
    delete_blocked: string | null;
    periods: PeriodRow[];
};

export type YearSuggestion = {
    name: string;
    starts_on: string;
    ends_on: string;
    copy_from_id: number | null;
    copy_from_name: string | null;
};

export type OrganizationRow = {
    id: number;
    name: string;
    code: string;
    category: string | null;
    is_active: boolean;
    rulesets_count: number;
    registrants_count: number;
    students_count: number;
    /** Rulesets, registrations, students, or employees refer to it. */
    in_use: boolean;
};

export type StudentRow = {
    id: number;
    person_id: number;
    registrant_id: number | null;
    name: string;
    nik: string | null;
    photo_url: string | null;
    nis: string | null;
    organization: string;
    year: string | null;
    year_id: number | null;
    placed_at: string | null;
};
