import { cn } from '@/lib/utils';

/** Compact grayscale layout sketch for the block library (Webflow-style). */
export function BlockWireframe({
    slug,
    category,
    className,
}: {
    slug: string;
    category: string;
    className?: string;
}) {
    const kind = wireframeKind(slug, category);

    return (
        <div
            aria-hidden="true"
            className={cn(
                'flex size-full flex-col justify-center gap-1.5 rounded-sm bg-[#f3f4f6] p-2 text-[#9ca3af]',
                className,
            )}
        >
            {kind === 'header' && <HeaderSketch />}
            {kind === 'hero' && <HeroSketch />}
            {kind === 'features' && <FeaturesSketch />}
            {kind === 'cta' && <CtaSketch />}
            {kind === 'contacts' && <ContactsSketch />}
            {kind === 'footer' && <FooterSketch />}
            {kind === 'vehicles' && <VehiclesSketch />}
            {kind === 'forms' && <FormsSketch />}
            {kind === 'gallery' && <GallerySketch />}
            {kind === 'other' && <OtherSketch />}
        </div>
    );
}

function wireframeKind(slug: string, category: string): string {
    if (slug === 'header' || category === 'menu') {
        return 'header';
    }

    if (slug === 'hero' || category === 'hero') {
        return 'hero';
    }

    if (slug === 'benefits' || category === 'features') {
        return 'features';
    }

    if (slug === 'cta' || category === 'cta') {
        return 'cta';
    }

    if (slug === 'contacts' || category === 'contacts') {
        return 'contacts';
    }

    if (slug === 'footer' || category === 'footer') {
        return 'footer';
    }

    if (category === 'vehicles' || slug.startsWith('vehicle-')) {
        return 'vehicles';
    }

    if (category === 'forms' || slug === 'quiz' || slug === 'chat-selection') {
        return 'forms';
    }

    if (category === 'gallery') {
        return 'gallery';
    }

    return 'other';
}

function Bar({ className }: { className?: string }) {
    return <div className={cn('rounded-sm bg-current/35', className)} />;
}

function HeaderSketch() {
    return (
        <div className="flex items-center gap-2">
            <Bar className="h-2.5 w-8" />
            <div className="ml-auto flex gap-1">
                <Bar className="h-1.5 w-4" />
                <Bar className="h-1.5 w-4" />
                <Bar className="h-1.5 w-4" />
            </div>
            <Bar className="h-3 w-8 rounded" />
        </div>
    );
}

function HeroSketch() {
    return (
        <div className="grid grid-cols-2 gap-2">
            <div className="flex flex-col justify-center gap-1">
                <Bar className="h-2 w-full" />
                <Bar className="h-2 w-4/5" />
                <Bar className="mt-1 h-1.5 w-full opacity-50" />
                <Bar className="h-1.5 w-3/4 opacity-50" />
                <Bar className="mt-1 h-3 w-10 rounded" />
            </div>
            <div className="flex aspect-square items-center justify-center rounded-sm bg-current/20">
                <div className="size-4 rounded-full border-2 border-current/40" />
            </div>
        </div>
    );
}

function FeaturesSketch() {
    return (
        <div className="space-y-1.5">
            <Bar className="mx-auto h-2 w-1/2" />
            <div className="grid grid-cols-3 gap-1.5">
                {[0, 1, 2].map((item) => (
                    <div key={item} className="space-y-1 rounded-sm bg-white/60 p-1">
                        <Bar className="mx-auto h-2 w-2 rounded-full" />
                        <Bar className="h-1 w-full" />
                        <Bar className="h-1 w-2/3 opacity-50" />
                    </div>
                ))}
            </div>
        </div>
    );
}

function CtaSketch() {
    return (
        <div className="flex flex-col items-center gap-1.5 rounded-sm bg-current/15 py-2">
            <Bar className="h-2 w-2/3" />
            <Bar className="h-1.5 w-1/2 opacity-50" />
            <Bar className="mt-0.5 h-3 w-12 rounded" />
        </div>
    );
}

function ContactsSketch() {
    return (
        <div className="grid grid-cols-2 gap-2">
            <div className="space-y-1">
                <Bar className="h-2 w-1/2" />
                <Bar className="h-1.5 w-full opacity-50" />
                <Bar className="h-1.5 w-3/4 opacity-50" />
                <Bar className="h-1.5 w-2/3 opacity-50" />
            </div>
            <div className="rounded-sm bg-current/20" />
        </div>
    );
}

function FooterSketch() {
    return (
        <div className="space-y-1.5">
            <div className="flex gap-2">
                <Bar className="h-2 w-10" />
                <div className="ml-auto flex gap-1">
                    <Bar className="h-1.5 w-4" />
                    <Bar className="h-1.5 w-4" />
                </div>
            </div>
            <Bar className="h-1 w-full opacity-40" />
        </div>
    );
}

function VehiclesSketch() {
    return (
        <div className="grid grid-cols-3 gap-1">
            {[0, 1, 2].map((item) => (
                <div key={item} className="space-y-1">
                    <div className="aspect-[4/3] rounded-sm bg-current/20" />
                    <Bar className="h-1 w-full" />
                    <Bar className="h-1 w-1/2 opacity-50" />
                </div>
            ))}
        </div>
    );
}

function FormsSketch() {
    return (
        <div className="mx-auto w-4/5 space-y-1 rounded-sm bg-white/70 p-1.5">
            <Bar className="h-2 w-2/3" />
            <Bar className="h-3 w-full rounded-sm bg-current/15" />
            <Bar className="h-3 w-full rounded-sm bg-current/15" />
            <Bar className="h-3 w-1/2 rounded" />
        </div>
    );
}

function GallerySketch() {
    return (
        <div className="grid grid-cols-4 gap-1">
            {[0, 1, 2, 3].map((item) => (
                <div key={item} className="aspect-square rounded-sm bg-current/20" />
            ))}
        </div>
    );
}

function OtherSketch() {
    return (
        <div className="space-y-1">
            <Bar className="h-2 w-1/2" />
            <Bar className="h-8 w-full rounded-sm bg-current/15" />
        </div>
    );
}
