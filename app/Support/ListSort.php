<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The sort a member asked for on a list, read from the address (`?sort=name&direction=desc`) and checked against
 * the closed list of columns the list accepts (playbook UI-46, SEC-45): anything else falls back to the default
 * order. The sorting itself is done by the model (`sortedBy` scopes), so a page and its export share it.
 */
final class ListSort
{
    /**
     * @param  list<string>  $allowed
     * @return array{sort: string|null, direction: 'asc'|'desc'}
     */
    public static function resolve(Request $request, array $allowed): array
    {
        $sort = $request->string('sort')->toString();

        if (! in_array($sort, $allowed, true)) {
            return ['sort' => null, 'direction' => 'asc'];
        }

        return [
            'sort' => $sort,
            'direction' => $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
        ];
    }
}
