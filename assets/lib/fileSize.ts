const formatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

/**
 * Formats a byte count for display in the selected-file list.
 */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let size = bytes / 1024;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${formatter.format(size)} ${units[unitIndex]}`;
}
