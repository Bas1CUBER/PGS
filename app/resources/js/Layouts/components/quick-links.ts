export function quickLinks(user: { role: string }): { title: string; href: string }[] {
    return user.role === 'admin' ? [] : [{ title: 'Survey', href: '/surveys' }];
}
