<?php

$toolchain = require __DIR__.'/v11_toolchain_v2_r1_synthetic.php';

return [
    'records' => [
        [
            'case_id' => 'synthetic-record-001',
            'language_bucket' => 'en',
            'utterance' => 'Show the synthetic catalog item.',
            'preconditions' => ['actor' => 'anonymous'],
            'multi_intent_branches' => [],
        ],
        [
            'case_id' => 'synthetic-record-002',
            'language_bucket' => 'en',
            'utterance' => 'Explain the synthetic order boundary.',
            'preconditions' => ['actor' => 'anonymous'],
            'multi_intent_branches' => [],
        ],
    ],
    'mixed' => $toolchain['mixed'],
    'prediction' => $toolchain['prediction'],
];
