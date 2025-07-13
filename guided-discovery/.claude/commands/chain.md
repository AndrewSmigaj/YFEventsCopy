# Execute Discovery Chain

Run a predefined chain of prompts to systematically explore and resolve uncertainties.

## 🔄 Loading Chain: $ARGUMENTS

```bash
# Parse chain arguments
if [[ "$ARGUMENTS" =~ ^--prompts ]]; then
  # Custom chain mode
  CUSTOM_MODE=true
  PROMPTS_LIST=$(echo "$ARGUMENTS" | sed 's/--prompts *//' | tr ',' ' ')
  CHAIN_NAME="custom_chain"
  echo "🔧 Custom chain mode"
  echo "Prompts to execute: $PROMPTS_LIST"
else
  # Predefined chain mode
  CUSTOM_MODE=false
  CHAIN_NAME="$ARGUMENTS"
  CHAIN_DIR="/mnt/d/YFEventsCopy/guided-discovery/chains"
  
  # Find chain definition file
  CHAIN_FILE=""
  if [ -f "$CHAIN_DIR/common/${CHAIN_NAME}.yaml" ]; then
    CHAIN_FILE="$CHAIN_DIR/common/${CHAIN_NAME}.yaml"
  elif [ -f "$CHAIN_DIR/specialized/${CHAIN_NAME}.yaml" ]; then
    CHAIN_FILE="$CHAIN_DIR/specialized/${CHAIN_NAME}.yaml"
  fi
  
  if [ -z "$CHAIN_FILE" ] || [ ! -f "$CHAIN_FILE" ]; then
    echo "❌ Error: Chain '$CHAIN_NAME' not found"
    echo ""
    echo "Available chains:"
    ls -1 $CHAIN_DIR/common/*.yaml 2>/dev/null | xargs -n1 basename | sed 's/\.yaml$//' | sed 's/^/  - /'
    ls -1 $CHAIN_DIR/specialized/*.yaml 2>/dev/null | xargs -n1 basename | sed 's/\.yaml$//' | sed 's/^/  - /'
    echo ""
    echo "Or create a custom chain: /chain --prompts prompt1,prompt2,prompt3"
    exit 1
  fi
  
  echo "Found chain definition: $CHAIN_FILE"
fi
```

## 📋 Chain Details

```bash
if [ "$CUSTOM_MODE" = false ]; then
  # Extract chain metadata for predefined chains
  CHAIN_DESC=$(grep "^description:" "$CHAIN_FILE" | sed 's/description: *//')
  echo "**Description**: $CHAIN_DESC"
  echo ""
  
  # Extract targets phase
  TARGETS_PHASE=$(grep "^targets_phase:" "$CHAIN_FILE" | sed 's/targets_phase: *//')
  if [ -n "$TARGETS_PHASE" ]; then
    echo "**Target Phase**: $TARGETS_PHASE"
  fi
  
  # List prompts in chain
  echo "**Prompts**:"
  grep "  - " "$CHAIN_FILE" | head -20 | sed 's/  - /• /' 
  echo ""
else
  # Custom chain details
  echo "**Type**: Custom chain"
  echo "**Prompts**: $(echo $PROMPTS_LIST | wc -w) prompts specified"
  echo ""
fi
```

## 🚀 Executing Chain

Let me load the current context and execute each prompt in the chain.

```bash
# Find most recent context file
CONTEXT_FILE=$(ls -t /mnt/d/YFEventsCopy/guided-discovery/contexts/active/*.json 2>/dev/null | head -1)

if [ -z "$CONTEXT_FILE" ]; then
  echo "❌ Error: No active context found"
  echo "Please run /discover first to create a discovery context"
  exit 1
fi

echo "Using context: $(basename "$CONTEXT_FILE")"

# Extract current phase
CURRENT_PHASE=$(grep -o '"current_phase": *"[^"]*"' "$CONTEXT_FILE" | head -1 | cut -d'"' -f4)
echo "Current phase: $CURRENT_PHASE"
echo ""
```

### Prompt Execution

Claude will execute each prompt in the chain sequence:

```bash
# Get prompts list
if [ "$CUSTOM_MODE" = false ]; then
  # Parse prompts from chain file
  PROMPTS=$(grep "  - " "$CHAIN_FILE" | sed 's/  - //')
  PROMPT_COUNT=$(echo "$PROMPTS" | wc -l)
else
  # Use custom prompts list
  PROMPTS="$PROMPTS_LIST"
  PROMPT_COUNT=$(echo $PROMPTS_LIST | wc -w)
fi

echo "📝 Prompts to execute ($PROMPT_COUNT total):"
echo "$PROMPTS" | tr ' ' '\n' | nl -w2 -s". "
echo ""

# Record chain execution start
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
echo "Starting at: $TIMESTAMP"
```

---

### Execution Process

Claude will now execute each prompt in sequence:

```bash
# Note: This shows what Claude will do for each prompt
echo "🚀 Chain execution process:"
echo "1. Load each prompt template"
echo "2. Execute prompt against codebase"
echo "3. Extract structured discoveries"
echo "4. Update context with findings"
echo "5. Update uncertainty status"
echo ""
```

**What happens during execution:**
- Claude reads each prompt template
- Analyzes the codebase based on prompt instructions
- Extracts discoveries in structured format
- Updates the context JSON with new findings
- Marks relevant uncertainties as resolved/partial

### 📊 Context Updates

```bash
echo "📝 Context will be updated with:"
echo "- New discoveries from each prompt"
echo "- Uncertainty status changes"
echo "- Chain execution history"
echo ""

# Show example of what gets recorded
echo "Example discovery structure:"
echo '```json'
echo '"discoveries": {'
echo '  "architecture": {'
echo '    "pattern": "MVC",'
echo '    "framework": "Express"'
echo '  },'
echo '  "authentication": {'
echo '    "type": "session-based",'
echo '    "middleware": "passport"'
echo '  }'
echo '}'
echo '```'
```

---

## 📈 Chain Completion

After Claude executes all prompts:

```bash
echo "## ✅ Chain Execution Complete"
echo ""
echo "**Chain**: $CHAIN_NAME"
echo "**Prompts executed**: $PROMPT_COUNT"
echo "**Phase**: $CURRENT_PHASE"
echo ""
echo "### What Was Updated"
echo "- ✓ Discoveries added to context"
echo "- ✓ Uncertainties status updated"
echo "- ✓ Chain execution recorded"
echo ""
```

### 🎯 Uncertainty Impact

```bash
echo "### Uncertainty Updates"
echo ""
echo "Claude will analyze which uncertainties were addressed by this chain."
echo "The context now contains updated uncertainty statuses."
echo ""
echo "To see detailed uncertainty analysis:"
echo "- Run: /uncertainty analyze"
echo "- Run: /context status"
```

### 💡 Viewing Discoveries

To see what was discovered:

```bash
echo "View your discoveries with:"
echo "- /context discoveries - See all findings"
echo "- /context status - See overall progress"
echo "- /uncertainty analyze - Get Claude's assessment"
```

### 🎯 Next Steps

```bash
echo "### What to do next:"
echo ""
echo "1. **Check your progress**: /context status"
echo "2. **Analyze uncertainties**: /uncertainty analyze"
echo "3. **Claude will recommend** next chains or phase transition"
echo ""
echo "Claude reads your updated context and provides tailored recommendations."
```

### 💾 Chain Execution Record

```bash
# Record chain execution
END_TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

echo "### Execution recorded:"
echo '```json'
echo '{'
echo '  "chain": "'$CHAIN_NAME'",'
echo '  "timestamp": "'$TIMESTAMP'",'
echo '  "prompts_run": ['$(echo "$PROMPTS" | tr '\n' ',' | sed 's/,$//')']'
echo '}'
echo '```'
echo ""
echo "✓ Chain execution complete"
echo "✓ Context updated with discoveries"
echo ""
echo "Run /context status to see your updated progress."
```