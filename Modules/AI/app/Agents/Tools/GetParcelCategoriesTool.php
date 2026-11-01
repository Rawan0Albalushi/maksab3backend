<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use App\Models\BusinessSetting;
use App\Models\ParcelCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetParcelCategoriesTool implements Tool
{
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
    ) {}

    public function description(): string
    {
        return 'Get parcel categories the platform supports, each with the additional charge it adds on top of the delivery fee. You do NOT know the delivery fee itself — it depends on the zone and the distance, which this tool cannot see, so never quote a total parcel price. Use this when the user asks what types of parcels they can ship or what categories exist. Read-only — suggestions only, you cannot create bookings.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->number()->description('Number of categories to return, default 8, max 10')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args  = $request->all();
        $limit = min((int) ($args['limit'] ?? 8), 10);

        $categories = ParcelCategory::active()
            ->when($this->moduleId, fn ($q) => $q->module($this->moduleId))
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'description', 'charge']);

        $this->context->recordTool('GetParcelCategoriesTool');

        if ($categories->isEmpty()) {
            return 'No parcel categories available.';
        }

        // A14 — the per-km and minimum rates this tool used to quote no longer charge anything,
        // and the delivery fee that replaced them needs a zone and a distance the AI context does
        // not carry. So it reports the one number it can state truthfully: the category's own
        // additional charge, labelled as an addition rather than a price.
        $lines = $categories->map(function (ParcelCategory $c): string {
            $charge = (float) $c->getAttribute('charge');

            $parts = [
                $c->getAttribute('name'),
                $charge > 0
                    ? '+' . $charge . ' on top of the delivery fee'
                    : 'no additional charge',
            ];

            $desc = trim((string) $c->getAttribute('description'));
            if ($desc !== '') {
                $parts[] = mb_substr($desc, 0, 80);
            }

            return implode(' — ', $parts);
        })->all();

        return count($lines) . ' parcel categories (the delivery fee itself depends on the zone '
            . 'and distance and is not known here): ' . implode(' | ', $lines);
    }
}
