<?php

namespace Modules\AI\app\Agents\Tools;

use App\CentralLogics\Helpers;
use Modules\AI\app\Agents\AiResponseContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetPlatformInfoTool implements Tool
{
    private const ALLOWED_KEYS = [
        'business_name',
        'address',
        'phone',
        'email_address',
        'country',
        'currency',
        'currency_symbol_position',
        'digit_after_decimal_point',
        'timezone',
        'timeformat',
        'additional_charge',
        'additional_charge_name',
        'additional_charge_status',
        'service_charge',
        // `free_delivery_over` / `free_delivery_over_status` were removed in S6. Free delivery is
        // a per-(zone, module) setup now, and this tool has no zone: AiResponseContext carries
        // none. Reporting the deprecated global would have the assistant quote a threshold that
        // no longer frees anything — worse than saying nothing. It can come back the day the
        // context carries a zone.
    ];

    public function __construct(
        private readonly AiResponseContext $context,
    ) {}

    public function description(): string
    {
        return 'Get public platform information: business name, contact address, phone, support email, country, currency symbol, decimal format, and any additional charges. Use this when the user asks about currency, pricing format, contact details, platform name, support info, or additional charges. It does NOT know delivery fees or free-delivery thresholds: those depend on the zone and module of the customer, which this tool has no access to. Always use the returned currency when displaying prices.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $rows = Helpers::get_business_settings_many(self::ALLOWED_KEYS);

        $this->context->recordTool('GetPlatformInfoTool');

        $info = $this->buildInfo($rows);

        if (empty($info)) {
            return 'Platform information is not configured.';
        }

        $lines = [];
        foreach ($info as $label => $value) {
            $lines[] = $label . ': ' . $value;
        }

        return 'Platform info — ' . implode('; ', $lines);
    }

    private function buildInfo(array $rows): array
    {
        $info = [];

        if (!empty($rows['business_name'])) {
            $info['Platform name'] = $rows['business_name'];
        }

        if (!empty($rows['country'])) {
            $info['Country'] = $rows['country'];
        }

        if (!empty($rows['currency'])) {
            $symbol   = $rows['currency'];
            $position = $rows['currency_symbol_position'] ?? 'left';
            $decimals = (int) ($rows['digit_after_decimal_point'] ?? 2);
            $info['Currency'] = $symbol;
            $info['Currency position'] = $position;
            $info['Decimal places'] = $decimals;
            $info['Price format example'] = $position === 'right'
                ? '100.' . str_repeat('0', $decimals) . $symbol
                : $symbol . '100.' . str_repeat('0', $decimals);
        }

        if (!empty($rows['address'])) {
            $info['Address'] = $rows['address'];
        }

        if (!empty($rows['phone'])) {
            $info['Phone'] = $rows['phone'];
        }

        if (!empty($rows['email_address'])) {
            $info['Support email'] = $rows['email_address'];
        }

        if (!empty($rows['timezone'])) {
            $info['Timezone'] = $rows['timezone'];
        }

        $additionalStatus = ($rows['additional_charge_status'] ?? '0') == '1';
        if ($additionalStatus && isset($rows['additional_charge'])) {
            $name             = $rows['additional_charge_name'] ?? 'Additional charge';
            $info[$name]      = $rows['additional_charge'];
        }

        if (isset($rows['service_charge']) && $rows['service_charge'] > 0) {
            $info['Service charge'] = $rows['service_charge'];
        }

        return $info;
    }
}
