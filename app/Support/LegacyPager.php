<?php

namespace App\Support;

/**
 * The offsets MY_Model::paginate() computes for the pager, over a list of $total rows.
 */
final class LegacyPager
{
    public function __construct(
        public readonly int $offset,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    /** `default_list_limit`, or 15 when it is unset (MY_Model::$default_limit). */
    public static function defaultPerPage(): int
    {
        return (int) get_setting('default_list_limit', 15);
    }

    public function previousOffset(): int
    {
        return $this->offset - $this->perPage;
    }

    public function nextOffset(): int
    {
        return $this->offset + $this->perPage;
    }

    public function lastOffset(): int
    {
        return (int) ceil($this->total / $this->perPage) * $this->perPage - $this->perPage;
    }
}
