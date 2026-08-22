import { Download, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ModuleTemplate } from './types';

interface TemplatesCardProps {
    templates: ModuleTemplate[];
    canManageTemplates: boolean;
    isTemplateDeletePending: boolean;
    onRemoveTemplate: (template: ModuleTemplate) => void;
}

export function TemplatesCard({
    templates,
    canManageTemplates,
    isTemplateDeletePending,
    onRemoveTemplate,
}: TemplatesCardProps) {
    if (templates.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Templates and process guides</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 lg:grid-cols-2">
                {templates.map((template) => (
                    <div key={template.file} className="kinetic-template-card">
                        <div className="flex items-center justify-between gap-3">
                            <p className="font-medium">{template.label}</p>
                            <Button asChild variant="outline" size="sm">
                                <a href={template.url} target="_blank" rel="noreferrer">
                                    <Download className="size-4" /> Open
                                </a>
                            </Button>
                            {canManageTemplates &&
                                template.source === 'managed' &&
                                template.id !== undefined && (
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label={`Remove ${template.label}`}
                                        loading={isTemplateDeletePending}
                                        onClick={() => {
                                            if (template.id !== undefined) {
                                                onRemoveTemplate(template);
                                            }
                                        }}
                                        className="text-destructive"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                        </div>
                        {template.preview && (
                            <iframe
                                src={template.url}
                                title={template.label}
                                className="mt-3 h-80 w-full rounded-lg border"
                            />
                        )}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
