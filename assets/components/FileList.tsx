import { FileArchive, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { formatFileSize } from '@/lib/fileSize';

export interface SelectedFile {
    id: string;
    file: File;
}

interface FileListProps {
    disabled?: boolean;
    files: SelectedFile[];
    onRemove: (id: string) => void;
}

/**
 * Displays selected files and lets users remove individual entries.
 */
export function FileList({ disabled = false, files, onRemove }: FileListProps) {
    if (files.length === 0) {
        return (
            <div className="rounded-xl border bg-muted/40 px-4 py-5 text-sm text-muted-foreground">
                No files selected yet.
            </div>
        );
    }

    const totalBytes = files.reduce((sum, selectedFile) => sum + selectedFile.file.size, 0);

    return (
        <div className="rounded-xl border bg-card shadow-sm">
            <div className="flex items-center justify-between border-b px-4 py-3 text-sm">
                <span className="font-medium">{files.length} selected files</span>
                <span className="text-muted-foreground">{formatFileSize(totalBytes)}</span>
            </div>
            <ul className="divide-y">
                {files.map(({ id, file }) => (
                    <li className="flex items-center gap-3 px-4 py-3" key={id}>
                        <FileArchive
                            className="h-5 w-5 flex-none text-primary"
                            aria-hidden="true"
                        />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">{file.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {formatFileSize(file.size)}
                            </p>
                        </div>
                        <Button
                            aria-label={`Remove ${file.name}`}
                            disabled={disabled}
                            onClick={() => onRemove(id)}
                            size="sm"
                            type="button"
                            variant="ghost"
                        >
                            <X className="h-4 w-4" aria-hidden="true" />
                        </Button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
