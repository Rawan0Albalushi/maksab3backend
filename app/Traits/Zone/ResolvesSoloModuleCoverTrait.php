<?php

namespace App\Traits\Zone;

use Illuminate\Database\Eloquent\Model;

/**
 * "Which of this setup's modules would have nothing left if it stopped covering them?"
 *
 * Four of the five zone setups ask it — delivery rule, ETA configuration, free delivery and
 * additional delivery charge — with the same shape: a `zone_id`, a `status`, and a `modules`
 * pivot. Surge price stores its module ids as a JSON column instead and answers the question in
 * its own service; everything else shares this so the five screens cannot disagree about when a
 * module is genuinely losing its last cover.
 *
 * S19 needs it at a second moment. The status toggles already asked it before switching a setup
 * OFF; the edit forms now ask it before SAVING, because dropping a module from the picker takes
 * that module's cover away just as surely, and used to do it in silence.
 *
 * @see \App\Services\Zone\SurgePriceService::soloModules() for the JSON-column variant
 */
trait ResolvesSoloModuleCoverTrait
{
    /**
     * The modules this row is the ONLY active cover for, keyed by id so a caller can match the
     * ids a form posts back without matching on names.
     *
     * An inactive row covers nothing, so nothing can be lost by editing it — the answer is empty
     * rather than "every module on it", which would warn about a change with no effect.
     *
     * One `exists()` per module on the row. That is fine here and nowhere else: this is asked by
     * a single edit screen about a single row, never down a list (rule 11). The list screens have
     * their own batched `lockedModulesFor()`.
     *
     * @param  Model  $setup  a row with `modules` loaded
     * @param  class-string<Model>  $model  the setup's own class, for the "who else covers it" query
     * @return array<int, string> module id => module name
     */
    protected function soloModuleCover(Model $setup, string $model): array
    {
        if (! $setup->status) {
            return [];
        }

        return $setup->modules
            ->reject(fn ($module) => $model::query()
                ->where('zone_id', $setup->zone_id)
                ->where('status', 1)
                ->whereKeyNot($setup->getKey())
                ->whereHas('modules', fn ($query) => $query->where('modules.id', $module->id))
                ->exists())
            ->mapWithKeys(fn ($module) => [(int) $module->id => (string) $module->module_name])
            ->all();
    }
}
