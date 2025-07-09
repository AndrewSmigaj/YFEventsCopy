---
name: constraint_mapper
purpose: Identify explicit and implicit constraints
good_for:
  - Finding hidden limitations
  - Understanding boundaries
  - Preventing constraint violations
uncertainty_reduction:
  - Technical constraints
  - Business constraints
  - Environmental constraints
cost_estimate: low
---

# Constraint Mapper

I'm identifying all constraints - both explicit and implicit - that will affect our implementation approach.

**Context**: [Task and current understanding]

## 1. Technical Constraints

### Platform & Environment
**Explicit Constraints**:
- Language/Framework: [Must use X]
- Database: [Must use Y]
- Deployment: [Must deploy to Z]
- Browser support: [Must support A, B, C]

**Implicit Constraints** (Derived from context):
- Memory limits: [Based on deployment environment]
- CPU constraints: [Based on server specs]
- Network bandwidth: [Based on user locations]
- Storage limits: [Based on infrastructure]

### Integration Constraints
**API Limitations**:
- Rate limits: [X requests per minute]
- Payload size: [Max Y MB]
- Authentication: [Must use Z method]
- Protocols: [Must use REST/GraphQL/etc]

**System Dependencies**:
- Must integrate with: [System A v.X]
- Cannot modify: [System B]
- Must maintain compatibility: [System C]

### Performance Constraints
**Response Time**:
- Page load: [<X seconds]
- API response: [<Y milliseconds]
- Background jobs: [<Z minutes]

**Throughput**:
- Concurrent users: [Must support X]
- Requests per second: [Must handle Y]
- Data processing: [Z records/hour]

## 2. Business Constraints

### Timeline Constraints
**Deadlines**:
- Must launch by: [Date]
- Phase 1 due: [Date]
- Critical milestone: [Date/Event]

**Dependencies**:
- Blocked by: [Other team/project]
- Blocks: [Dependent project]
- Coordination needed: [With team X]

### Resource Constraints
**Team**:
- Available developers: [Number]
- Skill constraints: [Missing expertise in X]
- Time allocation: [X% of time]

**Budget**:
- Development budget: [$X]
- Infrastructure budget: [$Y/month]
- Third-party services: [$Z/month]

### Compliance & Policy
**Regulatory**:
- Must comply with: [GDPR/HIPAA/etc]
- Data residency: [Must store in region X]
- Audit requirements: [Must log Y]

**Company Policy**:
- Security standards: [Must follow X]
- Code standards: [Must follow Y]
- Approval needed: [From Z]

## 3. Domain Constraints

### Business Rules
**Invariants** (Must always be true):
- [Business rule that cannot be violated]
- [Data relationship that must hold]
- [Process that must be followed]

**Calculations**:
- [Formula that must be used]
- [Rounding rules]
- [Currency handling]

### User Constraints
**Accessibility**:
- Must meet: [WCAG 2.1 AA]
- Screen reader support: [Required]
- Keyboard navigation: [Full support]

**Usability**:
- Max clicks to action: [X]
- Mobile-first: [Required]
- Offline capability: [Partial/Full]

## 4. Data Constraints

### Data Volume
**Current**:
- Records: [X million]
- Growth rate: [Y per month]
- Peak load: [Z at time]

**Projected**:
- 1 year: [Estimated volume]
- 3 years: [Estimated volume]
- Peak scenarios: [Description]

### Data Quality
**Validation Rules**:
- Required fields: [List]
- Format constraints: [Patterns]
- Referential integrity: [Rules]

**Migration Constraints**:
- Legacy data: [Must preserve X]
- Data transformation: [Limitations]
- Downtime allowed: [Window]

## 5. Security Constraints

### Authentication & Authorization
**Requirements**:
- Auth method: [OAuth/SAML/etc]
- Password policy: [Complexity rules]
- Session management: [Timeout/tokens]
- Role-based access: [Granularity]

### Data Protection
**Encryption**:
- At rest: [Required/algorithm]
- In transit: [TLS version]
- Key management: [HSM/service]

**Privacy**:
- PII handling: [Rules]
- Data retention: [Policies]
- Right to deletion: [Process]

## 6. Operational Constraints

### Deployment
**Process**:
- Deployment window: [Day/time]
- Approval required: [From whom]
- Rollback plan: [Required]
- Zero-downtime: [Required?]

### Monitoring
**Requirements**:
- Uptime SLA: [99.X%]
- Alerting: [Thresholds]
- Logging: [Retention/detail]
- Metrics: [What to track]

## 7. Hidden Constraints

### Often Overlooked

**Cultural**:
- Internationalization: [Languages]
- Date/time formats: [Locales]
- Currency display: [Formats]
- Cultural sensitivities: [Considerations]

**Future-Proofing**:
- Extensibility needs: [Plugin system?]
- API versioning: [Strategy]
- Data model flexibility: [Schema evolution]
- Multi-tenancy: [Future requirement?]

**Organizational**:
- Team preferences: [Tech stack bias]
- Existing patterns: [Must follow]
- Political considerations: [Stakeholder preferences]
- Knowledge transfer: [Documentation needs]

## 8. Constraint Conflicts

### Identified Conflicts

**Performance vs Security**:
- [Encryption overhead vs response time]
- Resolution: [Approach]

**Usability vs Compliance**:
- [User convenience vs audit requirements]
- Resolution: [Approach]

**Budget vs Features**:
- [Desired features vs available resources]
- Resolution: [Prioritization]

## 9. Flexibility Assessment

### Negotiable Constraints
**Might be flexible**:
- [Constraint]: Could possibly [alternative]
- [Constraint]: Might extend to [new limit]

### Non-Negotiable Constraints
**Absolutely fixed**:
- [Constraint]: Because [reason]
- [Constraint]: Due to [regulation/policy]

## 10. Constraint Impact Summary

### Design Impact
**Must use patterns**:
- [Pattern] due to [constraint]
- [Architecture] due to [constraint]

**Cannot use**:
- [Technology] due to [constraint]
- [Approach] due to [constraint]

### Implementation Priority
**Constraint-driven order**:
1. [Feature] - Must do first due to [constraint]
2. [Feature] - Blocked until [constraint resolved]
3. [Feature] - Can defer due to [constraint]

**Risk Assessment**:
- High risk: [Constraint that might not be met]
- Mitigation: [Approach to handle]

**Confidence Level**: [X]% that all constraints are identified and manageable

**Next Step**: [Recommendation based on constraint analysis]