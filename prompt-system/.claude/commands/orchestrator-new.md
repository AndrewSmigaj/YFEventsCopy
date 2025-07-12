# Start New Orchestrated Task

I'll help you tackle this task using an uncertainty-driven, phase-based approach with automated prompt chains.

## Task: $ARGUMENTS

### 🎯 Creating Task Context

Let me create a new context file to track our progress through this task.

**Context File**: `contexts/chain-[ID]-[task-slug]-[timestamp].json`

I'm initializing a chain execution context with:
- Task description
- Phase tracking (Requirements → Context → Design → Implementation)
- Confidence scoring across multiple dimensions
- Chain execution history

### 📋 Phase 1: Requirements Clarification

**Current Confidence**: 25% (starting baseline)

Let me analyze your request to identify uncertainties and plan our first prompt chain.

**Initial Analysis**: "$ARGUMENTS"

Key uncertainties to explore:
- [ ] Specific requirements and constraints
- [ ] Success criteria and scope boundaries
- [ ] Integration points and dependencies
- [ ] Performance and security requirements

### 🔗 Planning First Chain

Based on initial analysis, I'll plan a chain of 3-5 prompts to clarify requirements:

**Recommended Chain**:
1. `requirement_disambiguator` - Identify and resolve ambiguities
2. `constraint_mapper` - Discover explicit and implicit constraints
3. `scope_boundary_definer` - Clarify what's in/out of scope
4. `success_criteria_extractor` - Define clear success metrics

**Expected Outcome**:
- Requirements confidence: 25% → 85%+
- Clear understanding of what to build
- Identified constraints and boundaries
- Defined success criteria

### 🚀 Execution Options

**Option A: Automated Chain Execution**
Run the planned chain automatically:
```
/execute-chain
```
This will:
- Execute all 4 prompts in sequence
- Update context after each prompt
- Show progress as we go
- Present consolidated findings

**Option B: Manual Step-Through**
Execute prompts one at a time:
```
/run requirement_disambiguator
/show-context
/run constraint_mapper
...
```

**Option C: Modify Chain**
Adjust the planned chain before execution:
```
/chain-planner --focus "specific area"
```

### 💡 How This Works

1. **Chains of 3-5 prompts** execute together
2. **No uncertainty checks** between prompts
3. **After chain completion**, we measure confidence
4. **If uncertain**, we plan another chain
5. **If confident**, we advance phases

Ready to execute the requirements clarification chain?

---
*Note: Your context is being tracked in a dedicated file. Each chain execution builds our understanding systematically until we reach high confidence.*