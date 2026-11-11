type IconProps = {
    className?: string;
};

const base = (className = 'size-5') => ({
    className,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.6,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
    'aria-hidden': true,
    focusable: false,
});

export function ArrowRight({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M5 12h14M13 6l6 6-6 6" />
        </svg>
    );
}

export function ArrowLeft({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M19 12H5M11 6l-6 6 6 6" />
        </svg>
    );
}

export function ArrowUpRight({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M7 17 17 7M8 7h9v9" />
        </svg>
    );
}

export function Close({ className }: IconProps) {
    return (
        <svg {...base(className ?? 'size-6')}>
            <path d="M6 6l12 12M18 6 6 18" />
        </svg>
    );
}

export function Check({ className }: IconProps) {
    return (
        <svg {...base(className ?? 'size-4')}>
            <path d="m5 12 5 5 9-10" />
        </svg>
    );
}

export function Warning({ className }: IconProps) {
    return (
        <svg {...base(className ?? 'size-4')}>
            <path d="M12 3 2 20h20L12 3Z" />
            <path d="M12 10v4M12 17.5v.01" />
        </svg>
    );
}

export function ChevronLeft({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="m15 6-6 6 6 6" />
        </svg>
    );
}

export function ChevronRight({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="m9 6 6 6-6 6" />
        </svg>
    );
}

export function ChevronDown({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="m6 9 6 6 6-6" />
        </svg>
    );
}

export function Sun({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2.5v2M12 19.5v2M4.6 4.6 6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4" />
        </svg>
    );
}

export function Moon({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z" />
        </svg>
    );
}

export function Phone({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M5 3.5h3.2l1.6 4.2-2.1 1.4a11 11 0 0 0 7.2 7.2l1.4-2.1 4.2 1.6V19a1.6 1.6 0 0 1-1.7 1.6A16.5 16.5 0 0 1 3.4 5.2 1.6 1.6 0 0 1 5 3.5Z" />
        </svg>
    );
}

export function Chat({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M4 5.5h16v10.5H9l-5 4V5.5Z" />
            <path d="M8 10h8M8 13h5" />
        </svg>
    );
}

export function Messenger({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M12 3c5 0 9 3.7 9 8.3s-4 8.2-9 8.2c-.9 0-1.8-.1-2.6-.3L5.5 21v-3.6A8 8 0 0 1 3 11.3C3 6.7 7 3 12 3Z" />
            <path d="m7.5 13.5 3-3.2 2.2 2 3.8-2.8-3 3.2-2.2-2-3.8 2.8Z" fill="currentColor" />
        </svg>
    );
}

export function Facebook({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M14.5 8.5H17V5h-2.5A3.5 3.5 0 0 0 11 8.5V11H8.5v3.5H11V21h3.5v-6.5H17l.5-3.5h-3V9a.5.5 0 0 1 .5-.5Z" />
        </svg>
    );
}

export function Instagram({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <rect x="3.5" y="3.5" width="17" height="17" rx="4.5" />
            <circle cx="12" cy="12" r="3.8" />
            <path d="M17.2 6.8v.01" />
        </svg>
    );
}

export function Mail({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <rect x="3.5" y="5.5" width="17" height="13" rx="2" />
            <path d="m4 7 8 6 8-6" />
        </svg>
    );
}

export function MapPin({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M12 21s-6.5-5.6-6.5-11A6.5 6.5 0 0 1 18.5 10c0 5.4-6.5 11-6.5 11Z" />
            <circle cx="12" cy="10" r="2.3" />
        </svg>
    );
}

/** A turn arrow: directions */
export function Directions({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M12 2.8 21.2 12 12 21.2 2.8 12 12 2.8Z" />
            <path d="M9 14v-2.5a1.5 1.5 0 0 1 1.5-1.5H15M13 8l2 2-2 2" />
        </svg>
    );
}

export function Calendar({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
            <path d="M3.5 10h17M8 3v4M16 3v4" />
        </svg>
    );
}

export function Clock({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
        </svg>
    );
}

export function Info({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 11v5.5M12 7.8v.01" />
        </svg>
    );
}

export function User({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <circle cx="12" cy="8.5" r="3.7" />
            <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
        </svg>
    );
}

export function SignOut({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M14 4.5H6.5a1.5 1.5 0 0 0-1.5 1.5v12a1.5 1.5 0 0 0 1.5 1.5H14" />
            <path d="M10.5 12H20M16.5 8.5 20 12l-3.5 3.5" />
        </svg>
    );
}

export function Copy({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <rect x="8.5" y="8.5" width="11.5" height="11.5" rx="1.8" />
            <path d="M15.5 8.5V5.8A1.8 1.8 0 0 0 13.7 4H5.8A1.8 1.8 0 0 0 4 5.8v7.9a1.8 1.8 0 0 0 1.8 1.8h2.7" />
        </svg>
    );
}

export function ImagePlus({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M20 12.5V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6.5" />
            <path d="m4.5 17 4.2-4.2a1.5 1.5 0 0 1 2.1 0L15 17M13.5 15.5l1.7-1.7a1.5 1.5 0 0 1 2.1 0l2.7 2.7" />
            <path d="M18 3v6M15 6h6" />
        </svg>
    );
}

export function Trash({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M4.5 7h15M9.5 7V4.8a.8.8 0 0 1 .8-.8h3.4a.8.8 0 0 1 .8.8V7" />
            <path d="M6.5 7l.9 12a1.6 1.6 0 0 0 1.6 1.5h6a1.6 1.6 0 0 0 1.6-1.5l.9-12M10 11v6M14 11v6" />
        </svg>
    );
}

export function Expand({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" />
        </svg>
    );
}

export function Download({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M12 4v11M7.5 10.5 12 15l4.5-4.5" />
            <path d="M5 19.5h14" />
        </svg>
    );
}

export function House({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M3.5 11 12 4l8.5 7" />
            <path d="M5.5 9.5V20h13V9.5" />
            <path d="M10 20v-5.5h4V20" />
        </svg>
    );
}

/** A car from the side */
export function Car({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M3 15.5v-2.2a2 2 0 0 1 1.2-1.8l2.3-1 2.2-3A2 2 0 0 1 10.3 6.7h4.4a2 2 0 0 1 1.5.7l2.6 3 1.7.6a2 2 0 0 1 1.5 1.9v2.6a1 1 0 0 1-1 1h-1" />
            <path d="M8.8 16.5h6.4M3 15.5a1 1 0 0 0 1 1h1" />
            <circle cx="7" cy="16.5" r="1.8" />
            <circle cx="17" cy="16.5" r="1.8" />
            <path d="M6.5 10.5h12" />
        </svg>
    );
}

/** A shield: paint protection film */
export function Shield({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M12 3.2 19.5 6v5.6c0 4.4-3.1 8-7.5 9.2-4.4-1.2-7.5-4.8-7.5-9.2V6L12 3.2Z" />
            <path d="m8.8 12.2 2.2 2.2 4.4-4.6" />
        </svg>
    );
}

/** A drop: coatings */
export function Droplet({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M12 3.5s6 6.4 6 10.8a6 6 0 0 1-12 0C6 9.9 12 3.5 12 3.5Z" />
            <path d="M9.2 14.6a2.9 2.9 0 0 0 2.6 2.6" />
        </svg>
    );
}

/** A spark: detailing */
export function Sparkle({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M12 3.5c.7 4.4 2.1 5.8 6.5 6.5-4.4.7-5.8 2.1-6.5 6.5-.7-4.4-2.1-5.8-6.5-6.5 4.4-.7 5.8-2.1 6.5-6.5Z" />
            <path d="M18.5 15.5c.3 1.6.9 2.2 2.5 2.5-1.6.3-2.2.9-2.5 2.5-.3-1.6-.9-2.2-2.5-2.5 1.6-.3 2.2-.9 2.5-2.5Z" />
        </svg>
    );
}

/** A car window with shade: tint */
export function Tint({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M4 18.5 7.6 7.2a2 2 0 0 1 1.9-1.4h7.7a2 2 0 0 1 2 2v10.7H4Z" />
            <path d="M8 18.5 12 9M12 18.5l4-9.5M16 18.5l3.2-7.6" />
        </svg>
    );
}

/** A bolt: power outlet */
export function Bolt({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M13 3 5.5 13.5H11l-1 7.5 7.5-10.5H12L13 3Z" />
        </svg>
    );
}

/** A roof: shade */
export function Shade({ className }: IconProps) {
    return (
        <svg {...base(className)}>
            <path d="M3 10.5 12 5l9 5.5" />
            <path d="M5.5 9.5v10M18.5 9.5v10" />
        </svg>
    );
}
