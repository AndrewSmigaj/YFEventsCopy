# Guided Discovery: A Context Engineering System

## What is Context Engineering?

Context engineering is the discipline of systematically providing an AI with all the information, tools, and structure it needs to successfully complete complex tasks. Unlike simple prompting, context engineering builds comprehensive understanding through:

- **Dynamic information gathering** - Building knowledge systematically
- **State management** - Preserving discoveries across interactions  
- **Structured reasoning** - Making thought processes explicit
- **Gap identification** - Knowing what you don't know

The Guided Discovery System implements context engineering through **Uncertainty-Driven Development** - a methodology that transforms unknown requirements into comprehensive context.

## Core Innovation: Uncertainties as Context Gaps

Traditional AI interactions fail when the AI lacks critical context. This system makes context gaps explicit through **uncertainties** - specific pieces of missing information that block progress.

```
CONTEXT ENGINEERING FLOW:
┌─────────────┐     ┌──────────────┐     ┌────────────┐     ┌──────────────┐
│  Identify   │────▶│  Prioritize  │────▶│   Build    │────▶│   Apply      │
│Context Gaps │     │  by Impact   │     │  Context   │     │  Context     │
│(Uncertainties)     │(Blocking/High)│     │(Discovery) │     │(Implementation)
└─────────────┘     └──────────────┘     └────────────┘     └──────────────┘
      ▲                                                              │
      └──────────────────── New context gaps found ─────────────────┘
```

Every uncertainty represents missing context. Resolving uncertainties builds comprehensive understanding.

## How Context Flows Through the System

### 1. Context Initialization
```
Human: /discover Create a deployment script for Digital Ocean
AI: [Creates initial context with identified gaps]
```

**What happens:**
- Task context established (what we're building)
- Initial uncertainties identified (what context is missing)
- Context file created to track everything

### 2. Context Building Through Discovery
```
Human: /chain tech_analysis 20250727-154946
AI: [Executes prompts that gather specific context]
```

**Context accumulation:**
- Each prompt targets specific uncertainties
- Discoveries add to context: technical stack, dependencies, infrastructure
- Uncertainties resolved = context gaps filled

### 3. Context Synthesis and Application
```
Human: /context phase planning
AI: [Synthesizes discoveries into actionable plans]
```

**Context evolution:**
- Discovery context → Planning context → Implementation context
- Each phase builds on previous context
- Confidence metrics track context completeness

## The Context Management Architecture

```
guided-discovery/
├── contexts/                    # Persistent context storage
│   ├── active/                 # Living context documents
│   │   └── task-*.json        # Complete task context
│   └── archived/              # Historical context
├── .claude/commands/           # Context manipulation commands
│   ├── discover.md            # Initialize context
│   ├── chain.md              # Build context systematically
│   ├── context.md            # Manage context state
│   └── uncertainty.md        # Analyze context gaps
├── chains/                    # Context building workflows
│   └── *.yaml                # Structured discovery paths
└── prompts/                   # Context gathering templates
    └── *.yaml                # Specific context builders
```

## Context Schema: The Living Document

```json
{
  "id": "unique-identifier",
  "task": {
    "description": "High-level goal",
    "type": "Context domain",
    "created_at": "timestamp"
  },
  "uncertainties": [
    {
      "id": "AREA-001",
      "description": "What context is missing?",
      "status": "unresolved|partial|resolved",
      "priority": "blocking|high|medium|low"
    }
  ],
  "discoveries": {
    "category": {
      "finding": "Context gathered",
      "confidence": 0.95
    }
  },
  "confidence": {
    "requirements": 0.0,
    "technical": 0.0,
    "implementation": 0.0,
    "overall": 0.0
  }
}
```

**Key insight**: This JSON is not just data - it's the AI's working memory, building comprehensive context over time.

## Command Engineering: Context Manipulation Primitives

Commands are context engineering tools, not just UI:

### /discover - Context Initialization
```markdown
Purpose: Create initial context and identify gaps
Actions:
1. Parse task into context structure
2. Identify uncertainties (context gaps)
3. Initialize persistent context file
4. Recommend context building paths
```

### /chain - Systematic Context Building
```markdown
Purpose: Execute workflows that build specific context
Actions:
1. Load chain definition (context building recipe)
2. Execute prompts in sequence (gather context)
3. Update discoveries (fill context gaps)
4. Track progress (context completeness)
```

### /uncertainty - Context Gap Analysis
```markdown
Purpose: Analyze what context is still missing
Actions:
1. Review current uncertainties
2. Assess context completeness
3. Prioritize remaining gaps
4. Recommend targeted exploration
```

### /context - Context State Management
```markdown
Purpose: Manage and inspect context state
Actions:
1. View accumulated context
2. Check confidence levels
3. Manage phase transitions
4. Archive completed contexts
```

## Prompt Engineering: Context Builders

Prompts are context gathering instruments:

```yaml
name: infrastructure_analyzer
category: discovery
targets_uncertainties: ["DEPLOY-001", "DEPLOY-002"]  # Context gaps to fill

template: |
  # Context Building Goal
  I need to discover deployment infrastructure requirements.
  
  # Current Context Available
  Task: {{task.description}}
  Prior discoveries: {{discoveries}}
  
  # Specific Context to Gather
  1. Server requirements
  2. Service dependencies
  3. Configuration needs
  
  # Structured Context Output
  ### DISCOVERIES
  ```json
  {
    "infrastructure": {
      "server": "findings",
      "services": ["list"],
      "configuration": "approach"
    }
  }
  ```
  
  ### UNCERTAINTY_UPDATES
  - DEPLOY-001: resolved (context gap filled)
  - DEPLOY-002: partial (more context needed)
```

**Key principles:**
1. **Target specific context gaps** (uncertainties)
2. **Build on existing context** (discoveries)
3. **Output structured context** (JSON discoveries)
4. **Track gap resolution** (uncertainty updates)

## Chain Design: Context Building Workflows

Chains orchestrate multi-step context building:

```yaml
name: tech_analysis
description: Build technical context systematically

prompt_sequence:
  - prompt: tech_stack_identifier
    purpose: Discover core technologies
    
  - prompt: dependency_mapper  
    purpose: Map external dependencies
    
  - prompt: infrastructure_analyzer
    purpose: Understand deployment needs
```

**Good chains:**
- Group related context gathering
- Build context progressively
- Have clear completion criteria
- Fill multiple related gaps

## Phase Management: Context Maturity Levels

### Discovery Phase (Context Gathering)
- **Goal**: Build foundational context
- **Activities**: Research, exploration, investigation
- **Exit criteria**: 80%+ uncertainties resolved
- **Context state**: Growing, exploratory

### Planning Phase (Context Synthesis)
- **Goal**: Transform context into actionable plans
- **Activities**: Design, architecture, strategy
- **Exit criteria**: 95%+ confidence
- **Context state**: Synthesizing, structuring

### Implementation Phase (Context Application)
- **Goal**: Apply context to build solution
- **Activities**: Building, testing, deploying
- **Exit criteria**: Solution delivered
- **Context state**: Applying, executing

## Why This Enables AI Success

### 1. Comprehensive Context
- AI has all information needed
- No hidden assumptions or gaps
- Rich, structured understanding

### 2. Persistent Memory
- Context survives between sessions
- Knowledge accumulates over time
- No repeated discovery work

### 3. Transparent Reasoning
- Every decision traceable to context
- Clear progression of understanding
- Auditable thought process

### 4. Systematic Exploration
- No random wandering
- Targeted information gathering
- Efficient context building

## Best Practices for Context Engineering

### 1. Let Uncertainties Drive
- Don't guess what context you need
- Let gaps emerge from exploration
- Target specific uncertainties

### 2. Build Context Incrementally
- Start with high-level understanding
- Add detail through discovery
- Synthesize when ready

### 3. Trust the Process
- Don't skip to implementation
- Let context mature naturally
- Confidence indicates readiness

### 4. Preserve Context
- Document all discoveries
- Keep context files updated
- Build on previous work

## Advanced Context Engineering Patterns

### Context Inheritance
When tasks are related, inherit context:
```json
"parent_context": "task-20240101-120000",
"inherited_discoveries": ["technical", "infrastructure"]
```

### Context Composition
Combine contexts for complex tasks:
```json
"composed_from": ["auth-context", "api-context"],
"composition_strategy": "merge-discoveries"
```

### Context Templates
Reusable context patterns:
```yaml
uncertainty_templates/auth_pattern.yaml
- Standard auth uncertainties
- Common discovery patterns
- Typical context needs
```

## The Meta-Level: Improving AI Through Context Engineering

This system demonstrates how AI can be enhanced through:

1. **Structured Working Memory** - Context files as external cognition
2. **Systematic Reasoning** - Uncertainty-driven exploration
3. **Knowledge Accumulation** - Persistent discoveries
4. **Confidence Calibration** - Linking context to certainty

Future AI systems could internalize these patterns for:
- Better long-term memory management
- More systematic problem-solving
- Improved uncertainty awareness
- Enhanced collaboration with humans

## Conclusion: Context is Everything

The Guided Discovery System succeeds because it makes context engineering explicit. By treating uncertainties as context gaps and discoveries as context building, it ensures AI has everything needed for complex tasks.

**Key insight**: We're not just "doing discovery" - we're engineering comprehensive context that enables AI to perform at its best. Every command, every prompt, every phase serves this goal.

This is the future of AI-assisted work: systematic context engineering that transforms uncertainty into understanding, enabling reliable execution of complex tasks.