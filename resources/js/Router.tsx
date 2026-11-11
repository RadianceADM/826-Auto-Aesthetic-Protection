import { Suspense, useEffect } from 'react';
import { Outlet, Route, Routes, useLocation } from 'react-router-dom';

import Footer from './components/Footer';
import Navbar from './components/Navbar';
import { copy } from './lib/copy';
import { lazyPage, preloadPages } from './lib/lazyPage';
import Home from './pages/Home';

// Every page but the home page comes in its own file, fetched once the first page is idle
const Book = lazyPage(() => import('./pages/Book'));
const BookingDetail = lazyPage(() => import('./pages/BookingDetail'));
const Bookings = lazyPage(() => import('./pages/Bookings'));
const BranchDetail = lazyPage(() => import('./pages/BranchDetail'));
const Branches = lazyPage(() => import('./pages/Branches'));
const Gallery = lazyPage(() => import('./pages/Gallery'));
const HomeService = lazyPage(() => import('./pages/HomeService'));
const NotFound = lazyPage(() => import('./pages/NotFound'));
const ServiceDetail = lazyPage(() => import('./pages/ServiceDetail'));
const Services = lazyPage(() => import('./pages/Services'));

preloadPages();

/**
 * New page: back to the top. A #hash: scroll to that section once it has
 * rendered.
 */
function ScrollManager() {
    const { pathname, hash } = useLocation();

    useEffect(() => {
        if (hash) {
            requestAnimationFrame(() => document.getElementById(decodeURIComponent(hash.slice(1)))?.scrollIntoView());
            return;
        }

        window.scrollTo(0, 0);
    }, [pathname, hash]);

    return null;
}

function Layout() {
    const { pathname } = useLocation();

    return (
        <div className="flex min-h-screen flex-col overflow-x-clip">
            <a
                href="#main"
                className="sr-only z-[70] rounded-control bg-accent px-4 py-2 font-semibold text-on-accent focus:not-sr-only focus:fixed focus:top-[max(0.75rem,env(safe-area-inset-top))] focus:left-3"
            >
                {copy.nav.skip}
            </a>
            <ScrollManager />
            <Navbar />
            {/* Navigation is a transition, so a page whose file is still on its way keeps the old
                page on screen. Only a visit that starts on such a page sees this fallback. */}
            <Suspense fallback={<main id="main" className="flex-1" />}>
                <main id="main" key={pathname} tabIndex={-1} className="flex-1 outline-none">
                    <Outlet />
                </main>
            </Suspense>
            <Footer />
        </div>
    );
}

export default function Router() {
    return (
        <Routes>
            <Route element={<Layout />}>
                <Route path="/" element={<Home />} />
                <Route path="/services" element={<Services />} />
                <Route path="/services/:slug" element={<ServiceDetail />} />
                <Route path="/branches" element={<Branches />} />
                <Route path="/branches/:slug" element={<BranchDetail />} />
                <Route path="/gallery" element={<Gallery />} />
                <Route path="/book" element={<Book />} />
                <Route path="/home-service" element={<HomeService />} />
                <Route path="/bookings" element={<Bookings />} />
                <Route path="/bookings/:id" element={<BookingDetail />} />
                <Route path="*" element={<NotFound />} />
            </Route>
        </Routes>
    );
}
