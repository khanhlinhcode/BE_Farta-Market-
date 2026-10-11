<?php

use Tests\Support\ChatFixtureAudit;

it('freezes the post-Phase 15 engineering candidate revision', function () {
    $parent = 'eb4d5d239789eeb087185e93612516090ad2c7760c7b18febf5a995d1327ce41';
    $candidate = 'bd2f2dcfe58085107fab47e20d4b95baeb304a4e7451d5f075e3ea8858cefcd0';

    expect(ChatFixtureAudit::runtimeHash())
        ->toBe($candidate)
        ->not->toBe($parent);
})->group('phase15-candidate-freeze');
