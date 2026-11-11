import { copy } from '../lib/copy';
import { site } from '../lib/site';

type MarkProps = {
    className?: string;
    /** Empty when whatever holds it already says the name */
    alt?: string;
    eager?: boolean;
};

/**
 * The "826" numerals (public/brand), transparent, readable on dark and
 * light. Until the file is there, the numerals set in type stand in.
 */
export function Mark({ className = 'h-8 w-auto', alt = '', eager = false }: MarkProps) {
    if (site.logo) {
        return <img src={site.logo} alt={alt} width={832} height={232} decoding="async" fetchPriority={eager ? 'high' : undefined} className={className} />;
    }

    return (
        <span className={`font-display inline-block text-accent ${className}`} aria-hidden={alt ? undefined : true}>
            {site.shortName}
        </span>
    );
}

/**
 * The logo as a lockup, for the hero: the numerals, the gold rule under
 * them and the spaced line, the way the badge is drawn. The line is real
 * text, so it reads in both modes (logoFull has it in black, for light
 * paper only).
 */
export function Lockup({ className = '' }: { className?: string }) {
    const line = site.name.replace(new RegExp(`^${site.shortName}\\s*`), '') || copy.home.hero.fallbackTagline;

    return (
        <span className={`inline-flex w-[min(25rem,80vw)] flex-col lg:w-[min(30rem,40vw)] ${className}`}>
            <Mark className="h-auto w-full" alt={site.shortName} eager />
            <span aria-hidden="true" className="gold-rule mt-3" />
            <span className="eyebrow mt-3 text-[min(0.95rem,2.6vw)] tracking-[0.19em] whitespace-nowrap text-text lg:text-[min(1.12rem,1.25vw)]">{line}</span>
        </span>
    );
}
