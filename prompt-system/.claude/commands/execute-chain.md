# Execute Prompt Chain

I'll execute the planned prompt chain automatically, updating the context after each prompt.

## 🔗 Current Chain Status

Let me check the current chain plan from your context...

**Active Context**: `contexts/chain-[ID]-[task]-[timestamp].json`
**Current Phase**: [Phase name]
**Planned Chain**: [List of prompts]

## 🚀 Chain Execution Process

I will:
1. Execute each prompt in the planned sequence
2. Update the context file after each prompt
3. Track discoveries and confidence changes
4. No uncertainty checks between prompts
5. Present consolidated findings at the end

## 📊 Execution Progress

### Chain: [Chain Name/ID]
**Objective**: [What this chain aims to discover]

```
[1/4] requirement_disambiguator
      Status: Executing...
      Purpose: Identify and resolve ambiguities
      
[2/4] constraint_mapper  
      Status: Pending
      Purpose: Discover constraints
      
[3/4] scope_boundary_definer
      Status: Pending
      Purpose: Define boundaries
      
[4/4] success_criteria_extractor
      Status: Pending
      Purpose: Extract success metrics
```

## 🔍 Prompt Execution

### Executing: requirement_disambiguator

[Prompt execution and findings]

**Key Discoveries**:
- [Discovery 1]
- [Discovery 2]
- [Discovery 3]

**Context Updated**: ✓

---

### Executing: constraint_mapper

[Prompt execution and findings]

**Key Discoveries**:
- [Constraint 1]
- [Constraint 2]
- [Constraint 3]

**Context Updated**: ✓

---

### Executing: scope_boundary_definer

[Prompt execution and findings]

**Key Discoveries**:
- [In scope items]
- [Out of scope items]
- [Gray areas identified]

**Context Updated**: ✓

---

### Executing: success_criteria_extractor

[Prompt execution and findings]

**Key Discoveries**:
- [Success metric 1]
- [Success metric 2]
- [Success metric 3]

**Context Updated**: ✓

## 📈 Chain Results

### Confidence Changes
- **Requirements**: 25% → 85% (↑60%)
- **Technical**: 25% → 45% (↑20%)
- **Implementation**: 25% → 35% (↑10%)
- **Overall**: 25% → 55% (↑30%)

### Key Findings Summary
1. **Requirements**: [Consolidated understanding]
2. **Constraints**: [Key limitations identified]
3. **Scope**: [Clear boundaries defined]
4. **Success**: [Measurable criteria established]

### Remaining Uncertainties
- [Technical architecture approach]
- [Integration specifics]
- [Performance requirements]

## 🎯 Next Steps

Based on chain results:

**Confidence Level**: 55% (Medium)

**Recommendation**: Run another discovery chain focused on technical aspects

**Suggested Next Chain**:
1. `architecture_detector` - Understand system structure
2. `component_mapper` - Map key components
3. `dependency_analyzer` - Identify dependencies
4. `trace_existing_flow` - Follow current implementation

Ready to:
- `/execute-chain` - Run the next suggested chain
- `/uncertainty-check` - Detailed uncertainty analysis
- `/chain-planner --focus technical` - Plan custom chain
- `/phase-controller` - Check if ready for next phase

## 💾 Context State

Your context has been updated with all discoveries from this chain execution. The context file now contains:
- Updated confidence scores
- New discoveries in each category
- Chain execution history
- Identified uncertainties for next iteration