import { copy } from '../lib/copy';

type Kind = 'film' | 'coating';

/** Which diagram a category gets: film for PPF, coating for coatings; none for the rest */
export function layersFor(slug: string): Kind | null {
    if (/ppf|protection-film/.test(slug)) return 'film';
    if (/coating/.test(slug)) return 'coating';

    return null;
}

/**
 * What sits on what, as an exploded stack: the body panel, primer, the
 * colour, the clear coat, and on top, the film (thick, clear) or the
 * coating (a very thin layer). Thicknesses are drawn in the right order of
 * size, not to scale, and carry no numbers.
 */
export default function LayerDiagram({ kind, className = '' }: { kind: Kind; className?: string }) {
    const text = copy.layers;
    const top = kind === 'film' ? { label: text.film, thickness: 18 } : { label: text.coating, thickness: 3 };
    const layers = [
        { key: 'top', label: top.label, note: kind === 'film' ? text.filmNote : text.coatingNote, thickness: top.thickness, tone: 'layer-top' },
        { key: 'clear', label: text.clear, note: text.clearNote, thickness: 10, tone: 'layer-clear' },
        { key: 'colour', label: text.colour, note: null, thickness: 7, tone: 'layer-colour' },
        { key: 'primer', label: text.primer, note: null, thickness: 7, tone: 'layer-primer' },
        { key: 'panel', label: text.panel, note: null, thickness: 16, tone: 'layer-panel' },
    ];

    const width = 176;
    const depth = 40;
    const rise = 22;
    const gap = 34;
    const left = 4;
    let y = 30;

    const slabs = layers.map((layer) => {
        const slab = { ...layer, y };
        y += layer.thickness + gap;

        return slab;
    });
    const height = y - gap + 10;

    return (
        <figure className={`layer-diagram ${className}`}>
            <svg viewBox={`0 0 500 ${height}`} role="img" aria-labelledby={`layers-${kind}`} className="block h-auto w-full">
                <title id={`layers-${kind}`}>{kind === 'film' ? text.filmTitle : text.coatingTitle}</title>
                {/* Bottom to top, so each slab sits over the one under it */}
                {[...slabs].reverse().map((slab) => {
                    const x0 = left;
                    const x1 = left + width;
                    const topFace = `M${x0},${slab.y} L${x1},${slab.y} L${x1 + depth},${slab.y - rise} L${x0 + depth},${slab.y - rise} Z`;
                    const front = `M${x0},${slab.y} L${x1},${slab.y} L${x1},${slab.y + slab.thickness} L${x0},${slab.y + slab.thickness} Z`;
                    const side = `M${x1},${slab.y} L${x1 + depth},${slab.y - rise} L${x1 + depth},${slab.y - rise + slab.thickness} L${x1},${slab.y + slab.thickness} Z`;
                    const labelY = slab.y - rise / 2 + slab.thickness / 2;

                    return (
                        <g key={slab.key} className={slab.tone}>
                            <path d={front} className="layer-front" />
                            <path d={side} className="layer-side" />
                            <path d={topFace} className="layer-face" />
                            <line x1={x1 + depth + 8} y1={labelY} x2={x1 + depth + 26} y2={labelY} className="layer-lead" />
                            <text x={x1 + depth + 34} y={labelY + (slab.note ? -2 : 5)} className="layer-label">
                                {slab.label}
                            </text>
                            {slab.note && (
                                <text x={x1 + depth + 34} y={labelY + 17} className="layer-note">
                                    {slab.note}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </figure>
    );
}
