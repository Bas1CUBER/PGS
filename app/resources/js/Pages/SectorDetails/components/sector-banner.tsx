import { Card, CardContent } from '@/components/ui/card';
import { legacyImageUrl } from '@/lib/legacy-asset';
import type { SectorDetailPageProps } from './types';

interface SectorBannerProps {
    module: SectorDetailPageProps['module'];
}

export function SectorBanner({ module }: SectorBannerProps) {
    return (
        <Card className="pgs-sector-banner">
            <CardContent className="flex items-center gap-4 p-5 sm:p-6">
                <div className="pgs-sector-logo" aria-hidden="true">
                    <img src={legacyImageUrl(module.logo)} alt="" loading="lazy" decoding="async" />
                </div>
                <div>
                    <p className="pgs-section-kicker">Detailed roadmap</p>
                    <h1 className="text-2xl font-semibold">{module.label}</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {module.pillar_label} · legacy roadmap detail
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
