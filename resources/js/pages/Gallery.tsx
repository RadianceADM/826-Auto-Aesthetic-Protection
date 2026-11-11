import { useState } from 'react';

import Button from '../components/Button';
import { PageHeader } from '../components/Heading';
import { Facebook } from '../components/Icons';
import Lightbox from '../components/Lightbox';
import Picture from '../components/Picture';
import { copy } from '../lib/copy';
import { photoAlt, photoCategories, site } from '../lib/site';
import usePageTitle from '../lib/usePageTitle';

const text = copy.gallery;

export default function Gallery() {
    usePageTitle(text.title);
    const [category, setCategory] = useState<string | null>(null);
    const [open, setOpen] = useState<number | null>(null);
    const photos = category ? site.photos.filter((photo) => photo.category === category) : site.photos;

    return (
        <>
            <PageHeader eyebrow={copy.nav.gallery} title={text.title}>
                <p>{text.intro}</p>
            </PageHeader>
            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-8 sm:py-14 xl:px-12">
                {site.photos.length === 0 ? (
                    <div className="max-w-xl panel p-6">
                        <p className="leading-relaxed">{text.empty}</p>
                        {site.settings.facebook && (
                            <Button href={site.settings.facebook} variant="outline" className="mt-5 min-h-11! px-5!">
                                <Facebook className="size-4" /> {copy.branch.facebook}
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        {photoCategories.length > 1 && (
                            <div role="group" aria-label={text.title} className="mb-8 flex flex-wrap gap-2">
                                {[null, ...photoCategories].map((key) => (
                                    <button
                                        key={key ?? 'all'}
                                        type="button"
                                        aria-pressed={category === key}
                                        onClick={() => setCategory(key)}
                                        className={`press inline-flex min-h-11 items-center rounded-full border px-4 font-medium ${category === key ? 'border-accent bg-accent text-on-accent' : 'border-field/60 hover:border-text/60'}`}
                                    >
                                        {key ? site.photoCategories[key] : text.all}
                                    </button>
                                ))}
                            </div>
                        )}
                        <ul className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                            {photos.map((photo, index) => (
                                <li key={photo.id}>
                                    <button type="button" onClick={() => setOpen(index)} aria-label={text.open(photoAlt(photo))} aria-haspopup="dialog" className="press block w-full overflow-hidden rounded-card bg-surface-3">
                                        <Picture image={photo} alt="" sizes="(min-width: 1024px) 25vw, (min-width: 768px) 33vw, 50vw" className="aspect-square w-full object-cover" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
            {open !== null && <Lightbox photos={photos} index={open} onClose={() => setOpen(null)} onChange={setOpen} />}
        </>
    );
}
