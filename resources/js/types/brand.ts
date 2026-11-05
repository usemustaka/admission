export type Brand = {
    name: string;
    organization: string | null;
    tagline: string | null;
    logo_url: string | null;
    show_name: boolean;
    accent: string;
    contact: {
        phone: string | null;
        whatsapp: string | null;
        email: string | null;
        address: string | null;
        website: string | null;
    };
    payment: {
        bank_name: string | null;
        account_number: string | null;
        account_holder: string | null;
        instructions: string | null;
    };
    public: {
        welcome_title: string;
        welcome_description: string;
        footer_text: string | null;
    };
};
