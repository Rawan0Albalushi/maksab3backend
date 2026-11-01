<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Tests\TestCase;

/**
 * Every `with()` / `load()` / `loadMissing()` in the codebase, checked against the model it is
 * applied to.
 *
 * `with('typo')` throws RelationNotFoundException at RUNTIME, on the request that happens to hit
 * it, and the failure surfaces wherever that request was caught — three separate 500s and one
 * silent 404 reached production this way:
 *
 *   - `rating` and five siblings loaded onto ItemCampaign  → every campaign item 404'd
 *   - `translations` loaded onto DeliveryMan               → every rider edit screen 500'd
 *   - `translations` loaded onto Admin                     → latent, no caller yet
 *
 * Static, so it costs nothing and finds them all at once instead of one incident at a time.
 *
 * ONLY UNAMBIGUOUS CALL SITES ARE CHECKED, so a failure is a real defect and never a guess:
 * `Model::with(...)`, and `$this->prop->with(...)` where `prop` is a Model-typed constructor
 * parameter. Anything it cannot resolve is skipped silently — this is a net, not a proof.
 */
class EagerLoadRelationSweepTest extends TestCase
{
    /**
     * Known-dead scaffolding, kept out of the way rather than "fixed".
     *
     * RideShare's parcel TRIP feature was scaffolded and abandoned: no route reaches these
     * methods, no `parcel*` table exists, and `ride_requests` holds no rows at all. Deleting the
     * relation names would make an unbuilt feature look wired up, which is worse than leaving it
     * visibly unfinished. Remove these entries when the feature lands or the files go.
     */
    private const KNOWN_DEAD = [
        'Modules/RideShare/Repository/TripManagement/TripRequestRepository.php',
        'Modules/RideShare/Repositories/TripManagement/TripRequestRepository.php',
    ];

    public function test_no_eager_load_names_a_relation_its_model_does_not_have(): void
    {
        $this->ensureMemoryLimit('512M');

        $models = $this->modelClasses();
        $this->assertGreaterThan(100, count($models), 'model discovery is broken, not the codebase');

        $findings = [];
        $sites = 0;
        $checked = 0;

        foreach ($this->sourceFiles() as $path) {
            $relative = str_replace(base_path().'/', '', $path);

            if (in_array($relative, self::KNOWN_DEAD, true)) {
                continue;
            }

            foreach ($this->callsIn($path, $models) as [$fqcn, $method, $names, $line]) {
                $sites++;

                foreach ($names as $name) {
                    $checked++;
                    // Only the first segment is ours: `store.discount` needs `store` here, and
                    // `discount` belongs to Store.
                    $first = strtok(strtok($name, '.'), ':');

                    if (! $this->isRelation($fqcn, $first)) {
                        $findings[] = sprintf('%s:%d  %s::%s()  ->  %s',
                            $relative, $line, class_basename($fqcn), $method, $name);
                    }
                }
            }
        }

        fwrite(STDERR, PHP_EOL."resolved $sites eager-load call sites, checked $checked relation names".PHP_EOL);

        $this->assertSame([], $findings,
            'These eager loads name a relation the model does not define. Each one throws '
            .'RelationNotFoundException the moment its code path runs.');
    }

    /** @return array<int, string> */
    private function modelClasses(): array
    {
        $out = [];

        foreach ($this->sourceFiles() as $path) {
            $src = file_get_contents($path);

            if (! preg_match('/^\s*namespace\s+([^;]+);/m', $src, $ns)) continue;
            if (! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $src, $cl)) continue;

            $fqcn = trim($ns[1]).'\\'.$cl[1];

            if (! class_exists($fqcn)) continue;

            try {
                $r = new \ReflectionClass($fqcn);
            } catch (\Throwable) {
                continue;
            }

            if (! $r->isAbstract() && $r->isSubclassOf(Model::class)) {
                $out[] = $fqcn;
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    private function sourceFiles(): array
    {
        static $files = null;

        if ($files !== null) return $files;

        $files = [];

        foreach (['app', 'Modules'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir))) as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') $files[] = $f->getPathname();
            }
        }

        return $files;
    }

    private function isRelation(string $fqcn, string $name): bool
    {
        static $memo = [];
        $key = $fqcn.'::'.$name;

        if (isset($memo[$key])) return $memo[$key];

        try {
            $model = new $fqcn;
        } catch (\Throwable) {
            return $memo[$key] = true;   // cannot instantiate; do not accuse it
        }

        return $memo[$key] = $model->isRelation($name);
    }

    /** @return array<int, array{0:string,1:string,2:array<int,string>,3:int}> */
    private function callsIn(string $path, array $models): array
    {
        static $parser = null;
        $parser ??= (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($path));
        } catch (\Throwable) {
            return [];
        }

        $visitor = new EagerLoadVisitor;
        (new NodeTraverser($visitor))->traverse($ast);

        $byShort = [];
        foreach ($models as $m) $byShort[class_basename($m)][] = $m;

        $out = [];

        foreach ($visitor->calls as [$model, $prop, $method, $names, $line]) {
            $short = $model ?? ($visitor->props[$prop] ?? null);

            if (! $short) continue;

            $short = ltrim($short, '\\');
            $fqcn = in_array($short, $models, true) ? $short : null;
            $fqcn ??= (isset($visitor->uses[$short]) && in_array($visitor->uses[$short], $models, true))
                ? $visitor->uses[$short] : null;
            $base = class_basename($short);
            $fqcn ??= (isset($visitor->uses[$base]) && in_array($visitor->uses[$base], $models, true))
                ? $visitor->uses[$base] : null;
            $fqcn ??= count($byShort[$base] ?? []) === 1 ? $byShort[$base][0] : null;

            if ($fqcn) $out[] = [$fqcn, $method, $names, $line];
        }

        return $out;
    }
}

/** @internal */
class EagerLoadVisitor extends NodeVisitorAbstract
{
    public array $uses = [];
    public array $props = [];
    public array $calls = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $u) $this->uses[$u->getAlias()->toString()] = $u->name->toString();
        }

        // Constructor params typed as a Model, promoted or not. The non-promoted case matters:
        // `__construct(RideRequest $model) { parent::__construct($model); }` puts it on a base
        // class property that subclasses then call `$this->model->query()->with(...)` on.
        // Scoped to __construct so a later `foo(SomethingElse $trip)` cannot overwrite the map.
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === '__construct') {
            foreach ($node->params as $p) {
                if ($p->type instanceof Node\Name && $p->var instanceof Node\Expr\Variable) {
                    $this->props[$p->var->name] = $p->type->toString();
                }
            }
        }

        if ($node instanceof Node\Stmt\Property && $node->type instanceof Node\Name) {
            foreach ($node->props as $p) $this->props[$p->name->toString()] = $node->type->toString();
        }

        if (! $node instanceof Node\Expr\MethodCall && ! $node instanceof Node\Expr\StaticCall) return null;
        if (! $node->name instanceof Node\Identifier) return null;
        if (! in_array($node->name->toString(), ['with', 'load', 'loadMissing'], true)) return null;
        if (! isset($node->args[0])) return null;

        // TOP LEVEL of the argument only. A nested closure's `select('id','name')` is a column
        // list, not an eager load, and scraping it produced nothing but false positives.
        $names = [];
        $arg = $node->args[0]->value;

        if ($arg instanceof Node\Scalar\String_) {
            $names[] = $arg->value;
        } elseif ($arg instanceof Node\Expr\Array_) {
            foreach ($arg->items as $item) {
                if (! $item) continue;
                if ($item->key instanceof Node\Scalar\String_) $names[] = $item->key->value;
                elseif ($item->value instanceof Node\Scalar\String_) $names[] = $item->value->value;
            }
        }

        if (! $names) return null;

        $model = null;
        $prop = null;

        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name) {
            $model = $node->class->toString();
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $v = $node->var;
            // Walk the builder chain back to its root receiver; only the root names the model.
            while ($v instanceof Node\Expr\MethodCall) $v = $v->var;

            if ($v instanceof Node\Expr\StaticCall && $v->class instanceof Node\Name) {
                $model = $v->class->toString();
            } elseif ($v instanceof Node\Expr\PropertyFetch
                && $v->var instanceof Node\Expr\Variable && $v->var->name === 'this'
                && $v->name instanceof Node\Identifier) {
                $prop = $v->name->toString();
            }
        }

        if ($model || $prop) {
            $this->calls[] = [$model, $prop, $node->name->toString(), $names, $node->getLine()];
        }

        return null;
    }
}
