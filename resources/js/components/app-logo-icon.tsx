import type { SVGAttributes } from 'react';

/**
 * The StoreUltra mark: a parcel (the kit) in three blues with an upward chevron. It keeps its own colours in
 * both themes, so callers only give it a size.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 64 64"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <polygon points="32,6 54.5,19 32,32 9.5,19" fill="#7db4f0" />
            <polygon points="9.5,19 32,32 32,58 9.5,45" fill="#2a78d6" />
            <polygon points="54.5,19 54.5,45 32,58 32,32" fill="#1b4f95" />
            <path
                d="M21 44 L32 37 L43 44"
                fill="none"
                stroke="#fff"
                strokeWidth="3.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
