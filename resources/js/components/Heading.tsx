import type { ReactNode } from 'react';

/**
 * The top of an inner page: a small label, the title in wide italic caps,
 * a line or two under it. With a car, the header becomes a bay: the car
 * parked on the right on wide screens, under the words on phones.
 */
export function PageHeader({
    eyebrow,
    title,
    children,
    aside,
    car,
    back,
    carOnPhones = true,
}: {
    eyebrow?: ReactNode;
    title: string;
    children?: ReactNode;
    aside?: ReactNode;
    car?: ReactNode;
    back?: ReactNode;
    /** False on a form page: phones get straight to the form */
    carOnPhones?: boolean;
}) {
    return (
        <header className={`border-b border-line ${car ? `bay page-bay ${carOnPhones ? '' : 'page-bay-wide'}` : ''}`}>
            <div className={`relative mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-8 lg:flex-row lg:items-end lg:justify-between xl:px-12 ${back ? 'pt-6 sm:pt-8' : 'pt-10 sm:pt-14'} ${car ? `${carOnPhones ? 'pb-0' : 'pb-9 sm:pb-12'} lg:min-h-[25rem] lg:pb-14` : 'pb-9 sm:pb-12'}`}>
                <div className="relative z-10 max-w-3xl lg:max-w-[min(48rem,52%)]">
                    {back && <div className="mb-6 lg:mb-8">{back}</div>}
                    {eyebrow && <div className="eyebrow text-muted">{eyebrow}</div>}
                    <span aria-hidden="true" className="gold-rule mt-4 block w-14" />
                    <h1 className="font-display mt-5 text-[clamp(2rem,7vw,3.6rem)]">{title}</h1>
                    {children && <div className="mt-5 max-w-2xl text-[1.05rem] leading-relaxed text-muted sm:text-lg">{children}</div>}
                </div>
                {aside}
                {car && <div className={`page-car ${carOnPhones ? '' : 'max-lg:hidden'}`}>{car}</div>}
            </div>
        </header>
    );
}

/** A section's heading: wide italic caps a step below the page title */
export function SectionTitle({ id, eyebrow, children, className = '' }: { id?: string; eyebrow?: string; children: ReactNode; className?: string }) {
    return (
        <div className={className}>
            {eyebrow && <p className="eyebrow mb-3 text-muted">{eyebrow}</p>}
            <h2 id={id} className="font-display text-[clamp(1.45rem,4.6vw,2.25rem)]">
                {children}
            </h2>
        </div>
    );
}
