<?php

namespace App\Services\System;

use App\CentralLogics\Helpers;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The weight and dimension units — product decision 4, the two siblings of `distance_unit`.
 *
 * Separate from DistanceService on purpose. They share a shape but not a job: a distance is
 * MEASURED and meets a rate, so `DistanceService` carries `chargeable()`, a money precision and
 * two wiring points inside the fee engine. Weight and dimension classes are **setup values only**
 * — nothing in mart measures a package and multiplies it by anything yet, because the parcel
 * pricing that would read them is still deferred. Here the unit is a LABEL and a meaning, not an
 * operand.
 *
 * The storage model is the same as distance's, and that consistency is the point of decision 4:
 *
 *   SETUP — `weights.from_weight`, `weights.to_weight`, `dimensions.max_*`. Stored EXACTLY AS
 *           TYPED. On a unit switch the stored number does not change; its MEANING does. A `2`
 *           band becomes 2 lb.
 *
 * So a switch is the same kind of event a distance switch is — silent, total, and needing the
 * same confirmation — with one honest difference the dialog states outright: **no fee moves**,
 * because nothing prices on these yet.
 */
class MeasurementUnitService extends BaseService
{
    public const KG = 'kg';

    public const LB = 'lb';

    public const CM = 'cm';

    public const IN = 'in';

    public const WEIGHT_UNITS = [self::KG, self::LB];

    public const DIMENSION_UNITS = [self::CM, self::IN];

    /** The factors, written once. A grep for these numbers must return only these four lines. */
    public const LB_PER_KG = 2.20462262;

    public const KG_PER_LB = 0.45359237;

    public const IN_PER_CM = 0.393700787;

    public const CM_PER_IN = 2.54;

    public const PRECISION = 4;

    /** Static, so a fresh resolution cannot lose the memo. Cleared by every write below. */
    private static array $memo = [];

    public function forgetUnits(): void
    {
        self::$memo = [];
    }

    public function weightUnit(): string
    {
        return $this->resolve('weight_unit', self::WEIGHT_UNITS, self::KG);
    }

    public function dimensionUnit(): string
    {
        return $this->resolve('dimension_unit', self::DIMENSION_UNITS, self::IN);
    }

    /**
     * The unit symbol, verbatim -- kg, lb, cm, in.
     *
     * Not routed through translate(), for the reason DistanceService::unitLabel() gives: these
     * are international symbols rather than prose, isPersistableTranslationKey() refuses to keep
     * them in the language files at all, and translate() ucfirst()s any key it cannot find. Left
     * on the translator every weight rendered as "2 Kg" and every dimension as "30 In".
     */
    public function weightUnitLabel(): string
    {
        return $this->weightUnit();
    }

    public function dimensionUnitLabel(): string
    {
        return $this->dimensionUnit();
    }

    /*
    |--------------------------------------------------------------------------
    | The three units, in one shape
    |--------------------------------------------------------------------------
    |
    | Decision 4 centralised distance, weight and dimension in Business Setup, but each was
    | still reported in its own shape and from its own place -- `/config` emitted two flat
    | `distance_unit*` keys and nothing emitted the other two at all. A client that wanted to
    | label a weight band had no source but a hard-coded "KG".
    |
    | So one descriptor shape answers all three, built here rather than in a resource: which
    | unit a platform is configured for is a settings question, and a resource is not where
    | settings are read (architecture rule 3). `UnitResource` renders what this returns.
    |
    | Distance is included even though `DistanceService` owns it. The client-facing question is
    | "what units does this platform use", and answering two thirds of it here and one third
    | somewhere else is what produced the inconsistency in the first place. The service is
    | resolved inline, never injected (rule 4).
    */

    public const TYPE_DISTANCE = 'distance';

    public const TYPE_WEIGHT = 'weight';

    public const TYPE_DIMENSION = 'dimension';

    public const TYPES = [self::TYPE_DISTANCE, self::TYPE_WEIGHT, self::TYPE_DIMENSION];

    /**
     * The full name behind each symbol.
     *
     * The SYMBOLS stay untranslated for the reason unitLabel() gives; the NAMES are prose and go
     * through translate() at read time, so an Arabic panel reads "kg" and "كيلوغرام" rather than
     * "kg" twice.
     */
    private const UNIT_NAMES = [
        DistanceService::KM => 'messages.Kilometer',
        DistanceService::MI => 'messages.Mile',
        self::KG => 'messages.Kilogram',
        self::LB => 'messages.Pound',
        self::CM => 'messages.Centimeter',
        self::IN => 'messages.Inch',
    ];

    /**
     * One unit, described.
     *
     * `supported` is the full enum rather than just the configured value, so a client can render
     * a unit switch, or validate what it was handed, without carrying the list itself.
     *
     * @return array{type:string,unit:string,label:string,name:string,supported:array<int,string>}
     */
    public function descriptor(string $type): array
    {
        [$unit, $supported] = match ($type) {
            self::TYPE_WEIGHT => [$this->weightUnit(), self::WEIGHT_UNITS],
            self::TYPE_DIMENSION => [$this->dimensionUnit(), self::DIMENSION_UNITS],
            self::TYPE_DISTANCE => [app(DistanceService::class)->unit(), [DistanceService::KM, DistanceService::MI]],
            default => throw new \InvalidArgumentException("Unknown measurement type [{$type}]."),
        };

        return [
            'type' => $type,
            'unit' => $unit,
            // The symbol, for suffixing a number -- "2.00 kg".
            'label' => $unit,
            'name' => $this->unitName($unit),
            'supported' => array_values($supported),
        ];
    }

    /**
     * All three, keyed by type -- what `/config` reports.
     *
     * @return array<string, array{type:string,unit:string,label:string,name:string,supported:array<int,string>}>
     */
    public function descriptors(): array
    {
        return collect(self::TYPES)
            ->mapWithKeys(fn (string $type) => [$type => $this->descriptor($type)])
            ->all();
    }

    /** "Kilogram" for `kg`. The symbol itself where no name is mapped, never an empty label. */
    public function unitName(string $unit): string
    {
        $key = self::UNIT_NAMES[$unit] ?? null;

        return $key ? translate($key) : $unit;
    }



    /** Read through BusinessSettingService (cached) plus a memo — M8. */
    private function resolve(string $key, array $allowed, string $default): string
    {
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        $value = (string) (app(BusinessSettingService::class)->value($key, false) ?? '');

        return self::$memo[$key] = in_array($value, $allowed, true) ? $value : $default;
    }
}
