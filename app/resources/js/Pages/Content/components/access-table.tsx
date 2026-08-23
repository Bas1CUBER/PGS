import type { AccessMatrix } from '@/Pages/Content/components/types';

export function AccessTable({ matrix }: { matrix: AccessMatrix }) {
    if (!Array.isArray(matrix.columns))
        return <p className="text-muted-foreground">No access matrix configured.</p>;
    return (
        <div data-slot="table-container" className="relative w-full overflow-x-auto">
            <table data-slot="table" className="w-full min-w-[680px] text-left text-sm">
                <thead data-slot="table-header">
                    <tr data-slot="table-row">
                        {matrix.columns.map((column) => (
                            <th
                                key={column}
                                data-slot="table-head"
                                className="border-b px-3 py-3 font-semibold"
                            >
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody data-slot="table-body">
                    {matrix.rows.map((row, index) =>
                        row.section ? (
                            <tr key={`section-${String(index)}`} data-slot="table-row">
                                <th
                                    colSpan={matrix.columns.length}
                                    data-slot="table-head"
                                    className="bg-muted/50 px-3 py-3 text-xs font-semibold tracking-wider uppercase"
                                >
                                    {row.section}
                                </th>
                            </tr>
                        ) : (
                            <tr
                                key={`row-${String(index)}`}
                                data-slot="table-row"
                                className="border-b last:border-0"
                            >
                                {matrix.columns.map((column) => (
                                    <td
                                        key={column}
                                        data-slot="table-cell"
                                        className="px-3 py-3 align-top"
                                    >
                                        {row[column] ?? ''}
                                    </td>
                                ))}
                            </tr>
                        ),
                    )}
                </tbody>
            </table>
        </div>
    );
}
