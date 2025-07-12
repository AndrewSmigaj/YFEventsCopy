# Context Files

This directory stores chain-specific context files that track the progress of orchestrated tasks.

## File Naming Convention

```
chain-[ID]-[task-name]-[timestamp].json
```

Example: `chain-001-jwt-auth-2024-01-15-14-30-00.json`

## Context Structure

Each context file tracks:

- **Chain metadata**: ID, goal, timestamps, status
- **Phase progression**: Current phase and history
- **Uncertainties**: What we don't know and confidence levels
- **Discoveries**: What we've learned about the system
- **Decisions**: Choices made and rationale
- **Confidence scores**: Numerical confidence tracking
- **Session memory**: Key insights and patterns
- **Cost tracking**: Token usage and estimated costs
- **Implementation tracking**: Progress on actual coding

## Context Lifecycle

1. **Created**: When `/orchestrator-new` is run
2. **Updated**: Throughout task execution
3. **Completed**: When task is finished
4. **Archived**: Can be moved to `completed/` subdirectory

## Usage

Context files are:
- Read by commands to understand current state
- Updated after each significant action
- Used to resume interrupted work
- Analyzed to extract patterns for reuse

## Template

See `context-template.json` for the structure of a new context file.