# Uncertainty-Driven Discovery System Design Document

## Executive Summary

The Uncertainty-Driven Discovery System (UDDS) is a guided exploration framework for Claude Code that systematically resolves unknowns before implementation. It identifies uncertainties, prioritizes them by impact, and guides users through targeted discovery chains to build confidence before coding.

**Key Point**: This system is implemented entirely through Claude Code commands (markdown files) and data files (JSON/YAML). There is no running code - Claude follows instructions in command files to guide discovery.

## Core Philosophy

**"Measure what we don't know, then systematically resolve uncertainties until we're confident enough to build."**

### Key Principles

1. **Uncertainty First**: Start by identifying what we don't know
2. **Impact-Driven**: Prioritize unknowns by their potential to derail success
3. **Targeted Discovery**: Each prompt chain addresses specific uncertainties
4. **Confidence-Based Progress**: Advance when uncertainties are resolved, not when steps are complete
5. **Context Accumulation**: Build understanding incrementally in task-specific contexts

## System Architecture

### Overview

The system consists of Claude Code commands that:
1. Read and write JSON context files
2. Execute prompt templates from YAML files
3. Guide users through discovery chains
4. Track progress and confidence

### Directory Structure

```
guided-discovery/
├── .claude/commands/            # Claude Code command definitions (markdown)
│   ├── discover.md             # Start new discovery task
│   ├── chain.md                # Execute discovery chains
│   ├── context.md              # Manage context
│   └── uncertainty.md          # Explore uncertainties
├── contexts/                    # Task-specific context files (JSON)
│   ├── active/                 # Currently active discoveries
│   │   └── task-001-jwt-auth.json
│   └── archived/               # Completed discoveries
├── prompts/                    # Prompt library (YAML)
│   ├── discovery/              # Initial exploration prompts
│   ├── analysis/               # Deep analysis prompts
│   ├── validation/             # Verification prompts
│   └── synthesis/              # Solution design prompts
├── chains/                     # Pre-defined chain templates (YAML)
│   ├── common/                 # General-purpose chains
│   └── specialized/            # Domain-specific chains
├── uncertainties/              # Uncertainty definitions (YAML)
│   └── templates/              # Common uncertainty patterns
├── patterns/                   # Learned successful patterns (YAML/JSON)
│   └── proven/                 # Validated patterns
└── DESIGN.md                   # This document
```

## How It Works

### Workflow Overview

1. **User**: Runs `/discover "Add JWT authentication"`
2. **Claude**: 
   - Creates context JSON file
   - Analyzes task to identify uncertainties
   - Recommends first discovery chain
3. **User**: Runs `/chain auth_discovery`
4. **Claude**:
   - Loads chain definition (YAML)
   - Executes each prompt in sequence
   - Updates context with discoveries
   - Calculates confidence improvements
5. **Process repeats** until confidence is high enough

### No Code Execution

**Important**: This system does NOT involve:
- Python scripts running
- Background processes
- APIs or servers
- Automated execution

Instead, Claude:
- Reads markdown command files for instructions
- Loads and saves JSON/YAML data files
- Executes prompts within the conversation
- Updates context based on findings

## Core Data Models

### Context Schema (JSON)

Each discovery task has a context file that tracks everything:

```json
{
  "version": "1.0",
  "schema": "udds-v1",
  "discovery_id": "d7f8a9b2-4c6e-11ee-be56-0242ac120002",
  "task": {
    "description": "Add JWT authentication to Express app",
    "type": "feature|bugfix|refactor|analysis",
    "complexity": "simple|moderate|complex",
    "created_at": "2024-01-07T10:00:00Z"
  },
  "uncertainties": {
    "blocking": [
      {
        "id": "AUTH-001",
        "description": "Current authentication implementation pattern",
        "impact": "Cannot design JWT solution without understanding existing auth",
        "depends_on": [],
        "enables": ["AUTH-002", "SEC-001"],
        "status": "unresolved|partial|resolved",
        "confidence": 0.0,
        "discoveries": {}
      }
    ],
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
    "requirements": 0.0,
    "technical": 0.0,
    "implementation": 0.0,
    "overall": 0.0
  },
  "execution_state": {
    "phase": "discovery|analysis|design|implementation",
    "last_chain": null,
    "chains_executed": [],
    "next_recommended": []
  }
}
```

### Prompt Template (YAML)

```yaml
name: auth_pattern_finder
category: discovery
targets_uncertainties: ["AUTH-001"]
requires_context:
  - task.description
provides_context:
  - discoveries.architecture.auth_pattern

template: |
  # Authentication Pattern Discovery
  
  I need to understand the current authentication implementation.
  
  Task: {{context.task.description}}
  
  Please help me:
  1. Find authentication middleware/guards
  2. Identify auth strategy (session/JWT/OAuth)
  3. Locate user model and auth utilities
  
  Structure your findings as:
  
  ### AUTH_METHOD
  - Type: [session|jwt|oauth|other]
  - Location: [file:line]
  
  ### SESSION_MANAGEMENT
  - Storage: [memory|redis|database]
  - Configuration: [details]

output_parser:
  sections: [AUTH_METHOD, SESSION_MANAGEMENT]
```

### Chain Definition (YAML)

```yaml
name: auth_discovery
description: Comprehensive authentication system discovery
targets_uncertainties:
  primary: ["AUTH-001", "AUTH-002"]
  secondary: ["SEC-001"]

prompt_sequence:
  - prompt: auth_pattern_finder
    mandatory: true
    purpose: Understand current auth
    
  - prompt: session_analyzer
    condition: "discoveries.auth_pattern == 'session'"
    purpose: Deep dive into sessions
    
  - prompt: security_audit
    mandatory: false
    purpose: Security analysis

success_criteria:
  min_confidence: 0.75
  required_discoveries:
    - architecture.auth_pattern
    - session_management.method
```

## Command Specifications

### `/discover` Command

**Purpose**: Initialize a new discovery task

**What it does**:
1. Parses the task description
2. Creates a new context JSON file
3. Identifies initial uncertainties based on task type
4. Recommends the first discovery chain

**Example**:
```
User: /discover "Add JWT authentication to Express app"

Claude: ## 🎯 Starting Discovery: Add JWT Authentication

I've analyzed your task and identified key uncertainties:

**Blocking** (must resolve):
1. **AUTH-001**: Current authentication pattern
2. **AUTH-002**: Session management approach
3. **SEC-001**: Security requirements

**Created**: `contexts/active/task-001-jwt-auth-2024-01-07.json`

**Recommended**: Start with `auth_discovery` chain
Execute: `/chain auth_discovery`
```

### `/chain` Command

**Purpose**: Execute a discovery chain (sequence of prompts)

**What it does**:
1. Loads chain definition from YAML
2. Executes each prompt in sequence
3. Parses responses and updates context
4. Shows progress and confidence gains

**Example**:
```
User: /chain auth_discovery

Claude: ## 🔄 Executing Chain: auth_discovery

### [1/3] auth_pattern_finder
Searching for authentication patterns...

Found: Passport.js with express-session
- Location: /middleware/auth.js
- Strategy: Local authentication
- Session store: MongoDB

✅ AUTH-001: 85% resolved
Confidence: 25% → 60% (+35%)

### [2/3] session_analyzer
[Continues with next prompt...]

## 📊 Chain Complete
- Resolved: AUTH-001 (85%), AUTH-002 (100%)
- Overall confidence: 75%
- Next: `/chain security_analysis`
```

### `/context` Command

**Purpose**: View and manage discovery context

**Subcommands**:
- `status` - Overview of current discovery
- `uncertainties` - List all uncertainties
- `discoveries` - Show what's been found
- `history` - Execution history
- `reset` - Start fresh

**Example**:
```
User: /context status

Claude: ## 📊 Discovery Status

**Task**: Add JWT authentication
**Phase**: Analysis (Discovery 75% complete)
**Confidence**: 75%

### Uncertainties
✅ AUTH-001: Current auth pattern (85%)
✅ AUTH-002: Session management (100%)
🔄 SEC-001: Security requirements (40%)

### Next Steps
High priority: Resolve SEC-001
Recommended: `/chain security_analysis`
```

### `/uncertainty` Command

**Purpose**: Deep dive into specific uncertainties

**What it does**:
1. Shows detailed information about an uncertainty
2. Lists what's needed to resolve it
3. Suggests specific prompts or chains
4. Shows dependencies

**Example**:
```
User: /uncertainty SEC-001

Claude: ## 🔍 Uncertainty: SEC-001

**Description**: Security requirements for authentication
**Status**: 40% resolved
**Impact**: Shapes token validation and storage approach

### Resolution Criteria
Required:
- [ ] Compliance requirements (GDPR, etc)
- [x] Data sensitivity level
- [ ] Threat model

### Dependencies
- Depends on: None
- Enables: TOKEN-001, IMPL-001

### Suggested Actions
1. Run: `/prompt security_requirements_analyzer`
2. Or full chain: `/chain security_analysis`
```

## Implementation Approach

### Phase 1: Core Foundation
1. Create directory structure
2. Implement basic commands:
   - `/discover` - Create context and identify uncertainties
   - `/chain` - Execute simple chains
   - `/context status` - View current state
3. Create initial prompt templates (5-10 prompts)
4. Define 2-3 basic chains

### Phase 2: Enhanced Intelligence
1. Add uncertainty dependency tracking
2. Implement smart chain recommendations
3. Add confidence calculations
4. Create specialized prompts and chains

### Phase 3: Knowledge Building
1. Expand prompt library (20+ prompts)
2. Create specialized chains for common tasks
3. Add pattern recognition
4. Build uncertainty templates

### Phase 4: Polish & Learning
1. Add context versioning
2. Implement progress visualizations
3. Add pattern learning from successful discoveries
4. Create comprehensive documentation

## Key Benefits

1. **Systematic Discovery**: Never miss important unknowns
2. **Confidence-Based**: Know when you know enough
3. **Reusable Knowledge**: Patterns help future tasks
4. **Flexible**: Manual control with intelligent guidance
5. **No Infrastructure**: Just markdown and data files

## Success Metrics

1. **Uncertainty Resolution Rate**: % successfully resolved
2. **Confidence Accuracy**: Correlation with implementation success
3. **Discovery Efficiency**: Prompts needed to reach 80% confidence
4. **Pattern Reuse**: % of tasks benefiting from patterns
5. **User Satisfaction**: Fewer surprises during implementation

## Conclusion

The Uncertainty-Driven Discovery System provides structured exploration for complex codebases using only Claude Code's native capabilities. By tracking what we don't know and systematically resolving uncertainties, it builds confidence before implementation.

The system requires no infrastructure beyond Claude Code commands and data files, making it simple to implement and use while providing powerful guidance for software discovery tasks.