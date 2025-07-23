# Show Discovery Status

## Usage: /status [context_id]

**IMPORTANT**: This command shows the current status of a discovery task. Use it to track progress and determine next steps.

### Parsing Arguments

[Extract context_id from $ARGUMENTS]

If no context_id provided:
```
📋 Active Discovery Contexts:

[List all .json files from /mnt/d/YFEventsCopy/guided-discovery/contexts/active/]
Format as:
- [context_id] - [task.description] (Phase: [current_phase])
  Created: [created_at]

To view specific context:
/status [context_id]
```

### Loading Context

If context_id provided:
[Load context file: /mnt/d/YFEventsCopy/guided-discovery/contexts/active/task-{context_id}.json]

If file not found:
```
❌ Context not found: [context_id]

Did you mean one of these?
[List similar context IDs]
```

### Display Status

```
## 📊 Discovery Status: [context_id]

**Task**: [task.description]
**Type**: [task.type]
**Current Phase**: [current_phase]
**Created**: [created_at]

### Phase Progress
- ✅ Discovery: [percentage or "In Progress"]
- [✅/🔒] Analysis: [status based on phase_gates.can_analyze]
- [✅/🔒] Design: [status based on phase_gates.can_design]
- [🔒] Implementation: [status based on phase_gates.can_implement]

### Confidence Levels
- Requirements: [confidence.requirements]%
- Technical: [confidence.technical]%
- Implementation: [confidence.implementation]%
- **Overall**: [confidence.overall]%

### Chain Progress
[For each chain in chain_progress:]
**[chain_name]**: [completed]/[total] prompts
- Last executed: [last_prompt]
- Next prompt: [next_prompt or "Complete"]

### Recent Discoveries
[List last 3-5 key discoveries from context.discoveries]

### Uncertainties
**Blocking**: [count] ([resolved count] resolved)
**High Priority**: [count] ([resolved count] resolved)
**Medium Priority**: [count] ([resolved count] resolved)
**Low Priority**: [count] ([resolved count] resolved)

### Next Actions
[Based on current state, recommend next action:]

If chain in progress:
```
Continue current chain:
/chain [chain_name] [context_id]
```

If chain complete but more discovery needed:
```
Recommended next chain: [chain_name]
/chain [chain_name] [context_id]
```

If discovery complete:
```
Discovery phase complete! (confidence: [X]%)
Ready to advance to analysis phase:
/context phase-up [context_id]
```

### Phase Gate Status
[Show requirements for next phase:]
- Discovery → Analysis: Requires 80% discovery completion
- Analysis → Design: Requires analysis completion
- Design → Implementation: Requires design approval
```

**REMEMBER**: Complete each phase thoroughly before advancing. No shortcuts to implementation!