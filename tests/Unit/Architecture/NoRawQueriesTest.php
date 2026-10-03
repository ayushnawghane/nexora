<?php

use Symfony\Component\Finder\Finder;

it('uses Eloquent instead of raw database queries', function () {
    $forbidden = '/DB::(table|select|selectOne|raw|statement|unprepared|insert|update|delete|affectingStatement|scalar)\s*\(|->(whereRaw|orWhereRaw|selectRaw|orderByRaw|havingRaw|groupByRaw|fromRaw)\s*\(/';

    $offenders = [];

    foreach (Finder::create()->files()->in([base_path('app'), base_path('routes'), base_path('database')])->name('*.php') as $file) {
        foreach (preg_split('/\R/', $file->getContents()) as $index => $line) {
            if (preg_match($forbidden, $line)) {
                $offenders[] = $file->getRelativePathname().':'.($index + 1).'  '.trim($line);
            }
        }
    }

    expect($offenders)->toBeEmpty('Raw queries found:'.PHP_EOL.implode(PHP_EOL, $offenders));
});
