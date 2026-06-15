<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PaginationPayload
{
    /**
     * Build a consistent JSON payload from Laravel paginate().
     *
     * @param  array<int, string>  $filterKeys
     */
    public static function fromPaginator(
        LengthAwarePaginator $paginator,
        Request $request,
        array $filterKeys = []
    ): array {
        $query = $request->query();
        $appends = $query;
        unset($appends['page']);
        $paginator->appends($appends);

        $filters = [];
        if ($filterKeys === []) {
            foreach ($query as $key => $value) {
                if (in_array($key, ['page', 'per_page', 'limit'], true)) {
                    continue;
                }

                if ($value !== null && $value !== '') {
                    $filters[$key] = $value;
                }
            }
        } else {
            foreach ($filterKeys as $key) {
                if ($request->query($key) !== null) {
                    $filters[$key] = $request->query($key);
                }
            }
        }

        return [
            'data' => array_values($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => max(1, $paginator->lastPage()),
                'has_next' => $paginator->hasMorePages(),
                'has_prev' => $paginator->currentPage() > 1,
                'filters' => $filters,
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url(max(1, $paginator->lastPage())),
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }
}
