<?php

namespace App\Traits\Item;

use Illuminate\Database\Eloquent\Model;
use Modules\TaxModule\Services\SystemTaxSetupService;

trait HasProductTaxablesTrait
{
    protected function productWiseTaxSetup(): mixed
    {
        if (! addon_published_status('TaxModule')) {
            return null;
        }

        return app(SystemTaxSetupService::class)->findProductWiseDefault();
    }

    protected function createTaxables(Model $model, array $taxIds): void
    {
        $setup = $this->productWiseTaxSetup();

        if (! $setup) {
            return;
        }

        foreach ($taxIds as $taxId) {
            $model->taxVats()->create(['system_tax_setup_id' => $setup->id, 'tax_id' => $taxId]);
        }
    }
}
