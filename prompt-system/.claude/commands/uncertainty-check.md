# Uncertainty Analysis - Confidence-Driven Decision Making

I'll perform a comprehensive analysis of current uncertainties to determine if we're ready to proceed or need more discovery.

## 🎯 Systematic Uncertainty Detection

Let me analyze what we know versus what remains unclear across all aspects of this task.

### 📊 Knowledge Inventory

**What We Know**:
- [ ] Clear requirements and constraints
- [ ] Existing code structure and patterns
- [ ] Dependencies and integration points
- [ ] Performance and security requirements
- [ ] Testing approach and standards
- [ ] Deployment considerations

**What We're Uncertain About**:
- [ ] Edge cases and error scenarios
- [ ] Performance implications
- [ ] Migration strategies
- [ ] Backward compatibility
- [ ] Third-party integrations
- [ ] Scaling considerations

### 🔍 Uncertainty Categories

**1. Requirements Uncertainties**
- Missing specifications
- Ambiguous success criteria
- Undefined constraints
- Implicit assumptions

**2. Technical Uncertainties**
- Architecture decisions
- Technology choices
- Integration approaches
- Performance targets

**3. Implementation Uncertainties**
- Code organization
- Testing strategies
- Error handling
- State management

**4. Operational Uncertainties**
- Deployment process
- Monitoring needs
- Rollback procedures
- Documentation requirements

### 📈 Confidence Scoring

I assess confidence across three dimensions:

**Requirements Confidence**: 
- Do I understand what needs to be built? [0-100%]
- Are success criteria clear? [0-100%]
- Are constraints well-defined? [0-100%]

**Technical Confidence**:
- Do I understand the system architecture? [0-100%]
- Are integration points clear? [0-100%]
- Is the technical approach sound? [0-100%]

**Implementation Confidence**:
- Do I know how to structure the code? [0-100%]
- Is the testing approach clear? [0-100%]
- Are edge cases identified? [0-100%]

**Overall Confidence**: [Weighted average]

### 🎯 Uncertainty Resolution Priority

**🔴 Critical (Must resolve before proceeding)**:
- Uncertainties that could lead to wrong implementation
- Missing core requirements
- Architectural decisions that affect everything

**🟡 Important (Should resolve soon)**:
- Performance optimization approaches
- Error handling strategies
- Testing methodologies

**🟢 Minor (Can resolve during implementation)**:
- Code style preferences
- Minor optimizations
- Documentation format

### 💡 Resolution Strategies

For each identified uncertainty:

1. **Can existing prompts help?**
   - Requirements: requirement_disambiguator, constraint_mapper
   - Technical: architecture_analyzer, dependency_mapper
   - Implementation: pattern_detector, best_practice_checker

2. **Need user clarification?**
   - Generate specific questions
   - Provide context for decision
   - Suggest options with tradeoffs

3. **Need code exploration?**
   - Trace existing implementations
   - Analyze similar patterns
   - Check framework documentation

4. **Novel situation?**
   - Consider generating new prompts
   - Break down into known components
   - Research external resources

### 🚀 Recommended Path Forward

**High Confidence (>80%)**:
✅ Ready to design solution
✅ Minor uncertainties manageable
✅ Can proceed with implementation planning

**Medium Confidence (60-80%)**:
⚠️ Need targeted discovery
⚠️ Address critical uncertainties
⚠️ May need 1-2 more discovery rounds

**Low Confidence (<60%)**:
🚫 Significant knowledge gaps
🚫 Need comprehensive discovery
🚫 Consider breaking into smaller tasks

### ⏭️ Next Actions

Based on this analysis:

1. **If confident**: Move to solution design phase
2. **If uncertain**: Run specific discovery prompts
3. **If blocked**: Request user clarification
4. **If novel**: Generate specialized prompts

### 🔗 Recommended Next Chain

Based on the uncertainty analysis, here's the optimal chain to execute next:

**For [Current Confidence Level]% confidence**:

**Chain Focus**: [Requirements/Technical/Implementation]

**Recommended Chain** (3-5 prompts):
1. `[prompt_1]` - [Purpose]
2. `[prompt_2]` - [Purpose]
3. `[prompt_3]` - [Purpose]
4. `[prompt_4]` - [Purpose] (if needed)

**Expected Outcome**:
- Confidence increase: [X]% → [Y]%
- Resolve uncertainties: [List key ones]
- Enable: [What this unlocks]

**Execute this chain?**
```
/execute-chain
```

Or plan a different chain:
```
/chain-planner --focus [specific area]
```

What would you like to do based on this uncertainty analysis?