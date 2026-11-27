import type { IllustrationKind } from '@/popups/launch';

export const ILLUSTRATIONS: { value: IllustrationKind; label: string }[] = [
    { value: 'manager', label: 'Менеджер' },
    { value: 'manager_woman', label: 'Менеджер (девушка)' },
    { value: 'car', label: 'Автомобиль' },
];

export function Illustration({
    kind,
    className,
}: {
    kind: IllustrationKind;
    className?: string;
}) {
    if (kind === 'car') {
        return (
            <svg
                viewBox="0 0 160 90"
                preserveAspectRatio="xMidYMax meet"
                className={className}
                aria-hidden="true"
            >
                <path
                    d="M8 62 L18 42 Q22 34 32 34 L58 34 L72 18 Q76 14 82 14 L116 14 Q124 14 130 22 L142 38 Q152 40 154 52 L154 66 L8 66 Z"
                    fill="#28ADCB"
                />
                <path
                    d="M66 36 L78 22 L112 22 L126 36 Z"
                    fill="#E2F3F7"
                    opacity=".9"
                />
                <rect x="94" y="22" width="3" height="14" fill="#28ADCB" />
                <circle cx="42" cy="68" r="14" fill="#3F3932" />
                <circle cx="42" cy="68" r="6" fill="#CABCAA" />
                <circle cx="122" cy="68" r="14" fill="#3F3932" />
                <circle cx="122" cy="68" r="6" fill="#CABCAA" />
                <rect x="10" y="54" width="10" height="5" fill="#FFE1A0" />
            </svg>
        );
    }

    const woman = kind === 'manager_woman';

    return (
        <svg
            viewBox="0 0 107 230"
            preserveAspectRatio="xMidYMax meet"
            className={className}
            aria-hidden="true"
        >
            <rect x="30" y="150" width="24" height="80" fill="#E3DDD2" />
            <rect x="56" y="150" width="22" height="80" fill="#CABCAA" />
            <path
                d="M6 230 L8 104 Q10 80 36 74 L72 74 Q98 80 100 104 L102 160 L74 160 L74 230 Z"
                fill={woman ? '#E8A33D' : '#28ADCB'}
            />
            <path d="M42 74 L54 112 L66 74 Z" fill="#FFFFFF" />
            <path
                d="M70 100 Q92 96 98 70 L90 62 Q86 80 68 86 Z"
                fill={woman ? '#C98524' : '#1E8DA8'}
            />
            <rect
                x="84"
                y="46"
                width="9"
                height="18"
                rx="2"
                fill="#3F3932"
                transform="rotate(14 88 55)"
            />
            <circle cx="54" cy="48" r="22" fill="#F4A57E" />
            {woman ? (
                <path
                    d="M30 52 Q26 18 56 18 Q84 18 80 52 L84 96 Q70 90 66 70 Q60 40 38 44 Q36 70 28 96 Z"
                    fill="#6B3B22"
                />
            ) : (
                <path
                    d="M32 44 Q34 20 58 22 Q78 24 76 44 Q66 32 50 36 Q40 36 32 44 Z"
                    fill="#3A2A22"
                />
            )}
        </svg>
    );
}
