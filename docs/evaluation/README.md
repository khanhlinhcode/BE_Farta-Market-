# Evaluation artifact policy

Commit executable regression tests, schemas, and the smallest canonical fixture
needed to reproduce an offline contract test. Do not commit generated captures,
scored output, intermediate candidate datasets, run manifests, preflight state,
or test reports. Evaluation-specific workflows should write those files under
`artifacts/evaluation/` and publish that directory as a retained CI artifact.
The general backend workflow in `.github/workflows/ci.yml` does not generate or
upload evaluation artifacts.

The V10 directory keeps only
`v10-independent/v10-final-audited-dataset.json` and
`v10-independent/v10-final-v3-raw-capture.jsonl`. They are the canonical audited
dataset and fixed raw capture required by offline scorer regression tests.
Historical headline metrics and their candidate scope are summarized in the
project README and phase report.

Final V11 is a frozen, opt-in holdout. Its tests are excluded from the default
PHPUnit suites and must not be read or executed during ordinary development,
CI, security review, or deployment validation.
