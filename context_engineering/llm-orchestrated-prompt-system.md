# LLM-Orchestrated Prompt Chain System v2.0

## Core Philosophy

**Certainty-Driven Development.** Build complete understanding before writing code. The system drives toward certainty through iterative context building and uncertainty analysis.

**Let the LLM think.** Rather than encoding rigid rules about prompt dependencies and execution order, we give the LLM a library of well-designed prompts and let it reason about how to use them.

**Uncertainty drives exploration.** The LLM's own confidence levels trigger deeper analysis, additional prompts, and refinement cycles until certainty is achieved.

**Context flows naturally.** No complex schemas or validation. The LLM manages context like a skilled developer taking notes, with safeguards against degradation.

**Human-in-the-loop at critical points.** Approval gates ensure alignment with actual needs, catching ambiguous or incorrect requirements early.

## Phase-Based Execution Model

The system operates in distinct phases, moving forward only when confidence thresholds are met:

```
1. Requirements Clarification Phase
   └─> Detect and resolve ambiguous/incorrect requirements
   
2. Context Building Phase  
   └─> Execute discovery prompts to understand the problem space
   └─> Generate new prompts if gaps detected
   
3. Uncertainty Analysis Phase
   └─> Measure confidence levels across all areas
   └─> Loop back to context building if confidence < 80%
   
4. Solution Approval Phase
   └─> Present synthesized solution to user
   └─> User approves, requests changes, or asks for more analysis
   
5. Implementation Phase
   └─> TDD-first implementation
   └─> Quality checks and security scans
   └─> Deployment readiness assessment
```

Each phase has specific prompts and exit criteria, ensuring systematic progress toward a well-understood solution.

## System Architecture

### 1. Minimal Infrastructure

```
prompt-system/
├── prompts/              # Library of focused prompts
├── contexts/             # Chain-specific context files
│   ├── chain-001-jwt-setup-2024-01-15-09-23.json
│   ├── chain-002-jwt-tests-2024-01-15-10-45.json
│   └── chain-003-user-api-2024-01-15-14-30.json
├── project_rules.md      # Project-specific memory
└── claude-exec           # Thin execution wrapper
```

No complex orchestration engine. No rigid schemas. No phase definitions.

The `claude-exec` wrapper is a simple script that:
- Creates a new chain context for each execution
- Feeds prompts to Claude (via API or terminal)
- Manages context.json updates for that specific chain
- Tracks token usage and costs per chain
- Handles user interaction for approvals

Each execution chain gets its own context file, providing complete isolation and history.

### 2. Enhanced Prompt Library

Prompts are organized for **human discovery** but the LLM can use any prompt at any time:

```
prompts/
├── system/
│   ├── new_case.md                  # Start a new analysis/task
│   ├── phase_controller.md          # Manage phase transitions
│   ├── uncertainty_analysis.md      # Analyze current uncertainties
│   ├── prompt_selector.md          # Help LLM choose next prompts
│   ├── prompt_gap_detector.md      # Identify when new prompts needed
│   ├── prompt_generator.md         # Create new prompts dynamically
│   ├── context_preservation.md     # Handle context compaction issues
│   ├── context_health_checker.md   # Monitor context degradation
│   ├── cost_monitor.md             # Track token usage/costs
│   ├── error_recovery.md           # When execution fails/crashes
│   ├── pattern_extractor.md        # Learn from successful chains
│   ├── memory_consolidator.md      # Synthesize learnings across chains
│   └── assumption_validator.md     # Verify implicit assumptions
├── requirements/
│   ├── requirement_disambiguator.md # Detect ambiguous requirements
│   ├── requirement_validator.md     # Check for incorrect requirements
│   ├── requirement_extractor.md     # Extract clear requirements
│   ├── clarification_generator.md   # Generate clarifying questions
│   └── constraint_mapper.md         # Identify constraints
├── discovery/
│   ├── trace_existing_flow.md      # Understand current code flow
│   ├── component_mapper.md         # Map system components
│   ├── dependency_analyzer.md      # Analyze dependencies
│   ├── api_surface_mapper.md       # Map all API endpoints/contracts
│   ├── database_schema_analyzer.md # Understand data models
│   ├── integration_point_finder.md # Find external dependencies
│   ├── performance_profiler.md     # Identify bottlenecks
│   ├── dead_code_detector.md       # Find unused code
│   └── hidden_coupling_finder.md   # Detect implicit dependencies
├── design/
│   ├── solution_synthesizer.md     # Synthesize solution from context
│   ├── solution_presenter.md       # Format solution for approval
│   ├── solution_designer.md        # Design implementation details
│   ├── tdd_flow_controller.md      # Test-first development
│   ├── api_contract_designer.md    # Design REST/GraphQL contracts
│   ├── migration_strategist.md     # Plan data/schema migrations
│   ├── refactoring_planner.md      # Break down large refactors
│   ├── feature_flag_designer.md    # Design toggle strategies
│   ├── cache_strategy_planner.md   # Design caching approach
│   └── error_handler_designer.md   # Design error hierarchies
├── debug/
│   ├── stack_trace_analyzer.md     # Interpret error stacks
│   ├── log_pattern_finder.md       # Analyze logs for issues
│   ├── reproduction_creator.md     # Create minimal repros
│   ├── bisect_assistant.md         # Help with git bisect
│   ├── state_debugger.md           # Debug complex state issues
│   └── heisenbug_hunter.md         # Track intermittent issues
├── testing/
│   ├── test_generator.md           # Generate comprehensive tests
│   ├── integration_test_designer.md # Design integration tests
│   ├── edge_case_generator.md      # Find boundary conditions
│   ├── mock_generator.md           # Create mocks/stubs
│   ├── test_data_factory.md        # Generate test datasets
│   ├── load_test_planner.md        # Design performance tests
│   └── snapshot_test_creator.md    # Create snapshot tests
├── quality/
│   ├── simplicity_checker.md       # Counter overengineering tendency
│   ├── security_scanner.md         # Dependency/vulnerability checks
│   ├── code_reviewer.md           # "Fast intern" review mode
│   ├── architecture_checker.md     # Check architectural alignment
│   ├── performance_auditor.md      # Analyze performance
│   ├── accessibility_checker.md    # Check a11y compliance
│   ├── api_consistency_checker.md  # Ensure API consistency
│   ├── breaking_change_detector.md # Find breaking changes
│   ├── dependency_auditor.md       # Audit dependencies
│   └── bundle_size_analyzer.md     # Analyze build outputs
├── communication/
│   ├── pr_description_writer.md    # Write PR descriptions
│   ├── adr_generator.md            # Architecture Decision Records
│   ├── status_reporter.md          # Generate status updates
│   ├── incident_postmortem.md      # Structure incident analysis
│   ├── api_doc_generator.md        # Generate API documentation
│   └── tech_debt_documenter.md     # Document technical debt
├── workflow/
│   ├── git_advisor.md              # Git best practices (no direct commits)
│   ├── pr_generator.md             # Clean PR creation
│   ├── parallel_work_planner.md    # Multi-feature orchestration
│   ├── deployment_checker.md       # Deployment readiness
│   ├── rollback_planner.md         # Plan safe rollbacks
│   ├── hotfix_orchestrator.md      # Emergency fix workflow
│   ├── release_notes_generator.md  # Generate changelogs
│   ├── environment_promoter.md     # Promote between envs
│   ├── feature_toggle_manager.md   # Manage feature flags
│   └── dependency_updater.md       # Update dependencies safely
├── stack_context/
│   ├── php_interpreter.md          # PHP-specific translations
│   ├── node_interpreter.md         # Node-specific translations
│   ├── python_interpreter.md       # Python-specific translations
│   └── stack_detector.md           # Auto-detect stack from code
├── meta/
│   ├── chain_planner.md            # Plan prompt sequences
│   ├── uncertainty_resolver.md     # Resolve specific uncertainties
│   ├── learning_optimizer.md       # Optimize prompt usage
│   ├── cost_optimizer.md           # Reduce token usage
│   ├── chain_debugger.md           # Debug failed chains
│   ├── confidence_calibrator.md    # Calibrate uncertainty levels
│   ├── prompt_effectiveness.md     # Measure prompt success
│   └── pattern_recognizer.md       # Identify reusable patterns
└── custom/                         # Project-specific prompts
    └── .gitkeep                    # Generated dynamically
```

### 3. Prompt Format

Each prompt is self-contained with metadata for LLM discovery:

```markdown
---
name: trace_existing_flow
purpose: Understand how data flows through an existing system
good_for: 
  - Debugging issues
  - Planning modifications  
  - Understanding unfamiliar code
uncertainty_reduction:
  - Code flow paths
  - Component interactions
  - Data transformations
example_context_needs:
  - Code files or structure
  - Entry point identification
  - Specific flow to trace (optional)
cost_estimate: low  # <100 tokens typical
---

# Trace Existing Flow

[Prompt content here]
```

**Cost estimates guide:**
- `minimal`: <100 tokens
- `low`: 100-1,000 tokens  
- `medium`: 1,000-5,000 tokens
- `high`: 5,000+ tokens

### 4. Enhanced Context Management

Context grows organically but with phase tracking:

```json
{
  "chain_id": "047",
  "goal": "Add JWT authentication",
  "current_phase": "uncertainty-analysis",
  "phase_history": [
    {
      "phase": "requirements-clarification",
      "duration": "3 minutes",
      "clarifications": ["Redis vs MongoDB for tokens", "Session migration needed"]
    },
    {
      "phase": "context-building", 
      "iterations": 2,
      "prompts_executed": ["trace_existing_flow", "dependency_analyzer"]
    }
  ],
  "discovered": {
    "stack": "node-express-mongo",
    "existing_auth": "session-based",
    "user_model": "models/User.js",
    "routes": ["POST /api/login", "POST /api/register"],
    "ambiguities_resolved": [
      "Refresh tokens required: YES",
      "Session migration approach: Parallel run"
    ]
  },
  "uncertainties": [
    {
      "area": "refresh_token_storage",
      "confidence": 0.85,
      "resolved": true,
      "resolution": "User confirmed Redis for tokens"
    },
    {
      "area": "backward_compatibility",
      "confidence": 0.6,
      "resolved": false,
      "blocking": true
    }
  ],
  "confidence_scores": {
    "overall": 0.82,
    "requirements": 0.95,
    "technical_approach": 0.85,
    "implementation_details": 0.65
  },
  "decisions": {
    "auth_strategy": "JWT with refresh tokens",
    "token_storage": "Redis",
    "expiry": "15min access, 7day refresh",
    "migration": "Parallel session/JWT for 30 days"
  },
  "generated_prompts": [
    {
      "name": "session_jwt_migrator",
      "reason": "No existing prompt for dual-auth migration",
      "effectiveness": 0.89
    }
  ],
  "approval_history": [
    {
      "phase": "solution-approval",
      "presented": "JWT with Redis and parallel migration",
      "user_response": "approved",
      "timestamp": "2024-01-16-14:35:22"
    }
  ],
  "session_memory": {
    "files_modified": ["routes/auth.js", "middleware/auth.js"],
    "tests_written": ["auth.test.js", "token.test.js"],
    "mistakes_corrected": ["Removed synchronous bcrypt calls"],
    "patterns_identified": ["Express middleware pattern works well"],
    "context_health": 8.5
  },
  "cost_tracking": {
    "tokens_used": 12450,
    "estimated_cost": 0.37,
    "by_phase": {
      "requirements": 0.05,
      "context_building": 0.18,
      "uncertainty_analysis": 0.08,
      "implementation": 0.06
    }
  }
}
```

### 5. The Phase Controller

The orchestrator manages phase transitions and prompt selection:

```markdown
# Phase Controller

You are managing a phased approach to software development.
Current chain context is in `contexts/chain-[ID]-[task]-[timestamp].json`.

Current phase: [requirements-clarification|context-building|uncertainty-analysis|solution-approval|implementation]
Overall confidence: [X%]

Phase-specific instructions:

## Requirements Clarification Phase
- Run requirement_disambiguator to detect ambiguities
- Run requirement_validator to check for contradictions
- Generate clarifying questions if ambiguity > 30%
- Exit when requirements confidence > 90%

## Context Building Phase  
- Select discovery prompts based on uncertainties
- Check for prompt gaps - generate new prompts if needed
- Build understanding iteratively
- Exit when ready for uncertainty analysis

## Uncertainty Analysis Phase
- Run comprehensive uncertainty_analysis
- Identify blocking uncertainties
- If overall confidence < 80%, plan more context building
- If confidence >= 80%, synthesize solution for approval

## Solution Approval Phase
- Present clear, implementable solution
- Include all key decisions and tradeoffs
- Wait for user approval/feedback
- Exit to implementation or loop back based on response

## Implementation Phase
- Start with TDD flow
- Implement in small, verifiable chunks
- Run quality checks after each chunk
- Exit when all tests pass and quality gates met

Available prompts: [List relevant to current phase]
Current context: [chain-specific context]
Blocking uncertainties: [list if any]

What is your next action?
```

### 6. Claude Code Command-Based Execution

```bash
# Start Claude Code with a new task
$ claude-code
> /new "Add JWT authentication to my Express app"

# Chain 047 created: contexts/chain-047-jwt-auth-2024-01-16-14-22.json

## Phase 1: Requirements Clarification
> Analyzing requirements for ambiguities...
> Running: requirement_disambiguator
> 
> Ambiguities detected:
> - "JWT authentication" - refresh tokens needed? [UNCLEAR]
> - "Express app" - existing auth system? [UNKNOWN]
> - Performance requirements? [NOT SPECIFIED]
>
> Clarifying questions:
> 1. Do you need refresh tokens for long-lived sessions? 
> 2. Is there existing authentication to migrate?
> 3. Expected concurrent users?
>
> User responses captured. Requirements confidence: 92%

## Phase 2: Context Building  
> Planning context discovery...
> Running: trace_existing_flow --focus auth
> Found: Session-based auth in 3 controllers
> 
> Running: dependency_analyzer
> Found: express-session, connect-mongo
>
> Running: database_schema_analyzer
> Found: users collection with password hashes (bcrypt)
>
> /uncertainty-check
> Current confidence: 68%
> Need more info on: session migration strategy
>
> Running: prompt_gap_detector
> No existing prompt for: "session-to-JWT migration"
> 
> /create-prompt --auto
> Generated: session_jwt_migration_analyzer
> Testing effectiveness... 91% uncertainty reduction
> Added to session prompts.
>
> Running: session_jwt_migration_analyzer
> Recommendation: Parallel auth for transition period
> Confidence increased to 84%

## Phase 3: Solution Approval
> /present-solution
>
> Proposed JWT Authentication Implementation:
> 
> Architecture:
> - Middleware: express-jwt + custom refresh handler
> - Storage: Redis for refresh tokens  
> - Migration: 30-day parallel session/JWT support
> 
> Key decisions:
> - Access tokens: 15 min expiry
> - Refresh tokens: 7 day expiry, rotate on use
> - Existing sessions: Honor for 30 days
> 
> Implementation approach:
> 1. TDD - write auth tests first
> 2. Implement JWT middleware
> 3. Add refresh token endpoint
> 4. Update login/register endpoints
> 5. Deploy with feature flag
>
> Approve this approach? [Y/n/more-info]

> User: more-info
> What specific information would you like?
> User: "How do we handle logout with JWT?"
>
> Running: solution_designer --focus "JWT logout"
> Logout strategy: Maintain Redis blacklist for invalidated tokens
> Updates solution...
>
> Approve updated approach? [Y/n/more-info]
> User: Y

## Phase 4: Implementation
> /implement --mode tdd
>
> Starting TDD implementation...
> Running: test_generator --feature "JWT auth"
> 
> Writing: test/auth/jwt.test.js
> Test scenarios:
> - User can login and receive tokens
> - Access token expires after 15 min
> - Refresh token rotates on use
> - Blacklisted tokens are rejected
> - Existing sessions still work
> 
> [Shows test code]
> Approve tests? [Y/n]
> Y
>
> Running: solution_designer --pattern "middleware"
> Implementing JWT middleware...
> 
> Writing: middleware/jwt-auth.js
> [Shows implementation]
> Approve? [Y/n]
> Y
>
> Running: code_reviewer --focus security
> Security check passed ✓
> - Tokens properly signed
> - Secrets from env vars
> - HttpOnly cookies for tokens
>
> Running: test_runner
> All tests passing ✓
>
> /status
> Chain 047 Progress:
> ✓ Requirements clarified (3 min)
> ✓ Context built (8 min)  
> ✓ Solution approved (4 min)
> ✓ Implementation complete (12 min)
> Total time: 27 minutes
> Total cost: $0.44
> Confidence: 95%

## Phase 5: Deployment Readiness
> Running: deployment_checker
> ✓ All tests passing
> ✓ No breaking changes
> ✓ Migration plan ready
> ✓ Feature flag configured
> 
> Ready for deployment!
> 
> /complete
> Chain 047 completed successfully.
> Pattern saved: "JWT with session migration"
> View full chain: contexts/chain-047-jwt-auth-2024-01-16-14-22.json
```

### 7. Key Prompt Examples

#### Requirement Disambiguator

```markdown
---
name: requirement_disambiguator
purpose: Detect and resolve ambiguous requirements  
good_for:
  - Catching vague specifications early
  - Preventing wrong implementations
  - Generating clarifying questions
cost_estimate: low
---

# Requirement Disambiguator

Original requirement: "[user input]"

Analyze for ambiguities:

1. Technical ambiguities:
   - "Fast" → Needs metric (response time? throughput?)
   - "Secure" → Which standards? (OWASP? SOC2?)
   - "User-friendly" → For which users? What metrics?
   
2. Scope ambiguities:
   - "Authentication" → Just login or full user management?
   - "API" → REST? GraphQL? Both?
   - "Mobile support" → Native apps or responsive web?

3. Hidden assumptions:
   - Assuming REST when GraphQL might be meant
   - Assuming single-tenant when multi-tenant needed
   - Assuming English-only when i18n required

Ambiguity score: [0-1]
Critical clarifications needed: [list]

Generate 3-5 clarifying questions that would most reduce uncertainty.
```

#### Prompt Gap Detector

```markdown
---
name: prompt_gap_detector
purpose: Identify when existing prompts can't address current needs
good_for:
  - Recognizing novel situations
  - Triggering prompt generation
  - Improving the system
cost_estimate: low
---

# Prompt Gap Detector

Current uncertainties: [list]
Available prompts searched: [list of considered prompts]
Current context: [relevant details]

Analysis:
1. Can existing prompts address these uncertainties?
2. Would combining 2-3 prompts solve this?
3. Is this a pattern we've seen before?
4. Is this genuinely novel?

If novel:
- Gap description: [what's missing]
- Suggested prompt type: [discovery|design|validation|etc]
- Similar patterns: [if any]
- Key questions the new prompt should answer: [list]

Recommendation:
- Use existing: [which prompts]
- Combine prompts: [which combination]  
- Generate new: [prompt specification]
```

#### Solution Synthesizer

```markdown
---
name: solution_synthesizer
purpose: Synthesize a complete solution from context
good_for:
  - Creating coherent proposals
  - Identifying tradeoffs
  - Preparing for user approval
cost_estimate: medium
---

# Solution Synthesizer

Context gathered: [all discoveries]
Requirements: [clarified list]
Constraints: [identified limits]
Uncertainties resolved: [list with confidence]

Synthesize a complete solution:

1. Architecture Overview
   - High-level approach
   - Key components
   - Technology choices with rationale

2. Implementation Strategy  
   - Phase 1: [MVP/core features]
   - Phase 2: [enhancements]
   - Phase 3: [optimizations]

3. Key Decisions
   - Decision: [choice] because [rationale]
   - Tradeoff: [option A vs B] chose [A] for [reason]

4. Risk Mitigation
   - Risk: [description] → Mitigation: [approach]

5. Success Criteria
   - Functional: [measurable outcomes]
   - Non-functional: [performance, security, etc]

Confidence in solution: [%]
Remaining concerns: [if any]
```

#### Dynamic Prompt Generator

```markdown
---
name: prompt_generator
purpose: Create new prompts for novel situations
good_for:
  - Handling unique requirements
  - Extending system capabilities
  - Learning from new patterns
cost_estimate: medium
---

# Dynamic Prompt Generator

Gap identified: [description]
Context needed: [what information would help]
Similar prompts: [existing prompts that are close]

Generate a new prompt:

1. Name: [descriptive_snake_case]
2. Purpose: [one line description]
3. Good for: [3-4 use cases]
4. Uncertainty reduction: [what it clarifies]

5. Prompt structure:
   - Input requirements
   - Analysis steps  
   - Output format
   - Success criteria

6. Test cases:
   - Input: [example] → Expected: [output]

7. Effectiveness prediction: [0-1]

Generate the complete prompt in standard format...
```

### 8. Dynamic Prompt System

The system adapts by generating new prompts when gaps are detected:

#### Automatic Prompt Generation Flow

```python
# When prompt_gap_detector identifies a need
if gap_detected and confidence < 0.7:
    # Generate specialized prompt
    new_prompt = prompt_generator.create(
        gap_description=gap,
        context=current_context,
        similar_prompts=find_similar()
    )
    
    # Test effectiveness
    test_result = test_prompt_on_current_context(new_prompt)
    
    if test_result.uncertainty_reduction > 0.7:
        # Add to session
        add_to_custom_prompts(new_prompt)
        
        # If highly effective, mark for library
        if test_result.uncertainty_reduction > 0.85:
            mark_for_promotion(new_prompt)
```

#### Learning from Patterns

```bash
# After successful chain completion
> /save-pattern

Pattern Analysis for Chain 047:
- Type: "Authentication with migration"  
- Key prompts used: 12
- Custom prompts generated: 1 (session_jwt_migrator)
- Uncertainty reduction path: 45% → 68% → 84% → 95%
- User approval iterations: 2

Save as reusable pattern? [Y/n]
> Y

Pattern saved. Will auto-suggest for similar contexts.
```

#### Prompt Effectiveness Tracking

```json
{
  "prompt_metrics": {
    "requirement_disambiguator": {
      "usage_count": 234,
      "avg_uncertainty_reduction": 0.82,
      "avg_tokens": 450,
      "user_satisfaction": 0.91,
      "common_patterns": ["auth", "api", "migration"]
    },
    "custom/session_jwt_migrator": {
      "usage_count": 8,
      "avg_uncertainty_reduction": 0.89,
      "promotion_status": "pending_review",
      "created_from_chain": "047"
    }
  }
}
```

### 9. Project Rules (project_rules.md)

```markdown
# Project Rules for LLM Orchestration

## Always
- Start with simplest solution that works
- Write tests first (TDD)
- Preserve context proactively at 80% capacity (~80k of 100k tokens)
- Track cost per feature
- Review every line of generated code
- Break complex tasks into 2-3 minute chunks
- Verify dependencies are current and secure

## Never  
- Execute git commits directly (use git_advisor)
- Modify core infrastructure without approval
- Create abstractions before they're needed
- Trust generated code without review
- Ignore uncertainty above 40%
- Skip tests to save time

## When Uncertain
- Uncertainty > 60%: Ask for clarification
- Uncertainty 40-60%: Document assumption, mark for validation
- Uncertainty < 40%: Proceed with current understanding
- Always run uncertainty_analyzer for critical paths

## Code Quality Gates
- All tests must pass
- Security scanner must clear
- Simplicity score must be < 7
- Code review must complete

## Context Management
- Run health check every 10 prompts
- Preserve context before major transitions
- Document key decisions in session_memory
- Reset if health score < 6
```

### 10. Claude Code Custom Commands

Create custom commands to support the phase-based workflow:

```javascript
// .claude/commands/uncertainty-check.js
module.exports = {
  name: 'uncertainty-check',
  description: 'Analyze uncertainties and decide next phase',
  execute: async (context) => {
    const result = await runPrompt('uncertainty_analysis', context);
    const confidence = calculateOverallConfidence(result);
    
    if (confidence < 0.8) {
      console.log(`Confidence: ${confidence}. Planning more discovery...`);
      return await runPrompt('chain_planner', {
        ...context,
        focus: 'reduce_uncertainty'
      });
    } else {
      console.log(`Confidence: ${confidence}. Ready for solution synthesis.`);
      context.phase = 'solution-approval';
      return '/present-solution';
    }
  }
};

// .claude/commands/present-solution.js  
module.exports = {
  name: 'present-solution',
  description: 'Synthesize and present solution for approval',
  execute: async (context) => {
    const solution = await runPrompt('solution_synthesizer', context);
    console.log(formatSolution(solution));
    
    const response = await prompt('Approve? [Y/n/more-info]');
    
    switch(response.toLowerCase()) {
      case 'y':
        context.phase = 'implementation';
        return '/implement --mode tdd';
      case 'n':
        return await runPrompt('requirement_disambiguator', {
          ...context,
          focus: 'understand_rejection'
        });
      case 'more-info':
        const question = await prompt('What would you like to know?');
        return await runPrompt('uncertainty_resolver', {
          ...context,
          specific_question: question
        });
    }
  }
};

// .claude/commands/create-prompt.js
module.exports = {
  name: 'create-prompt',
  description: 'Generate new prompt for novel situation',
  options: {
    auto: {
      type: 'boolean',
      description: 'Auto-generate without review'
    }
  },
  execute: async (context, { auto }) => {
    const gap = context.last_gap_detected;
    const newPrompt = await runPrompt('prompt_generator', {
      gap,
      context
    });
    
    if (auto) {
      const effectiveness = await testPrompt(newPrompt, context);
      if (effectiveness > 0.7) {
        saveToCustomPrompts(newPrompt);
        console.log(`✓ Generated ${newPrompt.name} (${effectiveness} effectiveness)`);
      }
    } else {
      console.log(formatPrompt(newPrompt));
      const save = await confirm('Save this prompt?');
      if (save) saveToCustomPrompts(newPrompt);
    }
  }
};
```

### 11. Cost Management

Track and optimize token usage per chain:

```bash
$ claude-exec cost-report

Chain Cost Summary (Last 7 days):
- Total chains: 23
- Total cost: $14.52
- Average per chain: $0.63

Most expensive chains:
1. chain-015-refactor-db (45,231 tokens - $1.36)
2. chain-022-debug-auth (38,455 tokens - $1.15)
3. chain-019-api-redesign (32,122 tokens - $0.96)

Cost by prompt type:
- solution_designer: 35% of costs
- code_reviewer: 22% of costs  
- test_generator: 15% of costs

Optimization suggestions:
- Chains over $1 should be broken down
- Use compact mode for reviews
- Clear context between major transitions
```

Monitor individual chain costs:

```bash
$ claude-exec chain-cost 047
> Chain 047 (jwt-auth):
>   - Total tokens: 12,450
>   - Estimated cost: $0.22
>   - Duration: 12 minutes
>   - Prompts executed: 8
>   - Cost per prompt: $0.027
```

### 12. Advanced Patterns

#### Pattern Recognition and Reuse
```bash
# After completing a complex task
$ claude-code
> /save-pattern

Analyzing chain 047 for patterns...
Found pattern: "Legacy Auth Migration"

Components:
- Requirements disambiguation (3 questions)
- Parallel run strategy 
- Custom prompt needed: migration analyzer
- Average duration: 25-30 minutes
- Success indicators: All tests pass, zero downtime

Save this pattern? [Y/n]
> Y

# Later, when similar task appears
> /new "Add OAuth to existing session-based app"
> Similar pattern detected: "Legacy Auth Migration" (87% match)
> 
> Apply pattern with adaptations? [Y/n]
> Y
>
> Adapting pattern for OAuth...
> - Using same parallel run approach
> - Generating: session_oauth_migration_analyzer
> - Estimated duration: 25-30 minutes
```

#### Handling Novel Situations
```bash
> /new "Implement WebAuthn with biometric fallback"
>
> Running: prompt_gap_detector
> No existing prompts for: WebAuthn implementation
> Confidence with existing prompts: 34%
>
> This appears to be a novel requirement.
> Options:
> 1. Research mode - gather external resources
> 2. First principles - break down into known components  
> 3. Generate specialized prompts
>
> Choice [1/2/3]: 3
>
> Generating prompts for WebAuthn...
> /create-prompt --auto
> Generated: webauthn_flow_analyzer
> Generated: biometric_fallback_designer
> Generated: browser_compatibility_checker
>
> Running generated prompts...
> Confidence increased to 76%
```

#### Multi-Chain Orchestration
```bash
# Working on large feature with sub-tasks
> /new "Complete payment system overhaul"
>
> This is complex. Suggested breakdown:
> - Chain A: Payment provider integration
> - Chain B: Webhook handling  
> - Chain C: Refund flow
> - Chain D: Admin dashboard
>
> Run in parallel? [Y/n]
> Y
>
> Spawning sub-chains...
> Use /switch [A|B|C|D] to move between chains
> Use /status all to see progress
>
> /switch A
> Switched to: Payment provider integration
> Current phase: context-building
> Confidence: 72%
```

#### Emergency Recovery
```bash
# When things go wrong
> Error: Context corrupted after system crash
>
> /recover
> Found partial context from 10 minutes ago
> Attempting recovery...
>
> Recovered:
> - Goal: Add JWT auth
> - Phase: implementation
> - Completed: Tests written, middleware 50% done
> - Lost: Last 2 file modifications
>
> Recovery options:
> 1. Continue from last good state
> 2. Re-run last phase with context
> 3. Start fresh with learnings
>
> Choice [1/2/3]: 1
> Restored to last good state. Continue with /implement
```

### 13. Deployment Guide

### Option 1: Local Terminal with Claude Code

The simplest deployment uses Claude Code directly:

```bash
# 1. Install Claude Code (if not already installed)
npm install -g @anthropic/claude-code

# 2. Set up the project structure
git clone your-prompt-system-repo
cd prompt-system
mkdir contexts

# 3. Create a simple executor script
cat > claude-exec.sh << 'EOF'
#!/bin/bash
set -e

# Configuration
PROMPT_DIR="./prompts"
CONTEXT_DIR="./contexts"

# Get task from argument
TASK="$1"
if [ -z "$TASK" ]; then
    echo "Usage: ./claude-exec.sh 'task description'"
    exit 1
fi

# Generate chain ID and filename
TIMESTAMP=$(date +%Y-%m-%d-%H-%M-%S)
CHAIN_ID=$(find "$CONTEXT_DIR" -name "chain-*.json" 2>/dev/null | wc -l | xargs printf "%03d")
SAFE_TASK=$(echo "$TASK" | tr ' /' '-' | tr -cd '[:alnum:]-')
CONTEXT_FILE="$CONTEXT_DIR/chain-${CHAIN_ID}-${SAFE_TASK}-${TIMESTAMP}.json"

# Initialize context
cat > "$CONTEXT_FILE" << JSON
{
  "chain_id": "$CHAIN_ID",
  "goal": "$TASK",
  "started": "$TIMESTAMP",
  "uncertainties": [],
  "decisions": {},
  "prompts_executed": [],
  "cost_tracking": {
    "tokens_used": 0,
    "estimated_cost": 0
  }
}
JSON

echo "Starting chain $CHAIN_ID for: $TASK"
echo "Context: $CONTEXT_FILE"

# Execute with Claude Code
claude-code "$TASK" \
  --context-file "$CONTEXT_FILE" \
  --rules-file ./project_rules.md \
  --verbose
EOF

chmod +x claude-exec.sh

# 4. Run your first task
./claude-exec.sh "Add user authentication to my Express app"
```

### Option 2: API-Based Deployment

For programmatic usage with the Anthropic API:

```python
#!/usr/bin/env python3
# claude-exec.py

import os
import json
import sys
from datetime import datetime
from anthropic import Anthropic
from pathlib import Path

class ClaudeOrchestrator:
    def __init__(self, api_key=None):
        self.client = Anthropic(api_key=api_key or os.environ.get("ANTHROPIC_API_KEY"))
        self.prompt_dir = Path("./prompts")
        self.context_dir = Path("./contexts")
        self.context_dir.mkdir(exist_ok=True)
        
    def create_chain_context(self, task):
        """Create a new chain context file"""
        timestamp = datetime.now().strftime("%Y-%m-%d-%H-%M-%S")
        chain_id = len(list(self.context_dir.glob("chain-*.json")))
        safe_task = "".join(c for c in task if c.isalnum() or c in " -").replace(" ", "-")
        
        filename = f"chain-{chain_id:03d}-{safe_task}-{timestamp}.json"
        context_path = self.context_dir / filename
        
        context = {
            "chain_id": f"{chain_id:03d}",
            "goal": task,
            "started": timestamp,
            "uncertainties": [],
            "decisions": {},
            "prompts_executed": [],
            "cost_tracking": {"tokens_used": 0, "estimated_cost": 0}
        }
        
        with open(context_path, 'w') as f:
            json.dump(context, f, indent=2)
            
        return context_path, context
    
    def load_prompt(self, prompt_name):
        """Load a prompt from the library"""
        prompt_path = self.prompt_dir / prompt_name
        if prompt_path.exists():
            return prompt_path.read_text()
        # Search in subdirectories
        for prompt_file in self.prompt_dir.rglob(f"*{prompt_name}*"):
            return prompt_file.read_text()
        raise FileNotFoundError(f"Prompt not found: {prompt_name}")
    
    def execute_chain(self, task):
        """Execute a prompt chain for the given task"""
        context_path, context = self.create_chain_context(task)
        print(f"Starting chain {context['chain_id']} for: {task}")
        print(f"Context: {context_path}")
        
        # Load the orchestrator prompt
        orchestrator_prompt = self.load_prompt("new_case.md")
        
        # Prepare the initial message
        message = f"""
{orchestrator_prompt}

Current task: {task}
Current context: {json.dumps(context, indent=2)}

Available prompts in library:
{self._list_available_prompts()}

Begin orchestration. What's your execution plan?
"""
        
        # Execute with Claude
        response = self.client.messages.create(
            model="claude-3-opus-20240229",
            max_tokens=4000,
            messages=[{"role": "user", "content": message}]
        )
        
        print("\nClaude's Response:")
        print(response.content[0].text)
        
        # Update context with response
        context["last_response"] = response.content[0].text
        context["cost_tracking"]["tokens_used"] += response.usage.input_tokens + response.usage.output_tokens
        
        with open(context_path, 'w') as f:
            json.dump(context, f, indent=2)
            
        return context_path
    
    def _list_available_prompts(self):
        """List all available prompts"""
        prompts = []
        for prompt_file in self.prompt_dir.rglob("*.md"):
            relative_path = prompt_file.relative_to(self.prompt_dir)
            prompts.append(str(relative_path))
        return "\n".join(f"- {p}" for p in sorted(prompts))

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python claude-exec.py 'task description'")
        sys.exit(1)
    
    task = sys.argv[1]
    orchestrator = ClaudeOrchestrator()
    orchestrator.execute_chain(task)
```

```bash
# Install dependencies
pip install anthropic

# Set API key
export ANTHROPIC_API_KEY="your-api-key-here"

# Run
python claude-exec.py "Add user authentication"
```

### Option 3: Web Interface Deployment

Create a simple web interface for the system:

```javascript
// server.js - Simple Express server
const express = require('express');
const { Anthropic } = require('@anthropic/sdk');
const fs = require('fs').promises;
const path = require('path');

const app = express();
app.use(express.json());
app.use(express.static('public'));

const anthropic = new Anthropic({
  apiKey: process.env.ANTHROPIC_API_KEY,
});

app.post('/api/execute', async (req, res) => {
  const { task } = req.body;
  
  // Create chain context
  const timestamp = new Date().toISOString().replace(/[:]/g, '-');
  const chainId = (await fs.readdir('./contexts')).length;
  const contextFile = `./contexts/chain-${chainId.toString().padStart(3, '0')}-${timestamp}.json`;
  
  const context = {
    chain_id: chainId,
    goal: task,
    started: timestamp,
    uncertainties: [],
    decisions: {},
  };
  
  await fs.writeFile(contextFile, JSON.stringify(context, null, 2));
  
  // Execute with Claude
  const orchestratorPrompt = await fs.readFile('./prompts/system/new_case.md', 'utf8');
  
  const response = await anthropic.messages.create({
    model: 'claude-3-opus-20240229',
    max_tokens: 4000,
    messages: [{
      role: 'user',
      content: `${orchestratorPrompt}\n\nTask: ${task}\nContext: ${JSON.stringify(context)}`
    }]
  });
  
  res.json({
    chain_id: chainId,
    response: response.content[0].text,
    context_file: contextFile
  });
});

app.listen(3000, () => {
  console.log('Prompt system running on http://localhost:3000');
});
```

### Configuration Files

Create these configuration files:

**`.env`** - Environment configuration
```bash
ANTHROPIC_API_KEY=your-api-key-here
TOKEN_LIMIT_WARNING=80000
COST_ALERT_THRESHOLD=5.00
DEFAULT_MODEL=claude-3-opus-20240229
```

**`config.json`** - System configuration
```json
{
  "models": {
    "default": "claude-3-opus-20240229",
    "fast": "claude-3-sonnet-20240229",
    "smart": "claude-3-opus-20240229"
  },
  "limits": {
    "max_tokens_per_prompt": 4000,
    "context_window": 100000,
    "warning_threshold": 0.8
  },
  "prompts": {
    "system_dir": "./prompts/system",
    "custom_dir": "./prompts/custom"
  }
}
```

### Production Deployment Checklist

- [ ] **Set up version control**
  ```bash
  git init
  echo "contexts/" >> .gitignore
  echo ".env" >> .gitignore
  git add .
  git commit -m "Initial prompt system setup"
  ```

- [ ] **Configure monitoring**
  - Set up cost alerts
  - Track chain success rates
  - Monitor token usage trends

- [ ] **Implement safety measures**
  - Add rate limiting
  - Implement approval workflows for destructive operations
  - Set up backup for contexts directory

- [ ] **Create operational scripts**
  ```bash
  # Clean old chains
  find contexts -name "*.json" -mtime +30 -delete
  
  # Backup contexts
  tar -czf "contexts-backup-$(date +%Y%m%d).tar.gz" contexts/
  
  # Analyze costs
  ./claude-exec cost-report --days 7
  ```

- [ ] **Set up team access**
  - Share API keys securely
  - Document custom prompts
  - Create onboarding guide

### Quick Start Commands

```bash
# Clone the starter template
git clone https://github.com/yourusername/llm-prompt-system-starter
cd llm-prompt-system-starter

# Install dependencies
npm install  # or pip install -r requirements.txt

# Configure
cp .env.example .env
# Edit .env with your API key

# Create your first prompt
cp prompts/templates/basic.md prompts/custom/my-first-prompt.md

# Run your first chain
./claude-exec "Build a REST API for user management"

# View the results
cat contexts/chain-001-*.json | jq .
```

The system is now battle-tested with community insights while maintaining its elegant, uncertainty-driven core.