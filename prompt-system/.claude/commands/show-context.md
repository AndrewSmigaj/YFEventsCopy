# Show Current Context

I'll display the current state of your task execution context.

## 📂 Active Context

**File**: `contexts/chain-[ID]-[task]-[timestamp].json`
**Task**: [Task description]
**Started**: [Timestamp]
**Last Updated**: [Timestamp]

## 📊 Current State

### Phase Progress
```
✓ Requirements Clarification    [85% confident]
→ Context Building             [Current - 55% confident]
  Solution Design              [Not started]
  Implementation               [Not started]
```

### Confidence Scores
```
Requirements:    ████████████████░░░░  85%
Technical:       ████████░░░░░░░░░░░░  45%
Implementation:  ███████░░░░░░░░░░░░░  35%
Overall:         ███████████░░░░░░░░░  55%
```

## 🔗 Chain History

### Chain 1: Requirements Clarification (Completed)
**Prompts executed**: 
1. ✓ requirement_disambiguator
2. ✓ constraint_mapper
3. ✓ scope_boundary_definer
4. ✓ success_criteria_extractor

**Confidence gain**: 25% → 85% (↑60%)

### Chain 2: Technical Discovery (Planned)
**Prompts to execute**:
1. ⏳ architecture_detector
2. ⏳ component_mapper
3. ⏳ dependency_analyzer
4. ⏳ trace_existing_flow

## 💡 Key Discoveries

### Requirements
- **Functional**: [Key requirements discovered]
- **Constraints**: [Key constraints identified]
- **Success Criteria**: [Clear metrics defined]

### Technical Understanding
- **Architecture**: [What we know so far]
- **Components**: [Identified components]
- **Dependencies**: [Known dependencies]

### Decisions Made
1. [Decision 1]: [Rationale]
2. [Decision 2]: [Rationale]

## 🎯 Current Uncertainties

### High Priority
- [ ] Technical architecture approach
- [ ] Integration strategy
- [ ] Performance requirements

### Medium Priority
- [ ] Error handling patterns
- [ ] Testing approach
- [ ] Deployment strategy

### Low Priority
- [ ] Code style preferences
- [ ] Documentation format

## 📈 Progress Summary

**Total Prompts Executed**: 4
**Total Chains Completed**: 1
**Current Phase**: Context Building
**Next Milestone**: 80% confidence for phase transition

## 🚀 Recommended Actions

Based on current state:

1. **Execute planned chain**:
   ```
   /execute-chain
   ```

2. **Check uncertainties**:
   ```
   /uncertainty-check
   ```

3. **Plan custom chain**:
   ```
   /chain-planner --focus [area]
   ```

**Recommendation**: Execute the technical discovery chain to increase technical confidence from 45% to 80%+.