import type { User } from '@/types';

export const ROLES = {
    Admin: 'admin',
    Focal: 'focal',
    Employee: 'employee',
} as const;

export type RoleValue = (typeof ROLES)[keyof typeof ROLES];

export function isReviewer(user: { role: string } | null | undefined): boolean {
    return user?.role === ROLES.Admin || user?.role === ROLES.Focal;
}

export function isAdmin(user: { role: string } | null | undefined): boolean {
    return user?.role === ROLES.Admin;
}
