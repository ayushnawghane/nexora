<?php

use App\Enums\CompanyClass;
use App\Support\IndianIdentifiers;

test('real GSTINs pass the check-digit test', function (string $gstin) {
    expect(IndianIdentifiers::isGstin($gstin))->toBeTrue();
})->with(['27AAPFU0939F1ZV', '29AAGCB7383J1Z4', '27AAACR5055K1Z7']);

test('GSTINs with a typo, wrong check digit or bad shape are rejected', function (string $gstin) {
    expect(IndianIdentifiers::isGstin($gstin))->toBeFalse();
})->with([
    'wrong check digit' => '27AAPFU0939F1ZW',
    'swapped digits' => '27AAPFU9039F1ZV',
    'missing Z' => '27AAPFU0939F1YV',
    'lowercase' => '27aapfu0939f1zv',
    'too short' => '27AAPFU0939F1Z',
    'entity number 0' => '27AAPFU0939F0ZV',
]);

test('the GSTIN state code and embedded PAN are extracted', function () {
    expect(IndianIdentifiers::gstinStateCode('27AAACR5055K1Z7'))->toBe('27')
        ->and(IndianIdentifiers::gstinPan('27AAACR5055K1Z7'))->toBe('AAACR5055K');
});

test('CIN parts give the year, class and listing status', function () {
    $cin = 'L17110MH1973PLC019786';

    expect(IndianIdentifiers::isCin($cin))->toBeTrue()
        ->and(IndianIdentifiers::cinYear($cin))->toBe(1973)
        ->and(IndianIdentifiers::cinClass($cin))->toBe(CompanyClass::Public)
        ->and(IndianIdentifiers::isListedCin($cin))->toBeTrue()
        ->and(IndianIdentifiers::cinClass('U65990MH2010PTC123456'))->toBe(CompanyClass::Private)
        ->and(IndianIdentifiers::isListedCin('U65990MH2010PTC123456'))->toBeFalse()
        ->and(IndianIdentifiers::cinClass('U65990MH2010XYZ123456'))->toBeNull();
});

test('CIN, LLPIN and PAN formats', function () {
    expect(IndianIdentifiers::isCin('U65990MH2010PTC12345'))->toBeFalse()
        ->and(IndianIdentifiers::isLlpin('AAB-1234'))->toBeTrue()
        ->and(IndianIdentifiers::isLlpin('AAB1234'))->toBeFalse()
        ->and(IndianIdentifiers::isPan('AAACR5055K'))->toBeTrue()
        ->and(IndianIdentifiers::isPan('AAAXR5055K'))->toBeFalse()
        ->and(IndianIdentifiers::panHolderType('AAACR5055K'))->toBe('C');
});

test('identifiers are normalised to uppercase without spaces', function () {
    expect(IndianIdentifiers::normalise(' 27aapfu 0939f1zv '))->toBe('27AAPFU0939F1ZV')
        ->and(IndianIdentifiers::normalise('   '))->toBeNull()
        ->and(IndianIdentifiers::normalise(null))->toBeNull();
});
