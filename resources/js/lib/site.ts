import type { Branch, BranchHours, Category, Photo, Rate, Service, VehicleSize } from '../types';

/**
 * Everything the site shows, embedded in the page by SiteController.
 */
export const site = window.APP826;

export const sizes: VehicleSize[] = site.catalog.sizes;

export const categories: Category[] = site.catalog.categories;

/** Every service, in the admin's order, with its category */
export const services: (Service & { category: Category })[] = categories.flatMap((category) => category.services.map((service) => ({ ...service, category })));

export const serviceById = (id: number) => services.find((service) => service.id === id);

export const serviceBySlug = (slug: string | null | undefined) => services.find((service) => service.slug === slug);

export const sizeById = (id: number | null | undefined) => sizes.find((size) => size.id === id);

export const branchById = (id: number | null | undefined) => site.branches.find((branch) => branch.id === id);

export const branchBySlug = (slug: string | null | undefined) => site.branches.find((branch) => branch.slug === slug);

/** Branches that take online reservations now */
export const bookableBranches = site.branches.filter((branch) => branch.bookable);

/** Branches that come to the customer */
export const homeBranches = site.branches.filter((branch) => branch.homeService.offered);

export const rateFor = (service: Service, sizeId: number | null | undefined): Rate | undefined => service.rates.find((rate) => rate.sizeId === sizeId);

/** The lowest price a service has, for "From ₱X"; null without rates */
export function fromPrice(service: Service): number | null {
    return service.rates.length ? Math.min(...service.rates.map((rate) => rate.price)) : null;
}

/** The lowest price over a category's services; null when none has rates */
export function categoryFromPrice(category: Category): number | null {
    const prices = category.services.map(fromPrice).filter((price): price is number => price !== null);

    return prices.length ? Math.min(...prices) : null;
}

/** Open branches where a service can be booked */
export const branchesOffering = (service: Service): Branch[] => site.branches.filter((branch) => branch.serviceIds.includes(service.id));

/** The services a branch offers for a vehicle size (a rate for that size), in the catalog's order */
export function servicesAt(branch: Branch | undefined, sizeId: number | null, { home = false } = {}) {
    if (!branch || !sizeId) {
        return [];
    }

    return services.filter((service) => branch.serviceIds.includes(service.id) && rateFor(service, sizeId) && (!home || service.homeServiceOk));
}

/**
 * The same sum the server makes: days add up, then minutes ("2 days + 4
 * hours"). Shown while the server's own answer is on its way.
 */
export function lengthLabel(days: number, minutes: number): string {
    const parts: string[] = [];

    if (days > 0) {
        parts.push(days === 1 ? '1 day' : `${days} days`);
    }

    if (minutes > 0) {
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;
        parts.push(rest === 0 ? (hours === 1 ? '1 hour' : `${hours} hours`) : hours === 0 ? `${rest} min` : `${hours} hr ${rest} min`);
    }

    return parts.join(' + ');
}

/**
 * 10500 → "₱10,500".
 */
export function peso(amount: number): string {
    return `₱${amount.toLocaleString('en-PH')}`;
}

/** "08:00" → "8:00 AM" */
export function clock(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const [hours, minutes] = value.split(':').map(Number);
    const suffix = hours >= 12 ? 'PM' : 'AM';
    const hour = hours % 12 || 12;

    return `${hour}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

/** Today's ISO weekday in Manila (1 = Monday … 7 = Sunday) */
export function manilaWeekday(): number {
    const name = new Intl.DateTimeFormat('en-US', { weekday: 'short', timeZone: 'Asia/Manila' }).format(new Date());

    return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].indexOf(name) + 1;
}

export const hoursToday = (branch: Branch): BranchHours | undefined => branch.hours.find((day) => day.day === manilaWeekday());

/** "8:00 AM – 6:00 PM", or null when closed */
export const hoursRange = (hours: BranchHours | undefined): string | null => (hours?.open && hours.close ? `${clock(hours.open)} – ${clock(hours.close)}` : null);

/**
 * The week in runs of days with the same hours: "Monday – Saturday",
 * "Sunday" (for a short summary; the branch page has every day).
 */
export function hoursSummary(branch: Branch): { days: string; hours: string | null }[] {
    const runs: { from: BranchHours; to: BranchHours }[] = [];

    for (const day of branch.hours) {
        const last = runs[runs.length - 1];

        if (last && last.to.open === day.open && last.to.close === day.close && last.to.day === day.day - 1) {
            last.to = day;
        } else {
            runs.push({ from: day, to: day });
        }
    }

    return runs.map(({ from, to }) => ({ days: from.day === to.day ? from.label : `${from.label} – ${to.label}`, hours: hoursRange(from) }));
}

/** Site-wide phone, for tel: and sms: links */
export const phone = site.settings.phone && site.settings.phoneHref ? { label: site.settings.phone, href: site.settings.phoneHref } : null;

/** Where to message 826: Messenger when set, else the Facebook page */
export const messageUrl = site.settings.messenger ?? site.settings.facebook;

/**
 * Photo categories that have photos, in the admin's order.
 */
export const photoCategories = Object.keys(site.photoCategories).filter((category) => site.photos.some((photo) => photo.category === category));

export const featuredPhotos: Photo[] = site.photos.some((photo) => photo.isFeatured) ? site.photos.filter((photo) => photo.isFeatured) : site.photos.slice(0, 8);

export function photoAlt(photo: Photo): string {
    if (photo.caption) {
        return photo.caption;
    }

    const category = site.photoCategories[photo.category]?.toLowerCase() ?? 'our work';

    return `${site.shortName}: ${category}`;
}

/** A path on this site's API, under the folder it's served from */
export const apiUrl = (path: string) => `${site.base}/api${path}`;
