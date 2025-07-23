# Context Management

## Usage: /context [action] [context_id] [additional_args]

**IMPORTANT**: This command manages discovery contexts. Always work within the appropriate phase - do not skip to implementation.

### Parsing Arguments

[Extract action, context_id, and additional args from $ARGUMENTS]

### Actions:

#### `show [context_id]`
Display full context details

If no context_id:
```
❌ No context ID provided!
Usage: /context show [context_id]
```

[Load and display full context JSON in readable format]

#### `archive [context_id]`
Move completed context to archive

Check if context is ready for archiving:
- All chains complete
- Implementation phase done or task abandoned

[Move file from contexts/active/ to contexts/archived/]

```
✅ Context [context_id] archived
Location: contexts/archived/task-[context_id].json
```

#### `phase-up [context_id]`
Advance to next phase (with gates)

[Load context and check phase gates]

Current phase transitions:
- discovery → analysis (requires 80% discovery completion)
- analysis → design (requires analysis completion)  
- design → implementation (requires explicit approval)

If gates not met:
```
❌ Cannot advance to [next_phase] phase yet!

Requirements not met:
- [List unmet requirements]

Current status:
- Discovery: [X]% complete
- [Other relevant metrics]
```

If gates met:
```
✅ Ready to advance from [current] to [next] phase!

This phase transition will:
- Update phase to [next]
- Enable new types of chains
- [Other changes]

Confirm phase advancement? This action cannot be undone.
Type: /context phase-up [context_id] --confirm
```

#### `list [filter]`
List all contexts with optional filter

Filters: active, archived, all (default: active)

```
📋 Discovery Contexts ([filter]):

Active:
[For each context in contexts/active/:]
- [context_id] - [task.description]
  Phase: [current_phase] | Confidence: [overall]%
  Created: [created_at] | Chains: [completed]/[total]

Archived: [count] contexts
(Use /context list archived to view)
```

#### `uncertainties [context_id]`
Deep dive into uncertainties

[Load context and display detailed uncertainty analysis]

```
## 🎯 Uncertainties: [context_id]

### Blocking ([count])
[For each blocking uncertainty:]
- [id]: [description]
  Status: [resolved/partial/unresolved] ([confidence]%)
  Blocks: [what it prevents]
  Resolve via: [suggested chain/prompt]

### High Priority ([count])
[Similar format]

### Resolved ([count])
[List resolved uncertainties with resolution method]
```

#### `reset [context_id]`
Reset context to initial state (with confirmation)

```
⚠️ WARNING: This will reset all progress!

This action will:
- Clear all discoveries
- Reset all chain progress
- Return to discovery phase
- Reset confidence to 0%

Are you sure? Type: /context reset [context_id] --confirm
```

### Error Handling

For any action, if context file not found:
```
❌ Context not found: [context_id]

Available contexts:
[List context IDs]
```

### Phase Enforcement

Every action checks current phase and enforces appropriate restrictions:
- Discovery phase: All discovery actions allowed
- Analysis phase: Discovery locked, analysis chains allowed
- Design phase: Discovery/analysis locked, design allowed
- Implementation phase: Only implementation actions allowed

**REMEMBER**: Follow the systematic process. Each phase builds on the previous one. No shortcuts!