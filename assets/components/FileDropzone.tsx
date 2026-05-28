import { UploadCloud } from 'lucide-react';
import { useId, useState } from 'react';

import { cn } from '@/lib/utils';

interface FileDropzoneProps {
    disabled?: boolean;
    onFilesAdded: (files: File[]) => void;
}

/**
 * Drag-and-drop and file-picker control for adding files to an archive.
 */
export function FileDropzone({ disabled = false, onFilesAdded }: FileDropzoneProps) {
    const inputId = useId();
    const [isDragging, setIsDragging] = useState(false);

    function addFiles(fileList: FileList | null): void {
        if (!fileList || disabled) {
            return;
        }

        onFilesAdded(Array.from(fileList));
    }

    return (
        <label
            className={cn(
                'flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed bg-card px-6 py-10 text-center shadow-sm transition-colors focus-within:border-primary focus-within:outline-none focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                isDragging
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-primary/60',
                disabled && 'cursor-not-allowed opacity-60',
            )}
            htmlFor={inputId}
            onDragEnter={(event) => {
                event.preventDefault();
                setIsDragging(true);
            }}
            onDragLeave={(event) => {
                event.preventDefault();
                setIsDragging(false);
            }}
            onDragOver={(event) => {
                event.preventDefault();
            }}
            onDrop={(event) => {
                event.preventDefault();
                setIsDragging(false);
                addFiles(event.dataTransfer.files);
            }}
        >
            <input
                className="sr-only"
                disabled={disabled}
                id={inputId}
                multiple
                onChange={(event) => {
                    addFiles(event.currentTarget.files);
                    event.currentTarget.value = '';
                }}
                type="file"
            />
            <UploadCloud className="mb-4 h-10 w-10 text-primary" aria-hidden="true" />
            <span className="text-lg font-semibold">Drop files here or click to browse</span>
            <span className="mt-2 max-w-md text-sm text-muted-foreground">
                Add all files you want included in the ZIP. You can drop more files later or remove
                any file before downloading.
            </span>
        </label>
    );
}
