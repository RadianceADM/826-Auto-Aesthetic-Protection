import type { CSSProperties } from 'react';

import Button from '../components/Button';
import { ArrowRight } from '../components/Icons';
import { Ceiling, ParkedCar } from '../components/Studio';
import { pageCars } from '../lib/cars';
import { copy } from '../lib/copy';
import usePageTitle from '../lib/usePageTitle';

export default function NotFound() {
    usePageTitle(copy.notFound.title);
    const parked = pageCars.lost;

    return (
        <div className="bay page-bay" style={{ '--horizon': '86%' } as CSSProperties}>
            <Ceiling kind="bars" reflect={false} className="opacity-60" />
            <div className="relative mx-auto grid max-w-7xl items-end gap-10 px-4 pt-20 pb-10 sm:px-8 sm:pt-28 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] xl:px-12">
                <div className="pb-6 lg:pb-16">
                    <p className="eyebrow text-muted">404</p>
                    <span aria-hidden="true" className="gold-rule mt-4 block w-14" />
                    <h1 className="font-display font-display-wide mt-5 text-[clamp(2.4rem,8vw,4.2rem)]">{copy.notFound.title}</h1>
                    <p className="mt-5 max-w-lg text-lg leading-relaxed text-muted">{copy.notFound.body}</p>
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Button to="/">{copy.notFound.home}</Button>
                        <Button to="/services" variant="outline" className="bg-surface/40">
                            {copy.cta.seeServices} <ArrowRight className="size-4" />
                        </Button>
                    </div>
                </div>
                <ParkedCar car={parked.car} face={parked.face} sizes="(min-width: 1024px) 46vw, 100vw" contact={parked.contact} shadow={parked.shadow} className="-mx-4 sm:mx-0" />
            </div>
        </div>
    );
}
