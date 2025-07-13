# Validate Command

Run architecture validation chains to assess implementation quality.

## Usage

```bash
/validate [architecture_type] [options]
```

## Arguments

- `architecture_type`: Type of architecture to validate (optional)
  - `php` or `php-clean`: PHP Clean Architecture validation
  - `laravel`: Laravel-specific clean architecture
  - `symfony`: Symfony-specific clean architecture
  - If omitted, will detect based on current context

## Options

- `--level=<level>`: Validation depth
  - `quick`: Fast validation (30-45 min)
  - `standard`: Normal validation (90-120 min) [default]
  - `deep`: Comprehensive validation (120-150 min)

- `--output=<format>`: Output format
  - `report`: Full markdown report [default]
  - `scorecard`: JSON scorecard only
  - `ci`: CI/CD compatible output

- `--fail-below=<score>`: Fail if score is below threshold (0-100)

## Examples

```bash
# Standard PHP clean architecture validation
/validate php

# Quick validation for CI pipeline
/validate php --level=quick --output=ci --fail-below=70

# Deep Laravel validation
/validate laravel --level=deep

# Validate with specific task context
/discover Validate our order processing system architecture
/validate php --level=standard
```

## Implementation

```bash
# Parse arguments
ARCHITECTURE_TYPE="${1:-auto}"
LEVEL="standard"
OUTPUT="report"
FAIL_BELOW="0"

# Parse options
for arg in "$@"; do
    case $arg in
        --level=*)
            LEVEL="${arg#*=}"
            ;;
        --output=*)
            OUTPUT="${arg#*=}"
            ;;
        --fail-below=*)
            FAIL_BELOW="${arg#*=}"
            ;;
    esac
done

# Auto-detect architecture if needed
if [ "$ARCHITECTURE_TYPE" = "auto" ]; then
    # Check discoveries context for clues
    if [ -f "contexts/active/current.json" ]; then
        # Detect based on discoveries
        echo "🔍 Auto-detecting architecture type..."
        ARCHITECTURE_TYPE="php"  # Default to PHP for now
    fi
fi

# Map architecture type to validation chain
case "$ARCHITECTURE_TYPE" in
    php|php-clean)
        CHAIN="php_architecture_validation"
        echo "🏗️ Validating PHP Clean Architecture"
        ;;
    laravel)
        CHAIN="php_architecture_validation"
        echo "🏗️ Validating Laravel Clean Architecture"
        echo "Setting framework context: Laravel"
        ;;
    symfony)
        CHAIN="php_architecture_validation"
        echo "🏗️ Validating Symfony Clean Architecture"
        echo "Setting framework context: Symfony"
        ;;
    *)
        echo "❌ Unknown architecture type: $ARCHITECTURE_TYPE"
        echo "Supported types: php, laravel, symfony"
        exit 1
        ;;
esac

# Set validation level in context
echo "📊 Validation Level: $LEVEL"
case "$LEVEL" in
    quick)
        echo "⚡ Running quick validation (30-45 minutes)"
        ;;
    standard)
        echo "📋 Running standard validation (90-120 minutes)"
        ;;
    deep)
        echo "🔬 Running deep validation (120-150 minutes)"
        ;;
esac

# Execute validation chain
echo ""
echo "🚀 Starting validation..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Run the validation chain with context
/chain "$CHAIN" --validation-level="$LEVEL"

# Check results based on output format
case "$OUTPUT" in
    report)
        echo ""
        echo "📄 Generating validation report..."
        # Display the full report from context
        ;;
    scorecard)
        echo ""
        echo "📊 Validation Scorecard:"
        # Display just the scorecard
        ;;
    ci)
        echo ""
        echo "🤖 CI/CD Output:"
        # Generate CI-compatible output
        ;;
esac

# Check fail threshold
if [ "$FAIL_BELOW" -gt "0" ]; then
    # Get score from context
    SCORE=$(grep "overall_score" contexts/active/*/validation_results.json | cut -d: -f2 | tr -d ' ",')
    
    if [ "$SCORE" -lt "$FAIL_BELOW" ]; then
        echo ""
        echo "❌ Validation failed: Score $SCORE is below threshold $FAIL_BELOW"
        exit 1
    else
        echo ""
        echo "✅ Validation passed: Score $SCORE meets threshold $FAIL_BELOW"
    fi
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Validation complete! Check the report above for details."
```

## Output Example

```
🏗️ Validating PHP Clean Architecture
📊 Validation Level: standard
📋 Running standard validation (90-120 minutes)

🚀 Starting validation...
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[Validation runs through all prompts...]

📄 Generating validation report...

# PHP Clean Architecture Validation Report

## Executive Summary
- **Overall Score**: 85/100 (🥈 Silver Certified)
- **Critical Issues**: 2
- **Recommendations**: 8
- **Validation Duration**: 95 minutes

## Detailed Findings
...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✨ Validation complete! Check the report above for details.
```

## Related Commands

- `/chain php_architecture_validation` - Run validation chain directly
- `/discover` - Start a new discovery task
- `/context status` - Check current validation results