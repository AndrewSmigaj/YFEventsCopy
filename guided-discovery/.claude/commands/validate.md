# Validate Architecture

Run validation to assess implementation quality.

## Command: /validate $ARGUMENTS

[Claude runs validation chains to assess the architecture and provide scoring]

### Options:
- Architecture type: `php`, `laravel`, `symfony` (or auto-detect)
- `--level`: `quick`, `standard`, `deep`
- `--output`: `report`, `scorecard`, `ci`
- `--fail-below`: Threshold score (0-100)