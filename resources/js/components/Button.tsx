import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { Link } from 'react-router-dom';

type Variant = 'accent' | 'outline' | 'quiet';

const variants: Record<Variant, string> = {
    accent: 'bg-accent text-on-accent hover:bg-accent-hover',
    outline: 'border border-field text-text hover:border-text hover:bg-text/5',
    quiet: 'text-text hover:bg-text/6',
};

export const buttonClass = (variant: Variant = 'accent', extra = '') =>
    `press inline-flex min-h-12 items-center justify-center gap-2.5 rounded-control px-6 py-3 text-[0.95rem] font-semibold tracking-[0.005em] disabled:cursor-not-allowed disabled:opacity-55 ${variants[variant]} ${extra}`;

type Props = {
    to?: string;
    href?: string;
    variant?: Variant;
    className?: string;
    /** Outside links open in a new tab; tel:, sms: and mailto: links don't */
    external?: boolean;
    children: ReactNode;
} & Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'>;

/**
 * A button-shaped link: `to` for pages on the site, `href` for links (call,
 * text, maps).
 */
export default function Button({ to, href, variant = 'accent', className = '', external, children, ...rest }: Props) {
    if (to) {
        return (
            <Link to={to} className={buttonClass(variant, className)} {...rest}>
                {children}
            </Link>
        );
    }

    const opensTab = external ?? /^https?:/.test(href ?? '');

    return (
        <a href={href} target={opensTab ? '_blank' : undefined} rel={opensTab ? 'noopener noreferrer' : undefined} className={buttonClass(variant, className)} {...rest}>
            {children}
        </a>
    );
}
