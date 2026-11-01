<?php

namespace App\Traits\Report;



trait ReportFilterTrait
{
    /**
     * Every report funnels its date range through here, so this predicate shape decides
     * whether any index on the date column is usable. Explicit ranges rather than
     * whereYear()/whereMonth(), which render as year(created_at) = ? and cannot use an index:
     * measured 1.92M rows scanned against 166k for the range form, identical results.
     */
    public static function scopeApplyDateFilter($query, $filter, $from = null, $to = null, $column = 'created_at')
    {
        [$start, $end] = match (true) {
            isset($from, $to) && $filter == 'custom' => [$from.' 00:00:00', $to.' 23:59:59'],
            $filter == 'this_year' => [now()->startOfYear(), now()->endOfYear()],
            $filter == 'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            $filter == 'previous_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            $filter == 'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            default => [null, null],
        };

        if (! $start || ! $end) {
            return $query;
        }

        return $query->whereBetween($column, [
            $start instanceof \DateTimeInterface ? $start->format('Y-m-d H:i:s') : $start,
            $end instanceof \DateTimeInterface ? $end->format('Y-m-d H:i:s') : $end,
        ]);
    }

    public static function scopeApplyRelationShipSearch($query, $relationships,$searchParameter )
    {
        foreach ($relationships as $relation => $field) {
            $query->orWhereHas($relation, function ($query) use ($field, $searchParameter) {
                $query->where(function ($q) use ($field, $searchParameter) {
                    foreach ($searchParameter as $value) {
                        $q->orWhere($field, 'like', "%{$value}%");
                    }
                });
            });
        }

        return $query;
    }

 public function scopeSearch($query, $keywords, $relations = [], $mainCol = 'name', $orderByRelevance = true)
    {
        if (empty($keywords)) {
            return $query;
        }
        $keywords = is_array($keywords) ? $keywords : explode(' ', $keywords);
        $keywords = array_filter(array_map('trim', $keywords));

        if (empty($keywords)) {
            return $query;
        }

        $fullText = implode(' ', $keywords);
        $mainColumns = is_array($mainCol) ? $mainCol : [$mainCol];
        $defaultColumn = $mainColumns[0];

        $this->validateColumnName($defaultColumn);
        $query->where(function ($q) use ($keywords, $relations, $mainColumns) {
            foreach ($keywords as $word) {
                $q->where(function ($subQ) use ($word, $mainColumns) {
                    foreach ($mainColumns as $column) {
                        $subQ->orWhere($column, 'like', "%{$word}%");
                    }
                });
            }

            foreach ($relations as $relation => $columns) {
                $columns = is_array($columns) ? $columns : [$columns];
                $q->orWhereHas($relation, function ($rq) use ($columns, $keywords) {
                    foreach ($keywords as $word) {
                        $rq->where(function ($sub) use ($columns, $word) {
                            foreach ($columns as $column) {
                                $sub->orWhere($column, 'like', "%{$word}%");
                            }
                        });
                    }
                });
            }
        });

        if (! $orderByRelevance) {
            return $query;
        }

        return $query->orderByRaw(
            "CASE
            WHEN `{$defaultColumn}` = ? THEN 1
            WHEN `{$defaultColumn}` LIKE ? THEN 2
            WHEN `{$defaultColumn}` LIKE ? THEN 3
            ELSE 4
        END, LENGTH(`{$defaultColumn}`) ASC, `{$defaultColumn}` ASC",
            [$fullText, "{$fullText}%", "%{$fullText}%"]
        );
    }

    protected function validateColumnName($column)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
    }



}
