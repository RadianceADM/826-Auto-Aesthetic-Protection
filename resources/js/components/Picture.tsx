import { useState } from 'react';
import type { Image } from '../types';

type Props = {
    image: Image;
    alt: string;
    sizes?: string;
    className?: string;
    width?: number | null;
    height?: number | null;
    eager?: boolean;
};

export default function Picture({ image, alt, sizes = '100vw', className = '', width, height, eager = false }: Props) {
    const [ready, setReady] = useState(false);

    // A shimmer behind the photo until it's in, then it fades up over it
    return (
        <img
            src={image.src}
            srcSet={image.srcset ?? undefined}
            sizes={image.srcset ? sizes : undefined}
            alt={alt}
            width={width ?? undefined}
            height={height ?? undefined}
            loading={eager ? 'eager' : 'lazy'}
            fetchPriority={eager ? 'high' : undefined}
            decoding="async"
            ref={(element) => {
                if (element?.complete && element.naturalWidth && !ready) {
                    setReady(true);
                }
            }}
            onLoad={() => setReady(true)}
            data-loading={ready ? undefined : ''}
            className={`photo-fade ${className}`}
        />
    );
}
