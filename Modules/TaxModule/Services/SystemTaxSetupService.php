<?php

namespace Modules\TaxModule\Services;

use App\Services\BaseService;
use Modules\TaxModule\Entities\SystemTaxSetup;
use Modules\TaxModule\Entities\Tax;

class SystemTaxSetupService extends BaseService
{
    public function findProductWiseDefault(): mixed
    {
        $setup = $this->findDefault();

        return $setup?->tax_type === 'product_wise' ? $setup : null;
    }

    public function findDefault(): ?SystemTaxSetup
    {
        return SystemTaxSetup::where('is_active', 1)->where('is_default', 1)->first();
    }

    public function findActiveByTaxPayer(string $taxPayer): ?SystemTaxSetup
    {
        return SystemTaxSetup::where('tax_payer', $taxPayer)->where('is_active', 1)->first();
    }

    public function findActiveByTaxPayerWithData(string $taxPayer, ?string $countryCode = null): ?SystemTaxSetup
    {
        return SystemTaxSetup::with('additionalData')
            ->when($countryCode, fn ($query) => $query->where('country_code', $countryCode))
            ->where('tax_payer', $taxPayer)
            ->where('is_active', 1)
            ->first();
    }


    public function getTaxSystemType(bool $getTaxVatList = true, string $taxPayer = 'vendor'): array
    {
        if (! addon_published_status('TaxModule')) {
            return ['productWiseTax' => false, 'categoryWiseTax' => false, 'taxVats' => []];
        }

        $systemTaxVat = SystemTaxSetup::where('is_active', 1)
            ->where('tax_payer', $taxPayer)->where('is_default', 1)->first();

        if (! $systemTaxVat || $systemTaxVat?->is_included == 1) {
            return ['productWiseTax' => false, 'categoryWiseTax' => false, 'taxVats' => []];
        }

        return [
            'productWiseTax' => $systemTaxVat?->tax_type == 'product_wise',
            'categoryWiseTax' => $systemTaxVat?->tax_type == 'category_wise',
            'taxVats' => $getTaxVatList
                ? Tax::where('is_active', 1)->where('is_default', 1)->get(['id', 'name', 'tax_rate'])
                : [],
        ];
    }
}
