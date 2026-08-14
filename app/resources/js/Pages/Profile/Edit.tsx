import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Profile</h2>}
        >
            <Head title="Profile" />

            <div className="mx-auto max-w-2xl space-y-6">
                <Card>
                    <CardContent>
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <UpdatePasswordForm />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <DeleteUserForm />
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
