# UDDS v2 Example Workflow

This example shows how the uncertainty-driven discovery system works in practice.

## Example Task: Add JWT Authentication to Express API

### 1. Start Discovery

```
User: /discover Add JWT authentication to Express API
```

Claude executes the discover command:
1. Creates context file with v2 structure
2. Analyzes the task and identifies uncertainties:
   - AUTH-001: Current authentication method (blocking)
   - AUTH-002: Session management approach (blocking)
   - TECH-001: Express version and middleware (high)
   - SEC-001: Security requirements (high)
   - USER-001: User model structure (medium)

Claude recommends: Start with `/chain auth_discovery`

### 2. Run Discovery Chain

```
User: /chain auth_discovery
```

Claude:
1. Reads chains/specialized/auth_discovery.yaml
2. Finds prompts: [current_auth_analyzer, session_explorer, user_model_analyzer]
3. Executes each prompt in sequence:
   - Reads prompts/discovery/current_auth_analyzer.yaml
   - Analyzes codebase for auth patterns
   - Updates discoveries in context
   - Updates uncertainty status

### 3. Check Progress

```
User: /context status
```

Claude shows:
- Current phase: Discovery
- Uncertainties: 3/5 resolved
- Key discoveries made
- Recommends next actions

### 4. Get Analysis

```
User: /uncertainty analyze
```

Claude analyzes context and provides:
- AUTH-001: ✓ Resolved - Found session-based auth
- AUTH-002: ✓ Resolved - Express-session with Redis
- TECH-001: ✓ Resolved - Express 4.18.2
- SEC-001: ⚡ Partial - Need token expiry strategy
- USER-001: ✗ Unresolved

Claude recommends: Run `/prompt security_requirements_analyzer`

### 5. Run Individual Prompt

```
User: /prompt security_requirements_analyzer
```

Claude:
1. Reads the prompt template
2. Analyzes security needs
3. Updates discoveries with findings
4. Updates SEC-001 to resolved

### 6. Phase Transition

```
User: /uncertainty analyze
```

Claude: "All blocking uncertainties resolved. Confidence high. Ready for planning phase."

```
User: /context phase planning
```

Claude:
1. Updates current_phase to "planning"
2. Adds phase transition to history
3. Identifies new planning-phase uncertainties
4. Recommends planning chains

## Key Points

1. **Claude does the thinking** - Commands just tell Claude what to do
2. **Context builds automatically** - Each prompt adds discoveries
3. **Uncertainties guide the process** - Claude tracks what's resolved
4. **User controls progression** - Decides when to change phases
5. **No hardcoded logic** - Claude analyzes dynamically

## Command Summary

- `/discover <task>` - Start new discovery
- `/chain <name>` - Run a sequence of prompts
- `/prompt <name>` - Run single prompt
- `/context status` - See current state
- `/context phase <phase>` - Change phases
- `/uncertainty analyze` - Get Claude's analysis
- `/uncertainty status` - List all uncertainties