# Uncertainty-Driven Discovery System (UDDS)

A Claude Code command system for systematically resolving unknowns before implementation. UDDS helps you build confidence through guided discovery chains, ensuring you understand requirements, architecture, and constraints before writing code.

## 🚀 Quick Start

Get started with UDDS in 3 minutes:

```bash
# 1. Start a new discovery task
/discover Add JWT authentication to Express app

# 2. Run the general discovery chain
/chain general_discovery

# 3. Check your progress
/context status

# 4. Continue with specialized discovery
/chain auth_discovery
```

## 📚 Core Concepts

### Uncertainties
Unknowns that block or impact your implementation:
- **Blocking**: Must resolve before proceeding (e.g., current auth method)
- **High**: Significantly impacts approach (e.g., security requirements)
- **Medium**: Affects design decisions (e.g., user model structure)
- **Low**: Nice to know but not critical

### Discovery Chains
Sequences of prompts that systematically resolve uncertainties:
- **general_discovery**: Initial exploration for any task
- **auth_discovery**: Deep dive into authentication systems
- **tech_analysis**: Technology stack and infrastructure analysis

### Context
Persistent storage of discoveries and confidence tracking:
- Tracks resolved/unresolved uncertainties
- Stores technical findings
- Calculates confidence scores
- Recommends next actions

## 🛠️ Command Reference

### `/discover <task description>`
Start a new discovery task and create initial context.

```bash
# Examples
/discover Implement OAuth2 with Google
/discover Migrate from MongoDB to PostgreSQL
/discover Add real-time notifications with WebSockets
```

### `/chain <chain_name> [task_id]`
Execute a discovery chain to resolve uncertainties.

```bash
# Run general discovery for current task
/chain general_discovery

# Run auth discovery for specific task
/chain auth_discovery task-20240107-jwt-auth

# Available chains:
# - general_discovery: Basic exploration
# - auth_discovery: Authentication deep dive
# - tech_analysis: Technology stack analysis
# - security_analysis: Security requirements
# - performance_analysis: Performance patterns
```

### `/context <subcommand> [task_id]`
Manage and view discovery context.

```bash
# View current status
/context status

# List all uncertainties
/context uncertainties

# Show discoveries made
/context discoveries

# View execution history
/context history

# List all tasks
/context list

# Reset context (careful!)
/context reset
```

### `/uncertainty <uncertainty_id> [task_id]`
Deep dive into a specific uncertainty.

```bash
# Examples
/uncertainty AUTH-001
/uncertainty SEC-001 task-20240107-jwt-auth
```

## 🎯 Workflow Examples

### Example 1: Adding JWT Authentication

```bash
# 1. Start discovery
/discover Add JWT authentication to Express app

# Output: Created task-20240107-143052-a1b2c3d4
# Identified uncertainties: AUTH-001, AUTH-002, SEC-001, TECH-001, USER-001

# 2. Run general discovery
/chain general_discovery

# Output: Discovered Express 4.18.2, MVC architecture, Passport.js
# Confidence: 35% → 63%

# 3. Check what we still need to know
/context uncertainties

# Output: 
# ❌ AUTH-002: Session persistence (blocking)
# ⚠️  SEC-001: Security requirements (40% resolved)

# 4. Run specialized auth discovery
/chain auth_discovery

# Output: Found session storage in MongoDB, 15-min timeout
# Confidence: 63% → 78%

# 5. Resolve security requirements
/uncertainty SEC-001

# 6. Once confident (>80%), proceed to implementation
# All key decisions documented in context!
```

### Example 2: Database Migration

```bash
# 1. Start discovery
/discover Migrate user data from MongoDB to PostgreSQL

# 2. Understand current state
/chain general_discovery
/chain tech_analysis

# 3. Analyze data patterns
/chain data_migration_analysis

# 4. Check migration complexity
/context status

# 5. Review specific concerns
/uncertainty DATA-001  # Current schema structure
/uncertainty MIG-001   # Migration strategy options
```

### Example 3: Performance Optimization

```bash
# 1. Start discovery
/discover Optimize API response times

# 2. Profile current performance
/chain performance_analysis

# 3. Identify bottlenecks
/context discoveries

# 4. Deep dive into specific issues
/uncertainty PERF-001  # Current bottlenecks
/uncertainty CACHE-001 # Caching strategy
```

## 💡 Best Practices

### 1. Start Broad, Then Focus
Always begin with `general_discovery` before specialized chains:
```bash
/discover <task>
/chain general_discovery
# Then run specialized chains based on findings
```

### 2. Track Confidence
Don't proceed to implementation until confidence is high:
- **< 60%**: Continue discovery
- **60-80%**: Focus on blocking uncertainties
- **> 80%**: Ready to implement

### 3. Use Task Context
UDDS maintains separate contexts per task:
```bash
# Work on multiple tasks
/discover Task A
/chain general_discovery

/discover Task B
/chain general_discovery

# Switch between contexts
/context status task-a-id
/context status task-b-id
```

### 4. Document Decisions
As you resolve uncertainties, decisions are recorded:
```bash
/context discoveries
# Shows all findings and decisions made
```

### 5. Leverage Dependencies
Some uncertainties enable others:
```bash
/uncertainty AUTH-001
# Resolving this enables AUTH-002, SEC-001, USER-001
```

## 🔧 Troubleshooting

### "No active discovery task"
```bash
# List all tasks
/context list

# Set active task
/discover <new task>
# OR work with existing:
/chain <chain_name> <task_id>
```

### "Chain not found"
```bash
# Check available chains
ls chains/common/
ls chains/specialized/

# Chains are YAML files in these directories
```

### "Low confidence after multiple chains"
```bash
# Check blocking uncertainties
/context uncertainties

# Focus on unresolved blockers
/uncertainty <blocking_id>

# Run targeted chains
/chain targeted_discovery
```

### "Context file corrupted"
```bash
# Backup current context
cp contexts/active/task-*.json contexts/backup/

# Reset if needed
/context reset <task_id>
```

## 📁 System Structure

```
guided-discovery/
├── .claude/commands/      # Command implementations
├── prompts/              # Discovery prompt templates
├── chains/               # Chain definitions
├── contexts/             # Task contexts and discoveries
├── uncertainties/        # Uncertainty templates
└── DESIGN.md            # System design documentation
```

## 🎨 Extending UDDS

### Adding New Prompts
Create YAML files in `prompts/discovery/`:
```yaml
name: my_custom_prompt
targets_uncertainties: ["CUSTOM-001"]
template: |
  # Custom Discovery Prompt
  Analyze the codebase for...
```

### Adding New Chains
Create YAML files in `chains/common/` or `chains/specialized/`:
```yaml
name: my_chain
prompt_sequence:
  - prompt: my_custom_prompt
    order: 1
    mandatory: true
```

### Adding Uncertainty Templates
Create YAML files in `uncertainties/templates/`:
```yaml
uncertainties:
  - id: CUSTOM-001
    name: Custom Uncertainty
    resolution_criteria:
      required:
        some_finding:
          description: What to find
```

## 📈 Confidence Scoring

UDDS calculates confidence based on:
- **Resolved uncertainties**: Each resolved uncertainty increases confidence
- **Discovery completeness**: Required vs optional findings
- **Chain execution**: Successfully completed chains boost confidence

Formula:
```
Overall Confidence = weighted_avg(
  requirements_confidence * 0.3,
  technical_confidence * 0.3,
  implementation_confidence * 0.4
)
```

## 🤝 Contributing

To improve UDDS:
1. Identify common uncertainty patterns
2. Create new prompt templates
3. Design specialized chains
4. Share successful discovery workflows

## 📖 Additional Resources

- **Design Document**: See `DESIGN.md` for system architecture
- **Example Contexts**: Check `contexts/examples/` for more scenarios
- **Prompt Writing**: Review `prompts/README.md` for template guidelines

---

Remember: **Resolve uncertainties before implementation**. Time spent in discovery saves debugging time later!