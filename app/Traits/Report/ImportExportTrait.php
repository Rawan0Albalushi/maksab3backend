<?php

namespace App\Traits\Report;

trait ImportExportTrait
{
    public function exportGenerator(object $data): bool|\Generator
    {
        foreach ($data as $item) {
            yield $item;
        }
        return true;
    }
}
