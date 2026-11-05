/** One row of the staff list. */
export type StaffRow = {
    id: number;
    name: string;
    email: string;
    photo_url: string | null;
    roles: string[];
    is_active: boolean;
    has_two_factor: boolean;
    email_verified: boolean;
    is_self: boolean;
    created_at: string | null;
};

export type StaffDetail = StaffRow & {
    person_id: number | null;
    password_change_required: boolean;
    is_last_super_admin: boolean;
};

/** A role that can be picked for a staff member. */
export type RoleOption = {
    name: string;
    abilities_count: number;
    /** False when the signed-in user may not hand this role out. */
    assignable: boolean;
};

export type RoleRow = {
    id: number;
    name: string;
    users_count: number;
    abilities_count: number;
    is_locked: boolean;
    is_built_in: boolean;
};

export type RoleDetail = RoleRow & { abilities: string[] };

export type AbilityGroup = {
    module: string;
    title: string;
    abilities: {
        value: string;
        label: string;
        /** False when the signed-in user lacks this ability and so cannot change it. */
        held: boolean;
    }[];
};
