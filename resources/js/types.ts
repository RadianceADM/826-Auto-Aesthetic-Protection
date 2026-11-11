/** A picture from the image pipeline: a medium file, its sizes and a thumbnail */
export type Image = {
    src: string;
    srcset: string | null;
    thumb: string;
};

export type Settings = {
    tagline: string | null;
    intro: string | null;
    hero: (Image & { full: string }) | null;
    phone: string | null;
    /** "+639488042284", for tel: and sms: links */
    phoneHref: string | null;
    email: string | null;
    facebook: string | null;
    instagram: string | null;
    messenger: string | null;
};

/** The customer signed in with Google */
export type Guest = {
    name: string;
    email: string;
    avatar: string | null;
};

export type AuthData = {
    googleEnabled: boolean;
    guest: Guest | null;
    /** /auth/google, takes ?return=<site-relative path> */
    signInUrl: string;
    /** POST with the CSRF header, 204 */
    signOutUrl: string;
    /** Local development only, while Google isn't set up: signs in a test customer */
    localSignInUrl: string | null;
};

export type VehicleSize = {
    id: number;
    name: string;
    /** "Vios, City, Mirage G4" */
    examples: string | null;
};

/** What a service costs and takes for one vehicle size */
export type Rate = {
    sizeId: number;
    price: number;
    days: number;
    minutes: number;
    /** "4 hours", "2 days" */
    duration: string;
};

export type Service = {
    id: number;
    name: string;
    slug: string;
    summary: string | null;
    description: string | null;
    inclusions: string[];
    image: Image | null;
    homeServiceOk: boolean;
    /** Only the bookable ones, for sizes on the site */
    rates: Rate[];
};

export type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    services: Service[];
};

export type BranchHours = {
    /** ISO weekday: 1 = Monday … 7 = Sunday */
    day: number;
    label: string;
    /** "08:00", null when closed */
    open: string | null;
    close: string | null;
    lastDropoff: string | null;
};

export type BranchMap = {
    google: string | null;
    waze: string | null;
    embed: string | null;
};

export type Branch = {
    id: number;
    slug: string;
    name: string;
    status: 'open' | 'coming_soon';
    city: string | null;
    address: string | null;
    phone: string | null;
    phoneHref: string | null;
    messenger: string | null;
    facebook: string | null;
    map: BranchMap | null;
    hours: BranchHours[];
    /** A job that runs over a day is dropped off by this time ("12:00") */
    multidayDropoffBy: string | null;
    /** The last day it takes bookings and home-service requests for ("2027-02-28") */
    lastDay: string;
    /** Takes online reservations now */
    bookable: boolean;
    /** The services that can be booked here */
    serviceIds: number[];
    homeService: { offered: boolean; areas: string[] };
};

export type HomeWindow = 'morning' | 'afternoon' | 'any';

export type BookingSettings = {
    cancelCutoffHours: number;
    rescheduleEnabled: boolean;
    rescheduleCutoffHours: number;
    maxReschedules: number;
    policy: string | null;
    fee: { enabled: boolean; label: string | null; holdMinutes: number };
    windows: Record<HomeWindow, string>;
};

export type PhotoCategory = string;

export type Photo = Image & {
    id: number;
    caption: string | null;
    category: PhotoCategory;
    width: number | null;
    height: number | null;
    isFeatured: boolean;
    full: string;
};

export type SiteData = {
    name: string;
    shortName: string;
    /** The "826" mark alone: reads on dark and light */
    logo: string | null;
    /** The whole logo, with black text: light backgrounds only */
    logoFull: string | null;
    /** The folder the site is served from ("" at a domain's root, "/demo/826") */
    base: string;
    /** A preview copy: sample prices, nothing is saved */
    demo: boolean;
    settings: Settings;
    auth: AuthData;
    catalog: { sizes: VehicleSize[]; categories: Category[] };
    branches: Branch[];
    booking: BookingSettings;
    photos: Photo[];
    photoCategories: Record<string, string>;
};

/** How long a job keeps the car */
export type JobLength = {
    days: number;
    minutes: number;
    /** "2 days + 4 hours" */
    label: string;
};

export type DayState = 'open' | 'full' | 'closed';

/** GET /api/availability */
export type MonthAvailability = {
    /** "2026-11" */
    month: string;
    /** The first and last days that can be booked */
    first: string;
    last: string;
    days: Record<string, DayState>;
    length: JobLength;
    total: number | null;
};

export type TimeSlot = {
    /** "09:00" */
    time: string;
    /** "9:00 AM" */
    label: string;
    startsAt: string;
    readyBy: string;
    /** "Ready 1:00 PM" */
    readyLabel: string;
};

/** GET /api/availability/day */
export type DayAvailability = {
    date: string;
    length: JobLength;
    total: number | null;
    slots: TimeSlot[];
};

export type Stage = 'requested' | 'awaiting_payment' | 'payment_review' | 'confirmed' | 'checked_in' | 'ready' | 'completed' | 'expired' | 'cancelled' | 'declined' | 'no_show';

export type PaymentMethodKey = 'gcash' | 'maya';

export type PaymentMethod = {
    key: PaymentMethodKey;
    label: string;
    /** The QR the branch uploaded */
    qr: string | null;
    accountName: string | null;
    accountNumber: string | null;
};

/** A customer's booking (`toCustomer`) */
export type CustomerBooking = {
    id: number;
    /** "826-AN8W2" */
    reference: string;
    /** Site-relative, without site.base, with the private key: "/bookings/4?key=…" */
    url: string;
    kind: 'onsite' | 'home';
    status: string;
    stage: Stage;
    branch: {
        id: number;
        name: string;
        slug: string;
        address: string | null;
        phone: string | null;
        phoneHref: string | null;
        messenger: string | null;
        map: BranchMap | null;
    } | null;
    startsAt: string | null;
    readyBy: string | null;
    /** "Sat, Nov 14, 2026, 1:00 PM ready 5:00 PM" */
    when: string;
    preferredDate: string | null;
    preferredWindow: HomeWindow | null;
    vehicle: { size: string | null; sizeId: number | null; model: string | null; plate: string | null };
    items: { name: string; category: string | null; price: number | null; duration: string | null }[];
    serviceIds: number[];
    length: JobLength;
    totalPrice: number | null;
    address: string | null;
    area: string | null;
    /** The reservation fee */
    amountDue: number | null;
    /** What's left to pay at the branch, once the fee is in */
    balance: number | null;
    /** Only while awaiting_payment */
    holdExpiresAt: string | null;
    payment: {
        method: PaymentMethodKey | null;
        submittedAt: string | null;
        /** Set after the branch turned a screenshot down */
        rejectedReason: string | null;
        proofUrl: string | null;
        paidAt: string | null;
    };
    methods: PaymentMethod[];
    instructions: string | null;
    canCancel: boolean;
    canReschedule: boolean;
    createdAt: string | null;
};

declare global {
    interface Window {
        APP826: SiteData;
    }
}
