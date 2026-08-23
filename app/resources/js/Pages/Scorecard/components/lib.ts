export function valueKey(measureId: number, yearId: number): string {
    return `${String(measureId)}:${String(yearId)}`;
}
