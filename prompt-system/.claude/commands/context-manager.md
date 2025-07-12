# Context Manager - Chain Context Operations

I'll help manage the context for your prompt chain execution.

## 📂 Available Operations

### 1. Create New Context
To start a new task chain with a fresh context file.

### 2. Load Existing Context
To continue work on an existing chain.

### 3. Update Context
To add discoveries, decisions, or update confidence scores.

### 4. Show Context Summary
To see the current state of your chain execution.

### 5. List All Contexts
To see all available context files.

## 🔧 Context File Management

When creating a new context, I will:
- Generate a unique chain ID
- Create a timestamped filename
- Initialize from the simplified template
- Set initial confidence scores based on task complexity

The context file will be created at:
`contexts/chain-{ID}-{task-slug}-{timestamp}.json`

## 📊 Context Structure

The simplified context tracks:
- **Task**: What we're trying to accomplish
- **Phase**: Current phase in the uncertainty-driven process
- **Chains**: History of prompt chains executed
- **Confidence**: Multi-dimensional confidence scores
- **Discoveries**: What we've learned
- **Decisions**: Key choices made

## 🎯 Typical Usage

```
# Start new task
/context-manager create "Add JWT authentication"
→ Created: contexts/chain-001-jwt-auth-2025-01-15-14-30.json

# Check current context
/context-manager show
→ Shows current phase, confidence, and recent discoveries

# Update after discoveries
/context-manager update discoveries "Found existing session auth in /routes/auth.js"

# List all contexts
/context-manager list
→ Shows all context files with status
```

## 💡 Context Health

I monitor context health by tracking:
- Size (keeping under 10KB for efficiency)
- Completeness (required fields populated)
- Consistency (no conflicting information)
- Age (warning if context is stale)

What context operation would you like me to perform?