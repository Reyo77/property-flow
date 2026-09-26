<?php

namespace App\Support\Api;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Validation\ValidationException;

/**
 * The list conventions every collection endpoint shares:
 *
 * - `filter[name]=value` narrows the list; only the filters an endpoint allows are accepted.
 * - `sort=field` or `sort=-field` (descending); only allowed fields, one at a time.
 * - `page` and `per_page` (1–100, default 25) page through it.
 *
 * Unknown filters or sort fields are a 422, never silently ignored, so a client can't think
 * it filtered when it didn't.
 */
final class ApiQuery
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, Closure>  $filters  filter name => applies the value: fn (Builder $query, string $value)
     * @param  list<string>  $sorts  sortable columns
     * @param  (Closure(TModel): bool)|null  $visible  when given, rows it rejects are left out (the
     *                                                 same check as the record's own endpoint), and
     *                                                 paging counts only what is left
     * @return Paginator<int, TModel>
     *
     * @throws ValidationException
     */
    public static function paginate(Request $request, Builder $query, array $filters = [], array $sorts = [], string $defaultSort = '-id', ?Closure $visible = null): Paginator
    {
        self::applyFilters($request, $query, $filters);
        self::applySort($request, $query, $sorts, $defaultSort);
        $perPage = self::perPage($request);

        if ($visible === null) {
            return $query->paginate($perPage)->withQueryString();
        }

        $rows = $query->get()->filter($visible)->values();
        $page = max(1, $request->integer('page', 1));

        /** @var Paginator<int, TModel> $paginator */
        $paginator = new Paginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);

        return $paginator->withQueryString();
    }

    /**
     * A filter on a column holding a backed enum: an unknown value is a 422 listing the valid ones.
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return Closure(Builder<Model>, string): void
     */
    public static function enum(string $enum, string $column, string $filter): Closure
    {
        return function (Builder $query, string $value) use ($enum, $column, $filter): void {
            if ($enum::tryFrom($value) === null) {
                throw ValidationException::withMessages(["filter.{$filter}" => __('Unknown value ":value". Allowed: :allowed.', [
                    'value' => $value,
                    'allowed' => implode(', ', array_map(fn (\BackedEnum $case) => (string) $case->value, $enum::cases())),
                ])]);
            }

            $query->where($column, $value);
        };
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, Closure>  $filters
     *
     * @throws ValidationException
     */
    private static function applyFilters(Request $request, Builder $query, array $filters): void
    {
        $requested = $request->query('filter', []);

        if (! is_array($requested)) {
            throw ValidationException::withMessages(['filter' => __('Filters are given as filter[name]=value.')]);
        }

        foreach ($requested as $name => $value) {
            if (! isset($filters[$name])) {
                throw ValidationException::withMessages(["filter.{$name}" => __('Unknown filter ":name". Allowed: :allowed.', ['name' => $name, 'allowed' => implode(', ', array_keys($filters)) ?: __('none')])]);
            }

            if (! is_scalar($value) || (string) $value === '') {
                throw ValidationException::withMessages(["filter.{$name}" => __('Give filter ":name" a single value.', ['name' => $name])]);
            }

            $filters[$name]($query, (string) $value);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $sorts
     *
     * @throws ValidationException
     */
    private static function applySort(Request $request, Builder $query, array $sorts, string $defaultSort): void
    {
        $sort = $request->query('sort', $defaultSort);
        $sort = is_string($sort) && $sort !== '' ? $sort : $defaultSort;
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        if ($column !== 'id' && ! in_array($column, $sorts, true)) {
            throw ValidationException::withMessages(['sort' => __('Unknown sort ":sort". Allowed: :allowed.', ['sort' => $column, 'allowed' => implode(', ', ['id', ...$sorts])])]);
        }

        $query->orderBy($query->qualifyColumn($column), $direction);

        if ($column !== 'id') {
            $query->orderBy($query->qualifyColumn('id'), $direction);
        }
    }

    /**
     * @throws ValidationException
     */
    private static function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw ValidationException::withMessages(['per_page' => __('per_page must be between 1 and :max.', ['max' => self::MAX_PER_PAGE])]);
        }

        return $perPage;
    }
}
