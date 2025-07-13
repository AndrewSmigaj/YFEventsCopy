# Start Discovery Task

I'll help you explore this task systematically using uncertainty-driven discovery.

## 🎯 Analyzing Task

Task: "$ARGUMENTS"

Let me identify what we need to discover before implementation.

```bash
# Generate unique discovery ID
DISCOVERY_ID=$(date +%Y%m%d-%H%M%S)-$(echo "$ARGUMENTS" | md5sum | cut -c1-8)
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Analyze task for keywords
TASK_LOWER=$(echo "$ARGUMENTS" | tr '[:upper:]' '[:lower:]')

# Determine task type
if echo "$TASK_LOWER" | grep -qE "fix|bug|error|issue"; then
  TASK_TYPE="bugfix"
elif echo "$TASK_LOWER" | grep -qE "refactor|improve|clean"; then
  TASK_TYPE="refactor"
elif echo "$TASK_LOWER" | grep -qE "analyze|investigate|understand"; then
  TASK_TYPE="analysis"
else
  TASK_TYPE="feature"
fi

# Determine complexity
COMPLEXITY="moderate"
if echo "$TASK_LOWER" | grep -qE "simple|basic|minor"; then
  COMPLEXITY="simple"
elif echo "$TASK_LOWER" | grep -qE "complex|major|large|migrate|integration"; then
  COMPLEXITY="complex"
fi

# Create safe filename
SAFE_NAME=$(echo "$ARGUMENTS" | tr ' ' '-' | tr -cd '[:alnum:]-' | cut -c1-30)
CONTEXT_FILE="/mnt/d/YFEventsCopy/guided-discovery/contexts/active/task-${DISCOVERY_ID:0:8}-${SAFE_NAME}.json"

echo "Discovery ID: $DISCOVERY_ID"
echo "Task Type: $TASK_TYPE"
echo "Complexity: $COMPLEXITY"
```

## 🔍 Identifying Initial Uncertainties

Based on your task description, I'll identify the key unknowns we need to resolve.

```bash
# Identify uncertainties based on keywords
UNCERTAINTIES=""

# Check for authentication-related uncertainties
if echo "$TASK_LOWER" | grep -qE "auth|login|jwt|token|session|oauth"; then
  echo "Found authentication-related task"
  UNCERTAINTIES="${UNCERTAINTIES}AUTH "
fi

# Check for API-related uncertainties
if echo "$TASK_LOWER" | grep -qE "api|endpoint|rest|graphql|route"; then
  echo "Found API-related task"
  UNCERTAINTIES="${UNCERTAINTIES}API "
fi

# Check for database-related uncertainties
if echo "$TASK_LOWER" | grep -qE "database|db|sql|schema|migration|query"; then
  echo "Found database-related task"
  UNCERTAINTIES="${UNCERTAINTIES}DB "
fi

# Check for architecture uncertainties
if echo "$TASK_LOWER" | grep -qE "architect|structure|pattern|design"; then
  echo "Found architecture-related task"
  UNCERTAINTIES="${UNCERTAINTIES}ARCH "
fi

# Always include general architecture uncertainty
if [ -z "$UNCERTAINTIES" ]; then
  UNCERTAINTIES="ARCH TECH"
fi

echo "Uncertainty categories: $UNCERTAINTIES"
```

## 📁 Creating Discovery Context

Now I'll create a context file to track our discovery progress.

```bash
# Create context JSON
cat > "$CONTEXT_FILE" << EOF
{
  "version": "1.0",
  "schema": "udds-v1",
  "discovery_id": "$DISCOVERY_ID",
  "task": {
    "description": "$ARGUMENTS",
    "type": "$TASK_TYPE",
    "complexity": "$COMPLEXITY",
    "created_at": "$TIMESTAMP"
  },
  "uncertainties": {
    "blocking": [],
    "high": [],
    "medium": [],
    "low": []
  },
  "discoveries": {
    "architecture": {},
    "constraints": {},
    "dependencies": {},
    "decisions": {}
  },
  "confidence": {
    "requirements": 0.25,
    "technical": 0.25,
    "implementation": 0.25,
    "overall": 0.25
  },
  "execution_state": {
    "phase": "discovery",
    "last_chain": null,
    "chains_executed": [],
    "next_recommended": []
  }
}
EOF

echo "Created context file: $CONTEXT_FILE"
```

## 🎯 Initial Uncertainties

Based on my analysis, here are the key uncertainties we need to resolve:

### Blocking Uncertainties (Must Resolve)

```bash
# Add specific uncertainties based on detected patterns
if [[ $UNCERTAINTIES == *"AUTH"* ]]; then
  echo "1. **AUTH-001**: Current authentication implementation"
  echo "   - How is authentication currently handled?"
  echo "   - What auth patterns are in use?"
  echo ""
  echo "2. **AUTH-002**: Session/token management"
  echo "   - How are user sessions managed?"
  echo "   - What are the security requirements?"
fi

if [[ $UNCERTAINTIES == *"API"* ]]; then
  echo "1. **API-001**: Current API architecture"
  echo "   - What API patterns are in use?"
  echo "   - How are endpoints structured?"
  echo ""
  echo "2. **API-002**: Data validation and security"
  echo "   - How is input validated?"
  echo "   - What security measures exist?"
fi

if [[ $UNCERTAINTIES == *"DB"* ]]; then
  echo "1. **DB-001**: Database schema and patterns"
  echo "   - What's the current data model?"
  echo "   - What database patterns are used?"
  echo ""
  echo "2. **DB-002**: Data access patterns"
  echo "   - How is data accessed?"
  echo "   - What ORMs or query builders are used?"
fi

# Always include architecture uncertainty
echo "**ARCH-001**: Overall architecture pattern"
echo "   - What's the high-level architecture?"
echo "   - What patterns and conventions are used?"
```

### High Priority Uncertainties

- **TECH-001**: Technology stack and dependencies
- **IMPL-001**: Implementation constraints and requirements

## 🚀 Recommended Next Step

```bash
# Recommend appropriate discovery chain based on uncertainties
if [[ $UNCERTAINTIES == *"AUTH"* ]]; then
  echo "Based on the authentication-related uncertainties, I recommend starting with:"
  echo ""
  echo "**\`auth_discovery\`** chain"
  echo "This will help us understand:"
  echo "- Current authentication implementation"
  echo "- Session/token management"
  echo "- Security requirements"
  echo ""
  echo "Execute with: \`/chain auth_discovery\`"
elif [[ $UNCERTAINTIES == *"API"* ]]; then
  echo "Based on the API-related uncertainties, I recommend starting with:"
  echo ""
  echo "**\`api_discovery\`** chain"
  echo "This will help us understand:"
  echo "- API architecture and patterns"
  echo "- Endpoint structure"
  echo "- Validation and security"
  echo ""
  echo "Execute with: \`/chain api_discovery\`"
elif [[ $UNCERTAINTIES == *"DB"* ]]; then
  echo "Based on the database-related uncertainties, I recommend starting with:"
  echo ""
  echo "**\`database_discovery\`** chain"
  echo "This will help us understand:"
  echo "- Database schema"
  echo "- Data access patterns"
  echo "- Query optimization"
  echo ""
  echo "Execute with: \`/chain database_discovery\`"
else
  echo "I recommend starting with a general discovery:"
  echo ""
  echo "**\`general_discovery\`** chain"
  echo "This will help us understand:"
  echo "- Overall architecture"
  echo "- Technology stack"
  echo "- Key components"
  echo ""
  echo "Execute with: \`/chain general_discovery\`"
fi
```

## 📊 Discovery Tracking

Your discovery context has been created and saved to:
`contexts/active/task-${DISCOVERY_ID:0:8}-${SAFE_NAME}.json`

Current confidence: **25%** (Starting baseline)

Next steps:
1. Run the recommended discovery chain
2. Or explore specific uncertainties with `/uncertainty [ID]`
3. Check progress anytime with `/context status`