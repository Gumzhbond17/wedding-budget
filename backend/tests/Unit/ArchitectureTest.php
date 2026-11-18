<?php

use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Architecture rules
|--------------------------------------------------------------------------
|
| Conventions that hold for every file in a directory, so a new file that breaks one fails
| here instead of in code review. Pest's arch() checks imports and declarations; the rules it
| cannot express (query-builder chains on a relation, backed enums anywhere in app/, model
| casts) are checked with reflection or the tokenizer below.
|
*/

/**
 * Every class-like name declared under app/, derived from PSR-4 paths.
 *
 * @return list<class-string>
 */
function appClassNames(): array
{
    $root = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $names = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root) + 1, -4);
        $names[] = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
    }

    sort($names);

    return $names;
}

/**
 * PHP source without comments, so a docblock that mentions "->where(" is not a violation.
 */
function codeWithoutComments(string $path): string
{
    $code = '';

    foreach (PhpToken::tokenize(file_get_contents($path)) as $token) {
        if (! $token->is([T_COMMENT, T_DOC_COMMENT])) {
            $code .= $token->text;
        }
    }

    return $code;
}

arch('debugging helpers never ship in app code')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch('controllers never reach the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Database\Query\Builder',
        'Illuminate\Database\Eloquent\Relations',
    ]);

/*
 * toUse() only sees imports, and a controller can build a query without importing anything:
 * $wedding->expenses()->where(...)->paginate(). Queries belong in app/Actions (List*, Manage*),
 * so controllers stay request -> action -> resource. Eager loading a bound model (load) and
 * reading validated input are allowed.
 */
it('keeps query builder calls out of controllers', function (string $controller) {
    $queryCalls = '/(::query\(|->(where\w*|orWhere\w*|orderBy\w*|latest|oldest|paginate|simplePaginate|cursorPaginate|get|first\w*|find\w*|pluck|count|sum|join\w*|leftJoin|select\w*|with|withCount|when|lockForUpdate|sharedLock)\()/';

    preg_match_all($queryCalls, codeWithoutComments($controller), $matches);

    expect($matches[0])->toBe([]);
})->with(function (): array {
    $root = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR;
    $controllers = array_filter(appClassNames(), fn (string $class): bool => str_starts_with($class, 'App\Http\Controllers\\'));

    return array_combine($controllers, array_map(
        fn (string $class): string => $root.str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4)).'.php',
        $controllers,
    ));
});

arch('the Enums namespace holds only enums')
    ->expect('App\Enums')
    ->toBeEnums();

it('backs every enum in app/ with a value, so it can be stored and serialized', function () {
    $enums = array_values(array_filter(appClassNames(), enum_exists(...)));
    $pure = array_values(array_filter($enums, fn (string $enum): bool => ! new ReflectionEnum($enum)->isBacked()));

    expect($enums)->not->toBeEmpty()
        ->and($pure)->toBe([]);
});

arch('actions are final')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal();

/*
 * Money is DECIMAL in MySQL and BCMath strings in PHP. A float cast would silently turn
 * 0.1 + 0.2 into 0.30000000000000004 and large LAK amounts into rounded values.
 */
it('never casts a model attribute to float, and casts money columns to decimal', function () {
    $models = array_values(array_filter(
        appClassNames(),
        fn (string $class): bool => class_exists($class) && is_subclass_of($class, Model::class) && ! new ReflectionClass($class)->isAbstract(),
    ));

    $violations = [];

    foreach ($models as $model) {
        foreach (new $model()->getCasts() as $attribute => $cast) {
            $isMoney = preg_match('/(^amount|_amount|rate|budget_target)$/', $attribute) === 1;

            if (in_array(strtolower($cast), ['float', 'double', 'real'], true)
                || ($isMoney && ! str_starts_with($cast, 'decimal:'))) {
                $violations[] = "{$model}::{$attribute} => {$cast}";
            }
        }
    }

    expect($models)->not->toBeEmpty()
        ->and($violations)->toBe([]);
});
