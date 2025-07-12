---
name: requirement_validator
purpose: Validate requirements for consistency, completeness, and feasibility
good_for:
  - Checking requirement contradictions
  - Ensuring completeness
  - Validating feasibility
uncertainty_reduction:
  - Requirement conflicts
  - Missing requirements
  - Technical feasibility
cost_estimate: low
---

# Requirement Validator

I'm validating the requirements to ensure they are consistent, complete, and technically feasible.

**Requirements to validate**: [Collected requirements]

## 1. Consistency Check

### Internal Contradictions

**Checking for conflicts**:

**Functional Conflicts**:
- [ ] Requirement A states X, but Requirement B implies NOT X
- [ ] Feature A depends on B, but B is excluded
- [ ] Workflow A breaks assumption of Workflow B

**Technical Conflicts**:
- [ ] Performance requirement conflicts with feature complexity
- [ ] Security requirement conflicts with usability requirement
- [ ] Scalability requirement conflicts with budget constraint
- [ ] Real-time requirement conflicts with batch processing

**Example Conflicts Found**:
- [Requirement 1] says [X] but [Requirement 2] requires [NOT X]
- [Feature A] needs [resource] but [Constraint B] limits it

## 2. Completeness Check

### Missing Requirements

**Functional Completeness**:
- [ ] All user journeys have defined outcomes
- [ ] Error scenarios are specified
- [ ] Edge cases are addressed
- [ ] Data validation rules are defined
- [ ] Business rules are complete

**Non-Functional Completeness**:
- [ ] Performance targets specified
- [ ] Security requirements defined
- [ ] Availability requirements stated
- [ ] Scalability limits defined
- [ ] Compliance needs identified

**Common Omissions Found**:
- Missing: [Error handling for scenario X]
- Missing: [Performance requirement for feature Y]
- Missing: [Security consideration for data Z]

## 3. Feasibility Analysis

### Technical Feasibility

**Possible with current tech?**:
- [Requirement]: ✅ Feasible with [approach]
- [Requirement]: ⚠️ Challenging because [reason]
- [Requirement]: ❌ Not feasible because [limitation]

**Resource Feasibility**:
- Time required: [estimate] vs available: [timeline]
- Skills needed: [list] vs available: [team skills]
- Infrastructure: [needs] vs available: [current]

**Integration Feasibility**:
- External system A: ✅ API available
- External system B: ⚠️ Limited API
- External system C: ❌ No integration path

## 4. Requirement Dependencies

### Dependency Map

```
Requirement A
  └─> Requires B (technical dependency)
       └─> Requires C (data dependency)
  └─> Requires D (business rule dependency)

Requirement E
  └─> Conflicts with F (mutual exclusion)
  └─> Optional enhancement to G
```

### Critical Path

Must implement in this order:
1. [Foundation requirement]
2. [Dependent requirement]
3. [Feature requirement]

## 5. Testability Assessment

### Each Requirement's Testability

**Well-Defined** (Easy to test):
- [Requirement]: Clear pass/fail criteria
- [Requirement]: Measurable outcome

**Vague** (Hard to test):
- [Requirement]: Subjective criteria
- [Requirement]: No clear metric

**Untestable**:
- [Requirement]: No observable outcome
- [Requirement]: Depends on external factors

## 6. Risk Assessment

### Requirement Risks

**High Risk Requirements**:
- [Complex requirement]: High technical risk
- [Integration requirement]: Dependency risk
- [Performance requirement]: Feasibility risk

**Mitigation Strategies**:
- For [risk]: [mitigation approach]
- For [risk]: [mitigation approach]

## 7. Validation Results

### Summary

**Validation Status**:
- ✅ Consistent: [X] requirements
- ⚠️ Conflicts found: [Y] pairs
- ❌ Missing: [Z] requirements

**Completeness Score**: [X]%
- Functional: [X]%
- Non-functional: [X]%
- Edge cases: [X]%

**Feasibility Score**: [X]%
- Technically feasible: [X]%
- Resource feasible: [X]%
- Timeline feasible: [X]%

## 8. Required Clarifications

### Must Resolve

1. **Conflict**: [Requirement A] vs [Requirement B]
   - Option 1: [Resolution]
   - Option 2: [Resolution]
   - Impact: [What changes]

2. **Missing**: [Required information]
   - Why needed: [Impact]
   - Suggested: [Default]

### Should Address

3. **Incomplete**: [Requirement]
   - Missing: [Aspect]
   - Assumption: [What I'd assume]

## 9. Validated Requirement Set

### Final Requirements (After Validation)

**Core Requirements**:
1. [Clear, complete requirement]
2. [Clear, complete requirement]

**Constraints**:
1. [Validated constraint]
2. [Validated constraint]

**Success Criteria**:
1. [Measurable criterion]
2. [Measurable criterion]

**Dependencies**:
1. [A] must complete before [B]
2. [C] requires [external system]

## 10. Confidence Assessment

**Requirements Confidence**: [X]%
- Clarity: [X]%
- Completeness: [X]%
- Feasibility: [X]%
- Testability: [X]%

**Ready to proceed?**
- If >90%: ✅ Requirements validated
- If 70-90%: ⚠️ Address clarifications
- If <70%: ❌ Need requirement refinement

**Next Step**: [Recommendation based on validation]