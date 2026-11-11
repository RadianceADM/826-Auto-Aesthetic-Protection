import type { CSSProperties } from 'react';
import { Link } from 'react-router-dom';

import { BranchCard } from '../components/BranchParts';
import Button from '../components/Button';
import DriveIn from '../components/DriveIn';
import { SectionTitle } from '../components/Heading';
import { ArrowRight, Facebook, Messenger, Phone } from '../components/Icons';
import Picture from '../components/Picture';
import { CarCrop, Ceiling, ParkedCar } from '../components/Studio';
import { cast, driveSpots, heroCar, shotFor } from '../lib/cars';
import { copy } from '../lib/copy';
import { categories, categoryFromPrice, featuredPhotos, fromPrice, messageUrl, peso, phone, photoAlt, site } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';

const text = copy.home;

const container = 'mx-auto max-w-7xl px-4 sm:px-8 xl:px-12';

function Hero() {
    const { tagline, intro } = site.settings;
    const line = site.name.replace(new RegExp(`^${site.shortName}\\s*`), '') || text.hero.fallbackTagline;

    return (
        <section aria-labelledby="hero-title" className="bay hero" style={{ '--horizon': 'var(--hero-horizon)', '--car-ar': heroCar.car ? heroCar.car.width / heroCar.car.height : 240 / 90 } as CSSProperties}>
            <Ceiling kind="hex" fade="left" />
            <div className={`${container} relative flex flex-col lg:min-h-[clamp(40rem,calc(100svh-var(--nav-h)),56rem)] lg:justify-center`}>
                {/* The car: first on phones (it's what the page is about), to the right from lg */}
                <div className="hero-car order-first -mx-4 sm:-mx-8 lg:absolute lg:mx-0">
                    <ParkedCar car={heroCar.car} face={heroCar.face} sizes="(min-width: 1024px) 64vw, 112vw" eager sweep contact={heroCar.contact} shadow={heroCar.shadow} />
                </div>

                <div className="relative z-10 max-w-[36rem] pt-10 pb-12 sm:pt-14 sm:pb-16 lg:py-24">
                    <p className="eyebrow text-muted">{line}</p>
                    <span aria-hidden="true" className="gold-rule mt-3.5 block w-16" />
                    <h1 id="hero-title" className="font-display font-display-wide mt-6 text-[clamp(2.5rem,9vw,4.6rem)]">
                        {text.hero.title}
                    </h1>
                    {tagline && <p className="mt-6 text-lg leading-snug font-semibold sm:text-xl">{tagline}</p>}
                    {intro && <p className="mt-3 max-w-lg text-[1.02rem] leading-relaxed text-muted">{intro}</p>}
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <Button to="/book" className="text-base">
                            {copy.cta.reserve} <ArrowRight className="size-4" />
                        </Button>
                        <Button to="/services" variant="outline" className="bg-surface/40 text-base backdrop-blur-sm">
                            {copy.cta.seeServices}
                        </Button>
                    </div>
                </div>
            </div>
        </section>
    );
}

/** The four kinds of work and where they start, in one line under the hero */
function SpecStrip() {
    return (
        <section aria-label={text.hero.prices} className="border-y border-line bg-surface-2">
            <ul className={`${container} grid grid-cols-2 lg:grid-cols-4`}>
                {categories.map((category, index) => {
                    const from = categoryFromPrice(category);

                    return (
                        <li key={category.id} className={`border-line ${index % 2 ? 'border-l' : ''} ${index > 1 ? 'border-t lg:border-t-0' : ''} ${index > 0 ? 'lg:border-l' : ''}`}>
                            <Link to={`/services#${category.slug}`} className="press group flex h-full min-h-24 flex-col justify-center gap-1.5 px-4 py-5 hover:bg-text/4 sm:px-6 lg:px-7">
                                <span className="eyebrow text-[0.7rem] text-muted group-hover:text-text">{category.name}</span>
                                <span className="flex items-center justify-between gap-3">
                                    <span className="price text-[1.05rem] sm:text-lg">{from !== null ? copy.price.from(peso(from)) : copy.price.onRequest}</span>
                                    <ArrowRight className="size-4 shrink-0 text-accent opacity-70 group-hover:opacity-100" />
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function ServicesSection() {
    return (
        <section aria-labelledby="services-title" className={`${container} py-16 sm:py-24`}>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="max-w-2xl">
                    <SectionTitle id="services-title" eyebrow={text.services.eyebrow}>
                        {text.services.title}
                    </SectionTitle>
                    <p className="mt-4 text-[1.05rem] leading-relaxed text-muted">{text.services.body}</p>
                </div>
                <Link to="/services" className="press inline-flex min-h-11 items-center gap-2 font-semibold text-link hover:underline hover:underline-offset-4">
                    {copy.cta.seeServices} <ArrowRight className="size-4" />
                </Link>
            </div>

            <ul className="mt-10 grid gap-5 md:grid-cols-2">
                {categories.map((category) => {
                    const shot = shotFor(category.slug);

                    return (
                        <li key={category.id} className="panel flex flex-col overflow-hidden">
                            <Link to={`/services#${category.slug}`} tabIndex={-1} aria-hidden="true" className="bay block" style={{ '--horizon': '72%' } as CSSProperties}>
                                <CarCrop car={shot.car} focus={shot.focus} face={shot.face} aspect={16 / 9} frame={{ wide: '46vw', narrow: '100vw', breakpoint: 768 }} alt={shot.alt}>
                                    {shot.gloss && <span aria-hidden="true" className="crop-gloss" />}
                                </CarCrop>
                            </Link>
                            <div className="flex flex-1 flex-col p-5 sm:p-7">
                                <p className="eyebrow text-[0.7rem] text-muted">{text.services.count(category.services.length)}</p>
                                <h3 className="font-display mt-3 text-[clamp(1.35rem,3.6vw,1.8rem)]">
                                    <Link to={`/services#${category.slug}`} className="hover:text-link">
                                        {category.name}
                                    </Link>
                                </h3>
                                {text.services.lines[category.slug] && <p className="mt-3 leading-relaxed text-muted">{text.services.lines[category.slug]}</p>}
                                {category.description && <p className="mt-2 text-[0.95rem] leading-relaxed">{category.description}</p>}
                                <ul className="mt-5 border-t border-line pt-1.5">
                                    {category.services.map((service) => {
                                        const price = fromPrice(service);

                                        return (
                                            <li key={service.id}>
                                                <Link to={`/services/${service.slug}`} className="press -mx-2 flex min-h-11 items-center justify-between gap-3 rounded-control px-2 text-[0.95rem] hover:bg-text/5">
                                                    <span>{service.name}</span>
                                                    <span className="shrink-0 text-sm text-muted tabular">{price !== null ? copy.price.from(peso(price)) : copy.price.onRequest}</span>
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function StepsSection() {
    const { fee, cancelCutoffHours, rescheduleEnabled, rescheduleCutoffHours, maxReschedules } = site.booking;
    const steps = [
        text.steps.branch,
        text.steps.vehicle,
        text.steps.services,
        text.steps.time,
        ...(fee.enabled ? [text.steps.fee(fee.label, fee.holdMinutes), text.steps.confirmedPaid] : [text.steps.confirmedFree]),
    ];

    return (
        <section aria-labelledby="steps-title" className="border-y border-line bg-surface-2">
            <div className={`${container} py-16 sm:py-24`}>
                <SectionTitle id="steps-title" eyebrow={text.steps.eyebrow} className="max-w-3xl">
                    {text.steps.title}
                </SectionTitle>
                <ol className={`mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 ${steps.length > 5 ? 'lg:grid-cols-3' : 'lg:grid-cols-5'}`}>
                    {steps.map((step, index) => (
                        <li key={step.title} className="relative border-t border-line pt-6">
                            <span aria-hidden="true" className={`absolute -top-px left-0 h-0.5 w-10 ${index === 0 ? 'bg-accent' : 'bg-text/35'}`} />
                            <span className="font-display text-[1.6rem] text-text/30 tabular" aria-hidden="true">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <h3 className="mt-3 text-lg leading-snug font-bold">{step.title}</h3>
                            <p className="mt-2 leading-relaxed text-muted">{step.body}</p>
                        </li>
                    ))}
                </ol>
                <div className="mt-12 flex flex-col gap-6 border-t border-line pt-8 lg:flex-row lg:items-center lg:justify-between">
                    <p className="max-w-2xl leading-relaxed text-muted">
                        {text.steps.cancel(cancelCutoffHours)} {rescheduleEnabled && maxReschedules > 0 && text.steps.move(rescheduleCutoffHours, maxReschedules)}
                    </p>
                    <Button to="/book" className="shrink-0 text-base">
                        {copy.cta.reserve} <ArrowRight className="size-4" />
                    </Button>
                </div>
            </div>
        </section>
    );
}

function BranchesSection() {
    if (site.branches.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="branches-title" className={`${container} py-16 sm:py-24`}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <SectionTitle id="branches-title" eyebrow={text.branches.eyebrow}>
                    {text.branches.title}
                </SectionTitle>
                <Link to="/branches" className="press inline-flex min-h-11 items-center gap-2 font-semibold text-link hover:underline hover:underline-offset-4">
                    {copy.cta.allBranches} <ArrowRight className="size-4" />
                </Link>
            </div>
            <ul className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {site.branches.map((branch) => (
                    <li key={branch.id}>
                        <BranchCard branch={branch} />
                    </li>
                ))}
            </ul>
        </section>
    );
}

function GalleryStrip() {
    if (featuredPhotos.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="work-title" className={`${container} pb-16 sm:pb-20`}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <SectionTitle id="work-title" eyebrow={text.gallery.eyebrow}>
                    {text.gallery.title}
                </SectionTitle>
                <Link to="/gallery" className="press inline-flex min-h-11 items-center gap-2 font-semibold text-link hover:underline hover:underline-offset-4">
                    {text.gallery.all} <ArrowRight className="size-4" />
                </Link>
            </div>
            <ul className="no-scrollbar -mx-4 mt-8 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 lg:grid-cols-4">
                {featuredPhotos.slice(0, 8).map((photo) => (
                    <li key={photo.id} className="w-[72vw] shrink-0 snap-start sm:w-auto">
                        <Picture image={photo} alt={photoAlt(photo)} sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 72vw" className="aspect-[4/3] w-full rounded-card bg-surface-3 object-cover" />
                    </li>
                ))}
            </ul>
        </section>
    );
}

function TalkBand() {
    if (!phone && !messageUrl) {
        return null;
    }

    return (
        <section aria-labelledby="talk-title" className={`${container} pb-16 sm:pb-24`}>
            <div className="panel relative flex flex-col gap-6 overflow-hidden p-6 sm:p-10 lg:flex-row lg:items-center lg:justify-between">
                <span aria-hidden="true" className="gold-rule absolute top-0 left-6 w-16 sm:left-10" />
                <div className="relative max-w-xl">
                    <h2 id="talk-title" className="font-display text-[clamp(1.35rem,4vw,1.9rem)]">
                        {text.talk.title}
                    </h2>
                    <p className="mt-3 leading-relaxed text-muted">{text.talk.body}</p>
                </div>
                <div className="relative flex flex-col gap-3 sm:flex-row">
                    {phone && (
                        <Button href={`tel:${phone.href}`}>
                            <Phone className="size-4" /> {copy.branch.call} <span className="tabular">{phone.label}</span>
                        </Button>
                    )}
                    {messageUrl && (
                        <Button href={messageUrl} variant="outline" className="bg-surface/40">
                            {site.settings.messenger ? <Messenger className="size-4" /> : <Facebook className="size-4" />}
                            {site.settings.messenger ? copy.branch.messenger : copy.branch.facebook}
                        </Button>
                    )}
                </div>
            </div>
        </section>
    );
}

export default function Home() {
    usePageTitle();

    return (
        <>
            <Hero />
            <SpecStrip />
            <DriveIn car={cast.drive} face="right" spots={driveSpots} />
            <ServicesSection />
            <StepsSection />
            <BranchesSection />
            <GalleryStrip />
            <TalkBand />
        </>
    );
}
