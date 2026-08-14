import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const deleteUser = () => {
        destroy(route('profile.destroy', undefined, false), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
            onError: () => passwordInput.current?.focus(),
            onFinish: () => {
                reset();
            },
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section>
            <header className="mb-6">
                <h2 className="text-lg font-semibold">Delete Account</h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Once your account is deleted, all of its resources and data will be permanently
                    deleted. Before deleting your account, please download any data or information
                    that you wish to retain.
                </p>
            </header>

            <Button
                variant="destructive"
                onClick={() => {
                    setConfirmingUserDeletion(true);
                }}
            >
                Delete Account
            </Button>

            <PgsConfirmationDialog
                open={confirmingUserDeletion}
                onOpenChange={(open) => {
                    if (!open) closeModal();
                }}
                title="Delete + password"
                description="Require a second verification step."
                confirmationTitle="Confirm with your password"
                confirmationDescription="Enter your password to permanently delete your account."
                onConfirm={deleteUser}
                loading={processing}
                loadingText="Deleting"
                password={{
                    value: data.password,
                    onChange: (value) => {
                        setData('password', value);
                    },
                    error: errors.password,
                }}
            />
        </section>
    );
}
