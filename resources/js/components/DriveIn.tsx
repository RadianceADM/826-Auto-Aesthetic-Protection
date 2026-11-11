import { type CSSProperties, type FocusEvent, useLayoutEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';

import { type Car, carAlt, carFile, carSrcSet, contactOf, type Facing } from '../lib/cars';
import { copy } from '../lib/copy';
import { categories } from '../lib/site';
import Button from './Button';
import { ArrowRight } from './Icons';
import { Ceiling } from './Studio';
import VehicleSilhouette from './VehicleSilhouette';

const text = copy.home.drive;

/** Where on the car each service points (fractions of the car's box, for a car facing right) */
export type CalloutSpot = { slug: string; x: number; y: number };

type Props = {
    car?: Car;
    /** The way it drives in: facing right, it comes in from the left */
    face?: Facing;
    spots: CalloutSpot[];
};

const clamp = (value: number, min = 0, max = 1) => Math.min(max, Math.max(min, value));

/** 0 before `from`, 1 after `to`, eased in between */
const span = (progress: number, from: number, to: number) => clamp((progress - from) / (to - from));

const easeOut = (t: number) => 1 - Math.pow(1 - t, 3);

const smooth = (t: number) => t * t * (3 - 2 * t);

/** A line that comes in at `from` and, with `out`, leaves again */
function beat(progress: number, from: number, out?: number) {
    const enter = smooth(span(progress, from, from + 0.06));
    const leave = out === undefined ? 1 : 1 - smooth(span(progress, out, out + 0.06));

    return { opacity: Math.min(enter, leave), lift: (1 - enter) * 14 - (1 - leave) * 10 };
}

/**
 * The drive-in: a tall run of the home page with the bay pinned in it.
 * Scrolling down drives the car in from off screen and parks it under the
 * lights; a light crosses the paint as it stops, the services come in one
 * by one, and it ends on the way to book. Scrolling back runs it back.
 * Everything that moves is a transform or an opacity, set straight on the
 * elements each frame (no re-render). Before it starts, without
 * JavaScript, and for anyone who prefers less motion: the last frame, still.
 */
export default function DriveIn({ car, face = 'right', spots }: Props) {
    const section = useRef<HTMLElement>(null);
    const stage = useRef<HTMLDivElement>(null);
    const rig = useRef<HTMLDivElement>(null);
    const [mode, setMode] = useState<'static' | 'scrub'>('static');
    const mirrored = Boolean(car && car.facing !== face);
    const wheels = car?.wheels ?? [];

    // Less motion wanted (now or later): the still frame
    useLayoutEffect(() => {
        const query = window.matchMedia('(prefers-reduced-motion: reduce)');
        const pick = () => setMode(query.matches ? 'static' : 'scrub');

        pick();
        query.addEventListener('change', pick);

        return () => query.removeEventListener('change', pick);
    }, []);

    useLayoutEffect(() => {
        const root = section.current;
        const box = stage.current;
        const carBox = rig.current;

        if (!root || !box || !carBox) {
            return;
        }

        const beats = Array.from(root.querySelectorAll<HTMLElement>('[data-beat]'));
        const bodies = Array.from(carBox.querySelectorAll<HTMLElement>('[data-body]'));
        const wheelEls = Array.from(carBox.querySelectorAll<HTMLElement>('[data-wheel]'));
        const sweep = carBox.querySelector<HTMLElement>('[data-sweep]');
        const lights = root.querySelector<HTMLElement>('[data-lights]');
        const reset = () => {
            carBox.style.transform = '';
            beats.forEach((element) => {
                element.style.opacity = '';
                element.style.transform = '';
                element.style.visibility = '';
                element.removeAttribute('data-faded');
            });
            wheelEls.forEach((element) => (element.style.transform = ''));
            if (sweep) sweep.style.transform = '';
            if (lights) lights.style.opacity = '';
            bodies.forEach((element) => (element.style.transform = ''));
        };

        if (mode !== 'scrub') {
            reset();
            return;
        }

        let frame = 0;
        let last = -1;
        let width = 0;
        let carWidth = 0;
        let stageWidth = 0;

        const measure = () => {
            width = carBox.offsetWidth;
            carWidth = width;
            stageWidth = box.offsetWidth;
        };

        const render = () => {
            frame = 0;
            const rect = root.getBoundingClientRect();
            const top = parseFloat(getComputedStyle(box).top) || 0;
            const run = rect.height - box.offsetHeight;
            const progress = run > 0 ? clamp((top - rect.top) / run) : 1;

            if (Math.abs(progress - last) < 0.0004) {
                return;
            }

            last = progress;

            // The drive: from just past the edge to its spot, braking as it gets there
            const direction = face === 'right' ? 1 : -1;
            const start = -direction * (stageWidth / 2 + carWidth / 2 + 24);
            const t = easeOut(span(progress, 0.03, 0.5));
            const x = start * (1 - t);
            carBox.style.transform = `translate3d(${x.toFixed(1)}px,0,0)`;

            // A touch of nose-down as it brakes, back level once it's stopped
            const braking = span(progress, 0.36, 0.5) * (1 - span(progress, 0.5, 0.58));
            const pitch = `rotate(${(direction * 0.35 * braking).toFixed(3)}deg)`;
            bodies.forEach((element) => (element.style.transform = pitch));

            // The wheels turn with the distance: degrees = distance / (2πr) · 360
            wheelEls.forEach((element) => {
                const radius = Number(element.dataset.r) * carWidth;
                // Mirrored to face the other way, or flipped in the floor, a wheel turns the other way round on screen
                const turn = radius > 0 ? (x / (2 * Math.PI * radius)) * 360 * (mirrored ? -1 : 1) * (element.dataset.wheel === 'flipped' ? -1 : 1) : 0;
                element.style.transform = `rotate(${turn.toFixed(2)}deg)`;
            });

            // The light crossing the paint as it settles
            if (sweep) {
                const pass = span(progress, 0.48, 0.7);
                sweep.style.transform = `translate3d(${(-130 + pass * 600).toFixed(1)}%,0,0) skewX(-14deg)`;
            }

            // The bay lights come up as the car rolls in
            if (lights) lights.style.opacity = (0.35 + 0.65 * smooth(span(progress, 0, 0.16))).toFixed(3);

            for (const element of beats) {
                const from = Number(element.dataset.from);
                const out = element.dataset.out ? Number(element.dataset.out) : undefined;
                const { opacity, lift } = beat(progress, from, out);
                element.style.opacity = opacity.toFixed(3);
                element.style.transform = `translate3d(0,${lift.toFixed(1)}px,0)`;
                // Words that have left aren't read out of turn; anything with a link stays reachable by Tab
                if (!('focusable' in element.dataset)) element.style.visibility = opacity < 0.02 ? 'hidden' : '';
                // …but it can't be clicked until it's there to see
                element.toggleAttribute('data-faded', opacity < 0.5);
            }
        };

        const request = () => {
            if (!frame) {
                frame = requestAnimationFrame(render);
            }
        };

        const resize = () => {
            measure();
            last = -1;
            request();
        };

        // Only while it's on screen: the scroll listener, and the layers it moves
        const watcher = new IntersectionObserver(([entry]) => {
            root.classList.toggle('is-live', entry.isIntersecting);

            if (entry.isIntersecting) {
                window.addEventListener('scroll', request, { passive: true });
                request();
            } else {
                window.removeEventListener('scroll', request);
            }
        });

        measure();
        render();
        watcher.observe(root);
        window.addEventListener('resize', resize, { passive: true });

        return () => {
            cancelAnimationFrame(frame);
            watcher.disconnect();
            window.removeEventListener('scroll', request);
            window.removeEventListener('resize', resize);
            root.classList.remove('is-live');
            reset();
        };
    }, [mode, face, mirrored]);

    // A keyboard user tabbing in while it's mid-drive: straight to the last frame, where everything shows
    const onFocus = (event: FocusEvent<HTMLElement>) => {
        const root = section.current;
        const box = stage.current;

        if (mode !== 'scrub' || !root || !box || !(event.target as HTMLElement).matches(':focus-visible')) {
            return;
        }

        const rect = root.getBoundingClientRect();
        const top = parseFloat(getComputedStyle(box).top) || 0;
        const run = rect.height - box.offsetHeight;

        if (top - rect.top < run - 2) {
            window.scrollTo({ top: window.scrollY + rect.top - top + run, behavior: 'instant' });
        }
    };

    const ordered = spots.map((spot, index) => ({ ...spot, category: categories.find((category) => category.slug === spot.slug), from: 0.5 + index * 0.075 })).filter((spot) => spot.category);

    // Wide screens: the names go in even columns from 10% before the car to 10% past it, left to right
    // in the order their spots sit on screen; shift is how far each name sits from its spot, in % of the car's width
    const onFloor = [...ordered]
        .map((spot) => ({ ...spot, pin: face === 'right' ? spot.x : 1 - spot.x }))
        .sort((a, b) => a.pin - b.pin)
        .map((spot, index, all) => ({ ...spot, shift: (-0.1 + (1.2 * (index + 0.5)) / all.length - spot.pin) * 100 }));
    const shared = car
        ? {
              src: carFile(car.files[1000]),
              srcSet: carSrcSet(car),
              sizes: '(min-width: 1024px) 62vw, 100vw',
              width: car.width,
              height: car.height,
              decoding: 'async' as const,
              loading: 'lazy' as const,
          }
        : null;

    return (
        <section ref={section} aria-labelledby="drive-title" data-mode={mode} className="drive" onFocus={onFocus}>
            <div ref={stage} className="drive-stage bay">
                <div data-lights className="absolute inset-0 -z-10">
                    <div className="contents max-sm:hidden">
                        <Ceiling kind="bars" />
                    </div>
                    <div className="contents sm:hidden">
                        <Ceiling kind="bars-narrow" reflect={false} />
                    </div>
                </div>

                <h2 id="drive-title" className="sr-only">
                    {text.title}
                </h2>

                {/* The lines at the top, one after the other */}
                <div className="drive-copy pointer-events-none absolute inset-x-0 z-10 px-5 text-center">
                    <div className="mx-auto grid max-w-4xl [&>*]:col-start-1 [&>*]:row-start-1">
                        <div data-beat data-from="-1" data-out="0.22" className="opacity-0">
                            <p className="eyebrow text-[#9ca4a8]">{text.title}</p>
                            <p className="font-display font-display-wide mt-3 text-[clamp(2.1rem,5vw,3.8rem)]">{text.intro}</p>
                            <p className="mt-3 text-[1.02rem] text-[#c3c9cc] sm:text-lg">{text.introBody}</p>
                        </div>
                        <div data-beat data-from="0.3" data-out="0.74" className="opacity-0">
                            <p className="font-display mx-auto max-w-[16ch] text-[clamp(1.6rem,3.6vw,2.8rem)] sm:max-w-none">{text.middle}</p>
                        </div>
                        <div data-beat data-focusable data-from="0.82" className="pointer-events-auto">
                            <p className="font-display font-display-wide text-[clamp(1.9rem,4.4vw,3.6rem)]">{text.end}</p>
                            <p className="drive-end-body mx-auto mt-3 max-w-xl text-[0.98rem] leading-relaxed text-[#c3c9cc] sm:text-[1.05rem]">{text.endBody}</p>
                            <Button to="/book" className="mt-6 w-full max-w-sm text-base sm:w-auto">
                                {copy.cta.reserve} <ArrowRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                {/* The car, parked on the floor line, and what each service is for */}
                <div className="drive-park absolute inset-x-0 z-0 flex justify-center">
                    <div ref={rig} className="drive-car relative" style={{ '--contact': contactOf(car) } as CSSProperties}>
                        <div aria-hidden="true" className="parked-shadow" style={{ '--shadow-inset': '3%', '--shadow-h': '15%' } as CSSProperties} />
                        <div style={mirrored ? { transform: 'scaleX(-1)' } : undefined} className="relative">
                            {car && shared ? (
                                <>
                                    {/* The car and its turning wheels, and the same again flipped in the floor's gloss */}
                                    {[false, true].map((reflected) => (
                                        <div key={String(reflected)} aria-hidden={reflected || undefined} className={reflected ? 'drive-reflection' : 'relative'}>
                                            <div data-body className="relative origin-[18%_92%]">
                                                <img {...shared} alt={reflected ? '' : carAlt(car)} className="parked-img" />
                                                {!reflected && (
                                                    <div
                                                        aria-hidden="true"
                                                        className="drive-sweep parked-sweep"
                                                        style={{ maskImage: `url("${carFile(car.files[1000])}")`, WebkitMaskImage: `url("${carFile(car.files[1000])}")` }}
                                                    >
                                                        <span data-sweep />
                                                    </div>
                                                )}
                                            </div>
                                            {wheels.map((wheel) => (
                                                <img
                                                    key={wheel.name}
                                                    data-wheel={reflected ? 'flipped' : ''}
                                                    data-r={wheel.r}
                                                    src={carFile(wheel.file)}
                                                    alt=""
                                                    aria-hidden="true"
                                                    loading="lazy"
                                                    decoding="async"
                                                    className="drive-wheel"
                                                    style={{ left: `${(wheel.cx - wheel.r) * 100}%`, top: `${(wheel.cy - (wheel.r * car.width) / car.height) * 100}%`, width: `${wheel.r * 200}%` }}
                                                />
                                            ))}
                                        </div>
                                    ))}
                                </>
                            ) : (
                                <VehicleSilhouette name="sedan" className="w-full text-[#2a2f33] [--silhouette-cut:#08090b]" />
                            )}
                        </div>

                        {/* Wide screens: each service points at the part it's for. The names sit in even columns on
                            the floor below (wider than the car, so they never crowd), each joined to its spot by a
                            thin line; cqw is a share of the car's width (.drive-car is the container) */}
                        <ul className="pointer-events-none absolute inset-0 z-[3] hidden lg:block">
                            {onFloor.map((spot) => (
                                <li
                                    key={spot.slug}
                                    data-beat
                                    data-focusable
                                    data-from={spot.from}
                                    className="absolute bottom-0"
                                    style={{ left: `${spot.pin * 100}%`, top: `${spot.y * 100}%` }}
                                >
                                    <span aria-hidden="true" className="absolute -top-[5px] -left-[5px] z-[1] size-2.5 rounded-full border-2 border-[#2ad3cb] bg-[#08090b] shadow-[0_0_0_5px_rgb(42_211_203/0.16)]" />
                                    <svg
                                        aria-hidden="true"
                                        viewBox="0 0 100 100"
                                        preserveAspectRatio="none"
                                        className="absolute top-2 h-[calc(100%+var(--drive-lead))] overflow-visible"
                                        style={{ left: `${Math.min(0, spot.shift)}cqw`, width: `max(1px, ${Math.abs(spot.shift)}cqw)` }}
                                    >
                                        <defs>
                                            <linearGradient id={`lead-${spot.slug}`} gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="100">
                                                <stop offset="0" stopColor="#2ad3cb" stopOpacity="0.75" />
                                                <stop offset="1" stopColor="#ffffff" stopOpacity="0.22" />
                                            </linearGradient>
                                        </defs>
                                        <line
                                            x1={spot.shift < 0 ? 100 : 0}
                                            y1="0"
                                            x2={spot.shift < 0 ? 0 : 100}
                                            y2="100"
                                            stroke={`url(#lead-${spot.slug})`}
                                            strokeWidth="1"
                                            vectorEffect="non-scaling-stroke"
                                        />
                                    </svg>
                                    <Link
                                        to={`/services#${spot.slug}`}
                                        className="pointer-events-auto absolute top-[calc(100%+var(--drive-lead)+0.75rem)] block w-max max-w-[29cqw] -translate-x-1/2 rounded-control px-2 py-1 text-center [text-wrap:balance]"
                                        style={{ left: `${spot.shift}cqw` }}
                                    >
                                        <span className="eyebrow block text-[0.66rem] whitespace-nowrap text-white xl:text-[0.7rem]">{spot.category!.name}</span>
                                        <span className="mt-1.5 block text-[0.8125rem] leading-snug text-[#b9c0c3] xl:text-sm">{text.parts[spot.slug]}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                {/* Phones and tablets: the services in a grid under the car */}
                <ul className="drive-list absolute inset-x-0 z-10 mx-auto grid max-w-xl grid-cols-2 gap-x-5 gap-y-2 px-5 lg:hidden">
                    {ordered.map((spot) => (
                        <li key={spot.slug} data-beat data-focusable data-from={spot.from} className="border-t border-white/15">
                            <Link to={`/services#${spot.slug}`} className="block min-h-11 pt-2.5">
                                <span className="eyebrow block text-[0.64rem] text-white">{spot.category!.name}</span>
                                <span className="mt-1 block text-[0.8125rem] leading-snug text-[#b9c0c3]">{text.parts[spot.slug]}</span>
                            </Link>
                        </li>
                    ))}
                </ul>

            </div>
        </section>
    );
}
