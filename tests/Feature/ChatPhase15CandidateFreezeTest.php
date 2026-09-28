<?php

use Tests\Support\ChatFixtureAudit;

it('freezes the Phase 15 mutation-target candidate revision', function () {
    $parent = 'af36f24e2cebe333137ecf607000ff54dbefec699c7a50bf0b32782b84a08ce9';
    $candidate = '5176b804e0bba6f5b09479ea6835fd4835790fd2e3097cce9d925fd31053a21d';

    expect(ChatFixtureAudit::runtimeHash())
        ->toBe($candidate)
        ->not->toBe($parent);
})->group('phase15-candidate-freeze');
