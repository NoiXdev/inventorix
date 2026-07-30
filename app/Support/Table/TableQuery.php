<?php

namespace App\Support\Table;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TableQuery
{
    private array $searchable = [];

    private array $sortable = [];

    private array $filterable = [];

    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    public function searchable(array $columns): self
    {
        $this->searchable = $columns;

        return $this;
    }

    public function sortable(array $columns): self
    {
        $this->sortable = $columns;

        return $this;
    }

    public function filterable(array $filters): self
    {
        foreach ($filters as $key => $column) {
            // list entry: key is int -> request key == column; assoc: key => qualified column
            $this->filterable[is_int($key) ? $column : $key] = $column;
        }

        return $this;
    }

    public function paginate(int $default = 15): LengthAwarePaginator
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySort();

        $perPage = $this->request->integer('perPage', $default);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : $default;

        return $this->query
            ->paginate($perPage, ['*'], 'page', $this->request->integer('page', 1))
            ->appends($this->request->query());
    }

    public function get(): Collection
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySort();

        return $this->query->get();
    }

    private function applySearch(): void
    {
        $term = trim((string) $this->request->string('search', ''));
        if ($term === '' || $this->searchable === []) {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        $this->query->where(function (Builder $q) use ($escaped): void {
            $grammar = $q->getQuery()->getGrammar();

            foreach ($this->searchable as $column) {
                $wrapped = $grammar->wrap($column);
                $q->orWhereRaw("{$wrapped} LIKE ? ESCAPE ?", ["%{$escaped}%", '\\']);
            }
        });
    }

    private function applySort(): void
    {
        $sort = (string) $this->request->string('sort', '');
        if ($sort === '') {
            return;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, $this->sortable, true)) {
            $this->query->orderBy($column, $direction);
        }
    }

    private function applyFilters(): void
    {
        foreach ($this->filterable as $key => $column) {
            $value = $this->request->input("filter.$key");

            if ($value === null || $value === '') {
                continue;
            }

            $this->query->where($column, $value);
        }
    }
}
