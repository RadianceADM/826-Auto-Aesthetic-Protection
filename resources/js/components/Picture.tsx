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
            className={className}
        />
    );
}
