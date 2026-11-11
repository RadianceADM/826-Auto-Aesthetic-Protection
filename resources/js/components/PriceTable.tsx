import { copy } from '../lib/copy';
import { peso, sizes } from '../lib/site';
import type { Service } from '../types';
import { Info } from './Icons';

/**
 * A service's price and time at the shop for each vehicle size it has a
 * rate for. Without rates: no prices, and where to ask for one.
 */
export default function PriceTable({ service, highlightSize, compact = false }: { service: Service; highlightSize?: number | null; compact?: boolean }) {
    const rows = sizes.map((size) => ({ size, rate: service.rates.find((rate) => rate.sizeId === size.id) })).filter((row) => row.rate);

    if (rows.length === 0) {
        return (
            <div className="flex gap-3 rounded-control bg-surface-3/60 px-4 py-3.5 text-[0.95rem]">
                <Info className="mt-0.5 size-5 shrink-0 text-accent" />
                <p>
                    <span className="font-semibold">{copy.price.onRequest}.</span> {copy.price.onRequestBody}
                </p>
            </div>
        );
    }

    const cell = compact ? 'py-2.5' : 'py-3';

    return (
        <table className="w-full border-collapse text-left text-[0.95rem]">
            <caption className="sr-only">{copy.price.table.caption(service.name)}</caption>
            <thead>
                <tr className="border-b border-line text-muted">
                    <th scope="col" className="eyebrow pb-2.5 font-semibold">
                        {copy.price.table.size}
                    </th>
                    <th scope="col" className="eyebrow pb-2.5 text-right font-semibold">
                        {copy.price.table.price}
                    </th>
                    <th scope="col" className="eyebrow pb-2.5 pl-4 text-right font-semibold max-[380px]:hidden">
                        {copy.price.table.time}
                    </th>
                </tr>
            </thead>
            <tbody>
                {rows.map(({ size, rate }) => (
                    <tr key={size.id} className={`border-b border-line last:border-b-0 ${highlightSize === size.id ? 'bg-accent/8' : ''}`}>
                        <th scope="row" className={`${cell} pr-3 font-medium`}>
                            {size.name}
                            {size.examples && !compact && <span className="block text-sm font-normal text-muted">{copy.price.examples(size.examples)}</span>}
                            <span className="block text-sm font-normal text-muted min-[381px]:hidden">{rate!.duration}</span>
                        </th>
                        <td className={`${cell} price text-right whitespace-nowrap`}>{peso(rate!.price)}</td>
                        <td className={`${cell} pl-4 text-right whitespace-nowrap text-muted max-[380px]:hidden`}>{rate!.duration}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
