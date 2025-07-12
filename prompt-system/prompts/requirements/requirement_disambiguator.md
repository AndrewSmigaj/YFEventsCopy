---
name: requirement_disambiguator
purpose: Detect and resolve ambiguous requirements
good_for:
  - Catching vague specifications early
  - Preventing wrong implementations
  - Generating clarifying questions
uncertainty_reduction:
  - Requirement ambiguities
  - Hidden assumptions
  - Success criteria
cost_estimate: low
---

# Requirement Disambiguator

I'm analyzing the requirements to identify ambiguities, vague terms, and hidden assumptions that could lead to incorrect implementation.

**Original requirement**: [User-provided description]

## 1. Ambiguity Detection

### Vague Terms Analysis

**Technical Ambiguities**:
- "Fast" → What metric? Response time <X ms? Throughput >Y req/s?
- "Secure" → Which standards? OWASP? Specific vulnerabilities?
- "User-friendly" → For which users? What usability metrics?
- "Scalable" → What scale? Users? Data? Requests?
- "Modern" → Which technologies? Patterns? UI standards?

**Scope Ambiguities**:
- "Authentication" → Just login or full user management?
- "API" → REST? GraphQL? gRPC? Which endpoints?
- "Integration" → One-way? Two-way? Real-time? Batch?
- "Mobile support" → Native apps? Responsive web? Both?
- "Dashboard" → Read-only? Interactive? Real-time?

**Behavioral Ambiguities**:
- "Handle errors" → Log? Retry? Notify? Graceful degradation?
- "Process data" → Transform? Validate? Store? All three?
- "Notify users" → Email? In-app? SMS? Push? When?
- "Update automatically" → How often? What triggers?
- "Sync data" → Real-time? Scheduled? Conflict resolution?

## 2. Hidden Assumptions

### Implicit Requirements Often Missed:

**Technical Assumptions**:
- Assuming REST when GraphQL might be expected
- Assuming single-tenant when multi-tenant needed
- Assuming English-only when i18n required
- Assuming desktop-first when mobile-first expected
- Assuming synchronous when async needed

**Business Assumptions**:
- Assuming all users have same permissions
- Assuming data can be deleted when audit trail needed
- Assuming immediate processing when batch preferred
- Assuming 24/7 availability when maintenance windows OK
- Assuming latest data when historical tracking needed

**Environmental Assumptions**:
- Assuming cloud deployment when on-premise required
- Assuming unlimited resources when constraints exist
- Assuming modern browsers when legacy support needed
- Assuming fast networks when offline capability required
- Assuming single region when global deployment needed

## 3. Missing Context

### Information Typically Needed:

**User Context**:
- Who are the primary users?
- What's their technical skill level?
- How many concurrent users?
- What are their goals?
- What devices do they use?

**System Context**:
- What's the current architecture?
- What are the integration points?
- What are the data volumes?
- What are the performance SLAs?
- What are the security requirements?

**Business Context**:
- What's the business value?
- What's the timeline?
- What's the budget constraint?
- What are the compliance needs?
- What's the maintenance plan?

## 4. Clarifying Questions

### Critical Questions (Must Answer):

1. **[Specific ambiguity]**: 
   - Option A: [Concrete interpretation]
   - Option B: [Alternative interpretation]
   - Impact: [How this affects implementation]

2. **[Specific ambiguity]**:
   - Option A: [Concrete interpretation]
   - Option B: [Alternative interpretation]
   - Impact: [How this affects implementation]

### Important Questions (Should Answer):

3. **[Clarification needed]**:
   - Default assumption: [What I'd assume]
   - Why it matters: [Impact on design]

4. **[Clarification needed]**:
   - Default assumption: [What I'd assume]
   - Why it matters: [Impact on design]

### Nice-to-Know Questions:

5. **[Additional context]**:
   - Helps with: [Design decision]
   - Can defer if needed

## 5. Requirement Restatement

### My Understanding (Please Confirm):

**Core Requirement**:
"You want me to [specific action] that will [specific outcome] for [specific users] with [specific constraints]."

**Key Features**:
1. [Specific feature with measurable outcome]
2. [Specific feature with measurable outcome]
3. [Specific feature with measurable outcome]

**Success Criteria**:
- [ ] [Measurable criterion]
- [ ] [Measurable criterion]
- [ ] [Measurable criterion]

**Out of Scope**:
- [Explicitly excluded item]
- [Explicitly excluded item]

## 6. Risk Assessment

### Ambiguity Risks:

**High Risk** (Could build wrong thing):
- [Ambiguity]: Could mean [A] or [B], completely different implementations

**Medium Risk** (Could affect quality):
- [Ambiguity]: Might impact performance/security/usability

**Low Risk** (Minor impact):
- [Ambiguity]: Can adjust during implementation

## 7. Disambiguation Strategy

### Recommended Approach:

**Option 1**: Answer clarifying questions first
- Pros: Clear requirements before starting
- Cons: Delays start
- When: High ambiguity, critical project

**Option 2**: Make reasonable assumptions, confirm later
- Pros: Faster start
- Cons: Might need rework
- When: Low ambiguity, prototype phase

**Option 3**: Build flexible solution supporting multiple interpretations
- Pros: Handles uncertainty
- Cons: More complex
- When: Medium ambiguity, time permits

## 8. Ambiguity Score

**Overall Ambiguity**: [0-10, where 10 is very ambiguous]

**Breakdown**:
- Functional requirements: [0-10]
- Technical requirements: [0-10]
- Scope definition: [0-10]
- Success criteria: [0-10]

**Confidence after disambiguation**: [X]%

**Next Step**: 
- If ambiguity >7: Must clarify before proceeding
- If ambiguity 4-7: Can proceed with assumptions
- If ambiguity <4: Ready for next phase