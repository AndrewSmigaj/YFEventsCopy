# Start Discovery Task - Intelligent Orchestrator

## Task: {{arguments}}

**IMPORTANT**: This command initiates the DISCOVERY phase. Do not jump to implementation. Follow the systematic discovery process to build understanding first.

### 1. Creating Discovery Context

[Generate context ID using format: YYYYMMDD-HHMMSS]
[Create context file: task-{context_id}.json in /mnt/d/YFEventsCopy/guided-discovery/contexts/active/]

### 2. Analyzing Task & Identifying Uncertainties

Based on your task, I'll identify initial uncertainties...

### 2.5. Task Intent Analysis 🆕

**Running intent analyzer to understand what you're really asking for...**

[Execute task_intent_analyzer prompt with task description]

**Intent Analysis Results**:
- Primary Intent: [VALIDATION/CREATION/DEBUGGING/ANALYSIS/OPTIMIZATION]
- Confidence: [HIGH/MEDIUM/LOW]
- Key Indicators: [words that revealed intent]
- What success looks like: [specific outcomes]

This intent analysis will guide our chain selection to ensure we use the right tools for your actual needs.

### 3. Scanning Available Discovery Chains

[Read and analyze all chain YAML files from:
- /mnt/d/YFEventsCopy/guided-discovery/chains/common/*.yaml
- /mnt/d/YFEventsCopy/guided-discovery/chains/specialized/**/*.yaml]

### 4. Chain Matching Analysis

For each potential chain, I'll evaluate fit using our chain_matcher:

[For top 3 chains, execute chain_matcher prompt to get match scores]

**Match Results**:
- Chain 1: [name] - Score: [X/100] - [USE/CONSIDER/AVOID]
- Chain 2: [name] - Score: [X/100] - [USE/CONSIDER/AVOID]  
- Chain 3: [name] - Score: [X/100] - [USE/CONSIDER/AVOID]

### 5. Recommended Discovery Strategy

Based on intent analysis and match scores:

**Best Match**: [chain_name] (Score: [X/100])
- **Why**: [explanation based on intent alignment]
- **What it explores**: [key areas covered]
- **Expected outcomes**: [what you'll learn]
- **Intent alignment**: ✅ This chain [creates/validates/debugs] which matches your [INTENT]

**Alternative Approaches**:
1. **Custom chain**: `/chain --prompts [prompt1,prompt2,prompt3]`
   - For more targeted discovery
   
2. **Different chain**: [alternative_chain]
   - Better for [specific aspect]

### 5.5. Chain Selection Validation 🆕

[Execute chain_selection_validator prompt if score < 80]

**Sanity Check**: Will [recommended_chain] actually help achieve "{{arguments}}"?
[Validation result and any warnings]

### 6. Next Steps

**Context ID**: `[context_id]`
**Current Phase**: Discovery
**Recommended Chain**: [chain_name] for [INTENT] tasks

1. **Run recommended chain**:
   ```
   /chain [recommended_chain_name] [context_id]
   ```

2. **Check progress**:
   ```
   /status [context_id]
   ```

3. **View context details**:
   ```
   /context show [context_id]
   ```

**REMEMBER**: 
- The intent analysis helps ensure we're using the right type of chain
- A design chain won't help validate existing code
- A validation chain won't help create new systems
- Match score < 60 suggests finding a better chain

The discovery context is now active with clear intent understanding!