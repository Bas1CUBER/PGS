import { contentText } from './lib';
import { RoadmapStatPreview } from './roadmap-stat-preview';
import type { RoadmapBlock } from './types';

interface RoadmapBlockPreviewProps {
    block: RoadmapBlock;
}

export function RoadmapBlockPreview({ block }: RoadmapBlockPreviewProps) {
    if (block.block_type === 'dashboard_stat') return <RoadmapStatPreview block={block} />;

    if (block.block_type === 'heading') {
        return (
            <h3 className="font-dot-gothic text-lg">
                {contentText(
                    block.content,
                    'text',
                    contentText(block.content, 'title', 'Untitled section'),
                )}
            </h3>
        );
    }

    if (block.block_type === 'paragraph') {
        return (
            <p className="text-muted-foreground text-sm leading-6 whitespace-pre-wrap">
                {contentText(
                    block.content,
                    'text',
                    contentText(block.content, 'body', 'No paragraph content yet.'),
                )}
            </p>
        );
    }

    if (block.block_type === 'table') {
        const columns = Array.isArray(block.content.columns)
            ? block.content.columns.filter((column): column is string => typeof column === 'string')
            : [];
        const rows = Array.isArray(block.content.rows)
            ? block.content.rows.filter(
                  (row): row is Record<string, unknown> => typeof row === 'object' && row !== null,
              )
            : [];

        return columns.length > 0 ? (
            <div data-slot="table-container" className="relative w-full overflow-x-auto">
                <table data-slot="table" className="w-full text-left text-sm">
                    <thead data-slot="table-header">
                        <tr data-slot="table-row">
                            {columns.map((column) => (
                                <th
                                    key={column}
                                    data-slot="table-head"
                                    className="border-b px-2 py-2 font-semibold"
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody data-slot="table-body">
                        {rows.map((row, index) => (
                            <tr
                                key={index}
                                data-slot="table-row"
                                className="border-b last:border-0"
                            >
                                {columns.map((column) => (
                                    <td key={column} data-slot="table-cell" className="px-2 py-2">
                                        {typeof row[column] === 'string' ||
                                        typeof row[column] === 'number'
                                            ? String(row[column])
                                            : ''}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        ) : (
            <p className="text-muted-foreground text-sm">
                Table needs a columns array and rows array.
            </p>
        );
    }

    return (
        <p className="text-muted-foreground font-mono text-xs">{JSON.stringify(block.content)}</p>
    );
}
