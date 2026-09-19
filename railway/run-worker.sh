#!/usr/bin/env bash

set -euo pipefail

exec php artisan queue:work database \
    --queue=emails,default \
    --sleep=3 \
    --tries=3 \
    --backoff=10 \
    --timeout=90
