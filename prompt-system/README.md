# LLM-Orchestrated Prompt System

An uncertainty-driven prompt orchestration system for Claude Code that guides complex software development tasks through phases based on confidence levels.

## Quick Start

```bash
# Start a new orchestrated task
/orchestrator-new "Add JWT authentication to my Express app"

# Check current chain status
/orchestrator-status

# Analyze uncertainties
/uncertainty-check

# Move to next phase
/phase-next
```

## Directory Structure

- `prompts/` - Library of focused prompts organized by category
- `contexts/` - Chain-specific context files (JSON)
- `.claude/commands/` - Claude Code command implementations
- `patterns/` - Learned patterns from successful chains
- `project_rules.md` - Project-specific guidelines

## Key Concepts

1. **Uncertainty-Driven**: Progress based on confidence levels, not rigid steps
2. **Phase-Based**: Requirements → Context Building → Design → Implementation
3. **Dynamic Adaptation**: Generates new prompts for novel situations
4. **Pattern Learning**: Captures and reuses successful approaches

## Commands

- `/orchestrator-new` - Start a new task chain
- `/orchestrator-status` - View current progress
- `/uncertainty-check` - Analyze confidence levels
- `/phase-controller` - Manage phase transitions
- `/pattern-save` - Save successful patterns

## Phases

1. **Requirements Clarification** - Resolve ambiguities, get clear specs
2. **Context Building** - Discover codebase patterns, understand constraints
3. **Uncertainty Analysis** - Measure confidence, identify gaps
4. **Solution Approval** - Present solution, get user confirmation
5. **Implementation** - TDD-first development with quality checks

## Learn More

See the [original design document](context_engineering/llm-orchestrated-prompt-system.md) for full details.