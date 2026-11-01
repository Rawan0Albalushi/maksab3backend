<?php

namespace App\Support\Builder;

/**
 * Stand-in for a Modules\Builder\Contracts\* interface the installed add-on does not ship.
 *
 * Deliberately empty. ContractFallback aliases this name to the missing contract, which is enough
 * for `class X implements ThatContract` to compile -- an interface declares no behaviour to satisfy,
 * so the adapter's own methods are unaffected.
 *
 * Nothing resolves it: class_implements() reports the alias TARGET, so an adapter standing on this
 * placeholder does not match the Modules\Builder\Contracts\ prefix AppServiceProvider binds on, and
 * is left unbound. The module's own Null* default stays in place for any contract that does exist.
 */
interface AbsentContract
{
}
