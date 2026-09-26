<?php

declare(strict_types=1);

use Foxws\Docs\Collections\VersionCollection;
use Foxws\Docs\Models\Version;

function makeVersions(string ...$names): VersionCollection
{
    return new VersionCollection(array_map(
        fn (string $name, int $id): Version => (new Version(['name' => $name]))->forceFill(['id' => $id + 1]),
        $names,
        array_keys($names),
    ));
}

it('picks the newest version by version number, not by registration order', function (array $names, string $expected) {
    expect(makeVersions(...$names)->newest()->name)->toBe($expected);
})->with([
    'major above patch' => [['1.0.0', '0.0.9'], '1.0.0'],
    'numeric, not string comparison' => [['1.10.0', '1.9.0'], '1.10.0'],
    'older registered last' => [['2.3.1', '2.2.1'], '2.3.1'],
    'leading v ignored' => [['v2.0.0', '1.5.0'], 'v2.0.0'],
    'release above prerelease' => [['2.0.0', '2.0.0-beta.1'], '2.0.0'],
    'version numbers above branch names' => [['latest', '0.1.0'], '0.1.0'],
    'most recent among branch names' => [['main', 'latest'], 'latest'],
]);

it('returns null for an empty collection', function () {
    expect((new VersionCollection)->newest())->toBeNull();
});

it('returns a version collection from queries', function () {
    expect(Version::query()->get())->toBeInstanceOf(VersionCollection::class);
});
