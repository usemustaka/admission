export type ExamSessionInfo = {
    id: number;
    /** ISO date, e.g. 2026-11-04. */
    date: string;
    /** Session label and time, e.g. "Sesi 1 · 08:00 s.d. 10:00". */
    label: string;
    /** Null when the schedule is for every organization. */
    organization: string | null;
    /** Null when the schedule is for every wave. */
    wave: string | null;
    quota: number;
    taken: number;
    /** Registrations that still lack at least one score. */
    missing: number;
    passed: number;
    failed: number;
};

export type ExamSessionRow = ExamSessionInfo & {
    /** The session is today or past and some registrations still lack scores. */
    needs_grading: boolean;
};

export type ExamFilters = {
    tanggal: string;
    sampai: string;
    lembaga: string;
    perlu_dinilai: boolean;
};

export type RosterPaymentState = 'none' | 'unpaid' | 'partial' | 'paid';

export type RosterRow = {
    id: number;
    name: string;
    nik: string | null;
    nisn: string | null;
    photo_url: string | null;
    needs_identity_review: boolean;
    organization: string;
    payment: RosterPaymentState;
    contact: { name: string; phone: string; whatsapp_url: string } | null;
};

export type GridAspect = { name: string; passing_score: number };

export type GridRow = {
    id: number;
    name: string;
    nik: string | null;
    photo_url: string | null;
    status_label: string;
    /** The result was already announced, so a change needs a confirmation. */
    announced: boolean;
    /** The aspects of this registration's own ruleset copy. */
    aspects: GridAspect[];
    scores: (number | null)[];
};
