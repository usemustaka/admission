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
    /** Rupiah of payment proofs waiting for verification; 0 when there are none. */
    payment_pending: number;
    contact: RegistrantContact | null;
    /** ISO 8601 timestamp. */
    registered_at: string | null;
    /** ISO 8601 timestamp of the last time the data was sent in for checking; null if never. */
    submitted_at: string | null;
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
    messages: RegistrantMessage[];
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
    /** Why announcing now would be early: missing scores, not passed, an open bill. */
    announce_warnings: string[];
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

export type BulkAction = 'accept' | 'announce' | 'revise';

/** What a bulk run would do, from the server, without changing anything. */
export type BulkPreview = {
    action: BulkAction;
    /** Every registrant the selection stands for. */
    total: number;
    /** How many of them one run handles (the first `cap` when `capped`). */
    processed: number;
    capped: boolean;
    cap: number;
    eligible: number;
    skipped: number;
    reasons: { reason: string; count: number }[];
    /** Only for announcing: how many of the registrants in the right status are incomplete, and how. */
    readiness: {
        missing_scores: number;
        not_passed: number;
        unsettled: number;
        incomplete: number;
    } | null;
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

/** A WhatsApp message with its template filled in, offered when it fits the registration's situation. */
export type RegistrantMessage = {
    kind: string;
    label: string;
    url: string;
};
