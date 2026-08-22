import { CalendarClock } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ScheduleEntry } from './types';

interface ScheduleCardProps {
    schedule: ScheduleEntry[] | null;
}

export function ScheduleCard({ schedule }: ScheduleCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <CalendarClock className="size-4" />
                    Schedule
                </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                {schedule === null ? (
                    <p className="text-muted-foreground px-6 pb-6 text-sm">
                        No schedule for this pillar.
                    </p>
                ) : schedule.length === 0 ? (
                    <p className="text-muted-foreground px-6 pb-6 text-sm">
                        No schedule entries.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {schedule.map((entry) => (
                            <li key={entry.id} className="px-6 py-3">
                                <p className="text-sm font-medium">
                                    {entry.category} - {entry.year} ({entry.month})
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {entry.description}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
