# V11 Toolchain V2-r2 — Structural Evidence-Domain Remediation

The R1 replacement run stopped at its twelfth record after the context-aware
security fix exposed a second toolchain boundary mismatch. A nested typed
`shipping_current_value` branch carried the application-internal domain
`shipping`, while the frozen V11 prediction schema accepts `shipping_settings`.

V2-r2 adds a structural canonicalization layer before frozen V2-r1
normalization. It uses only typed `semantic_intent`, typed route domain, and
parent/branch nesting. It does not inspect record IDs, V11 utterances, gold
labels, response text, or keywords. Conflicting and unknown mappings fail
closed.

The candidate, V11 dataset, audit, final manifest, historical partial capture,
historical state, R1 state, and all V2-r1 files remain byte-identical. No V11
request or scoring operation is part of this remediation build.
