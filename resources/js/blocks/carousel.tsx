import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import type { CSSProperties, KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { flag, group } from './state';
import type { BlockState } from './state';

/**
 * Vendor-neutral Carousel settings stored in Block state (D-032): the `carousel` schema group
 * produced by OfficialBlockCatalog::carousel().
 */
export type CarouselSettings = {
    enabled: boolean;
    perView: number;
    gap: number;
    arrows: boolean;
    dots: boolean;
    loop: boolean;
    autoplay: boolean;
    delayMs: number;
};

const perViewOptions: Record<string, number> = {
    one: 1,
    two: 2,
    three: 3,
    four: 4,
};
const gapOptions: Record<string, number> = {
    small: 8,
    medium: 24,
    large: 40,
};
const delayOptions: Record<string, number> = { s3: 3000, s5: 5000, s8: 8000 };

function option(
    options: Record<string, number>,
    value: unknown,
    fallback: string,
): number {
    return typeof value === 'string' && value in options
        ? options[value]
        : options[fallback];
}

export function carouselSettings(state: BlockState): CarouselSettings {
    const settings = group(state, 'carousel');

    return {
        enabled: flag(settings, 'enabled', false),
        perView: option(perViewOptions, settings.per_view, 'three'),
        gap: option(gapOptions, settings.gap, 'medium'),
        arrows: flag(settings, 'arrows', true),
        dots: flag(settings, 'dots', true),
        loop: flag(settings, 'loop', false),
        autoplay: flag(settings, 'autoplay', false),
        delayMs: option(delayOptions, settings.delay, 's5'),
    };
}

const reducedMotionQuery = '(prefers-reduced-motion: reduce)';

function subscribeReducedMotion(onChange: () => void) {
    const media = window.matchMedia(reducedMotionQuery);
    media.addEventListener('change', onChange);

    return () => media.removeEventListener('change', onChange);
}

export function usePrefersReducedMotion(): boolean {
    return useSyncExternalStore(
        subscribeReducedMotion,
        () => window.matchMedia(reducedMotionQuery).matches,
        () => false,
    );
}

const controlClass =
    'inline-flex size-10 items-center justify-center rounded-full border border-neutral-300 bg-white text-neutral-800 shadow-sm transition hover:bg-neutral-50 focus-visible:ring-2 focus-visible:ring-(--lf-primary) focus-visible:outline-none disabled:opacity-40';

/**
 * Accessible scroll-snap Carousel: arrow buttons, dots, Left/Right keys on the region,
 * pausable autoplay that stops on hover/focus and is disabled for reduced motion.
 * Slides stay in the normal tab order, so there is no keyboard trap.
 */
export function Carousel({
    label,
    settings,
    slides,
}: {
    label: string;
    settings: CarouselSettings;
    slides: { key: string; content: ReactNode }[];
}) {
    const id = useId();
    const track = useRef<HTMLUListElement>(null);
    const reducedMotion = usePrefersReducedMotion();
    const [index, setIndex] = useState(0);
    const [maxIndex, setMaxIndex] = useState(Math.max(0, slides.length - 1));
    const [paused, setPaused] = useState(false);
    const [interacting, setInteracting] = useState(false);

    const stride = useCallback(() => {
        const element = track.current;
        const first = element?.firstElementChild;

        return element && first instanceof HTMLElement
            ? first.offsetWidth + settings.gap
            : 0;
    }, [settings.gap]);

    const measure = useCallback(() => {
        const element = track.current;
        const step = stride();

        if (!element || step === 0) {
            return;
        }

        const visible = Math.max(
            1,
            Math.round((element.clientWidth + settings.gap) / step),
        );
        setMaxIndex(Math.max(0, slides.length - visible));
        setIndex(Math.round(element.scrollLeft / step));
    }, [settings.gap, slides.length, stride]);

    useEffect(() => {
        const element = track.current;

        if (!element) {
            return;
        }

        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(element);

        return () => observer.disconnect();
    }, [measure]);

    const goTo = useCallback(
        (target: number) => {
            const element = track.current;

            if (!element) {
                return;
            }

            const next = Math.min(Math.max(target, 0), maxIndex);
            element.scrollTo({
                left: next * stride(),
                behavior: reducedMotion ? 'auto' : 'smooth',
            });
            setIndex(next);
        },
        [maxIndex, reducedMotion, stride],
    );

    const next = useCallback(
        () => goTo(index >= maxIndex ? (settings.loop ? 0 : index) : index + 1),
        [goTo, index, maxIndex, settings.loop],
    );
    const previous = () =>
        goTo(index <= 0 ? (settings.loop ? maxIndex : 0) : index - 1);

    const autoplayActive = settings.autoplay && !reducedMotion && maxIndex > 0;
    const running = autoplayActive && !paused && !interacting;

    useEffect(() => {
        if (!running) {
            return;
        }

        const timer = window.setInterval(() => {
            if (index >= maxIndex && !settings.loop) {
                setPaused(true);

                return;
            }

            goTo(index >= maxIndex ? 0 : index + 1);
        }, settings.delayMs);

        return () => window.clearInterval(timer);
    }, [running, index, maxIndex, settings.loop, settings.delayMs, goTo]);

    function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        if (event.target !== event.currentTarget) {
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            next();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            previous();
        }
    }

    const style = {
        '--lf-carousel-gap': `${settings.gap}px`,
        '--lf-carousel-per-view': settings.perView,
    } as CSSProperties;
    const canScroll = maxIndex > 0;

    return (
        <div
            role="region"
            aria-roledescription="карусель"
            aria-label={label}
            tabIndex={0}
            onKeyDown={onKeyDown}
            onMouseEnter={() => setInteracting(true)}
            onMouseLeave={() => setInteracting(false)}
            onFocus={() => setInteracting(true)}
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    setInteracting(false);
                }
            }}
            className="flex flex-col gap-4 rounded-(--lf-radius) outline-none focus-visible:ring-2 focus-visible:ring-(--lf-primary) focus-visible:ring-offset-4"
            style={style}
        >
            <ul
                ref={track}
                id={`${id}-track`}
                onScroll={measure}
                className={cn(
                    'flex snap-x snap-mandatory [scrollbar-width:none] gap-(--lf-carousel-gap) overflow-x-auto overscroll-x-contain [&::-webkit-scrollbar]:hidden',
                    '[--lf-carousel-visible:1] sm:[--lf-carousel-visible:min(2,var(--lf-carousel-per-view))] lg:[--lf-carousel-visible:var(--lf-carousel-per-view)]',
                )}
            >
                {slides.map((slide, position) => (
                    <li
                        key={slide.key}
                        role="group"
                        aria-roledescription="слайд"
                        aria-label={`${position + 1} из ${slides.length}`}
                        className="shrink-0 grow-0 basis-[calc((100%_-_(var(--lf-carousel-visible)_-_1)_*_var(--lf-carousel-gap))_/_var(--lf-carousel-visible))] snap-start"
                    >
                        {slide.content}
                    </li>
                ))}
            </ul>
            {canScroll &&
                (settings.arrows || settings.dots || autoplayActive) && (
                    <div className="flex flex-wrap items-center justify-center gap-3">
                        {settings.arrows && (
                            <button
                                type="button"
                                aria-controls={`${id}-track`}
                                aria-label="Предыдущий слайд"
                                disabled={!settings.loop && index <= 0}
                                onClick={previous}
                                className={controlClass}
                            >
                                <ChevronLeft
                                    aria-hidden="true"
                                    className="size-5"
                                />
                            </button>
                        )}
                        {settings.dots && (
                            <div className="flex items-center gap-1">
                                {Array.from(
                                    { length: maxIndex + 1 },
                                    (_, position) => (
                                        <button
                                            key={position}
                                            type="button"
                                            aria-controls={`${id}-track`}
                                            aria-label={`Перейти к слайду ${position + 1}`}
                                            aria-current={
                                                position === index
                                                    ? 'true'
                                                    : undefined
                                            }
                                            onClick={() => goTo(position)}
                                            className="flex size-6 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-(--lf-primary) focus-visible:outline-none"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'size-2.5 rounded-full transition',
                                                    position === index
                                                        ? 'bg-(--lf-primary)'
                                                        : 'bg-neutral-300',
                                                )}
                                            />
                                        </button>
                                    ),
                                )}
                            </div>
                        )}
                        {settings.arrows && (
                            <button
                                type="button"
                                aria-controls={`${id}-track`}
                                aria-label="Следующий слайд"
                                disabled={!settings.loop && index >= maxIndex}
                                onClick={next}
                                className={controlClass}
                            >
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-5"
                                />
                            </button>
                        )}
                        {autoplayActive && (
                            <button
                                type="button"
                                aria-label={
                                    paused
                                        ? 'Запустить автопрокрутку'
                                        : 'Остановить автопрокрутку'
                                }
                                onClick={() => setPaused((value) => !value)}
                                className={controlClass}
                            >
                                {paused ? (
                                    <Play
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                ) : (
                                    <Pause
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                )}
                            </button>
                        )}
                    </div>
                )}
        </div>
    );
}
