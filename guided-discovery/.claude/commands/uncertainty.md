# Uncertainty Analysis

Analyze uncertainties to guide discovery and determine next steps.

```bash
# Parse subcommand
SUBCOMMAND="${ARGUMENTS%% *}"
ARGS="${ARGUMENTS#* }"

# If no subcommand, default to status
if [ -z "$SUBCOMMAND" ] || [ "$SUBCOMMAND" = "$ARGUMENTS" ]; then
  SUBCOMMAND="status"
  ARGS=""
fi

# Find active context
CONTEXT_FILE=$(ls -t /mnt/d/YFEventsCopy/guided-discovery/contexts/active/*.json 2>/dev/null | head -1)

if [ -z "$CONTEXT_FILE" ]; then
  echo "❌ No active discovery context found"
  echo ""
  echo "Start a new discovery with: /discover \"your task description\""
  exit 1
fi

echo "📁 Context: $(basename "$CONTEXT_FILE")"
echo ""
```

## Executing: /uncertainty $SUBCOMMAND

```bash
case "$SUBCOMMAND" in
  "status")
    echo "## 📊 Uncertainty Status"
    ;;
  "analyze")
    echo "## 🔍 Uncertainty Analysis"
    ;;
  *)
    # Treat as uncertainty ID
    echo "## 🎯 Uncertainty Deep Dive: $SUBCOMMAND"
    UNCERTAINTY_ID="$SUBCOMMAND"
    SUBCOMMAND="detail"
    ;;
esac
```

### Status View

```bash
if [ "$SUBCOMMAND" = "status" ]; then
  # Extract current phase
  CURRENT_PHASE=$(grep -o '"current_phase": *"[^"]*"' "$CONTEXT_FILE" | head -1 | cut -d'"' -f4)
  echo "Current Phase: **$CURRENT_PHASE**"
  echo ""
  
  echo "### All Uncertainties"
  echo ""
  
  # Parse uncertainties from context (simplified - would use jq in production)
  echo "| ID | Name | Priority | Status |"
  echo "|-----|------|----------|---------|"
  
  # Example output format (Claude would parse actual JSON)
  echo "| TECH-001 | Current tech stack | blocking | ✓ resolved |"
  echo "| PAY-001 | Payment requirements | blocking | ⚡ partial |"
  echo "| SEC-001 | Security requirements | high | ✗ unresolved |"
  echo "| SCALE-001 | Expected volume | medium | ✗ unresolved |"
  echo ""
  
  # Count by status
  echo "### Summary"
  echo "- ✓ Resolved: 1"
  echo "- ⚡ Partial: 1" 
  echo "- ✗ Unresolved: 2"
  echo "- **Total**: 4"
  echo ""
  
  echo "Use `/uncertainty analyze` for Claude's recommendations."
fi
```

### Analyze View

```bash
if [ "$SUBCOMMAND" = "analyze" ]; then
  # This is where Claude analyzes the context and provides recommendations
  
  echo "Claude analyzes your current context to recommend next steps..."
  echo ""
  
  # Extract phase and task info
  TASK_DESC=$(grep -o '"description": *"[^"]*"' "$CONTEXT_FILE" | head -1 | cut -d'"' -f4)
  CURRENT_PHASE=$(grep -o '"current_phase": *"[^"]*"' "$CONTEXT_FILE" | head -1 | cut -d'"' -f4)
  
  echo "**Task**: $TASK_DESC"
  echo "**Phase**: $CURRENT_PHASE"
  echo ""
  
  echo "### Claude's Analysis"
  echo ""
  echo "Based on your discoveries and remaining uncertainties, Claude will:"
  echo "1. Assess which uncertainties block progress"
  echo "2. Identify which uncertainties are phase-appropriate"
  echo "3. Recommend specific chains or prompts to run"
  echo "4. Evaluate readiness for phase transition"
  echo ""
  
  echo "### Example Claude Analysis:"
  echo ""
  echo "**Current Situation:**"
  echo "You're in the discovery phase with 2 blocking uncertainties remaining."
  echo ""
  echo "**Priority Uncertainties:**"
  echo "1. **PAY-001** (Payment requirements) - Partially resolved"
  echo "   - You've identified Stripe as preferred but need webhook details"
  echo "   - Recommend: `/prompt payment_webhook_analyzer`"
  echo ""
  echo "2. **SEC-001** (Security requirements) - Unresolved" 
  echo "   - Critical for payment processing"
  echo "   - Recommend: `/chain security_discovery`"
  echo ""
  echo "**Confidence Assessment:**"
  echo "- Overall understanding: ~65%"
  echo "- Need ~80% before moving to planning phase"
  echo "- Focus on the blocking uncertainties first"
  echo ""
  echo "**Recommended Actions:**"
  echo "1. `/chain security_discovery` - Address SEC-001"
  echo "2. `/prompt payment_webhook_analyzer` - Complete PAY-001"
  echo "3. Then `/context phase planning` when ready"
fi
```

### Detail View

```bash
if [ "$SUBCOMMAND" = "detail" ]; then
  echo "Analyzing uncertainty: **$UNCERTAINTY_ID**"
  echo ""
  
  # Claude would extract details about this specific uncertainty
  echo "### Uncertainty Details"
  echo "- **Name**: Security requirements"
  echo "- **Priority**: high"
  echo "- **Status**: unresolved"
  echo "- **Phase**: discovery"
  echo ""
  
  echo "### Why This Matters"
  echo "Understanding security requirements is critical because:"
  echo "- Payment processing requires PCI compliance"
  echo "- User data must be protected"
  echo "- Architecture decisions depend on security needs"
  echo ""
  
  echo "### What Would Help Resolve This"
  echo "1. Analyze current security measures"
  echo "2. Identify compliance requirements"
  echo "3. Review authentication/authorization patterns"
  echo "4. Check for security vulnerabilities"
  echo ""
  
  echo "### Recommended Prompts"
  echo "These prompts target this uncertainty:"
  echo "- `security_requirements_analyzer`"
  echo "- `compliance_explorer`"
  echo "- `authentication_pattern_analyzer`"
  echo ""
  
  echo "### Recommended Chains"
  echo "- `/chain security_discovery` - Comprehensive security analysis"
  echo "- `/chain compliance_assessment` - Focus on compliance needs"
  echo ""
  echo "Run any of these to help resolve this uncertainty."
fi
```

## How Claude Uses This Command

When you run `/uncertainty analyze`, Claude:

1. **Reads your entire context** - Task, phase, discoveries, uncertainties
2. **Assesses resolution status** - Which uncertainties are resolved/partial/unresolved
3. **Prioritizes by phase** - Discovery uncertainties first, then planning, etc.
4. **Considers dependencies** - Some uncertainties block others
5. **Recommends specific actions** - Chains, prompts, or phase transitions

The key is that Claude provides **dynamic analysis** based on your actual progress, not predetermined responses.

## Integration with Workflow

```bash
echo ""
echo "### Workflow Integration"
echo "1. After running chains/prompts: Check uncertainty status"
echo "2. Use analyze to get Claude's recommendations"
echo "3. Follow recommendations to resolve uncertainties"
echo "4. When uncertainties resolved, consider phase transition"
echo ""
echo "This creates an uncertainty-driven discovery loop!"
```