// PROTOTYPE CHROME — host-owned rail/settings nav DATA for @splicewire/beam-ux-prototype.
// This is one of the few host-owned remainders: the generic chrome (Gallery, VariantBar,
// SettingsFrame, PrototypeDesk) ships from the package; only this nav data + the brand/nav-injecting
// wrappers live here. Edit freely — replace the sample groups with your app's real rail.
import type { NavGroup, NavTab } from '@splicewire/beam-ux-prototype';
import { Home, Settings, SlidersHorizontal, Sparkles } from 'lucide-react';

// The rail groups the PrototypeDesk brand wrapper injects (realm → preset).
export const nav: NavGroup[] = [
    {
        label: 'Workspace',
        items: [
            { key: 'home', label: 'Home', icon: Home },
            { key: 'starter', label: 'Starter', icon: Sparkles },
        ],
    },
];

// The Settings meta-area sub-nav SettingsFrame takes as a prop.
export const settingsTabs: NavTab[] = [
    { key: 'general', label: 'General', icon: Settings },
    { key: 'advanced', label: 'Advanced', icon: SlidersHorizontal },
];
