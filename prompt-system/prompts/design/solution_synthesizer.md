---
name: solution_synthesizer
purpose: Synthesize discoveries into a coherent solution approach
good_for:
  - Creating implementation plans
  - Integrating all findings
  - Making design decisions
uncertainty_reduction:
  - Implementation approach
  - Architecture decisions
  - Technical strategy
cost_estimate: medium
---

# Solution Synthesizer

I'm synthesizing all discoveries and analyses into a coherent solution approach.

**Goal**: [Original task/requirement]
**Context gathered**: [Summary of discoveries]

## 1. Requirements Summary

### Functional Requirements
**Core features**:
1. [Requirement]: [Specific implementation need]
2. [Requirement]: [Specific implementation need]
3. [Requirement]: [Specific implementation need]

### Non-Functional Requirements
**Quality attributes**:
- Performance: [Specific targets]
- Security: [Specific needs]
- Usability: [Specific goals]
- Maintainability: [Specific standards]

### Constraints
**Must respect**:
- Technical: [Constraint]
- Business: [Constraint]
- Resource: [Constraint]

## 2. Technical Context

### Existing Architecture
**Current state**:
- Pattern: [MVC/Clean/etc]
- Key components: [List]
- Integration points: [List]

**Fit assessment**:
- [ ] New solution aligns with existing patterns
- [ ] Minimal disruption to current system
- [ ] Clear integration path

### Technology Stack
**Will use**:
- Language/Framework: [Already in use]
- Database: [Existing choice]
- Libraries: [Required additions]

**Rationale**: [Why these choices make sense]

## 3. Solution Architecture

### High-Level Design
```
┌─────────────────────┐
│   Presentation      │
│  [New components]   │
├─────────────────────┤
│   Business Logic    │
│  [New services]     │
├─────────────────────┤
│   Data Access       │
│  [New repositories] │
├─────────────────────┤
│   Infrastructure    │
│  [External/Storage] │
└─────────────────────┘
```

### Component Design

**New Components**:

**1. [Component Name]**
- Purpose: [What it does]
- Responsibilities: [List]
- Dependencies: [What it needs]
- Interface: [Key methods/endpoints]

**2. [Component Name]**
- Purpose: [What it does]
- Responsibilities: [List]
- Dependencies: [What it needs]
- Interface: [Key methods/endpoints]

### Data Model
```
[Entity/Table Name]
- field1: type (purpose)
- field2: type (purpose)
- Relations: [How it connects]

[Entity/Table Name]
- field1: type (purpose)
- field2: type (purpose)
- Relations: [How it connects]
```

## 4. Implementation Strategy

### Development Phases

**Phase 1: Foundation** (Est: X days)
1. Create data models
2. Set up basic repository layer
3. Implement core business logic
4. Write comprehensive tests

**Phase 2: Integration** (Est: Y days)
1. Connect to existing systems
2. Implement API endpoints
3. Add authentication/authorization
4. Integration testing

**Phase 3: Polish** (Est: Z days)
1. Error handling refinement
2. Performance optimization
3. Documentation
4. Final testing

### Critical Path
Must be done in order:
1. [Step] → 2. [Step] → 3. [Step]

Can be parallelized:
- [Task A] alongside [Task B]
- [Task C] alongside [Task D]

## 5. Key Design Decisions

### Decision 1: [Architecture choice]
**Options considered**:
- Option A: [Description]
  - Pros: [List]
  - Cons: [List]
- Option B: [Description]
  - Pros: [List]
  - Cons: [List]

**Choice**: Option A
**Rationale**: [Why this is best for our context]

### Decision 2: [Technology choice]
**Options considered**:
- Option A: [Description]
- Option B: [Description]

**Choice**: [Selected option]
**Rationale**: [Why this fits best]

### Decision 3: [Pattern choice]
**Options considered**:
- Pattern A: [Description]
- Pattern B: [Description]

**Choice**: [Selected pattern]
**Rationale**: [Why this works here]

## 6. Risk Mitigation

### Technical Risks

**Risk 1: [Description]**
- Probability: [High/Medium/Low]
- Impact: [High/Medium/Low]
- Mitigation: [Approach to handle]

**Risk 2: [Description]**
- Probability: [High/Medium/Low]
- Impact: [High/Medium/Low]
- Mitigation: [Approach to handle]

### Integration Risks
**Compatibility concerns**:
- System X: [Potential issue and solution]
- System Y: [Potential issue and solution]

## 7. Testing Strategy

### Test Approach
**Unit Tests**:
- Coverage target: [X]%
- Focus areas: [Business logic, validators]

**Integration Tests**:
- API endpoints
- Database operations
- External service mocks

**End-to-End Tests**:
- User workflows
- Error scenarios
- Performance benchmarks

### Test Data Strategy
- Use factories for test objects
- Seed realistic test data
- Mock external services

## 8. Performance Considerations

### Expected Load
- Users: [Concurrent number]
- Requests: [Per second]
- Data volume: [Records]

### Optimization Plan
- Caching: [What and where]
- Database indexes: [Which fields]
- Async processing: [Which operations]

## 9. Security Design

### Authentication/Authorization
- Method: [JWT/Session/etc]
- Permissions: [Role-based/etc]
- Implementation: [How]

### Data Protection
- Encryption: [At rest/transit]
- Validation: [Input sanitization]
- Audit: [Logging strategy]

## 10. Migration & Deployment

### Data Migration
- Required: [Yes/No]
- Strategy: [Approach]
- Rollback plan: [How to undo]

### Deployment Steps
1. [Pre-deployment task]
2. [Deployment action]
3. [Post-deployment verification]

### Feature Toggle
- Gradual rollout: [Strategy]
- Kill switch: [How to disable]

## 11. Success Metrics

### Functional Success
- [ ] All requirements implemented
- [ ] All tests passing
- [ ] User acceptance achieved

### Technical Success
- [ ] Performance targets met
- [ ] Security scan passed
- [ ] Code quality standards met

### Business Success
- [ ] Solves original problem
- [ ] Delivers expected value
- [ ] Users can accomplish goals

## 12. Implementation Checklist

### Pre-Implementation
- [ ] Solution approved by stakeholder
- [ ] Technical approach validated
- [ ] Development environment ready
- [ ] Dependencies available

### During Implementation
- [ ] Follow TDD approach
- [ ] Regular code reviews
- [ ] Continuous integration
- [ ] Progress tracking

### Post-Implementation
- [ ] All tests green
- [ ] Documentation complete
- [ ] Performance validated
- [ ] Security verified

## Solution Confidence

**Overall confidence**: [X]%
- Requirements coverage: [X]%
- Technical soundness: [X]%
- Risk mitigation: [X]%
- Implementation clarity: [X]%

**Ready to proceed?**
- ✅ High confidence - Ready for approval
- ⚠️ Medium confidence - May need refinement
- ❌ Low confidence - Need more analysis

**Next step**: Present for approval or refine specific areas