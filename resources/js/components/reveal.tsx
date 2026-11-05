import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type RevealProps = {
    as?: 'div' | 'li';
    delay?: number;
    className?: string;
    children: ReactNode;
};

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

/**
 * Fades a block in once, the first time it scrolls into view. It is only an
 * enhancement: a block already on screen, a browser without
 * IntersectionObserver or a reader who asked for less motion sees the content
 * straight away.
 */
export default function Reveal({
    as: Tag = 'div',
    delay = 0,
    className,
    children,
}: RevealProps) {
    return (
        <Tag
            className={cn(className)}
            ref={(node: HTMLElement | null) => {
                if (
                    !node ||
                    node.classList.contains('is-visible') ||
                    !('IntersectionObserver' in window) ||
                    window.matchMedia(REDUCED_MOTION).matches ||
                    node.getBoundingClientRect().top < window.innerHeight
                ) {
                    return;
                }

                node.style.transitionDelay = `${delay}ms`;
                node.classList.add('reveal-ready');

                const observer = new IntersectionObserver(
                    ([entry]) => {
                        if (entry.isIntersecting) {
                            node.classList.add('is-visible');
                            observer.disconnect();
                        }
                    },
                    { threshold: 0.15 },
                );

                observer.observe(node);

                return () => observer.disconnect();
            }}
        >
            {children}
        </Tag>
    );
}
