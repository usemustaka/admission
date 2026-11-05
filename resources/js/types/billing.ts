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
