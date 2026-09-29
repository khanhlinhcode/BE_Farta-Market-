<?php

use Tests\Support\ChatFixtureAudit;

it('freezes the post-Phase 15 engineering candidate revision', function () {
    $parent = 'b61cec4c948ce18aeececa343472b7da71579a3c4e5a57ca484db71b81c71d03';
    $candidate = 'eb4d5d239789eeb087185e93612516090ad2c7760c7b18febf5a995d1327ce41';

    expect(ChatFixtureAudit::runtimeHash())
        ->toBe($candidate)
        ->not->toBe($parent);
})->group('phase15-candidate-freeze');
