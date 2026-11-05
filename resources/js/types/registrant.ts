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
    payment: PaymentState;
    contact: RegistrantContact | null;
    /** ISO 8601 timestamp. */
    registered_at: string | null;
    can: RegistrantAbilities;
};

export type PaymentState = 'none' | 'unpaid' | 'partial' | 'paid';

export type RegistrantContact = {
    name: string;
    phone: string;
    /** "Kontak utama", or "Yang mendaftarkan" while the NIK is unconfirmed. */
    label: string;
    whatsapp_url: string;
};

export type RegistrantAbilities = {
    manage: boolean;
    accept: boolean;
    revise: boolean;
    announce: boolean;
    place: boolean;
    bypass: boolean;
    /** Placed, and the staff member may undo the placement. */
    cancel_placement: boolean;
};

export type RegistrantQuickViewData = {
    id: number;
    status: string;
    status_label: string;
    needs_identity_review: boolean;
    organization: string;
    wave: string;
    registered_at: string | null;
    child: {
        name: string;
        nik: string | null;
        gender: string | null;
        birth: string | null;
        photo_url: string | null;
    };
    family: {
        id: number;
        name: string;
        relation: string;
        phone: string | null;
        whatsapp_url: string | null;
        is_primary_contact: boolean;
    }[];
    contact: RegistrantContact | null;
    bills: {
        status: PaymentState;
        total: number;
        paid: number;
        pending: number;
        outstanding: number;
        items: {
            id: number;
            type_label: string;
            status: string;
            total: number;
            paid: number;
        }[];
    };
    exam: { session: ChosenSession | null; result: ExamResultData };
    readiness: string[];
    timeline: TimelineEntry[];
    can: RegistrantAbilities;
};

export type RegistrantQueue = {
    position: number;
    total: number;
    previous_id: number | null;
    next_id: number | null;
};

/** The list filters the detail page was opened with, passed on to the queue links, and how the queue is ordered. */
export type RegistrantQueueContext = {
    query: Record<string, string>;
    order: string;
};

export type StatusCounts = Record<string, number> & { all: number };

export type BulkSkipped = { id: number; name: string; reason: string };

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
