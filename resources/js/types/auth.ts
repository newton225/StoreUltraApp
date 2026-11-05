export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    role: 'owner' | 'employee' | 'agent';
    is_active: boolean;
    locale: string | null;
    appearance: 'light' | 'dark' | 'system';
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    canViewProfit: boolean;
    canManageSettings: boolean;
    /** The permissions of the profile of the user (the values of the closed catalogue). */
    permissions: string[];
    restrictedToZones: boolean;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
