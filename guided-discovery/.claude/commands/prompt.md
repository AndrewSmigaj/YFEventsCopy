# Execute Individual Prompt

Run a single discovery prompt to target specific uncertainties.

```bash
# Parse prompt name
PROMPT_NAME="$ARGUMENTS"

if [ -z "$PROMPT_NAME" ]; then
  echo "❌ Please specify a prompt to run"
  echo ""
  echo "Usage: /prompt <prompt_name>"
  echo ""
  echo "Example: /prompt php_architecture_explorer"
  exit 1
fi

# Check if prompt exists
PROMPT_FILE="/mnt/d/YFEventsCopy/guided-discovery/prompts/**/${PROMPT_NAME}.yaml"
FOUND_PROMPT=$(find /mnt/d/YFEventsCopy/guided-discovery/prompts -name "${PROMPT_NAME}.yaml" -type f | head -1)

if [ -z "$FOUND_PROMPT" ]; then
  echo "❌ Prompt not found: $PROMPT_NAME"
  echo ""
  echo "Available prompts:"
  find /mnt/d/YFEventsCopy/guided-discovery/prompts -name "*.yaml" -type f | while read prompt; do
    basename "$prompt" .yaml | sed 's/^/  - /'
  done | sort | head -20
  echo ""
  echo "Use /prompt <name> to run a specific prompt"
  exit 1
fi

# Find active context
CONTEXT_FILE=$(ls -t /mnt/d/YFEventsCopy/guided-discovery/contexts/active/*.json 2>/dev/null | head -1)

if [ -z "$CONTEXT_FILE" ]; then
  echo "❌ No active discovery context found"
  echo ""
  echo "Start a new discovery with: /discover \"your task description\""
  exit 1
fi

echo "📋 Running prompt: $PROMPT_NAME"
echo "📁 Context: $(basename "$CONTEXT_FILE")"
echo ""
```

## Loading prompt template...

```bash
# Extract prompt metadata
CATEGORY=$(grep "^category:" "$FOUND_PROMPT" | cut -d: -f2 | xargs)
COMPLEXITY=$(grep "^complexity:" "$FOUND_PROMPT" | cut -d: -f2 | xargs)
TARGETS=$(grep "^targets_uncertainties:" "$FOUND_PROMPT" | cut -d: -f2-)

echo "### Prompt Details"
echo "- **Category**: $CATEGORY"
echo "- **Complexity**: $COMPLEXITY"
echo "- **Targets**: $TARGETS"
echo ""

# Extract the template section
echo "### Prompt Template"
echo ""
echo "The following prompt will be executed:"
echo ""
echo '```'
# Extract template content between "template: |" and next top-level key
awk '/^template: \|/{flag=1; next} /^[a-z_]+:/{flag=0} flag && /^  /' "$FOUND_PROMPT" | sed 's/^  //'
echo '```'
echo ""

# Record execution in context (would be done by Claude after running)
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
echo "### Execution Record"
echo "This prompt execution will be recorded in the context:"
echo "- Timestamp: $TIMESTAMP"
echo "- Prompt: $PROMPT_NAME"
echo ""

echo "### Next Steps"
echo "1. Claude will execute this prompt against your codebase"
echo "2. Discoveries will be automatically added to your context"
echo "3. Uncertainties targeted by this prompt may be resolved"
echo "4. Run \`/context status\` to see updated progress"
echo ""

# Show what uncertainties this might help with
if [ -n "$TARGETS" ]; then
  echo "### Uncertainties This May Address"
  echo "$TARGETS" | tr -d '[]' | tr ',' '\n' | while read unc; do
    echo "- $unc" | xargs
  done
  echo ""
fi
```

## Example Usage

```bash
# Run a specific discovery prompt
/prompt php_architecture_explorer

# Run an analysis prompt
/prompt dependency_analyzer

# Run a validation prompt
/prompt test_coverage_analyzer
```

## Prompt Execution Flow

1. **Validation**: Check prompt exists and context is active
2. **Display**: Show prompt template and metadata
3. **Claude Executes**: Claude runs the prompt against codebase
4. **Context Update**: Discoveries added to context automatically
5. **Uncertainty Resolution**: Related uncertainties may be resolved
6. **Next Recommendations**: Claude suggests follow-up actions

## Integration with Uncertainty System

- Prompts declare which uncertainties they target
- Claude tracks which prompts have been run
- Discoveries from prompts automatically update context
- Claude can recommend specific prompts for unresolved uncertainties

## Custom Prompt Execution

For ad-hoc exploration, Claude can also:
- Create custom prompts on the fly
- Combine multiple prompt templates
- Adjust prompts based on current discoveries

Use `/chain --prompts prompt1,prompt2` to run multiple prompts in sequence.