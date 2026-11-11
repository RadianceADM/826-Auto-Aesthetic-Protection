import { useEffect } from 'react';

import { site } from './site';

/**
 * Keeps the tab title in step with client-side navigation (the server sets
 * it for the first page load and link previews).
 */
export default function usePageTitle(title?: string) {
    useEffect(() => {
        document.title = title ? `${title} | ${site.name}` : `${site.name} | PPF, Graphene Coating, Tint and Detailing`;
    }, [title]);
}
