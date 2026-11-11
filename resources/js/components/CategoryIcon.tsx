import type { ComponentType } from 'react';

import { Car, Droplet, Shield, Sparkle, Tint } from './Icons';

/** A line icon for the categories 826 has; anything new gets the car */
const CATEGORY_ICONS: Record<string, ComponentType<{ className?: string }>> = {
    detailing: Sparkle,
    coating: Droplet,
    'paint-protection-film': Shield,
    ppf: Shield,
    'window-tint': Tint,
    tint: Tint,
};

export default function CategoryIcon({ slug, className = 'size-6' }: { slug: string; className?: string }) {
    const Icon = CATEGORY_ICONS[slug] ?? Car;

    return <Icon className={className} />;
}
