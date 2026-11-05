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
