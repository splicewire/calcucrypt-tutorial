import { usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';

type SiteAccent = {
    accent: string;
    accentForeground: string;
    darkAccent: string;
    darkAccentForeground: string;
};

type AccentStyle = CSSProperties &
    Partial<
        Record<
            | '--desk-accent'
            | '--desk-accent-foreground'
            | '--desk-dark-accent'
            | '--desk-dark-accent-foreground',
            string
        >
    >;

/** Map the published host Theme into console content, inside the packaged chrome. */
export function ServiceDeskTheme({ children }: { children: ReactNode }) {
    const site = usePage<{ theme?: { site?: Partial<SiteAccent> } }>().props
        .theme?.site;
    const style: AccentStyle = {
        '--desk-accent': site?.accent,
        '--desk-accent-foreground': site?.accentForeground,
        '--desk-dark-accent': site?.darkAccent,
        '--desk-dark-accent-foreground': site?.darkAccentForeground,
    };

    return (
        <div className="service-desk-theme" style={style}>
            {children}
        </div>
    );
}
