# Automated Prompt Chain Workflow Example

This example demonstrates the complete workflow for using the automated prompt chain system.

## Example Task: "Add JWT authentication to Express app"

### Step 1: Start New Task

```
User: /orchestrator-new "Add JWT authentication to Express app"

System: 
🎯 Creating Task Context

Context File: contexts/chain-001-jwt-auth-2025-01-15-14-30.json

Phase 1: Requirements Clarification
Current Confidence: 25% (starting baseline)

🔗 Planning First Chain

Recommended Chain:
1. requirement_disambiguator - Identify ambiguities
2. constraint_mapper - Discover constraints
3. scope_boundary_definer - Define boundaries
4. success_criteria_extractor - Extract success metrics

Expected Outcome: Requirements confidence 25% → 85%+

Ready to execute? Use /execute-chain
```

### Step 2: Execute First Chain

```
User: /execute-chain

System:
🚀 Chain Execution Process

Executing Chain: Requirements Clarification
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[1/4] requirement_disambiguator ✓
      Found: Need refresh tokens, 15min access token expiry
      
[2/4] constraint_mapper ✓
      Found: Must maintain existing session auth for 30 days
      
[3/4] scope_boundary_definer ✓
      In scope: Login, register, refresh
      Out: Social auth, MFA
      
[4/4] success_criteria_extractor ✓
      Success: <100ms auth check, zero downtime migration
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📈 Chain Results
Confidence: 25% → 85% (↑60%)

Key Findings:
- JWT with refresh tokens required
- Parallel session/JWT for migration
- Performance target: <100ms

Next recommended chain:
1. architecture_detector
2. component_mapper
3. trace_existing_flow
4. dependency_analyzer

Ready for next chain? Use /execute-chain
```

### Step 3: Check Context

```
User: /show-context

System:
📊 Current State

Phase Progress:
✓ Requirements Clarification    [85% confident]
→ Context Building             [Current - 25% confident]
  Solution Design              [Not started]
  Implementation               [Not started]

Confidence Scores:
Requirements:    ████████████████░░░░  85%
Technical:       █████░░░░░░░░░░░░░░  25%
Implementation:  █████░░░░░░░░░░░░░░  25%
Overall:         ███████████░░░░░░░░░  55%

Key Discoveries:
- JWT + refresh tokens (15min/7day)
- Parallel auth for migration
- Performance: <100ms requirement

Next: Execute technical discovery chain
```

### Step 4: Execute Second Chain

```
User: /execute-chain

System:
🚀 Executing Chain: Technical Discovery
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[1/4] architecture_detector ✓
      Found: Express + MongoDB, MVC pattern
      
[2/4] component_mapper ✓
      Auth: /middleware/auth.js, /routes/auth.js
      User: /models/User.js
      
[3/4] trace_existing_flow ✓
      Session: express-session + connect-mongo
      Password: bcrypt rounds=10
      
[4/4] dependency_analyzer ✓
      Critical: express-session, mongoose
      Can add: jsonwebtoken, redis
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📈 Chain Results
Technical confidence: 25% → 90% (↑65%)
Overall: 55% → 87% (↑32%)

Ready to advance to Solution Design phase!
```

### Step 5: Uncertainty Check

```
User: /uncertainty-check

System:
📊 Confidence Scoring

Requirements:    ████████████████░░░░  85%
Technical:       ██████████████████░░  90%
Implementation:  ███████░░░░░░░░░░░░░  35%
Overall:         ████████████████░░░░  87%

✅ High Confidence (>80%)
Ready to design solution!

Remaining uncertainties (minor):
- Redis vs MongoDB for refresh tokens
- Exact migration timeline

🔗 Recommended Next Chain
Move to Solution Design phase:
1. solution_synthesizer
2. architecture_validator
3. migration_planner

Execute? /execute-chain
```

### Step 6: Manual Prompt Execution (Optional)

```
User: /run-prompt redis_vs_mongo_analyzer

System:
🔍 Executing Prompt: redis_vs_mongo_analyzer

Findings:
- Redis: Better performance (2ms vs 15ms)
- MongoDB: Simpler (existing connection)
- Recommendation: Start with MongoDB, add Redis later

Context updated ✓
Confidence: 87% → 92% (↑5%)
```

### Step 7: Solution Design Chain

```
User: /execute-chain

System:
🚀 Executing Chain: Solution Design
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[1/3] solution_synthesizer ✓
      Designed dual-auth middleware approach
      
[2/3] architecture_validator ✓
      Validated: Minimal changes, backward compatible
      
[3/3] migration_planner ✓
      Phase 1: Add JWT alongside sessions
      Phase 2: Monitor for 30 days
      Phase 3: Remove session auth
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Solution ready for approval!
Confidence: 96%

Proposed implementation plan available.
Ready to proceed? /phase-controller
```

### Step 8: View Complete Context

```
User: /show-context

System:
📊 Complete Context Summary

Task: Add JWT authentication to Express app
Chains Executed: 3
Total Prompts: 11
Overall Confidence: 96%

✓ Requirements understood
✓ Technical approach validated
✓ Solution designed
→ Ready for implementation

Key Decisions:
1. JWT + refresh tokens (15min/7day)
2. MongoDB for token storage initially
3. Dual-auth migration over 30 days
4. Middleware-based implementation

Next: Begin implementation phase
```

## Workflow Summary

1. **Start**: `/orchestrator-new "task description"`
2. **Execute chains**: `/execute-chain` (3-5 prompts per chain)
3. **Check progress**: `/show-context` or `/uncertainty-check`
4. **Manual prompts**: `/run-prompt [name]` (optional)
5. **Continue until**: Confidence > 80% for phase transition
6. **Advance phases**: Requirements → Context → Design → Implementation

## Key Benefits

- **Automated batching**: 3-5 prompts execute together
- **No interruptions**: Uncertainty only checked after chains
- **Clear progress**: Confidence scores guide advancement
- **Flexible**: Can run manual prompts when needed
- **Context preserved**: All discoveries tracked in JSON file