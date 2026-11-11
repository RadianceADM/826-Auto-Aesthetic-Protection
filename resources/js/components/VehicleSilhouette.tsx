/**
 * A side profile for each vehicle size, drawn in one colour (currentColor)
 * with the glass and the wheel faces let through. Decorative: the size's
 * name always sits next to it.
 */

type Kind = 'motorcycle' | 'small' | 'sedan' | 'suv' | 'pickup';

/** The size's name says which shape it is ("Small car / Hatchback", "SUV / Crossover"); anything else is a sedan */
export function silhouetteFor(name: string | null | undefined): Kind {
    const value = (name ?? '').toLowerCase();

    if (/motor|bike|scooter/.test(value)) return 'motorcycle';
    if (/pick ?up|van|truck/.test(value)) return 'pickup';
    if (/suv|crossover|mpv|4x4/.test(value)) return 'suv';
    if (/small|hatch|compact|mini|city/.test(value)) return 'small';

    return 'sedan';
}

type Shape = { body: string; glass: string[]; wheels: { cx: number; cy: number; r: number }[] };

const SHAPES: Record<Exclude<Kind, 'motorcycle'>, Shape> = {
    small: {
        body: 'M36,74 L33,61 C33,53 36,48 42,45 C52,39 66,33.5 82,32 C100,30.5 118,30.6 131,32.2 C142,36 151,42.6 160,48 L195,53 C202,54.4 206,58 207,63 L207,72 C207,73.4 206,74 204,74 L188.5,74 A17,17 0 1 0 155.5,74 L84.5,74 A17,17 0 1 0 51.5,74 Z',
        glass: ['M45,48.4 C53,42.6 65,37.4 80,35.8 L99,34.6 L99,48.6 Z', 'M103,34.5 L129.5,35.2 C138,38.4 146,43.4 152.5,48 L103,48.6 Z'],
        wheels: [
            { cx: 68, cy: 70, r: 14 },
            { cx: 172, cy: 70, r: 14 },
        ],
    },
    sedan: {
        body: 'M18,74 L16,63 C16,57 19,55 25,54.5 L58,51 C66,44 76,37.5 90,34.4 C108,31 128,31 140,32.6 C150,36 160,43 170,48 L210,53 C218,54.5 224,58 226,63 L226,72 C226,73.4 225,74 223,74 L200.5,74 A17,17 0 1 0 167.5,74 L78.5,74 A17,17 0 1 0 45.5,74 Z',
        glass: ['M66,50.2 C73,44.4 81,40.2 92,37.8 L111,36.4 L111,49.4 Z', 'M115,36.3 L137.5,35.9 C146,39.4 154,44 161,48.4 L115,49.3 Z'],
        wheels: [
            { cx: 62, cy: 70, r: 14 },
            { cx: 184, cy: 70, r: 14 },
        ],
    },
    suv: {
        body: 'M16,72 L15,52 C15,44 18,40 24,38 L38,26 C42,22 46,21 52,21 L140,21 C146,21 150,23 154,27 L172,42 L212,47 C220,48.5 225,53 226,59 L226,70 C226,71.4 225,72 223,72 L202.6,72 A19,19 0 1 0 165.4,72 L80.6,72 A19,19 0 1 0 43.4,72 Z',
        glass: ['M30,40 L42,28.4 C45,25.8 48,25 52,25 L91,25 L91,40.6 Z', 'M95,25 L139,25 C143,25 146,26.4 149,29.4 L163,41.6 L95,41.4 Z'],
        wheels: [
            { cx: 62, cy: 68, r: 16 },
            { cx: 184, cy: 68, r: 16 },
        ],
    },
    pickup: {
        body: 'M14,72 L13,46 L16,43.6 L97,43.6 L100,26 C101,22 104,20 108,20 L150,20 C155,20 158,22 161,25 L176,41 L214,46 C221,47.5 226,52 227,58 L227,70 C227,71.4 226,72 224,72 L202.6,72 A19,19 0 1 0 165.4,72 L76.6,72 A19,19 0 1 0 39.4,72 Z',
        glass: ['M104.4,40 L106.2,26 C106.6,24.6 107.6,23.8 109.2,23.8 L128,23.8 L128,40 Z', 'M132,23.8 L149,23.8 C152.5,23.8 155,24.8 157,26.8 L170.6,40 L132,40 Z'],
        wheels: [
            { cx: 58, cy: 68, r: 16 },
            { cx: 184, cy: 68, r: 16 },
        ],
    },
};

function Wheel({ cx, cy, r }: { cx: number; cy: number; r: number }) {
    return (
        <g>
            <circle cx={cx} cy={cy} r={r} fill="currentColor" />
            <circle cx={cx} cy={cy} r={r * 0.62} className="silhouette-cut" />
            <circle cx={cx} cy={cy} r={r * 0.2} fill="currentColor" />
        </g>
    );
}

export default function VehicleSilhouette({ name, className = '' }: { name: string | null | undefined; className?: string }) {
    const kind = silhouetteFor(name);

    return (
        <svg viewBox="0 0 240 90" aria-hidden="true" focusable="false" className={`silhouette ${className}`}>
            {/* The floor under it */}
            <ellipse cx="120" cy="85.5" rx="104" ry="2.6" className="silhouette-floor" />
            {kind === 'motorcycle' ? (
                <g>
                    <path d="M76,60 L101,57 C104,50 111,45.5 120,44.5 L139,44 C145,44 149,46.4 151,50 L157,55.6 L163,50.5 L170,50.5 L167,58 L150,61.5 L113,64.5 Z" fill="currentColor" />
                    <path d="M98,52 C102,47.5 108,45.6 114,45.4" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                    <path d="M160,52 L171,70" fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
                    <path d="M86,66.5 L120,66" fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
                    <path d="M159,47 L166,41.5 L174,42.5" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                    {[
                        { cx: 72, cy: 70 },
                        { cx: 170, cy: 70 },
                    ].map((wheel) => (
                        <g key={wheel.cx}>
                            <circle cx={wheel.cx} cy={wheel.cy} r="14" fill="none" stroke="currentColor" strokeWidth="5" />
                            <circle cx={wheel.cx} cy={wheel.cy} r="2.6" fill="currentColor" />
                        </g>
                    ))}
                </g>
            ) : (
                <g>
                    <path d={SHAPES[kind].body} fill="currentColor" />
                    {SHAPES[kind].glass.map((glass) => (
                        <path key={glass} d={glass} className="silhouette-glass" />
                    ))}
                    {SHAPES[kind].wheels.map((wheel) => (
                        <Wheel key={wheel.cx} {...wheel} />
                    ))}
                </g>
            )}
        </svg>
    );
}
