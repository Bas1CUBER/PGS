import { ImageUp } from 'lucide-react';
import { type Dispatch, type SetStateAction } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface ImageSectionProps {
    title: string;
    imageUrl: string | null;
    file: File | null;
    setFile: Dispatch<SetStateAction<File | null>>;
    onSubmit: (event: { preventDefault(): void }) => void;
    isPending: (action: string) => boolean;
    canManage: boolean;
}

export function ImageSection({
    title,
    imageUrl,
    file,
    setFile,
    onSubmit,
    isPending,
    canManage,
}: ImageSectionProps) {
    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                </CardHeader>
                <CardContent>
                    {imageUrl !== null ? (
                        <img
                            src={imageUrl}
                            alt={title}
                            className="mx-auto max-h-[70vh] w-auto rounded-lg border"
                        />
                    ) : (
                        <p className="text-muted-foreground py-10 text-center">
                            Image not found.
                        </p>
                    )}
                </CardContent>
            </Card>

            {canManage && (
                <Card>
                    <CardHeader>
                        <CardTitle>Replace image</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={onSubmit}
                            className="flex flex-col gap-3 sm:flex-row sm:items-center"
                        >
                            <Input
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    setFile(e.target.files?.[0] ?? null);
                                }}
                                className="max-w-md"
                            />
                            <Button
                                type="submit"
                                loading={isPending('replace')}
                                loadingText="Uploading"
                                disabled={file === null}
                            >
                                <ImageUp className="size-4" /> Upload
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}
        </>
    );
}
