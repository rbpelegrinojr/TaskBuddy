<?php
declare(strict_types=1);

namespace TaskBuddy\Admin\Support;

final class Pager
{
    public int $pages;
    public int $offset;

    public function __construct(public readonly int $total, public int $page, public readonly int $perPage = 20)
    {
        $this->pages = max(1, (int)ceil($total / $perPage));
        $this->page = max(1, min($page, $this->pages));
        $this->offset = ($this->page - 1) * $perPage;
    }
}
