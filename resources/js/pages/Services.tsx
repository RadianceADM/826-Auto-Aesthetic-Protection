import { Link } from 'react-router-dom';

import type { CSSProperties } from 'react';

import Button from '../components/Button';
import CategoryIcon from '../components/CategoryIcon';
import { PageHeader } from '../components/Heading';
import { ArrowRight, Check, House } from '../components/Icons';
import LayerDiagram, { layersFor } from '../components/LayerDiagram';
import PriceTable from '../components/PriceTable';
import { CarCrop, ParkedCar } from '../components/Studio';
import { pageCars, shotFor } from '../lib/cars';
import { copy } from '../lib/copy';
import { branchesOffering, categories, fromPrice, peso, site } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';
import type { Service } from '../types';

const text = copy.services;

export function ServiceCard({ service }: { service: Service }) {
    const from = fromPrice(service);
    const bookable = branchesOffering(service).length > 0;

    return (
        <article aria-labelledby={`service-${service.id}`} className="flex flex-col panel p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <h3 id={`service-${service.id}`} className="text-xl leading-snug font-bold">
                    <Link to={`/services/${service.slug}`} className="hover:text-link">
                        {service.name}
                    </Link>
                </h3>
                {from !== null && <p className="price pt-0.5 text-[1.05rem]">{copy.price.from(peso(from))}</p>}
            </div>
            {service.summary && <p className="mt-2 leading-relaxed text-muted">{service.summary}</p>}
            {service.homeServiceOk && (
                <p className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium">
                    <House className="size-4 text-accent" /> {text.atHome}
                </p>
            )}
            {service.inclusions.length > 0 && (
                <ul className="mt-4 grid gap-1.5 text-[0.95rem]">
                    {service.inclusions.slice(0, 4).map((item) => (
                        <li key={item} className="flex gap-2">
                            <Check className="mt-1 size-4 shrink-0 text-accent" /> {item}
                        </li>
                    ))}
                </ul>
            )}
            <div className="mt-5">
                <PriceTable service={service} compact />
            </div>
            <div className="mt-auto flex flex-wrap gap-2 pt-6">
                {bookable && (
                    <Button to={`/book?service=${service.slug}`} className="min-h-11! px-5!">
                        {copy.cta.book}
                    </Button>
                )}
                <Button to={`/services/${service.slug}`} variant="outline" className="min-h-11! px-5!">
                    {copy.cta.details} <ArrowRight className="size-4" />
                </Button>
            </div>
        </article>
    );
}

export default function Services() {
    usePageTitle(text.title);
    const header = pageCars.services;

    return (
        <>
            <PageHeader eyebrow={copy.nav.services} title={text.title} car={<ParkedCar car={header.car} face={header.face} sizes="(min-width: 1024px) 46vw, 100vw" eager contact={header.contact} shadow={header.shadow} />}>
                <p>{text.intro}</p>
            </PageHeader>

            {categories.length > 1 && (
                <nav
                    aria-label={text.jump}
                    className={`sticky z-30 border-b border-line bg-surface/95 backdrop-blur-md ${
                        // Under the header, which is 2rem taller with the demo bar
                        site.demo ? 'top-[calc(6rem+env(safe-area-inset-top))] sm:top-[calc(6.5rem+env(safe-area-inset-top))]' : 'top-[calc(4rem+env(safe-area-inset-top))] sm:top-[calc(4.5rem+env(safe-area-inset-top))]'
                    }`}
                >
                    <ul className="no-scrollbar mx-auto flex max-w-7xl gap-2 overflow-x-auto px-4 py-3 sm:px-8 xl:px-12">
                        {categories.map((category) => (
                            <li key={category.id} className="shrink-0">
                                <a href={`#${category.slug}`} className="press inline-flex min-h-11 items-center gap-2 rounded-full border border-field/60 px-4 text-[0.92rem] font-medium whitespace-nowrap hover:border-text/60">
                                    <CategoryIcon slug={category.slug} className="size-4 text-accent" /> {category.name}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>
            )}

            <div className="mx-auto max-w-7xl px-4 pb-20 sm:px-8 xl:px-12">
                {categories.map((category) => {
                    const shot = shotFor(category.slug);
                    const layers = layersFor(category.slug);

                    return (
                        <section key={category.id} id={category.slug} aria-labelledby={`category-${category.id}`} className="scroll-mt-36 border-b border-line pt-14 pb-14 last:border-b-0 sm:pt-20 sm:pb-16">
                            <div className="grid items-center gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:gap-14">
                                <div>
                                    <p className="eyebrow flex items-center gap-2 text-muted">
                                        <CategoryIcon slug={category.slug} className="size-4 text-accent" /> {copy.home.services.count(category.services.length)}
                                    </p>
                                    <h2 id={`category-${category.id}`} className="font-display mt-4 text-[clamp(1.7rem,5vw,2.6rem)]">
                                        {category.name}
                                    </h2>
                                    {copy.home.services.lines[category.slug] && <p className="mt-4 max-w-xl text-[1.05rem] leading-relaxed text-muted">{copy.home.services.lines[category.slug]}</p>}
                                    {category.description && <p className="mt-3 max-w-xl leading-relaxed font-medium">{category.description}</p>}
                                    {layers && (
                                        <div className="mt-8 max-w-md">
                                            <LayerDiagram kind={layers} />
                                            <p className="mt-2 text-xs text-muted">{copy.layers.caption}</p>
                                        </div>
                                    )}
                                </div>
                                <div className="bay overflow-hidden rounded-card border border-line" style={{ '--horizon': '78%' } as CSSProperties}>
                                    <CarCrop car={shot.car} focus={shot.focus} face={shot.face} detail={shot.detail} aspect={3 / 2} frame={{ wide: '50vw', narrow: '100vw' }} alt={shot.alt}>
                                        {shot.gloss && <span aria-hidden="true" className="crop-gloss" />}
                                    </CarCrop>
                                </div>
                            </div>
                            <div className="mt-10 grid gap-4 lg:grid-cols-2">
                                {category.services.map((service) => (
                                    <ServiceCard key={service.id} service={service} />
                                ))}
                            </div>
                        </section>
                    );
                })}
            </div>
        </>
    );
}
