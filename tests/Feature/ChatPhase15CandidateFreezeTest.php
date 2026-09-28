<?php

use Tests\Support\ChatFixtureAudit;

it('freezes the Phase 15 mutation-target candidate revision', function () {
    $parent = 'af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9';
    $candidate = 'b61cec4c948ce18aeececa343472b7da71579a3c4e5a57ca484db71b81c71d03';

    expect(ChatFixtureAudit::runtimeHash())
        ->toBe($candidate)
        ->not->toBe($parent);
})->group('phase15-candidate-freeze');
