# V11 Toolchain V2-r3 — Typed Cross-Account Read Remediation

The R2 one-shot stopped at a typed boundary mismatch. A symbolic order
reference was recognized conceptually but was not materialized into the route
entity slot. The router therefore relabeled a denied other-user order read as
`privileged_mutation`; the frozen V2 adapter then correctly failed closed
because there was no mutation target or mutation-specific denial reason.

V2-r3 canonicalizes only the typed structural combination
`other_user_data/read/other_user_data_access`, an order topic, a typed account
target, mentioned resources, and explicit parent/branch scope. Genuine
mutations remain unchanged. Conflicting or incomplete structures fail closed.

This revision does not change the candidate, dataset, prediction schema,
scoring semantics, V2-r1, V2-r2, or any earlier execution state. It performs
no V11 request and does not authorize execution.
