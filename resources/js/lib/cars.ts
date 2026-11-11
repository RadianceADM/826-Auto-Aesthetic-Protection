import { site } from './site';

/**
 * The car pictures: cutouts on a transparent background (public/cars, one
 * file each at 1800, 1000 and 600 px wide), described by
 * public/cars/manifest.json. They stand in until 826 sends photos of its
 * own work, so they only ever illustrate the kind of cars the shops care
 * for: never "our work", a customer's car or a result.
 */

export type CarAngle = 'side' | 'front-34' | 'rear-34';

export type Facing = 'left' | 'right';

/** A wheel drawn on its own layer: centre and radius as fractions of the image's width and height */
export type Wheel = { name: string; cx: number; cy: number; r: number; file: string };

export type Car = {
    slug: string;
    make: string;
    model: string;
    year: number | string | null;
    angle: CarAngle;
    facing: Facing;
    colour: string;
    width: number;
    height: number;
    files: { 1800: string; 1000: string; 600: string };
    licence: string;
    author: string;
    source: string;
    attribution: string;
    wheels?: Wheel[];
};

/** The cars, from public/cars/manifest.json (copied in when the files change) */
export const cars: Car[] = [
    {
        "slug": "bmw-7-series-side-black",
        "make": "BMW",
        "model": "7 Series 740i M Sport (G70)",
        "year": null,
        "angle": "side",
        "facing": "right",
        "colour": "black",
        "width": 1800,
        "height": 594,
        "files": {
            "600": "bmw-7-series-side-black-600.webp",
            "1000": "bmw-7-series-side-black-1000.webp",
            "1800": "bmw-7-series-side-black.webp"
        },
        "licence": "CC BY-SA 4.0",
        "author": "Damian B Oh",
        "source": "https://commons.wikimedia.org/wiki/File:BMW_G70_740i_sDrive_M_Sport_Black_Sapphire_Metallic_(167).jpg",
        "attribution": "\"BMW G70 740i sDrive M Sport Black Sapphire Metallic (167)\" by Damian B Oh, CC BY-SA 4.0, via Wikimedia Commons, background removed",
        "wheels": [
            {
                "name": "front",
                "cx": 0.84127,
                "cy": 0.74759,
                "r": 0.06783,
                "file": "bmw-7-series-side-black-wheel-front.webp"
            },
            {
                "name": "rear",
                "cx": 0.21518,
                "cy": 0.74744,
                "r": 0.06783,
                "file": "bmw-7-series-side-black-wheel-rear.webp"
            }
        ]
    },
    {
        "slug": "range-rover-side-white",
        "make": "Land Rover",
        "model": "Range Rover SV LWB (L460)",
        "year": null,
        "angle": "side",
        "facing": "left",
        "colour": "white",
        "width": 1800,
        "height": 727,
        "files": {
            "600": "range-rover-side-white-600.webp",
            "1000": "range-rover-side-white-1000.webp",
            "1800": "range-rover-side-white.webp"
        },
        "licence": "CC BY-SA 4.0",
        "author": "Damian B Oh",
        "source": "https://commons.wikimedia.org/wiki/File:Land_Rover_Range_Rover_P615_SV_LWB_L460_Ostuni_Pearl_White_(10).jpg",
        "attribution": "\"Land Rover Range Rover P615 SV LWB L460 Ostuni Pearl White (10)\" by Damian B Oh, CC BY-SA 4.0, via Wikimedia Commons, background removed"
    },
    {
        "slug": "land-cruiser-300-side-black",
        "make": "Toyota",
        "model": "Land Cruiser 300 VXR",
        "year": 2025,
        "angle": "side",
        "facing": "left",
        "colour": "black",
        "width": 1800,
        "height": 809,
        "files": {
            "600": "land-cruiser-300-side-black-600.webp",
            "1000": "land-cruiser-300-side-black-1000.webp",
            "1800": "land-cruiser-300-side-black.webp"
        },
        "licence": "CC BY-SA 4.0",
        "author": "Captainmorlypogi1959",
        "source": "https://commons.wikimedia.org/wiki/File:Toyota_Land_Cruiser_300_4x4_VXR_2025_(8).jpg",
        "attribution": "\"Toyota Land Cruiser 300 4x4 VXR 2025 (8)\" by Captainmorlypogi1959, CC BY-SA 4.0, via Wikimedia Commons, background removed, wheel chock and window sticker retouched out"
    },
    {
        "slug": "ferrari-roma-side-red",
        "make": "Ferrari",
        "model": "Roma",
        "year": null,
        "angle": "side",
        "facing": "left",
        "colour": "red",
        "width": 1800,
        "height": 554,
        "files": {
            "600": "ferrari-roma-side-red-600.webp",
            "1000": "ferrari-roma-side-red-1000.webp",
            "1800": "ferrari-roma-side-red.webp"
        },
        "licence": "CC BY-SA 4.0",
        "author": "Epensock",
        "source": "https://commons.wikimedia.org/wiki/File:Award_Winning_Roma_Design_(Rosso_Maranello).jpg",
        "attribution": "\"Award Winning Roma Design (Rosso Maranello)\" by Epensock, CC BY-SA 4.0, via Wikimedia Commons, background removed, background seen through the glass darkened"
    },
    {
        "slug": "porsche-911-34-white",
        "make": "Porsche",
        "model": "911 Carrera S (992)",
        "year": null,
        "angle": "front-34",
        "facing": "right",
        "colour": "white",
        "width": 1800,
        "height": 817,
        "files": {
            "600": "porsche-911-34-white-600.webp",
            "1000": "porsche-911-34-white-1000.webp",
            "1800": "porsche-911-34-white.webp"
        },
        "licence": "CC BY-SA 4.0",
        "author": "Alexander Migl",
        "source": "https://commons.wikimedia.org/wiki/File:Porsche_992_Carrera_S_coupe_IMG_5838.jpg",
        "attribution": "\"Porsche 992 Carrera S coupe IMG 5838\" by Alexander Migl, CC BY-SA 4.0, via Wikimedia Commons, background removed, number plate blanked"
    },
    {
        "slug": "g-class-g63-34-black",
        "make": "Mercedes-AMG",
        "model": "G 63 (W463A)",
        "year": null,
        "angle": "front-34",
        "facing": "right",
        "colour": "black",
        "width": 1800,
        "height": 1300,
        "files": {
            "600": "g-class-g63-34-black-600.webp",
            "1000": "g-class-g63-34-black-1000.webp",
            "1800": "g-class-g63-34-black.webp"
        },
        "licence": "CC BY-SA 4.0",
        "author": "Alexander Migl",
        "source": "https://commons.wikimedia.org/wiki/File:Mercedes-AMG_G_63_(2018)_IMG_3428.jpg",
        "attribution": "\"Mercedes-AMG G 63 (2018) IMG 3428\" by Alexander Migl, CC BY-SA 4.0, via Wikimedia Commons, background removed, floor equipment retouched out"
    },
    {
        "slug": "lexus-lx600-34-silver",
        "make": "Lexus",
        "model": "LX 600 Luxury",
        "year": 2026,
        "angle": "front-34",
        "facing": "left",
        "colour": "silver",
        "width": 1800,
        "height": 1131,
        "files": {
            "600": "lexus-lx600-34-silver-600.webp",
            "1000": "lexus-lx600-34-silver-1000.webp",
            "1800": "lexus-lx600-34-silver.webp"
        },
        "licence": "CC0",
        "author": "HJUdall",
        "source": "https://commons.wikimedia.org/wiki/File:26_Lexus_LX_600_Luxury.jpg",
        "attribution": "\"26 Lexus LX 600 Luxury\" by HJUdall, CC0, via Wikimedia Commons, background removed, dealer sticker retouched out"
    },
    {
        "slug": "mustang-dark-horse-34-blue",
        "make": "Ford",
        "model": "Mustang Dark Horse",
        "year": 2024,
        "angle": "front-34",
        "facing": "left",
        "colour": "blue",
        "width": 1800,
        "height": 965,
        "files": {
            "600": "mustang-dark-horse-34-blue-600.webp",
            "1000": "mustang-dark-horse-34-blue-1000.webp",
            "1800": "mustang-dark-horse-34-blue.webp"
        },
        "licence": "CC0",
        "author": "HJUdall",
        "source": "https://commons.wikimedia.org/wiki/File:24_Ford_Mustang_Dark_Horse.jpg",
        "attribution": "\"24 Ford Mustang Dark Horse\" by HJUdall, CC0, via Wikimedia Commons, background removed, dealer windshield stickers retouched out"
    }
];

export const carBySlug = (slug: string): Car | undefined => cars.find((car) => car.slug === slug);

/** A file's address on this site (the manifest may give a bare name or a /cars/… path) */
export function carFile(file: string): string {
    if (/^https?:/.test(file)) {
        return file;
    }

    return `${site.base}/cars/${file.replace(/^\/?(cars\/)?/, '')}`;
}

export const carSrcSet = (car: Car): string => `${carFile(car.files[600])} 600w, ${carFile(car.files[1000])} 1000w, ${carFile(car.files[1800])} 1800w`;

const ANGLES: Record<CarAngle, string> = {
    side: 'side view',
    'front-34': 'front three-quarter view',
    'rear-34': 'rear three-quarter view',
};

/** Shorter names where the manifest's model carries a trim word we don't use on the site */
const NAMES: Record<string, string> = {
    'lexus-lx600-34-silver': 'LX 600',
};

/** "White Porsche 911 Carrera S (992), front three-quarter view" */
export function carAlt(car: Car, view?: string): string {
    const colour = car.colour ? `${car.colour.charAt(0).toUpperCase()}${car.colour.slice(1)} ` : '';

    return `${colour}${car.make} ${NAMES[car.slug] ?? car.model}, ${view ?? ANGLES[car.angle]}`;
}

/** Every car pictured, with its credit (the CC0 ones need none, but they're listed too) */
export const creditedCars = cars;

const car = (slug: string) => carBySlug(slug);

/** The cutouts have 24 px of clear space round them (at 1800 wide): where the tyres meet the floor, in % of the height up from the bottom edge */
export const contactOf = (picked: Car | undefined): number => (picked ? (24 / picked.height) * 100 * (1800 / picked.width) : 0);

/**
 * Who plays which part: the hero, the drive-in (the one with its wheels
 * on their own layers) and the cars parked in the inner pages' headers.
 */
export const cast = {
    hero: car('porsche-911-34-white'),
    drive: car('bmw-7-series-side-black'),
    services: car('ferrari-roma-side-red'),
    branches: car('range-rover-side-white'),
    branch: car('land-cruiser-300-side-black'),
    home: car('lexus-lx600-34-silver'),
    lost: car('mustang-dark-horse-34-blue'),
};

/** The hero: the car, the way it points (into the page, toward the words), where its tyres meet the floor and its shadow */
export const heroCar = { car: cast.hero, face: 'left' as Facing, contact: contactOf(cast.hero), shadow: { inset: '4%', height: '16%' } };

/** The drive-in's callouts: which part of the car each kind of work is for (fractions of the BMW, facing right) */
export const driveSpots = [
    { slug: 'detailing', x: 0.215, y: 0.62 },
    { slug: 'window-tint', x: 0.4, y: 0.2 },
    { slug: 'coating', x: 0.6, y: 0.5 },
    { slug: 'paint-protection-film', x: 0.915, y: 0.4 },
];

export type Shot = { car?: Car; focus: { x: number; y: number; zoom: number; floor?: number }; face?: Facing; alt?: string; gloss?: boolean };

const shot = (slug: string, focus: Shot['focus'], view: string, extra: Partial<Shot> = {}): Shot => {
    const picked = car(slug);

    return { car: picked, focus, alt: picked ? carAlt(picked, view) : undefined, ...extra };
};

/**
 * The close-up for each kind of work: the front end (bumper, hood,
 * headlights) for film, a panel's gloss for coating, the glass for tint,
 * the whole car for detailing.
 */
const SHOTS: Record<string, Shot> = {
    'paint-protection-film': shot('mustang-dark-horse-34-blue', { x: 0.32, y: 0.55, zoom: 1.45 }, 'close-up of the front bumper, hood and headlights'),
    coating: shot('ferrari-roma-side-red', { x: 0.66, y: 0.5, zoom: 2.3, floor: 0.93 }, 'close-up of the paint over the rear wheel', { gloss: true }),
    'window-tint': shot('land-cruiser-300-side-black', { x: 0.58, y: 0.3, zoom: 2.4 }, 'close-up of the side windows'),
    detailing: shot('lexus-lx600-34-silver', { x: 0.5, y: 0.5, zoom: 0.8, floor: 0.92 }, 'front three-quarter view'),
};

/** Services that show a different part than the rest of their kind (the whole car for full-body film, the cabin for interior work) */
const SERVICE_SHOTS: Record<string, Shot> = {
    'full-body-ppf': shot('porsche-911-34-white', { x: 0.5, y: 0.5, zoom: 0.9, floor: 0.86 }, 'front three-quarter view'),
    'windshield-ppf': shot('porsche-911-34-white', { x: 0.47, y: 0.24, zoom: 2.1 }, 'close-up of the windshield'),
    'exterior-detailing': shot('g-class-g63-34-black', { x: 0.5, y: 0.5, zoom: 0.62, floor: 0.92 }, 'front three-quarter view'),
    'interior-detailing': shot('land-cruiser-300-side-black', { x: 0.62, y: 0.3, zoom: 2.8 }, 'close-up of the seats through the side windows'),
    'interior-leather-coating': shot('land-cruiser-300-side-black', { x: 0.66, y: 0.31, zoom: 3.2 }, 'close-up of the seats through the side windows'),
};

export const shotFor = (category: string, service?: string): Shot => (service && SERVICE_SHOTS[service]) || SHOTS[category] || SHOTS.detailing;

/** The cars parked in the inner pages' headers, each pointing into the page */
export const pageCars = {
    services: { car: cast.services, face: 'left' as Facing, contact: contactOf(cast.services), shadow: { inset: '3%', height: '14%' } },
    branches: { car: cast.branches, face: 'left' as Facing, contact: contactOf(cast.branches), shadow: { inset: '3%', height: '13%' } },
    branch: { car: cast.branch, face: 'left' as Facing, contact: contactOf(cast.branch), shadow: { inset: '3%', height: '13%' } },
    home: { car: cast.home, face: 'left' as Facing, contact: contactOf(cast.home), shadow: { inset: '6%', height: '15%' } },
    lost: { car: cast.lost, face: 'left' as Facing, contact: contactOf(cast.lost), shadow: { inset: '6%', height: '15%' } },
};
