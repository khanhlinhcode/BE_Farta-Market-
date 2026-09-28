# Evaluation artifact policy

Commit executable regression tests, schemas, and the smallest canonical fixture
needed to reproduce an offline contract test. Do not commit generated captures,
scored output, intermediate candidate datasets, run manifests, preflight state,
or test reports. CI should publish those files from `artifacts/evaluation/` as
retained build artifacts.

The V10 directory keeps only the final audited dataset and fixed raw capture
used by the offline scorer regression tests. Historical headline metrics and
their candidate scope are summarized in the project README and phase report.

Final V11 is a frozen, opt-in holdout. Its tests are excluded from the default
PHPUnit suites and must not be read or executed during ordinary development,
CI, security review, or deployment validation.
