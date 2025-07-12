# Phase Controller - Orchestration State Management

I'll analyze the current orchestration state and guide the next steps based on confidence levels and remaining uncertainties.

## 🎯 Current Phase Assessment

Let me check where we are in the orchestration process and what should happen next.

### 📊 Confidence-Driven Phase Transitions

The orchestration system uses these phases:

1. **Requirements Clarification** (Exit: >90% requirements confidence)
2. **Context Building** (Exit: Sufficient understanding achieved)
3. **Uncertainty Analysis** (Exit: >80% overall confidence)
4. **Solution Approval** (Exit: User approves approach)
5. **Implementation** (Exit: All tests pass, quality gates met)

### 🔍 Current State Analysis

To determine the next phase, I need to assess:

**Requirements Understanding**:
- [ ] All ambiguities resolved
- [ ] Constraints clearly defined
- [ ] Success criteria established
- [ ] Scope boundaries set

**Context Completeness**:
- [ ] Relevant code discovered
- [ ] Patterns identified
- [ ] Dependencies mapped
- [ ] Integration points understood

**Confidence Levels**:
- Requirements clarity: [X%]
- Technical approach: [X%]
- Implementation details: [X%]
- Overall confidence: [X%]

**Blocking Issues**:
- [ ] Unresolved ambiguities
- [ ] Missing information
- [ ] Technical uncertainties
- [ ] Design decisions needed

### 🚀 Recommended Next Action

Based on the current state:

**If in Requirements Phase**:
- ✅ Requirements clear → Move to Context Building
- ❓ Ambiguities exist → Stay and clarify
- 🚫 Major gaps → Need user input

**If in Context Building**:
- ✅ Sufficient context → Move to Uncertainty Analysis
- 🔍 More needed → Continue discovery with specific focus
- 🆕 Novel situation → Consider dynamic prompt generation

**If in Uncertainty Analysis**:
- ✅ High confidence (>80%) → Move to Solution Design
- ⚠️ Low confidence → Return to Context Building
- 🚫 Blockers → Resolve with user

**If in Solution Design**:
- ✅ Complete solution → Present for approval
- 🔧 Needs refinement → Continue design work
- ❓ New uncertainties → Re-analyze

**If in Implementation**:
- ✅ Tests passing → Prepare for completion
- 🐛 Issues found → Debug and fix
- 🏗️ Design flaws → Return to design phase

### 💡 Phase-Specific Prompts

Depending on the phase, these prompts may help:

**Requirements**: requirement_disambiguator, constraint_mapper
**Context**: trace_existing_flow, dependency_analyzer, component_mapper
**Analysis**: uncertainty_analysis, confidence_calibrator
**Design**: solution_synthesizer, architecture_validator
**Implementation**: tdd_flow_controller, code_reviewer

### ⏭️ Next Steps

1. **Continue in current phase** with specific focus
2. **Advance to next phase** (if criteria met)
3. **Return to previous phase** (if gaps found)
4. **Pause for user input** (if blocked)
5. **Generate new prompts** (if novel situation)

Which action should we take? Or would you like me to perform a deeper analysis of the current state?