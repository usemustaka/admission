export type BillItemData = {
    id: number;
    name: string;
    amount: number;
    paid: number;
    pending: number;
    outstanding: number;
};

export type BillData = {
    id: number;
    type: 'registration' | 'settlement';
    type_label: string;
    status: string;
    registrant: { id: number; child_name: string; organization: string };
    total: number;
    paid: number;
    pending: number;
    items: BillItemData[];
};

export type PaymentData = {
    id: number;
    amount: number;
    paid_on: string;
    method: string;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    has_proof: boolean;
    payer: string;
    for: string[];
    created_at: string | null;
};

export type OpenGroup = {
    bill_id: number;
    type_label: string;
    registrant: { id: number; child_name: string; organization: string };
    items: { id: number; name: string; amount: number; outstanding: number }[];
};

/** Item id to the amount typed for it, as text. Missing means not selected. */
export type Selection = Record<number, string>;

/** One row of the admin payments list. */
export type PaymentRow = PaymentData & {
    method_value: string;
    note: string | null;
    items: { name: string; amount: number }[];
};

export type PaymentListFilters = {
    status: string;
    cari: string;
    cara: string;
    dari: string;
    sampai: string;
    urut: 'tanggal' | 'nominal';
    arah: 'asc' | 'desc';
};

export type PaymentSummary = {
    pending_count: number;
    pending_amount: number;
    verified_this_month: number;
    month: { dari: string; sampai: string };
    rejected_count: number;
    /** Still asked by the bill items, counted per item. */
    outstanding: number;
    /** Verified money beyond what bill items asked for ("kelebihan bayar"). */
    overpaid: number;
};

export type PaymentAllocationRow = {
    bill_item_id: number;
    item: string;
    bill: string;
    child: string;
    item_amount: number;
    amount: number;
    /** What the item can still take; only known while the payment is pending. */
    room: number | null;
};

export type PaymentDetail = PaymentData & {
    method_value: string;
    note: string | null;
    proof_is_image: boolean;
    created_by: string | null;
    verified_by: string | null;
    verified_at: string | null;
};

/** What the quick view of the list loads for one payment. */
export type PaymentQuickViewData = {
    payment: PaymentDetail;
    allocations: PaymentAllocationRow[];
    can_verify_as_submitted: boolean;
    can: { verify: boolean };
    queue: { next_id: number | null; left: number };
};

/** What the signed-in staff member may do with the payment on its detail page. */
export type PaymentDetailCan = {
    verify: boolean;
    /** Take back a verified payment. */
    void: boolean;
    /** Put a rejected payment back in the queue. */
    reopen: boolean;
    /** The payment is verified, so it has a receipt. */
    receipt: boolean;
};

/** A line from the system's own audit trail about one payment. */
export type PaymentHistoryEntry = {
    id: number;
    body: string;
    created_at: string | null;
};

/** A payment of the payer that still waits for verification, shown as a warning when recording another. */
export type PendingPaymentHint = {
    id: number;
    amount: number;
    paid_on: string;
    method: string;
};

export type ReceiptLine = {
    child: string;
    organization: string;
    bill: string;
    item: string;
    amount: number;
};

export type ReceiptData = {
    number: string;
    paid_on: string;
    payer: string;
    method: string;
    lines: ReceiptLine[];
    total: number;
    total_words: string;
    note: string | null;
    verified_by: string | null;
    verified_at: string | null;
};

export type ReconciliationMethod = {
    value: string;
    label: string;
    count: number;
    total: number;
};

export type ReconciliationVerifiedRow = PaymentRow & {
    verified_by: string | null;
    verified_time: string | null;
};

export type ReconciliationPendingRow = PaymentRow & {
    age_days: number;
};

export type ReconciliationData = {
    date: string;
    today: string;
    methods: ReconciliationMethod[];
    verified: {
        count: number;
        total: number;
        transfer_total: number;
        rows: ReconciliationVerifiedRow[];
    };
    pending: {
        count: number;
        total: number;
        rows: ReconciliationPendingRow[];
    };
    list_limit: number;
    can: { export: boolean };
};
