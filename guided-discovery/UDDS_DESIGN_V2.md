# Uncertainty-Driven Discovery System (UDDS) Design V2

## Executive Summary

UDDS is a phase-based discovery system where Claude orchestrates the exploration process by assessing uncertainties and recommending next steps. The system provides Claude Code commands that track discoveries and support uncertainty-driven development. Prompts automatically generate structured discoveries that build up the context.

## Core Requirements

### 1. Phase-Based Workflow

The system operates in four distinct phases:

- **Discovery Phase**: Understanding what exists and identifying uncertainties
- **Planning Phase**: Designing solutions to address requirements
- **Implementation Phase**: Building the solution (happens outside UDDS)
- **Validation Phase**: Verifying the implementation meets requirements

### 2. Claude-Driven Orchestration

Claude acts as the orchestrator:
- Claude reads the context built from prompt discoveries
- Claude assesses which uncertainties remain based on discoveries
- Claude recommends which commands to run next
- Claude evaluates confidence based on what has been discovered
- User decides when to transition phases based on Claude's assessment

### 3. Uncertainty Assessment

- No hardcoded confidence values
- Claude evaluates which uncertainties are resolved based on prompt discoveries
- Claude determines confidence level from accumulated discoveries
- Prompts reveal new uncertainties through structured exploration
- Context automatically tracks discoveries from prompt outputs

### 4. Flexible Execution Options

Claude can recommend:
1. **Use existing chains** - Pre-built sequences for common scenarios
2. **Create custom chains** - Compose prompts into new sequences  
3. **Run individual prompts** - Target specific uncertainties

User executes the recommended commands.

### 5. User-Controlled Phase Transitions

- Claude recommends when ready for next phase based on discoveries
- Claude provides confidence assessment with reasoning
- **User decides when to transition phases**
- User must be satisfied with certainty level before moving forward
- Can return to earlier phases if more discovery needed

## System Architecture

### Context Structure

```json
{
  "task_id": "task-20240115-payment-api",
  "description": "Implement payment processing API",
  "current_phase": "discovery",
  "phase_history": [
    {
      "phase": "discovery",
      "entered_at": "2024-01-15T10:00:00Z",
      "reason": "Starting new task"
    }
  ],
  "uncertainties": {
    "TECH-001": {
      "name": "Current tech stack",
      "priority": "blocking",
      "status": "resolved",
      "resolved_by": ["tech_stack_explorer"]
    },
    "PAY-001": {
      "name": "Payment requirements",
      "priority": "blocking",
      "status": "resolved",
      "resolved_by": ["payment_requirements_analyzer"]
    },
    "SEC-001": {
      "name": "Security requirements",
      "priority": "high",
      "status": "partial",
      "addressed_by": ["compliance_explorer"]
    },
    "SCALE-001": {
      "name": "Expected transaction volume",
      "priority": "medium",
      "status": "unresolved"
    }
  },
  "discoveries": {
    "technical": {
      "language": "JavaScript",
      "framework": "Express",
      "database": "PostgreSQL"
    },
    "requirements": {
      "pci_compliance": true,
      "payment_providers": ["stripe", "paypal"]
    },
    "security": {
      "authentication": "JWT",
      "compliance_level": "PCI SAQ-A"
    }
  },
  "chains_executed": [
    {
      "chain": "general_discovery",
      "timestamp": "2024-01-15T10:15:00Z",
      "prompts_run": ["tech_stack_explorer", "requirements_analyzer"]
    },
    {
      "chain": "payment_discovery", 
      "timestamp": "2024-01-15T10:30:00Z",
      "prompts_run": ["payment_requirements_analyzer", "compliance_explorer"]
    }
  ]
}
```

### Commands

#### `/discover <task description>`
- Creates new discovery context
- Initializes in discovery phase
- Claude identifies initial uncertainties from the task
- Claude recommends initial chains to run

#### `/chain <chain_name> [options]`
- Executes a predefined chain of prompts
- Prompts generate structured discoveries
- Context automatically updated with discoveries
- Claude reads updated context and recommends next steps
- Options:
  - `--prompts <prompt1,prompt2>`: Create ad-hoc chain

#### `/prompt <prompt_name>`
- Execute individual prompt
- Prompt generates discoveries
- Context updated automatically

#### `/context <subcommand>`
- `status`: Shows current phase and discoveries
- `phase <phase>`: Request phase transition (requires user confirmation)
- `list`: Show all discovery contexts

#### `/uncertainty <subcommand> [args]`
- `status`: Show all uncertainties with current resolution status
- `analyze`: Claude analyzes uncertainties vs discoveries and recommends next steps
- `<uncertainty_id>`: Deep dive into specific uncertainty
- Shows what would help resolve it
- Claude recommends targeted prompts or chains

### Chains

Chains are YAML files that define reusable prompt sequences:

```yaml
name: payment_discovery
description: Explore payment processing requirements
targets_phase: discovery
prompts:
  - payment_requirements_analyzer
  - compliance_explorer
  - provider_comparison
  - security_assessment
# No hardcoded confidence or effectiveness values
```

### Prompts

Individual discovery tools the LLM can use:

```yaml
name: payment_compliance_explorer
targets_uncertainties: ["SEC-001", "COMPLY-001"]
template: |
  Analyze the codebase and requirements to understand:
  1. What compliance standards apply (PCI, SOC2, etc.)
  2. Current compliance measures
  3. Gaps that need addressing
# No predetermined outcomes or confidence impacts
```

## Workflow Examples

### Example 1: Complete Phase Flow

```
User: /discover Implement payment processing API

[Context created with task, phase set to "discovery"]

Claude: I've identified these uncertainties for the task:
- TECH-001: Current tech stack (blocking)
- PAY-001: Payment requirements (blocking)
- SEC-001: Security requirements (high)
- SCALE-001: Expected transaction volume (medium)

Currently in Discovery phase. To address the blocking uncertainties, I recommend:
/chain payment_discovery (addresses PAY-001, SEC-001)

User: /chain payment_discovery

[Chain runs: payment_requirements_analyzer, compliance_explorer, provider_comparison]
[Context updated with discoveries: Stripe preferred, PCI compliance needed, 50k/month volume]

Claude: Good progress! Uncertainty status:
- PAY-001: ✓ Resolved (Stripe identified as best fit)
- SEC-001: ⚡ Partial (PCI SAQ-A identified, but webhook security unclear)
- TECH-001: ✗ Unresolved (still need tech stack info)
- SCALE-001: ✓ Resolved (50k transactions/month)

Confidence: 65% - Resolved 2/4 uncertainties, 1 partial.

To address TECH-001 (blocking), recommend:
/chain tech_analysis

User: /chain tech_analysis  

[Chain runs, discovers: Node.js/Express, JWT auth, PostgreSQL]

Claude: Excellent! Uncertainty status update:
- TECH-001: ✓ Resolved (Node.js, Express, PostgreSQL, JWT auth)
- PAY-001: ✓ Resolved
- SEC-001: ✓ Resolved (webhook security approach clarified)
- SCALE-001: ✓ Resolved

All discovery phase uncertainties resolved!
Confidence: 90% - Comprehensive understanding achieved.

I recommend transitioning to Planning phase where we'll address new uncertainties about:
- Architecture design
- Migration strategy
- API design patterns

Are you satisfied with our discovery and ready to move to planning?

User: /context phase planning

[Phase updated to "planning"]

Claude: Transitioned to Planning phase. Now we can run:
- /chain architecture_planning - Design the payment system
- /chain api_design - Plan the API endpoints
- /chain security_planning - Plan security measures

I recommend starting with architecture_planning.
```

### Example 2: Individual Prompts and Custom Chains

```
User: /context status

Claude: Current phase: Discovery
Recent discoveries:
- Database: PostgreSQL with user sessions
- Auth: Session-based (not JWT)

I notice we're using sessions but considering JWT. Let me run specific prompts to understand the auth migration needs:

Run: /prompt jwt_migration_analyzer

User: /prompt jwt_migration_analyzer

[Prompt analyzes current session system and JWT requirements]

Claude: The prompt revealed:
- Current: Express sessions with Redis store
- Migration complexity: Medium (need to handle active sessions)
- Benefits: Stateless, better for microservices

For deeper analysis, I recommend a custom chain:
/chain --prompts session_analyzer,jwt_explorer,migration_planner

User: /chain --prompts session_analyzer,jwt_explorer,migration_planner

[Custom chain executes all three prompts]

Claude: Custom chain discoveries:
- 10k active sessions to migrate
- JWT secret rotation strategy needed
- Gradual migration path available

This gives us 75% confidence on the auth migration approach.
```

### Example 3: User-Controlled Phase Transitions

```
User: /context status

Claude: Current phase: Discovery
Task: Migrate monolith to microservices

From discoveries so far:
- Current architecture: Django monolith
- Database: Single PostgreSQL instance  
- Identified domains: Users, Orders, Inventory, Shipping
- Service boundaries: Still unclear

Confidence: 72% - We understand the system but need clearer boundaries.

Key remaining uncertainties:
- How to split the database
- Service communication patterns
- Transaction boundaries

I recommend:
1. /chain domain_analysis - Clarify service boundaries
2. /chain data_decomposition - Plan database splitting
3. /prompt transaction_analyzer - Understand transaction needs

We need about 80-85% confidence before moving to Planning phase.

User: /chain domain_analysis

[Chain executes bounded context analysis]

Claude: Excellent discoveries:
- Clear bounded contexts identified
- Service boundaries now well-defined
- Shared kernel minimized

Confidence: 87% - I believe we're ready for Planning phase.

The discoveries show:
- User service: Authentication, profiles
- Order service: Order processing, payment
- Inventory service: Stock, warehouses  
- Shipping service: Fulfillment, tracking

Shall we transition to Planning phase to design the migration approach?

User: Yes, let's move to planning

User: /context phase planning

Claude: Transitioned to Planning phase. Now we can focus on:
- Migration strategy (strangler fig vs big bang)
- Service communication design
- Data migration approach

Recommend starting with: /chain migration_strategy_planner
```

### Example 4: Uncertainty Analysis

```
User: /uncertainty analyze

Claude: Analyzing uncertainties for current task...

Current Phase: Discovery
Total Uncertainties: 8

Status Breakdown:
✓ Resolved (3):
  - ARCH-001: Current architecture → Django monolith confirmed
  - DATA-001: Database structure → Single PostgreSQL identified
  - DOMAIN-001: Business domains → 4 domains mapped

⚡ Partial (2):
  - BOUND-001: Service boundaries → Domains identified, exact cuts unclear
  - TRANS-001: Transactions → Know they exist, patterns unclear

✗ Unresolved (3):
  - COMM-001: Service communication approach (blocking)
  - SPLIT-001: Database splitting strategy (blocking)
  - DEPLOY-001: Deployment architecture (medium)

Confidence: 62% - Good domain understanding, technical approach unclear

Priority recommendations to reach 80%+ confidence:
1. /chain technical_decomposition (addresses COMM-001, SPLIT-001)
2. /prompt transaction_boundary_analyzer (completes TRANS-001)
3. /uncertainty COMM-001 (deep dive if still unclear)

The blocking uncertainties prevent moving to Planning phase.
```

## Key Differences from V1

### What's Removed:
- Hardcoded confidence values in chains/prompts
- Static effectiveness ratings
- Complex llm_assessments array in context
- Automatic phase transitions
- Fixed confidence calculations

### What's Clarified:
- Context builds from prompt discoveries automatically
- Claude orchestrates by reading context and recommending commands
- User runs the commands
- User controls phase transitions
- Prompts generate structured discoveries

### Core Workflow:
1. User runs discovery commands
2. Prompts generate structured findings
3. Context accumulates discoveries automatically
4. Uncertainties are resolved based on discoveries
5. Claude reads context and analyzes uncertainties
6. Claude recommends commands to address remaining uncertainties
7. Claude assesses confidence based on uncertainty resolution
8. User decides when to change phases based on confidence
9. New phase brings new phase-appropriate uncertainties
10. Process repeats until user is satisfied

### How Phases and Uncertainties Work Together:
- **Discovery Phase**: Focus on uncertainties about what exists (TECH-001, ARCH-001)
- **Planning Phase**: Focus on uncertainties about how to build (DESIGN-001, PATTERN-001)
- **Validation Phase**: Focus on uncertainties about quality (QUALITY-001, PERF-001)
- Uncertainties drive what commands to run
- Phases guide which uncertainties to prioritize
- User controls phase transitions when satisfied with certainty

## Implementation Requirements

### Core Changes Needed:

1. **Context Structure**
   - Add current_phase field
   - Add phase_history tracking
   - Add uncertainties tracking with status
   - Remove any hardcoded confidence
   - Keep discoveries structure for prompt outputs

2. **Command Enhancements**
   - `/chain` shows what was discovered and updates uncertainties
   - `/prompt` command for individual execution
   - `/context phase` for user-controlled transitions
   - `/uncertainty analyze` for uncertainty assessment
   - Commands output data for Claude to interpret

3. **Chain/Prompt Files**
   - Remove confidence_gain fields
   - Remove effectiveness ratings
   - Remove rigid success_criteria
   - Keep only descriptive metadata

4. **Claude Integration Points**
   - After discover: Claude identifies uncertainties
   - After chain/prompt: Claude assesses discoveries
   - On context status: Claude provides recommendations
   - On phase request: Claude evaluates readiness

## Success Criteria

The system is successful when:

1. **Claude orchestrates effectively** - Recommends appropriate commands
2. **Discoveries build naturally** - From prompt outputs to context
3. **User controls progression** - Decides when ready for next phase
4. **Flexible execution** - Chains, custom chains, or individual prompts
5. **Clear confidence** - Claude explains reasoning based on discoveries
6. **Phase-appropriate work** - Right chains for current phase

## Anti-Patterns to Avoid

1. **Hardcoding confidence changes**
2. **Forcing specific chain sequences**
3. **Automatic phase transitions**
4. **Predetermined uncertainty resolution**
5. **Static assessment criteria**
6. **Treating chains as mandatory flows**

## Future Enhancements

1. **Learning System**
   - Save successful custom chains
   - Track which approaches work best
   - Suggest based on similar tasks

2. **Team Collaboration**
   - Multiple LLMs can assess
   - Share discovery contexts
   - Build organizational knowledge

3. **Integration Points**
   - Connect to code analysis tools
   - Import uncertainties from other systems
   - Export discoveries to documentation