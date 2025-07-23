# Execute Discovery Chain

## Usage: /chain [chain_name] [context_id]

**IMPORTANT**: This command executes discovery chains. Do not skip ahead to implementation. Complete all discovery prompts systematically.

### Parsing Arguments

[Extract chain_name and context_id from $ARGUMENTS]

If context_id is missing:
```
❌ No context ID provided!

Active contexts:
[List all .json files from /mnt/d/YFEventsCopy/guided-discovery/contexts/active/]

Please run with context ID:
/chain [chain_name] [context_id]
```

### Loading Context

[Load context file: /mnt/d/YFEventsCopy/guided-discovery/contexts/active/task-{context_id}.json]

Check current phase:
- If phase != "discovery" and chain is discovery type: Show warning
- If phase gates don't allow current action: Block execution

### Loading Chain Definition

[Load chain YAML from:
- /mnt/d/YFEventsCopy/guided-discovery/chains/common/{chain_name}.yaml
- /mnt/d/YFEventsCopy/guided-discovery/chains/specialized/**/{chain_name}.yaml]

### Checking Progress

[Check context.chain_progress[chain_name] to see which prompts are complete]

If chain already complete:
```
✅ Chain [chain_name] already completed!
Prompts executed: [list]
Next recommended action: [based on chain's next_chain_logic]
```

If chain in progress:
```
📊 Chain Progress: [chain_name]
Completed: [X/Y] prompts
Next prompt: [prompt_name]
Continuing from where we left off...
```

### Executing Next Prompt

[Load next unexecuted prompt from the chain's prompt_sequence]
[Load prompt YAML from /mnt/d/YFEventsCopy/guided-discovery/prompts/]

**Executing**: [prompt_name] ([X/Y] in chain)
**Purpose**: [prompt.purpose from chain definition]

[Execute the prompt template, substituting context variables]

### Updating Progress

After prompt execution:
1. Update context.chain_progress[chain_name].completed
2. Update context.discoveries with findings
3. Update context.uncertainties resolution status
4. Calculate new confidence scores
5. Save updated context file

### Chain Completion Check

If all mandatory prompts complete:
```
✅ Chain [chain_name] completed!

Summary:
- Prompts executed: [count]
- Uncertainties resolved: [list]
- Confidence gain: [before]% → [after]%

Next steps:
[Based on chain.next_chain_logic and current confidence]

To continue:
/chain [next_chain_name] [context_id]
```

If more prompts remain:
```
✅ Prompt [prompt_name] completed

Progress: [X/Y] prompts done
Next prompt: [next_prompt_name]

To continue:
/chain [chain_name] [context_id]
```

### Phase Gate Check

After chain completion, check if phase advancement criteria are met:
- If discovery > 80% complete: can_analyze = true
- If analysis complete: can_design = true
- If design complete and approved: can_implement = true

**REMEMBER**: Always complete the full discovery process before moving to implementation!