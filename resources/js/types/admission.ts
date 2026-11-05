export type WaveState = 'upcoming' | 'ongoing' | 'ended';

export type Wave = {
    id: number;
    name: string;
    starts_on: string | null;
    ends_on: string | null;
    is_open: boolean;
    is_accepting: boolean;
    state: WaveState;
    state_label: string;
    days_left: number | null;
    days_until_start: number | null;
    days_since_end: number | null;
    /** Share of the window that has passed, 0-100; null without both dates. */
    progress: number | null;
    rulesets_count: number;
    registrants: {
        total: number;
        by_status: { status: string; label: string; count: number }[];
    };
};

export type RulesetSummary = {
    id: number;
    name: string;
    priority: number;
    is_active: boolean;
    wave_id: number | null;
    organization_id: number | null;
    gender_value: string | null;
    wave: string | null;
    organization: string | null;
    gender: string | null;
    registrants_count: number;
};

export type RulesetOptions = {
    waves: { value: number; label: string }[];
    organizations: { value: number; label: string }[];
    genders: { value: string; label: string }[];
};

export type BillItemRow = {
    id?: number;
    name: string;
    amount: number | string;
};

export type ExamAspectRow = {
    id?: number;
    name: string;
    passing_score: number | string;
};

export type ExamSessionRow = {
    id?: number;
    label: string;
    start_time: string;
    end_time: string;
    quota: number | string;
};

export type ExamDayRow = {
    id?: number;
    date: string;
    sessions: ExamSessionRow[];
};
