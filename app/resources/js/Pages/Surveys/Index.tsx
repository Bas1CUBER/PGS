import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Archive,
    CheckCircle2,
    ClipboardList,
    ExternalLink,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { PgsConfirmationDialog } from '@/components/pgs-confirmation-dialog';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { usePendingAction } from '@/hooks/use-pending-action';

interface SurveyRow {
    id: number;
    title: string;
    url: string;
    status: string;
    created_at: string;
    done?: number;
    archived_at?: string;
    completion_count: number;
}

interface SurveysPageProps extends PageProps {
    surveys: SurveyRow[];
    archived: SurveyRow[];
    canManage: boolean;
}

export default function SurveysIndex({ surveys, archived, canManage }: SurveysPageProps) {
    const { isPending, start, finish } = usePendingAction();
    const [title, setTitle] = useState('');
    const [url, setUrl] = useState('');
    const [editing, setEditing] = useState<SurveyRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<SurveyRow | null>(null);

    function markDone(id: number): void {
        const action = `done:${String(id)}`;
        start(action);
        router.post(
            `/surveys/${String(id)}/done`,
            {},
            {
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function createSurvey(e: { preventDefault(): void }): void {
        e.preventDefault();
        start('create');
        router.post(
            '/surveys',
            { title, url },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTitle('');
                    setUrl('');
                },
                onFinish: () => {
                    finish('create');
                },
            },
        );
    }

    function saveEdit(): void {
        if (editing === null) return;
        start('save');
        router.put(
            `/surveys/${String(editing.id)}`,
            { title, url },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditing(null);
                },
                onFinish: () => {
                    finish('save');
                },
            },
        );
    }

    function archiveSurvey(survey: SurveyRow): void {
        const action = `archive:${String(survey.id)}`;
        start(action);
        router.post(
            `/surveys/${String(survey.id)}/archive`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    finish(action);
                },
            },
        );
    }

    function deleteSurvey(): void {
        if (deleteTarget === null) return;
        start('delete');
        router.delete(`/surveys/${String(deleteTarget.id)}`, {
            preserveScroll: true,
            onFinish: () => {
                finish('delete');
                setDeleteTarget(null);
            },
        });
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Surveys</h2>}
        >
            <Head title="Surveys" />

            <div className="mx-auto max-w-4xl space-y-6">
                {canManage && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Publish a survey</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={createSurvey}
                                className="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="survey-title">Title</Label>
                                    <Input
                                        id="survey-title"
                                        value={title}
                                        onChange={(e) => {
                                            setTitle(e.target.value);
                                        }}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="survey-url">Survey URL</Label>
                                    <Input
                                        id="survey-url"
                                        type="url"
                                        value={url}
                                        onChange={(e) => {
                                            setUrl(e.target.value);
                                        }}
                                        placeholder="https://..."
                                        required
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    loading={isPending('create')}
                                    loadingText="Publishing"
                                >
                                    <Plus className="size-4" /> Publish
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="pgs-section-kicker">Active surveys</p>
                            <h3 className="text-lg font-semibold">Available to the workspace</h3>
                        </div>
                        <Badge variant="outline">{surveys.length} open</Badge>
                    </div>
                    {surveys.length === 0 && (
                        <Card>
                            <CardContent className="text-muted-foreground py-10 text-center">
                                <ClipboardList className="mx-auto mb-2 size-8" />
                                No surveys available.
                            </CardContent>
                        </Card>
                    )}
                    {surveys.map((survey) => (
                        <Card key={survey.id} className={cn(survey.done === 1 && 'opacity-80')}>
                            <CardHeader className="flex flex-row items-start justify-between gap-3">
                                <div>
                                    <CardTitle>{survey.title}</CardTitle>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Published {new Date(survey.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant={survey.done === 1 ? 'success' : 'outline'}>
                                        {survey.done === 1 ? 'Done' : 'Open'}
                                    </Badge>
                                    {canManage && (
                                        <Badge variant="secondary">
                                            {survey.completion_count} completed
                                        </Badge>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                {survey.done === 1 ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            markDone(survey.id);
                                        }}
                                        loading={isPending(`done:${String(survey.id)}`)}
                                        loadingText="Saving"
                                    >
                                        <CheckCircle2 className="size-4" /> Mark again
                                    </Button>
                                ) : (
                                    <>
                                        <Button asChild size="sm">
                                            <a href={survey.url} target="_blank" rel="noreferrer">
                                                Take survey <ExternalLink className="size-4" />
                                            </a>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                markDone(survey.id);
                                            }}
                                            loading={isPending(`done:${String(survey.id)}`)}
                                            loadingText="Saving"
                                        >
                                            Mark as done
                                        </Button>
                                    </>
                                )}
                                {canManage && (
                                    <div className="ml-auto flex gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Edit survey"
                                            onClick={() => {
                                                setEditing(survey);
                                                setTitle(survey.title);
                                                setUrl(survey.url);
                                            }}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Archive survey"
                                            onClick={() => {
                                                archiveSurvey(survey);
                                            }}
                                            loading={isPending(`archive:${String(survey.id)}`)}
                                        >
                                            <Archive className="size-4" />
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>

                {canManage && archived.length > 0 && (
                    <section className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-semibold">Archived surveys</h3>
                            <Badge variant="outline">{archived.length}</Badge>
                        </div>
                        {archived.map((survey) => (
                            <Card key={survey.id} className="opacity-75">
                                <CardHeader className="flex flex-row items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>{survey.title}</CardTitle>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Archived{' '}
                                            {survey.archived_at
                                                ? new Date(survey.archived_at).toLocaleDateString()
                                                : ''}
                                        </p>
                                    </div>
                                    <Badge variant="secondary">
                                        {survey.completion_count} completed
                                    </Badge>
                                </CardHeader>
                                <CardContent className="flex justify-end">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={() => {
                                            setDeleteTarget(survey);
                                        }}
                                    >
                                        <Trash2 className="size-4" /> Delete permanently
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                )}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) setEditing(null);
                }}
            >
                <DialogContent className="pgs-modal-form-dialog">
                    <DialogHeader>
                        <DialogTitle>Edit survey</DialogTitle>
                    </DialogHeader>
                    <DialogBody className="space-y-3">
                        <div className="space-y-2">
                            <Label htmlFor="edit-survey-title">Title</Label>
                            <Input
                                id="edit-survey-title"
                                value={title}
                                onChange={(e) => {
                                    setTitle(e.target.value);
                                }}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-survey-url">Survey URL</Label>
                            <Input
                                id="edit-survey-url"
                                type="url"
                                value={url}
                                onChange={(e) => {
                                    setUrl(e.target.value);
                                }}
                            />
                        </div>
                    </DialogBody>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setEditing(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button onClick={saveEdit} loading={isPending('save')} loadingText="Saving">
                            Save changes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <PgsConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteTarget(null);
                }}
                title="Delete survey"
                description="This action permanently removes the archived survey."
                confirmationTitle="Confirm survey deletion"
                confirmationDescription="The survey and its completion history will be removed."
                onConfirm={deleteSurvey}
                loading={isPending('delete')}
                loadingText="Deleting"
            />
        </AuthenticatedLayout>
    );
}
