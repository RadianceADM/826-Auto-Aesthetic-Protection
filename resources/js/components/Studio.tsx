import { type CSSProperties, type ReactNode, useEffect, useRef, useState } from 'react';

import { avifOf, type Car, carAlt, carAvifSrcSet, carFile, carSrcSet, contactOf, type Detail, type Facing } from '../lib/cars';
import VehicleSilhouette from './VehicleSilhouette';

/* ------------------------------------------------------------- lights */

type Bucket = { d: string; width: number; opacity: number };

const W = 1600;
const H = 600;

/** A point on the ceiling (x across, z away from the eye), on the drawing */
const project = (x: number, z: number, focal: number, height: number, vanishY: number) => [W / 2 + (focal * x) / z, vanishY - (focal * height) / z] as const;

type Segment = { a: readonly [number, number]; b: readonly [number, number]; z: number };

/** Lines grouped by how far away they are: nearer ones thicker and brighter */
function bucketed(segments: Segment[], near: number, far: number, focal: number, thickness: number, bands = 6): Bucket[] {
    const groups: string[][] = Array.from({ length: bands }, () => []);

    for (const { a, b, z } of segments) {
        const band = Math.min(bands - 1, Math.max(0, Math.floor(((z - near) / (far - near)) * bands)));
        groups[band].push(`M${a[0].toFixed(1)} ${a[1].toFixed(1)}L${b[0].toFixed(1)} ${b[1].toFixed(1)}`);
    }

    return groups.map((paths, band) => {
        const z = near + ((band + 0.5) / bands) * (far - near);

        return { d: paths.join(''), width: (thickness * focal) / z, opacity: Math.max(0.3, 0.82 - band * 0.1) };
    });
}

/** The part of a line inside a rectangle (x from -halfWidth to halfWidth, z from near to far), or null */
function clip(p: number[], q: number[], halfWidth: number, near: number, far: number): [number[], number[]] | null {
    let t0 = 0;
    let t1 = 1;
    const dx = q[0] - p[0];
    const dz = q[1] - p[1];
    const edges: [number, number][] = [
        [-dx, p[0] + halfWidth],
        [dx, halfWidth - p[0]],
        [-dz, p[1] - near],
        [dz, far - p[1]],
    ];

    for (const [direction, distance] of edges) {
        if (direction === 0) {
            if (distance < 0) return null;
            continue;
        }

        const t = distance / direction;

        if (direction < 0) t0 = Math.max(t0, t);
        else t1 = Math.min(t1, t);

        if (t0 > t1) return null;
    }

    return [
        [p[0] + dx * t0, p[1] + dz * t0],
        [p[0] + dx * t1, p[1] + dz * t1],
    ];
}

const FOCAL = 700;


/** A run of tube between two points on the ceiling, a little short at each end (the gap at each joint) */
function tube(from: number[], to: number[], height: number, gap: number, box = H): Segment {
    const vanish = box * 1.1;
    const start = [from[0] + (to[0] - from[0]) * gap, from[1] + (to[1] - from[1]) * gap];
    const end = [to[0] - (to[0] - from[0]) * gap, to[1] - (to[1] - from[1]) * gap];

    return { a: project(start[0], start[1], FOCAL, height, vanish), b: project(end[0], end[1], FOCAL, height, vanish), z: (start[1] + end[1]) / 2 };
}

/** The frame around an installation: two long rails and the two ends */
function frame(halfWidth: number, near: number, far: number, height: number, box = H): Segment[] {
    const corners = [
        [-halfWidth, near],
        [halfWidth, near],
        [halfWidth, far],
        [-halfWidth, far],
    ];

    return corners.map((corner, index) => tube(corner, corners[(index + 1) % 4], height, 0.004, box));
}

/**
 * The hexagon LED ceiling detailing bays are known for: a framed panel of
 * hexagons over the car, in perspective, each side its own tube with a
 * small gap at the corners.
 */
function hexagonPanel(): Bucket[] {
    const size = 0.2;
    const halfWidth = 1.0;
    const near = 1.5;
    const far = 5.4;
    const height = 1.6;
    const seen = new Set<string>();
    const segments: Segment[] = frame(halfWidth, near, far, height);
    const stepX = size * 1.5;
    const stepZ = Math.sqrt(3) * size;

    for (let column = -9; column <= 9; column++) {
        for (let row = -1; row < 24; row++) {
            const cx = column * stepX;
            const cz = near + row * stepZ + (Math.abs(column) % 2 ? stepZ / 2 : 0);

            for (let corner = 0; corner < 6; corner++) {
                const a0 = (Math.PI / 3) * corner;
                const a1 = (Math.PI / 3) * (corner + 1);
                const p = [cx + size * Math.cos(a0), cz + size * Math.sin(a0)];
                const q = [cx + size * Math.cos(a1), cz + size * Math.sin(a1)];
                const key = [p, q]
                    .map(([x, z]) => `${x.toFixed(2)},${z.toFixed(2)}`)
                    .sort()
                    .join('|');

                if (seen.has(key)) {
                    continue;
                }

                seen.add(key);
                const inside = clip(p, q, halfWidth - 0.02, near + 0.02, far - 0.02);

                if (inside) {
                    // Whole sides get the gap at both corners; sides cut by the frame keep their length
                    const whole = inside[0][0] === p[0] && inside[1][0] === q[0] && inside[0][1] === p[1] && inside[1][1] === q[1];
                    segments.push(tube(inside[0], inside[1], height, whole ? 0.09 : 0.02));
                }
            }
        }
    }

    return bucketed(segments, near, far, FOCAL, 0.0075);
}

/** The drive-in's lights: a framed run of straight LED bars over the bay, one behind the other (a shorter, wider drawing) */
const BAR_BOX = 340;

function barPanel(halfWidth: number, height: number, box: number): Bucket[] {
    const near = 2.1;
    const far = 6.6;
    const bars = [2.1, 2.7, 3.4, 4.3, 5.35, 6.6].map((z) => tube([-halfWidth, z], [halfWidth, z], height, 0.002, box));

    return bucketed([...bars, ...frame(halfWidth, near, far, height, box).filter((_, index) => index % 2 === 1)], near, far, FOCAL, 0.013);
}

const HEX = hexagonPanel();
const BARS = barPanel(2.6, 1.08, BAR_BOX);

/** The same bars for a phone: a narrower bay, drawn taller */
const BARS_NARROW = barPanel(1.7, 1.3, H);

/**
 * The ceiling of the bay: its fixtures (hexagons or bars), lit, and the
 * same lights blurred in the floor's gloss. Decorative.
 */
export function Ceiling({ kind = 'hex', reflect = true, fade, className = '', style }: { kind?: 'hex' | 'bars' | 'bars-narrow'; reflect?: boolean; fade?: 'left'; className?: string; style?: CSSProperties }) {
    const buckets = kind === 'hex' ? HEX : kind === 'bars' ? BARS : BARS_NARROW;
    const svg = (
        <svg viewBox={`0 0 ${W} ${kind === 'bars' ? BAR_BOX : H}`} preserveAspectRatio="xMidYMax slice" focusable="false">
            <g fill="none" strokeLinecap="round">
                {buckets.map((bucket, index) => (
                    <path key={`h${index}`} d={bucket.d} className="bay-housing" strokeWidth={bucket.width * 2.4} />
                ))}
                {buckets.map((bucket, index) => (
                    <path key={`g${index}`} d={bucket.d} className="bay-glow" strokeWidth={bucket.width * 3.2} strokeOpacity={bucket.opacity * 0.32} />
                ))}
                {buckets.map((bucket, index) => (
                    <path key={`t${index}`} d={bucket.d} className="bay-tube" strokeWidth={bucket.width} strokeOpacity={bucket.opacity} />
                ))}
            </g>
        </svg>
    );

    return (
        <>
            <div aria-hidden="true" data-fade={fade} className={`bay-ceiling ${className}`} style={style}>
                {svg}
            </div>
            {reflect && (
                <div aria-hidden="true" data-fade={fade} className={`bay-ceiling bay-ceiling-reflection ${className}`} style={style}>
                    {svg}
                </div>
            )}
        </>
    );
}

/* --------------------------------------------------------------- cars */

type ParkedProps = {
    car?: Car;
    /** The way the car should point; a cutout facing the other way is mirrored */
    face?: Facing;
    sizes: string;
    eager?: boolean;
    /** A band of light crosses the paint now and then (stops for reduced motion) */
    sweep?: boolean;
    reflection?: boolean;
    alt?: string;
    className?: string;
    style?: CSSProperties;
    /** How far up from the image's bottom edge the tyres touch the floor, in % of its height */
    contact?: number;
    /** The shadow pool: how far in from each side, how tall */
    shadow?: { inset?: string; height?: string };
    children?: ReactNode;
};

/**
 * A car standing on the bay's floor: its shadow, the cutout (srcset, with
 * width and height so nothing jumps), its reflection in the floor, and a
 * passing light. Until the cutouts are in, a drawn sedan stands in.
 */
export function ParkedCar({ car, face, sizes, eager = false, sweep = false, reflection = true, alt, className = '', style, contact = 0, shadow, children }: ParkedProps) {
    const image = useRef<HTMLImageElement>(null);
    const [loaded, setLoaded] = useState<string | null>(null);
    const mirrored = Boolean(car && face && car.facing !== face);
    const vars = {
        ...style,
        '--contact': contact,
        ...(shadow?.inset ? { '--shadow-inset': shadow.inset } : {}),
        ...(shadow?.height ? { '--shadow-h': shadow.height } : {}),
    } as CSSProperties;

    // The file the browser picked, once it's in: the reflection and the sweep's mask reuse it, so the
    // car is fetched once
    useEffect(() => {
        const element = image.current;

        if (element?.complete && element.naturalWidth) {
            setLoaded(element.currentSrc);
        }
    }, [car]);

    if (!car) {
        return (
            <div className={`parked text-steel/50 ${className}`} style={vars}>
                <VehicleSilhouette name="sedan" className="w-full [--silhouette-cut:var(--surface)]" />
            </div>
        );
    }

    const shared = {
        src: carFile(car.files[1000]),
        srcSet: carSrcSet(car),
        sizes,
        width: car.width,
        height: car.height,
        decoding: 'async' as const,
        loading: eager ? ('eager' as const) : ('lazy' as const),
    };

    return (
        <div className={`parked ${className}`} style={vars}>
            <div aria-hidden="true" className="parked-shadow" />
            <div className="parked-body" style={mirrored ? { transform: 'scaleX(-1)' } : undefined}>
                <picture className="contents">
                    <source type="image/avif" srcSet={carAvifSrcSet(car)} sizes={sizes} />
                    <img
                        ref={image}
                        {...shared}
                        alt={alt ?? carAlt(car)}
                        fetchPriority={eager ? 'high' : undefined}
                        onLoad={(event) => setLoaded(event.currentTarget.currentSrc)}
                        className="parked-img"
                    />
                </picture>
                {reflection && loaded && <img src={loaded} width={car.width} height={car.height} decoding="async" alt="" aria-hidden="true" className="parked-reflection" />}
                {sweep && loaded && (
                    <div aria-hidden="true" className="parked-sweep sweep-slow" style={{ maskImage: `url("${loaded}")`, WebkitMaskImage: `url("${loaded}")` }}>
                        <span />
                    </div>
                )}
            </div>
            {children}
        </div>
    );
}

type CropProps = {
    car?: Car;
    /**
     * The point to centre (fractions of the cutout's width and height) and how far in to go (1 = the whole car
     * fits the frame's width). With `floor`, a whole car instead stands with its tyres that far down the frame.
     */
    focus: { x: number; y: number; zoom: number; floor?: number };
    /** The frame's width over its height */
    aspect: number;
    face?: Facing;
    /** The frame's width in the layout ("(min-width: 1024px) 30vw, 100vw"): the picked file is that times the zoom */
    frame: { wide: string; narrow: string; breakpoint?: number };
    alt?: string;
    eager?: boolean;
    /** A sharper crop of the part shown, when there is one */
    detail?: Detail;
    className?: string;
    children?: ReactNode;
};

/**
 * A close-up from a cutout (the bumper for film, the glass for tint, a
 * panel for coating): the whole file, zoomed and placed in a frame.
 */
export function CarCrop({ car, focus, aspect, face, frame, alt, eager = false, detail, className = '', children }: CropProps) {
    if (!car) {
        return (
            <div className={`crop grid place-items-center text-steel/40 ${className}`} style={{ aspectRatio: aspect }}>
                <VehicleSilhouette name="sedan" className="w-[70%] [--silhouette-cut:var(--surface)]" />
                {children}
            </div>
        );
    }

    const mirrored = Boolean(face && car.facing !== face);
    const x = mirrored ? 1 - focus.x : focus.x;
    const ratio = car.width / car.height;
    const left = 50 - x * focus.zoom * 100;
    const shownHeight = focus.zoom * (aspect / ratio) * 100;
    const top = focus.floor !== undefined ? focus.floor * 100 - shownHeight * (1 - contactOf(car) / 100) : 50 - focus.y * shownHeight;
    const scale = (value: string, share = 1) => value.replace(/(\d+(?:\.\d+)?)vw/, (_, number: string) => `${Math.round(Number(number) * focus.zoom * share)}vw`);

    const placed = { width: `${focus.zoom * 100}%`, left: `${left}%`, top: `${top}%`, transform: mirrored ? 'scaleX(-1)' : undefined };
    const loading = eager ? ('eager' as const) : ('lazy' as const);

    return (
        <div className={`crop ${className}`} style={{ aspectRatio: aspect }}>
            {detail ? (
                // The car's box, placed as the whole file would be, holding only the sharp crop of the part shown
                <div className="crop-detail" style={{ ...placed, aspectRatio: ratio }}>
                    <picture className="contents">
                    <source
                        type="image/avif"
                        srcSet={`${carFile(avifOf(detail.files[1000]))} 1000w, ${carFile(avifOf(detail.files.full))} ${detail.width}w`}
                        sizes={`(min-width: ${frame.breakpoint ?? 1024}px) ${scale(frame.wide, detail.w)}, ${scale(frame.narrow, detail.w)}`}
                    />
                    <img
                        src={carFile(detail.files[1000])}
                        srcSet={`${carFile(detail.files[1000])} 1000w, ${carFile(detail.files.full)} ${detail.width}w`}
                        sizes={`(min-width: ${frame.breakpoint ?? 1024}px) ${scale(frame.wide, detail.w)}, ${scale(frame.narrow, detail.w)}`}
                        width={detail.width}
                        height={Math.round((detail.width * detail.h) / (detail.w * ratio))}
                        alt={alt ?? carAlt(car)}
                        loading={loading}
                        fetchPriority={eager ? 'high' : undefined}
                        decoding="async"
                        style={{ left: `${detail.x * 100}%`, top: `${detail.y * 100}%`, width: `${detail.w * 100}%`, height: `${detail.h * 100}%` }}
                    />
                    </picture>
                </div>
            ) : (
                <picture className="contents">
                <source type="image/avif" srcSet={carAvifSrcSet(car)} sizes={`(min-width: ${frame.breakpoint ?? 1024}px) ${scale(frame.wide)}, ${scale(frame.narrow)}`} />
                <img
                    src={carFile(car.files[1000])}
                    srcSet={carSrcSet(car)}
                    sizes={`(min-width: ${frame.breakpoint ?? 1024}px) ${scale(frame.wide)}, ${scale(frame.narrow)}`}
                    width={car.width}
                    height={car.height}
                    alt={alt ?? carAlt(car)}
                    loading={loading}
                    fetchPriority={eager ? 'high' : undefined}
                    decoding="async"
                    style={placed}
                />
                </picture>
            )}
            {/* Standing on the floor: the dark line where the tyres touch it */}
            {focus.floor !== undefined && <span aria-hidden="true" className="crop-contact" style={{ top: `${focus.floor * 100}%` }} />}
            {children}
        </div>
    );
}
