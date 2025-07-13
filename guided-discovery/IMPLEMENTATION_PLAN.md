# UDDS Implementation Plan

## Overview

This plan details how to transform the current system into the LLM-orchestrated, phase-aware system described in UDDS_DESIGN_V2.md.

## Current State Assessment

### What We Have:
- ✅ Prompt library (35+ prompts)
- ✅ Chain definitions (with phases)
- ✅ Basic commands (/discover, /chain, /context, /uncertainty)
- ✅ Context tracking system
- ✅ Uncertainty templates

### What Needs Changing:
- ❌ Hardcoded confidence values in chains
- ❌ No LLM orchestration capability
- ❌ No phase tracking in context
- ❌ No individual prompt execution
- ❌ No custom chain creation
- ❌ Static chain execution

## Implementation Phases

### Phase 1: Core Infrastructure (Week 1)

#### 1.1 Update Context Structure

**File**: Create new context version handler
```
contexts/
├── v1/  (current contexts)
└── v2/  (new structure)
```

**Changes**:
- Add `current_phase` field
- Add `phase_history` array
- Add `llm_assessments` array
- Add version field for migration

#### 1.2 Remove Hardcoded Values

**Files to Update**:
- `chains/common/*.yaml` - Remove confidence_gain, effectiveness
- `chains/specialized/*/*.yaml` - Remove all hardcoded metrics
- `prompts/*/*.yaml` - Remove any confidence impacts

**Specific Removals**:
```yaml
# Remove these fields:
- min_confidence_gain
- effectiveness  
- confidence_impact
- success_criteria (if rigid)
```

#### 1.3 Update Commands for New Context

**Files**:
- `.claude/commands/context.md` - Handle v2 structure
- `.claude/commands/discover.md` - Create v2 contexts

### Phase 2: LLM Orchestration (Week 2)

#### 2.1 Enhance Chain Command

**File**: `.claude/commands/chain.md`

**Add**:
```bash
# After chain execution:
echo "## 🤖 LLM Assessment Required"
echo "Please assess what was discovered:"
echo "1. Which uncertainties were addressed?"
echo "2. How well were they resolved?"
echo "3. What new uncertainties emerged?"
echo "4. Current confidence level and why?"
echo "5. Recommended next steps?"
```

#### 2.2 Create Orchestrate Command

**File**: `.claude/commands/orchestrate.md`

**Features**:
- Analyze all current uncertainties
- Consider current phase
- Recommend next actions with reasoning
- Support phase transition evaluation

#### 2.3 Create Prompt Command

**File**: `.claude/commands/prompt.md`

**Features**:
- Execute individual prompts
- Update context with findings
- Trigger LLM assessment

### Phase 3: Flexible Execution (Week 3)

#### 3.1 Custom Chain Support

**Update**: `.claude/commands/chain.md`

**Add Options**:
- `--prompts`: Create ad-hoc chain
- `--save-as`: Save custom chain
- Support for dynamic chain creation

#### 3.2 Phase Management

**Update**: `.claude/commands/context.md`

**Add Subcommands**:
- `phase <name>`: Request phase transition
- `assess`: Trigger full LLM assessment

#### 3.3 Enhanced Discovery

**Update**: `.claude/commands/discover.md`

**Changes**:
- LLM identifies uncertainties
- LLM recommends initial approach
- Support starting in different phases

### Phase 4: Testing & Polish (Week 4)

#### 4.1 Migration Tools

**Create**: `scripts/migrate_contexts.py`
- Convert v1 contexts to v2
- Preserve discovery history

#### 4.2 Documentation Updates

**Update**:
- `README.md` - New workflow examples
- `DESIGN.md` - Archive as v1
- Command help text

#### 4.3 Test Scenarios

**Create**: `test_scenarios/`
- PHP clean architecture discovery
- Auth implementation 
- Database migration
- Custom chain creation

## Detailed File Changes

### Priority 1: Context Structure

**File**: `.claude/commands/discover.md`

**Change Section**: Context initialization
```bash
# OLD:
cat > "$CONTEXT_FILE" << EOF
{
  "task_id": "$TASK_ID",
  "description": "$DESCRIPTION",
  "uncertainties": {},
  "discoveries": {},
  "confidence": 0.0
}
EOF

# NEW:
cat > "$CONTEXT_FILE" << EOF
{
  "version": "2.0",
  "task_id": "$TASK_ID", 
  "description": "$DESCRIPTION",
  "current_phase": "discovery",
  "phase_history": [{
    "phase": "discovery",
    "entered_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "reason": "New task initiated"
  }],
  "uncertainties": {},
  "discoveries": {},
  "llm_assessments": [],
  "chains_executed": []
}
EOF
```

### Priority 2: Remove Hardcoded Confidence

**Example File**: `chains/common/general_discovery.yaml`

**Remove**:
```yaml
min_confidence_gain: 0.35
effectiveness: 0.75
```

**Keep**:
```yaml
name: general_discovery
description: Basic exploration chain
targets_phase: discovery
```

### Priority 3: LLM Assessment Points

**File**: `.claude/commands/chain.md`

**After Chain Execution Add**:
```bash
# Record chain execution
echo "Recording chain execution..."
jq --arg chain "$CHAIN_NAME" \
   --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
   '.chains_executed += [{
     "chain": $chain,
     "timestamp": $timestamp
   }]' "$CONTEXT_FILE" > tmp.json && mv tmp.json "$CONTEXT_FILE"

# Prompt for LLM assessment
echo ""
echo "## 🤖 Assessment Needed"
echo ""
echo "The chain has completed. Please assess:"
echo ""
echo "1. **Discoveries Made**: What did we learn?"
echo "2. **Uncertainties Resolved**: Which unknowns are now known?"
echo "3. **New Uncertainties**: What new questions emerged?"
echo "4. **Confidence Level**: How confident are you now? (0-100%)"
echo "5. **Phase Assessment**: Should we stay in $CURRENT_PHASE or transition?"
echo "6. **Next Steps**: What do you recommend doing next?"
echo ""
echo "Update the context with your assessment using:"
echo "\`/context assess\`"
```

## Rollout Strategy

### Week 1: Non-Breaking Changes
1. Create v2 context structure
2. Update commands to handle both v1 and v2
3. Remove hardcoded values from chains

### Week 2: LLM Integration
1. Add assessment prompts
2. Create orchestrate command
3. Test with simple scenarios

### Week 3: Advanced Features
1. Custom chain creation
2. Individual prompt execution
3. Phase management

### Week 4: Migration & Polish
1. Migrate existing contexts
2. Update all documentation
3. Create comprehensive examples

## Success Metrics

### Phase 1 Complete When:
- [ ] New context structure defined
- [ ] Commands handle v2 contexts
- [ ] All hardcoded confidence removed

### Phase 2 Complete When:
- [ ] LLM assessment after chains works
- [ ] Orchestrate command provides recommendations
- [ ] Individual prompts can be run

### Phase 3 Complete When:
- [ ] Custom chains can be created and saved
- [ ] Phase transitions work smoothly
- [ ] All execution modes supported

### Phase 4 Complete When:
- [ ] All contexts migrated
- [ ] Documentation updated
- [ ] Test scenarios pass

## Risk Mitigation

### Risk 1: Breaking Existing Workflows
- **Mitigation**: Support both v1 and v2 contexts during transition
- **Fallback**: Keep v1 commands available with deprecation warning

### Risk 2: LLM Assessment Overhead
- **Mitigation**: Make assessments optional initially
- **Optimization**: Batch assessments when multiple prompts run

### Risk 3: Complex Custom Chains
- **Mitigation**: Start with simple prompt lists
- **Enhancement**: Add chain builder wizard later

## Command Priority Order

1. **Update discover** - Creates v2 contexts (non-breaking)
2. **Update context** - Handles v2 structure (backward compatible)
3. **Create prompt** - New command (no conflicts)
4. **Update chain** - Add assessment (enhancement)
5. **Create orchestrate** - New command (no conflicts)

## Notes

- Each change should be tested individually
- Maintain backward compatibility during transition
- Focus on LLM empowerment, not automation
- Keep the system flexible and adaptable