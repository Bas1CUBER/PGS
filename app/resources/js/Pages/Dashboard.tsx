import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { Bell, FileText, Users } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

const demoStats = [
    { label: 'Unread notifications', value: 0, icon: Bell },
    { label: 'Deliverables', value: 0, icon: FileText },
    { label: 'Active users', value: 0, icon: Users },
];

export default function Dashboard() {
    const { auth, unreadCount } = usePage().props;
    const user = auth.user;

    if (user === null) {
        return null;
    }

    const stats = [{ ...demoStats[0], value: unreadCount }, demoStats[1], demoStats[2]];

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Welcome back, {user.name ?? user.email}
                        </h1>
                        <p className="text-muted-foreground">
                            Performance Governance System — TRC DOH
                        </p>
                    </div>
                    <Badge variant="outline" className="capitalize">
                        {user.role}
                    </Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {stats.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{stat.label}</CardTitle>
                                <stat.icon className="text-muted-foreground size-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stat.value}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Getting started</CardTitle>
                        <CardDescription>
                            Module dashboards arrive as the migration progresses (Phase 5).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-muted-foreground text-sm">
                            You're logged in. Use the sidebar to navigate; your notifications and
                            submission deadlines will appear here as modules are ported.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
