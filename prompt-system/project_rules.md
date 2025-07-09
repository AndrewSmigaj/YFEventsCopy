# Project Rules for LLM Orchestration

These rules guide the behavior of the LLM-orchestrated prompt system to ensure consistent, high-quality results.

## Core Principles

### 1. Uncertainty Drives Progress
- **Always measure confidence** before proceeding to next phase
- **80% confidence threshold** for moving from analysis to design
- **90% confidence threshold** for moving from requirements to building
- **Document uncertainties** explicitly in context files

### 2. Context Over Memory
- **File-based context** is source of truth, not conversation memory
- **Update context files** after every significant discovery or decision
- **Preserve context** proactively when approaching token limits
- **Start fresh conversations** with context file for resumption

### 3. Human in the Loop
- **Get approval** before implementation starts
- **Clarify ambiguities** rather than making assumptions
- **Present tradeoffs** for significant decisions
- **Stop and ask** when confidence drops below 60%

## Phase-Specific Rules

### Requirements Clarification Phase
**Always**:
- Run requirement_disambiguator on initial request
- Identify vague terms and get specific definitions
- Confirm understanding with concrete restatement
- Document all clarifications in context

**Never**:
- Assume technical choices from vague requirements
- Skip clarification for "obvious" things
- Proceed with ambiguity score >7

### Context Building Phase
**Always**:
- Start with high-level component mapping
- Trace actual code flows, not assumed flows
- Verify findings with code examination
- Update discoveries section progressively

**Never**:
- Assume patterns without verification
- Skip dependency analysis
- Trust documentation over code

### Uncertainty Analysis Phase
**Always**:
- Calculate confidence scores quantitatively
- Identify blocking vs non-blocking uncertainties
- Map uncertainties to specific prompts for resolution
- Document confidence progression

**Never**:
- Inflate confidence scores
- Ignore "small" uncertainties
- Proceed if blocking uncertainties exist

### Solution Design Phase
**Always**:
- Synthesize discoveries into coherent approach
- Present multiple options with tradeoffs
- Include specific implementation steps
- Get explicit approval before coding

**Never**:
- Design beyond current understanding
- Hide complexity or risks
- Make irreversible decisions without approval

### Implementation Phase
**Always**:
- Start with tests (TDD approach)
- Implement in small, verifiable chunks
- Run quality checks after each chunk
- Update context with progress

**Never**:
- Write large blocks of code at once
- Skip tests to save time
- Ignore failing tests
- Refactor without tests

## Technical Rules

### Code Quality
**Always**:
- Follow existing patterns in the codebase
- Maintain consistent style
- Write self-documenting code
- Include error handling

**Never**:
- Introduce new patterns without discussion
- Mix styles within a module
- Leave TODOs without tracking
- Swallow errors silently

### Testing
**Always**:
- Write tests first for new functionality
- Test edge cases and error paths
- Maintain or improve coverage
- Run full test suite before completion

**Never**:
- Write implementation before tests
- Test only happy paths
- Disable failing tests
- Mock without understanding

### Performance
**Always**:
- Consider performance implications
- Measure before optimizing
- Document performance decisions
- Use appropriate data structures

**Never**:
- Optimize prematurely
- Introduce N+1 queries
- Load unnecessary data
- Cache without invalidation strategy

## Orchestration Rules

### Prompt Selection
**Always**:
- Choose prompts based on specific uncertainties
- Run complementary prompts together
- Track prompt effectiveness
- Generate new prompts for novel situations

**Never**:
- Run prompts without clear purpose
- Repeat ineffective prompts
- Skip prompt selection analysis
- Use wrong prompt for the phase

### Context Management
**Always**:
- Update context after each prompt
- Track token usage and costs
- Monitor context health score
- Clean up completed chains

**Never**:
- Let context files grow unbounded
- Lose decision rationale
- Skip context updates
- Mix multiple tasks in one chain

### Pattern Learning
**Always**:
- Extract patterns from successful chains
- Document reusable approaches
- Tag chains for easy retrieval
- Share patterns across projects

**Never**:
- Ignore repeated solutions
- Keep patterns project-specific
- Forget learned optimizations
- Apply patterns blindly

## Decision Making

### When Uncertain
- **>80% confident**: Proceed with documented assumptions
- **60-80% confident**: Run targeted discovery
- **40-60% confident**: Need comprehensive analysis  
- **<40% confident**: Stop and get human input

### When Blocked
1. Document the blocker clearly
2. Identify what information would unblock
3. Present options to user
4. Wait for guidance

### When Choosing Approaches
1. Present 2-3 viable options
2. List tradeoffs for each
3. Make recommendation with rationale
4. Get confirmation before proceeding

## Cost Management

### Token Usage
**Always**:
- Track tokens per phase
- Warn when approaching limits
- Summarize before context switch
- Use appropriate model for task

**Never**:
- Waste tokens on repeated analysis
- Generate unnecessarily verbose output
- Keep full history in context
- Use expensive models for simple tasks

### Efficiency Rules
- Batch related prompts together
- Cache repeated analyses
- Reuse patterns when applicable
- Stop when confidence is sufficient

## Error Handling

### When Things Go Wrong
1. Document what happened
2. Identify root cause
3. Propose fix or workaround
4. Update context with learning

### Recovery Strategies
- **Context corrupted**: Restore from last good state
- **Wrong path taken**: Document and backtrack
- **Tests failing**: Stop and fix before proceeding
- **Integration broken**: Isolate and diagnose

## Communication

### Status Updates
**Always**:
- Communicate current phase clearly
- Show confidence levels
- Explain what comes next
- Highlight blockers early

**Never**:
- Hide problems or uncertainties
- Use jargon without explanation
- Make promises about timelines
- Assume context is understood

### Documentation
**Always**:
- Update context files in real-time
- Document decisions with rationale
- Include examples in explanations
- Keep language clear and concrete

**Never**:
- Rely on memory alone
- Skip "obvious" documentation
- Use ambiguous language
- Document after the fact

## Meta Rules

### System Improvement
- Track prompt effectiveness metrics
- Identify missing prompts
- Propose system enhancements
- Learn from failures

### Flexibility
- Rules guide but don't constrain
- Novel situations may need novel approaches
- User needs override system preferences
- Pragmatism over dogma

## Quick Reference

### Phase Transitions
- Requirements → Context: When requirements confidence >90%
- Context → Analysis: When sufficient discovery done
- Analysis → Design: When overall confidence >80%
- Design → Implementation: When solution approved
- Implementation → Complete: When tests pass and quality gates met

### Confidence Thresholds
- **95%+**: Very confident, minimal risk
- **80-95%**: Confident, acceptable risk
- **60-80%**: Somewhat confident, needs attention
- **40-60%**: Low confidence, significant gaps
- **<40%**: Very uncertain, need help

### Red Flags
- 🚩 Circular dependencies discovered
- 🚩 Security vulnerabilities found
- 🚩 Performance requirements can't be met
- 🚩 Core assumptions proven wrong
- 🚩 Integration not possible as planned

When red flags appear: STOP, DOCUMENT, and CONSULT.