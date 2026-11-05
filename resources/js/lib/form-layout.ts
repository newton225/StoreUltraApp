/**
 * The grid of a form: six columns from the tablet up, one column on a phone. A field takes a third of the
 * row (`third`), a half (`half`), two thirds (`twoThirds`) or the whole row (`full`), and the fields of a
 * form are laid out so that every row is filled: no field is left alone with a hole beside it. A short
 * field (name, phone, amount, date, a choice) takes a third or a half; a long one (address, note, a list
 * of boxes, a title) takes two thirds or the whole row.
 */
export const formGrid =
    'grid grid-cols-1 items-start gap-x-6 gap-y-5 md:grid-cols-6';

export const span = {
    third: 'md:col-span-2',
    half: 'md:col-span-3',
    twoThirds: 'md:col-span-4',
    full: 'col-span-full',
} as const;
