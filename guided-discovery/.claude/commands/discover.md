# Start Discovery Task

I'll help you explore this task systematically using uncertainty-driven discovery.

## 🎯 Creating Discovery Context

Task: "$ARGUMENTS"

```bash
# Generate unique discovery ID
DISCOVERY_ID="task-$(date +%Y%m%d-%H%M%S)-$(echo "$ARGUMENTS" | md5sum | cut -c1-8)"
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Create safe filename
SAFE_NAME=$(echo "$ARGUMENTS" | tr ' ' '-' | tr -cd '[:alnum:]-' | cut -c1-30)
CONTEXT_FILE="/mnt/d/YFEventsCopy/guided-discovery/contexts/active/${DISCOVERY_ID:0:19}-${SAFE_NAME}.json"

echo "Discovery ID: $DISCOVERY_ID"
echo "Starting at: $TIMESTAMP"
```

## 📁 Creating Task Context

I'll create a context file to track our discovery journey.

```bash
# Create v2 context structure
cat > "$CONTEXT_FILE" << EOF
{
  "task_id": "$DISCOVERY_ID",
  "description": "$ARGUMENTS",
  "current_phase": "discovery",
  "phase_history": [
    {
      "phase": "discovery",
      "entered_at": "$TIMESTAMP",
      "reason": "Starting new task"
    }
  ],
  "uncertainties": {},
  "discoveries": {}
}
EOF

echo "✅ Created context file: $(basename "$CONTEXT_FILE")"
```

## 🔍 Analyzing Your Task

Now I'll analyze your task and identify the key uncertainties we need to resolve.

[Claude reads the task description and identifies uncertainties based on understanding]

### Initial Uncertainties Identified

[Claude populates the uncertainties in the context based on the task]

## 🚀 Recommended Next Steps

[Claude analyzes the uncertainties and recommends appropriate chains]

### Quick Commands:
- Check your progress: `/context status`
- Get my analysis: `/uncertainty analyze`
- Run a discovery chain: `/chain <recommended_chain>`

## 📊 Phase-Based Workflow

You're now in the **Discovery phase**. The system will guide you through:

1. **Discovery** → Understanding what exists
2. **Planning** → Designing the solution
3. **Implementation** → Building (outside UDDS)
4. **Validation** → Verifying requirements

I'll help you resolve uncertainties and know when you're ready to progress.