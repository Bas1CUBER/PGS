export interface User {
    id: number;
    name: string | null;
    email: string;
    role: 'admin' | 'focal' | 'employee';
    office: string | null;
    is_active: boolean;
    email_verified_at?: string;
}

export interface DeadlineState {
    enabled: boolean;
    end_time: string | null;
    message: string | null;
}

export interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        // Nullable: guests see `auth.user = null` (Welcome page).
        user: User | null;
    };
    unreadCount: number;
    deadline: DeadlineState | null;
    flash: FlashMessages;
};
