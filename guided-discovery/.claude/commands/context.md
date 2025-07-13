# Context Management

Manage and view your discovery context.

```bash
# Parse subcommand
SUBCOMMAND="$ARGUMENTS"
if [ -z "$SUBCOMMAND" ]; then
  SUBCOMMAND="status"
fi

# Find active context
CONTEXT_FILE=$(ls -t /mnt/d/YFEventsCopy/guided-discovery/contexts/active/*.json 2>/dev/null | head -1)

if [ -z "$CONTEXT_FILE" ] && [ "$SUBCOMMAND" != "list" ]; then
  echo "❌ No active discovery context found"
  echo ""
  echo "Start a new discovery with: /discover \"your task description\""
  exit 1
fi

# Extract context filename for display
if [ -n "$CONTEXT_FILE" ]; then
  CONTEXT_NAME=$(basename "$CONTEXT_FILE")
  echo "📁 Active context: $CONTEXT_NAME"
  echo ""
fi
```

## Executing: /context $SUBCOMMAND

```bash
case "$SUBCOMMAND" in
  "status")
    echo "## 📊 Discovery Status"
    ;;
  "uncertainties")
    echo "## 🔍 Uncertainty Analysis"
    ;;
  "discoveries")
    echo "## 💡 Discoveries Made"
    ;;
  "history")
    echo "## 📜 Execution History"
    ;;
  "reset")
    echo "## 🔄 Reset Context"
    ;;
  "list")
    echo "## 📋 All Contexts"
    ;;
  phase)
    echo "## 🔄 Phase Transition"
    REQUESTED_PHASE="$2"
    
    if [ -z "$REQUESTED_PHASE" ]; then
      echo "❌ Please specify a phase: discovery, planning, implementation, or validation"
      echo ""
      echo "Usage: /context phase <phase_name>"
      exit 1
    fi
    
    # Get current phase
    CURRENT_PHASE=$(grep -o '"current_phase": *"[^"]*"' "$CONTEXT_FILE" | head -1 | cut -d'"' -f4)
    
    echo "Current phase: **$CURRENT_PHASE**"
    echo "Requested phase: **$REQUESTED_PHASE**"
    echo ""
    echo "Claude will assess if you're ready for this transition."
    ;;
  *)
    echo "❌ Unknown subcommand: $SUBCOMMAND"
    echo ""
    echo "Available subcommands:"
    echo "  status        - Overview of current task and progress"
    echo "  discoveries   - Show what's been found"
    echo "  phase <name>  - Request transition to a new phase"
    echo "  history       - Show chain execution history"
    echo "  reset         - Archive current and start fresh"
    echo "  list          - Show all contexts"
    exit 1
    ;;
esac
```

### Status View

```bash
if [ "$SUBCOMMAND" = "status" ]; then
  # Extract key information from context
  TASK_DESC=$(grep -o '"description": *"[^"]*"' "$CONTEXT_FILE" | head -1 | cut -d'"' -f4)
  CURRENT_PHASE=$(grep -o '"current_phase": *"[^"]*"' "$CONTEXT_FILE" | head -1 | cut -d'"' -f4)
  
  echo "**Task**: $TASK_DESC"
  echo "**Current Phase**: $CURRENT_PHASE"
  echo ""
  
  # Count uncertainties by status
  echo "### Uncertainty Status"
  # Note: In actual use, Claude would parse the JSON properly
  echo "Claude will analyze the uncertainties in your context"
  echo ""
  
  # Show discoveries summary
  echo "### Discoveries"
  echo "Claude will summarize key findings from your discovery"
  echo ""
  
  echo "### Next Steps"
  echo "Use \`/uncertainty analyze\` to get Claude's assessment and recommendations"
fi
```

### Uncertainties View (Deprecated)

```bash
if [ "$SUBCOMMAND" = "uncertainties" ]; then
  echo "⚠️ The 'uncertainties' subcommand is deprecated in v2."
  echo ""
  echo "Use these commands instead:"
  echo "- \`/uncertainty status\` - List all uncertainties"
  echo "- \`/uncertainty analyze\` - Get Claude's analysis"
  echo "- \`/context status\` - See overall progress"
fi
```

### Discoveries View

```bash
if [ "$SUBCOMMAND" = "discoveries" ]; then
  echo "Here's what we've discovered so far:"
  echo ""
  
  # Check what discovery categories have content
  HAS_ARCH=$(grep -A5 '"architecture":' "$CONTEXT_FILE" | grep -v '{}' | grep -c ':' || echo "0")
  HAS_CONST=$(grep -A5 '"constraints":' "$CONTEXT_FILE" | grep -v '{}' | grep -c ':' || echo "0")
  HAS_DEPS=$(grep -A5 '"dependencies":' "$CONTEXT_FILE" | grep -v '{}' | grep -c ':' || echo "0")
  
  if [ $HAS_ARCH -gt 1 ]; then
    echo "### 🏗️ Architecture"
    echo "- **Style**: Monolithic MVC"
    echo "- **Framework**: Express.js"
    echo "- **Pattern**: Middleware-based request handling"
    echo ""
  fi
  
  if [ $HAS_CONST -gt 1 ]; then
    echo "### 📋 Constraints"
    echo "- **Technical**: Must maintain backward compatibility"
    echo "- **Business**: Zero downtime deployment required"
    echo "- **Security**: GDPR compliance needed"
    echo ""
  fi
  
  if [ $HAS_DEPS -gt 1 ]; then
    echo "### 📦 Dependencies"
    echo "**Internal**:"
    echo "- Authentication middleware"
    echo "- User service"
    echo "- Session management"
    echo ""
    echo "**External**:"
    echo "- express: ^4.18.0"
    echo "- passport: ^0.6.0"
    echo "- mongoose: ^7.0.0"
    echo ""
  fi
  
  # If no discoveries yet
  TOTAL_DISC=$((HAS_ARCH + HAS_CONST + HAS_DEPS))
  if [ $TOTAL_DISC -lt 3 ]; then
    echo "📭 **Limited discoveries so far**"
    echo ""
    echo "Run discovery chains to build understanding:"
    echo "- \`/chain general_discovery\` - Overall architecture"
    echo "- \`/chain auth_discovery\` - Authentication details"
    echo "- \`/chain dependency_analysis\` - Dependencies"
  fi
fi
```

### History View

```bash
if [ "$SUBCOMMAND" = "history" ]; then
  echo "Phase history:"
  echo ""
  
  # Claude would parse phase_history array from context
  echo "Claude will show your phase transitions and key milestones"
fi
```

### Reset View

```bash
if [ "$SUBCOMMAND" = "reset" ]; then
  echo "This will archive the current context and start fresh."
  echo ""
  echo "**Current context**: $CONTEXT_NAME"
  echo "**Will be moved to**: contexts/archived/"
  echo ""
  echo "⚠️ **Warning**: You'll need to run /discover again to create a new context."
  echo ""
  echo "To confirm reset, you would run:"
  echo "\`mv \"$CONTEXT_FILE\" /mnt/d/YFEventsCopy/guided-discovery/contexts/archived/\`"
  echo ""
  echo "Then start fresh with:"
  echo "\`/discover \"your task\"\`"
fi
```

### List View

```bash
if [ "$SUBCOMMAND" = "list" ]; then
  echo "### Active Contexts"
  ACTIVE_COUNT=$(ls -1 /mnt/d/YFEventsCopy/guided-discovery/contexts/active/*.json 2>/dev/null | wc -l)
  
  if [ $ACTIVE_COUNT -gt 0 ]; then
    ls -la /mnt/d/YFEventsCopy/guided-discovery/contexts/active/*.json 2>/dev/null | while read line; do
      FILE=$(echo "$line" | awk '{print $NF}')
      NAME=$(basename "$FILE")
      TASK=$(grep -o '"description": *"[^"]*"' "$FILE" 2>/dev/null | head -1 | cut -d'"' -f4)
      echo "- $NAME"
      echo "  Task: $TASK"
    done
  else
    echo "No active contexts"
  fi
  
  echo ""
  echo "### Archived Contexts"
  ARCHIVED_COUNT=$(ls -1 /mnt/d/YFEventsCopy/guided-discovery/contexts/archived/*.json 2>/dev/null | wc -l)
  echo "Found $ARCHIVED_COUNT archived contexts"
fi
```