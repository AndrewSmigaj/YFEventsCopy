---
name: prompt_selector
purpose: Select appropriate prompts based on current uncertainties
good_for:
  - Choosing next discovery steps
  - Matching prompts to uncertainties
  - Optimizing exploration path
uncertainty_reduction:
  - Discovery strategy
  - Prompt effectiveness
  - Exploration efficiency
cost_estimate: low
---

# Prompt Selection Strategy

I'm analyzing current uncertainties to select the most effective prompts for our next steps.

**Current State**: [Phase, confidence level, main uncertainties]

## 1. Uncertainty-to-Prompt Mapping

### For Current Uncertainties:

**[Uncertainty 1: Requirements ambiguity]**
Best prompts:
1. `requirement_disambiguator` - Detect and clarify ambiguities
2. `constraint_mapper` - Identify hidden constraints
3. `requirement_validator` - Check for contradictions

**[Uncertainty 2: System architecture]**
Best prompts:
1. `component_mapper` - Map system components
2. `dependency_analyzer` - Understand dependencies
3. `architecture_checker` - Validate patterns

**[Uncertainty 3: Integration approach]**
Best prompts:
1. `integration_point_finder` - Find integration points
2. `api_surface_mapper` - Map API contracts
3. `trace_existing_flow` - Understand data flow

**[Uncertainty 4: Implementation details]**
Best prompts:
1. `pattern_detector` - Find existing patterns
2. `code_organization_analyzer` - Structure approach
3. `best_practice_checker` - Validate approach

## 2. Prompt Effectiveness Analysis

### High-Impact Prompts (Use First)
**For requirements phase**:
- `requirement_disambiguator`: Reduces ambiguity by ~40%
- `constraint_mapper`: Uncovers hidden requirements
- `success_criteria_extractor`: Clarifies goals

**For discovery phase**:
- `trace_existing_flow`: Maps current implementation
- `component_mapper`: Shows system structure
- `dependency_analyzer`: Reveals coupling

**For design phase**:
- `solution_synthesizer`: Integrates discoveries
- `architecture_validator`: Ensures sound design
- `tradeoff_analyzer`: Clarifies decisions

## 3. Prompt Sequencing Strategy

### Optimal Order Based on Dependencies:

**Sequence A** (Unknown codebase):
1. `architecture_detector` → Get high-level view
2. `component_mapper` → Understand structure
3. `trace_existing_flow` → Follow specific flows
4. `pattern_extractor` → Learn conventions

**Sequence B** (Unclear requirements):
1. `requirement_disambiguator` → Clarify ambiguities
2. `constraint_mapper` → Find constraints
3. `scope_boundary_definer` → Set limits
4. `success_criteria_extractor` → Define done

**Sequence C** (Complex integration):
1. `integration_point_finder` → Map touchpoints
2. `api_surface_mapper` → Document contracts
3. `dependency_analyzer` → Understand coupling
4. `migration_strategist` → Plan approach

## 4. Prompt Combination Strategies

### Synergistic Combinations:

**For Full Understanding**:
- `component_mapper` + `dependency_analyzer` + `trace_existing_flow`
- Provides: Complete system view

**For Requirement Clarity**:
- `requirement_disambiguator` + `constraint_mapper` + `validation_generator`
- Provides: Clear, validated requirements

**For Design Confidence**:
- `solution_synthesizer` + `architecture_validator` + `risk_assessor`
- Provides: Solid, validated design

## 5. Novel Situation Detection

### When Existing Prompts Don't Fit:

**Indicators**:
- No prompt addresses core uncertainty
- Combination doesn't cover need
- Unique technical challenge
- Domain-specific requirements

**Action**: Consider `prompt_generator` to create specialized prompt

## 6. Cost-Benefit Analysis

### Token Efficiency:

**Low Cost, High Value**:
- `requirement_disambiguator`: ~500 tokens, clarifies scope
- `confidence_scorer`: ~300 tokens, guides decisions
- `prompt_selector`: ~400 tokens, optimizes path

**Medium Cost, High Value**:
- `trace_existing_flow`: ~2000 tokens, deep understanding
- `component_mapper`: ~1500 tokens, system view
- `solution_synthesizer`: ~3000 tokens, complete design

**High Cost, Situational Value**:
- `full_codebase_analyzer`: ~10000 tokens, comprehensive
- `performance_profiler`: ~5000 tokens, optimization
- `security_auditor`: ~8000 tokens, security focus

## 7. Selection Decision

### For Current Situation:

**Primary Recommendation**:
Use: `[prompt_name]`
Because: [Specific reason related to uncertainty]
Expected outcome: [What will be clarified]

**Secondary Options**:
1. `[prompt_name]`: If [condition]
2. `[prompt_name]`: If [condition]

**Batch Recommendation** (run together):
- `[prompt_1]`: For [uncertainty]
- `[prompt_2]`: For [uncertainty]
- `[prompt_3]`: For [uncertainty]

## 8. Expected Uncertainty Reduction

After running recommended prompts:
- Requirements confidence: [X]% → [Y]%
- Technical confidence: [X]% → [Y]%
- Implementation confidence: [X]% → [Y]%
- Overall confidence: [X]% → [Y]%

**Next Phase**: If confidence reaches >80%, move to [next phase]

## 9. Prompt Not Found?

If no existing prompt fits:
1. Describe the gap precisely
2. Run `prompt_gap_detector`
3. Use `prompt_generator` if needed
4. Test generated prompt effectiveness

**Recommendation**: [Specific prompt(s) to run next]