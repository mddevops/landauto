import { BlockWireframe } from '@/components/designer/block-wireframe';
import { cn } from '@/lib/utils';

/** Uploaded version preview of a Block, or its schematic wireframe when there is none. */
export function BlockThumbnail({
    previewUrl,
    slug,
    category,
    className,
}: {
    previewUrl: string | null;
    slug: string;
    category: string;
    className?: string;
}) {
    if (previewUrl) {
        return (
            <img
                src={previewUrl}
                alt=""
                loading="lazy"
                decoding="async"
                className={cn('size-full object-cover object-top', className)}
            />
        );
    }

    return (
        <BlockWireframe slug={slug} category={category} className={className} />
    );
}
