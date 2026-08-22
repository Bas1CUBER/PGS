import { Card, CardContent } from '@/components/ui/card';
import { legacyImageUrl } from '@/lib/legacy-asset';
import type { SectorShowPageProps } from './types';

interface SectorBannerProps {
    module: SectorShowPageProps['module'];
}

export function SectorBanner({ module }: SectorBannerProps) {
    return (
        <Card className="pgs-sector-banner">
            <CardContent className="flex items-center gap-4 p-5 sm:p-6">
                <div className="pgs-sector-logo" aria-hidden="true">
                    <img src={legacyImageUrl(module.logo)} alt="" />
                </div>
                <div>
                    <p className="pgs-section-kicker">Sector roadmap</p>
                    <h1 className="text-2xl font-semibold">{module.label}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Indicators, progress tracking, schedules, and detailed roadmaps.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
