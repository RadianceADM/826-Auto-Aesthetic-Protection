import { Link, useParams } from 'react-router-dom';

import { BranchStatus, ContactButtons, HoursTable, TodayLine } from '../components/BranchParts';
import Button from '../components/Button';
import CategoryIcon from '../components/CategoryIcon';
import { PageHeader } from '../components/Heading';
import { ArrowLeft, ArrowRight, Clock, House, Info, MapPin } from '../components/Icons';
import { ParkedCar } from '../components/Studio';
import { pageCars } from '../lib/cars';
import { copy } from '../lib/copy';
import { branchBySlug, categories, clock, fromPrice, peso } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';
import NotFound from './NotFound';

const text = copy.branch;

export default function BranchDetail() {
    const { slug } = useParams();
    const branch = branchBySlug(slug);

    usePageTitle(branch ? `826 ${branch.name}` : undefined);

    if (!branch) {
        return <NotFound />;
    }

    const open = branch.status === 'open';
    const offered = categories
        .map((category) => ({ category, services: category.services.filter((service) => branch.serviceIds.includes(service.id)) }))
        .filter((group) => group.services.length > 0);

    const parked = pageCars.branch;

    return (
        <>
            <PageHeader
                eyebrow={<BranchStatus branch={branch} />}
                title={branch.name}
                back={
                    <Link to="/branches" className="press -ml-2 inline-flex min-h-11 items-center gap-2 rounded-control px-2 text-[0.95rem] font-medium text-muted hover:text-text">
                        <ArrowLeft className="size-4" /> {copy.cta.allBranches}
                    </Link>
                }
                car={<ParkedCar car={parked.car} face={parked.face} sizes="(min-width: 1024px) 46vw, 100vw" eager contact={parked.contact} shadow={parked.shadow} />}
            >
                {(branch.address || branch.city) && (
                    <p className="flex gap-2.5 text-lg leading-relaxed text-text">
                        <MapPin className="mt-1 size-5 shrink-0 text-accent" />
                        <span>{branch.address ?? branch.city}</span>
                    </p>
                )}
                <TodayLine branch={branch} className="mt-2 text-muted" />
                {open ? (
                    <div className="mt-7">
                        {branch.bookable ? (
                            <Button to={`/book?branch=${branch.slug}`} className="w-full text-base sm:w-auto">
                                {copy.cta.bookHere} <ArrowRight className="size-4" />
                            </Button>
                        ) : (
                            <p className="flex gap-3 rounded-card bg-surface-3/60 px-4 py-3.5 text-base text-text">
                                <Info className="mt-0.5 size-5 shrink-0 text-accent" /> {text.notBookable}
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="panel mt-7 p-5 text-base">
                        <p className="font-semibold text-text">{text.comingSoonBody}</p>
                        {branch.facebook && <p className="mt-1 text-muted">{text.comingSoonFollow}</p>}
                    </div>
                )}
            </PageHeader>

            <div className="mx-auto grid max-w-7xl gap-10 px-4 pt-10 pb-20 sm:px-8 sm:pt-14 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:gap-16 xl:px-12">
                <div>
                    <section aria-labelledby="contact-title">
                        <h2 id="contact-title" className="text-lg font-bold">
                            {text.contact}
                        </h2>
                        <div className="mt-4">
                            <ContactButtons target={branch} />
                        </div>
                    </section>

                    {branch.map?.embed && (
                        <iframe
                            title={text.map(branch.name)}
                            src={branch.map.embed}
                            loading="lazy"
                            referrerPolicy="no-referrer-when-downgrade"
                            className="mt-10 aspect-[16/10] w-full rounded-card border border-line bg-surface-3"
                        />
                    )}

                    {branch.homeService.offered && (
                        <section aria-labelledby="home-title" className="mt-10 panel p-5 sm:p-6">
                            <h2 id="home-title" className="flex items-center gap-2.5 text-lg font-bold">
                                <House className="size-5 text-accent" /> {text.homeTitle}
                            </h2>
                            <p className="mt-2 leading-relaxed text-muted">{text.homeBody}</p>
                            {branch.homeService.areas.length > 0 && (
                                <>
                                    <p className="eyebrow mt-5 text-muted">{text.homeAreas}</p>
                                    <ul className="mt-2.5 flex flex-wrap gap-2">
                                        {branch.homeService.areas.map((area) => (
                                            <li key={area} className="rounded-full bg-surface-3 px-3.5 py-1.5 text-[0.92rem] font-medium">
                                                {area}
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                            <Button to={`/home-service?branch=${branch.slug}`} variant="outline" className="mt-6 min-h-11! px-5!">
                                {copy.cta.requestHome} <ArrowRight className="size-4" />
                            </Button>
                        </section>
                    )}
                    {offered.length > 0 && (
                        <section aria-labelledby="offered-title" className="panel mt-10 p-5 sm:p-6">
                            <h2 id="offered-title" className="text-lg font-bold">
                                {text.servicesTitle}
                            </h2>
                            <p className="mt-1 text-sm text-muted">{text.servicesBody}</p>
                            <div className="mt-4 space-y-5">
                                {offered.map(({ category, services }) => (
                                    <div key={category.id}>
                                        <p className="eyebrow flex items-center gap-2 text-muted">
                                            <CategoryIcon slug={category.slug} className="size-4 text-accent" /> {category.name}
                                        </p>
                                        <ul className="mt-1.5">
                                            {services.map((service) => {
                                                const from = fromPrice(service);

                                                return (
                                                    <li key={service.id}>
                                                        <Link to={`/services/${service.slug}`} className="press -mx-2 flex min-h-11 items-center justify-between gap-3 rounded-control px-2 hover:bg-text/5">
                                                            <span>{service.name}</span>
                                                            {from !== null && <span className="shrink-0 text-sm text-muted tabular">{copy.price.from(peso(from))}</span>}
                                                        </Link>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                </div>

                <aside className="space-y-6">
                    {open && (
                        <section aria-labelledby="hours-title" className="panel p-5 sm:p-6">
                            <h2 id="hours-title" className="flex items-center gap-2.5 text-lg font-bold">
                                <Clock className="size-5 text-accent" /> {text.hours}
                            </h2>
                            <div className="mt-3">
                                <HoursTable branch={branch} />
                            </div>
                            {branch.multidayDropoffBy && <p className="mt-4 border-t border-line pt-4 text-sm leading-relaxed text-muted">{text.multiday(clock(branch.multidayDropoffBy))}</p>}
                        </section>
                    )}

                </aside>
            </div>
        </>
    );
}
