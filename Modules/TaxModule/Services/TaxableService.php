<?php

namespace Modules\TaxModule\Services;

use App\Services\BaseService;
use Modules\TaxModule\Entities\Taxable;

class TaxableService extends BaseService
{
    public function getTaxIds(mixed $taxableType, mixed $taxableId, mixed $systemTaxSetupId): array
    {
        return Taxable::where('taxable_type', $taxableType)
            ->where('taxable_id', $taxableId)
            ->where('system_tax_setup_id', $systemTaxSetupId)
            ->pluck('tax_id')
            ->toArray();
    }
}
