import type { NodeFormat } from './types';

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
});

const percent = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2,
});

const plain = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 6,
});

export function formatValue(
    value: number,
    format: NodeFormat | undefined,
): string {
    if (!Number.isFinite(value)) {
        return '—';
    }

    switch (format) {
        case 'currency':
            return currency.format(value);
        case 'percent':
            return `${percent.format(value)}%`;
        default:
            return plain.format(value);
    }
}

// A short "as of" for provenance chips — local time, no date when it is today.
export function formatAsOf(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
