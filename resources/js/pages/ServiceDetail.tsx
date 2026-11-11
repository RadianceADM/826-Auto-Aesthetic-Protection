import type { CSSProperties } from 'react';
import { Link, useParams } from 'react-router-dom';

import { BranchStatus, TodayLine } from '../components/BranchParts';
import Button from '../components/Button';
import CategoryIcon from '../components/CategoryIcon';
import { ArrowLeft, ArrowRight, Check, House } from '../components/Icons';
import LayerDiagram, { layersFor } from '../components/LayerDiagram';
import Picture from '../components/Picture';
import PriceTable from '../components/PriceTable';
import { CarCrop } from '../components/Studio';
import { shotFor } from '../lib/cars';
import { copy } from '../lib/copy';
import { branchesOffering, fromPrice, homeBranches, peso, serviceBySlug } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';
import NotFound from './NotFound';

const text = copy.services;

export default function ServiceDetail() {
    const { slug } = useParams();
    const service = serviceBySlug(slug);

    usePageTitle(service?.name);

    if (!service) {
        return <NotFound />;
    }

    const from = fromPrice(service);
    const branches = branchesOffering(service);
    const atHome = service.homeServiceOk ? homeBranches.filter((branch) => branch.serviceIds.includes(service.id)) : [];
    const bookHref = `/book?service=${service.slug}${branches.length === 1 ? `&branch=${branches[0].slug}` : ''}`;

    const shot = shotFor(service.category.slug, service.slug);
    const layers = layersFor(service.category.slug);

    return (
        <>
            <section aria-labelledby="service-title" className="bay border-b border-line" style={{ '--horizon': '100%' } as CSSProperties}>
                <div className="mx-auto max-w-7xl px-4 pt-6 sm:px-8 sm:pt-8 xl:px-12">
                    <Link to={`/services#${service.category.slug}`} className="press -ml-2 inline-flex min-h-11 items-center gap-2 rounded-control px-2 text-[0.95rem] font-medium text-muted hover:text-text">
                        <ArrowLeft className="size-4" /> {text.back}
                    </Link>
                </div>
                <div className="mx-auto grid max-w-7xl items-center gap-8 px-4 pt-4 pb-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-14 lg:pt-2 lg:pb-14 xl:px-12">
                    <div>
                        <p className="eyebrow flex items-center gap-2 text-muted">
                            <CategoryIcon slug={service.category.slug} className="size-4 text-accent" /> {service.category.name}
                        </p>
                        <span aria-hidden="true" className="gold-rule mt-4 block w-14" />
                        <h1 id="service-title" className="font-display mt-5 text-[clamp(2.1rem,7vw,3.6rem)]">
                            {service.name}
                        </h1>
                        {from !== null && <p className="price mt-5 text-xl">{copy.price.from(peso(from))}</p>}
                        {service.summary && <p className="mt-4 max-w-xl text-lg leading-relaxed">{service.summary}</p>}
                        {service.category.description && <p className="mt-3 max-w-xl leading-relaxed text-muted">{service.category.description}</p>}

                        {branches.length > 0 && (
                            <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                <Button to={bookHref} className="text-base">
                                    {copy.cta.bookService(service.name)} <ArrowRight className="size-4" />
                                </Button>
                                {atHome.length > 0 && (
                                    <Button to={`/home-service?service=${service.slug}`} variant="outline" className="bg-surface/40 text-base">
                                        <House className="size-4" /> {copy.cta.requestHome}
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                    <div className="bay order-first overflow-hidden rounded-card border border-line lg:order-none" style={{ '--horizon': '80%' } as CSSProperties}>
                        {service.image ? (
                            <Picture image={service.image} alt={service.name} sizes="(min-width: 1024px) 50vw, 100vw" eager className="aspect-[4/3] w-full object-cover" />
                        ) : (
                            <CarCrop car={shot.car} focus={shot.focus} face={shot.face} aspect={4 / 3} frame={{ wide: '50vw', narrow: '100vw' }} alt={shot.alt} eager>
                                {shot.gloss && <span aria-hidden="true" className="crop-gloss" />}
                            </CarCrop>
                        )}
                    </div>
                </div>
            </section>

            <div className="mx-auto grid max-w-7xl gap-8 px-4 pt-10 pb-20 sm:px-8 sm:pt-14 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:gap-12 xl:px-12">
                <div className="min-w-0 space-y-8">
                    <section aria-labelledby="prices-title" className="panel p-5 sm:p-7">
                        <h2 id="prices-title" className="text-lg font-bold">
                            {text.prices}
                        </h2>
                        <div className="mt-4">
                            <PriceTable service={service} />
                        </div>
                    </section>

                    {service.description && <p className="max-w-2xl leading-relaxed whitespace-pre-line text-muted">{service.description}</p>}

                    {service.inclusions.length > 0 && (
                        <section aria-labelledby="included-title">
                            <h2 id="included-title" className="font-display text-[1.35rem]">
                                {text.included}
                            </h2>
                            <ul className="mt-5 grid gap-3 sm:grid-cols-2">
                                {service.inclusions.map((item) => (
                                    <li key={item} className="flex gap-2.5 leading-relaxed">
                                        <Check className="mt-1 size-4 shrink-0 text-accent" /> {item}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {layers && (
                        <section aria-labelledby="layers-title" className="panel p-5 sm:p-7">
                            <h2 id="layers-title" className="text-lg font-bold">
                                {copy.layers.heading}
                            </h2>
                            <div className="mt-5 max-w-lg">
                                <LayerDiagram kind={layers} />
                            </div>
                            <p className="mt-3 text-sm text-muted">{copy.layers.caption}</p>
                        </section>
                    )}
                </div>

                <aside className="space-y-6">
                    <section aria-labelledby="where-title" className="panel p-5 sm:p-7">
                        <h2 id="where-title" className="text-lg font-bold">
                            {text.where}
                        </h2>
                        {branches.length === 0 ? (
                            <p className="mt-3 leading-relaxed text-muted">{text.whereNone}</p>
                        ) : (
                            <ul className="mt-3 divide-y divide-line">
                                {branches.map((branch) => (
                                    <li key={branch.id} className="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-2 last:pb-0">
                                        <div className="min-w-0">
                                            <Link to={`/branches/${branch.slug}`} className="font-semibold hover:text-link">
                                                {branch.name}
                                            </Link>
                                            <TodayLine branch={branch} className="mt-1 text-sm text-muted" />
                                        </div>
                                        {branch.bookable ? (
                                            <Button to={`/book?branch=${branch.slug}&service=${service.slug}`} variant="outline" className="min-h-11! px-4! text-[0.9rem]!">
                                                {copy.cta.book}
                                            </Button>
                                        ) : (
                                            <BranchStatus branch={branch} />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {atHome.length > 0 && (
                            <div className="mt-5 flex gap-3 rounded-control bg-surface-3/60 px-4 py-3.5 text-[0.95rem]">
                                <House className="mt-0.5 size-5 shrink-0 text-accent" />
                                <p>
                                    <span className="font-semibold">{text.atHome}.</span> {text.atHomeBody(atHome.map((branch) => branch.name).join(', '))}
                                </p>
                            </div>
                        )}
                    </section>
                </aside>
            </div>
        </>
    );
}
