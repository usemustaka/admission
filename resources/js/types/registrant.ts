export type RegistrantRow = {
    id: number;
    child: { name: string; nik: string | null; photo_url: string | null };
    organization: string;
    wave: string;
    status: string;
    status_label: string;
    needs_identity_review: boolean;
    exam_session: string | null;
    exam_date: string | null;
    passed: boolean | null;
    registered_at: string | null;
};

export type ExamResultData = {
    aspects: {
        name: string;
        passing_score: number;
        score: number | null;
        passed: boolean | null;
    }[];
    complete: boolean;
    passed: boolean | null;
};

export type TimelineEntry = {
    id: number;
    from: string | null;
    to: string;
    to_label: string;
    note: string | null;
    by: string | null;
    is_bypass: boolean;
    at: string;
};

export type ChosenSession = { date: string; label: string };
