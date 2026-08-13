import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

interface SurveyRow {
    id: number;
    title: string;
    url: string | null;
    status: string;
    created_at: string;
    done: number;
}

interface SurveysPageProps extends PageProps {
    surveys: SurveyRow[];
}

export default function SurveysIndex({ surveys }: SurveysPageProps) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl leading-tight font-semibold">Surveys</h2>}
        >
            <Head title="Surveys" />

            <div className="mx-auto max-w-3xl space-y-4">
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
                            <Badge variant={survey.done === 1 ? 'success' : 'outline'}>
                                {survey.done === 1 ? 'Done' : 'Open'}
                            </Badge>
                        </CardHeader>
                        <CardContent className="flex gap-2">
                            {survey.done === 1 ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        router.post(`/surveys/${String(survey.id)}/done`);
                                    }}
                                >
                                    <CheckCircle2 className="size-4" />
                                    Mark again
                                </Button>
                            ) : (
                                <>
                                    {survey.url !== null && (
                                        <Button asChild size="sm">
                                            <a href={survey.url} target="_blank" rel="noreferrer">
                                                Take survey
                                                <ExternalLink className="size-4" />
                                            </a>
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            router.post(`/surveys/${String(survey.id)}/done`);
                                        }}
                                    >
                                        Mark as done
                                    </Button>
                                </>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
