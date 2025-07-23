# Start Discovery Task - Intelligent Orchestrator

## Task: $ARGUMENTS

**IMPORTANT**: This command initiates the DISCOVERY phase. Do not jump to implementation. Follow the systematic discovery process to build understanding first.

I'll analyze your task and set up a discovery context for systematic exploration.

### 1. Creating Discovery Context

Let me create a new context file for this task...

[Generate context ID using format: YYYYMMDD-HHMMSS]
[Create context file: task-{context_id}.json in /mnt/d/YFEventsCopy/guided-discovery/contexts/active/]

```json
{
  "id": "[context_id]",
  "task": {
    "description": "$ARGUMENTS",
    "type": "[analyze task to determine: feature|bugfix|refactor|analysis|deployment]",
    "created_at": "[current timestamp]"
  },
  "current_phase": "discovery",
  "phase_history": [
    {
      "phase": "discovery",
      "entered_at": "[current timestamp]",
      "reason": "Initial task creation"
    }
  ],
  "phase_gates": {
    "can_analyze": false,
    "can_design": false,
    "can_implement": false
  },
  "uncertainties": [
    // Will be populated based on task analysis
  ],
  "discoveries": {},
  "chain_history": [],
  "chain_progress": {},
  "confidence": {
    "requirements": 0.0,
    "technical": 0.0,
    "implementation": 0.0,
    "overall": 0.0
  }
}
```

<!-- TEMPORARILY COMMENTED OUT FOR RAW DISCOVERY TEST
### 1.5. Reading System Architecture

**CRITICAL**: Always read architecture.yaml first to understand the system structure.

[Read /mnt/d/YFEventsCopy/architecture.yaml and extract:
- web_root: The actual production web root path
- project.status: Current project state
- modules: Active modules
- Any other critical paths or configurations]

**Key Information from architecture.yaml**:
- **Web Root**: [extracted web_root value]
- **Project Status**: [production-ready/development/etc]
- **Important**: All web exploration MUST start from the web_root path, not any other index.php files

[Update the context file with architecture information:
Add to discoveries object:
"architecture": {
  "web_root": "[extracted from routing_system.web_root]",
  "project_status": "[extracted from project.status]",
  "namespace": "[extracted from project.namespace]",
  "key_paths": {
    "domain": "[extracted from structure.src.layers.domain.path]",
    "application": "[extracted from structure.src.layers.application.path]",
    "infrastructure": "[extracted from structure.src.layers.infrastructure.path]",
    "presentation": "[extracted from structure.src.layers.presentation.path if exists]",
    "routes": "[check structure.root array for 'routes/' entry]",
    "config": "[check structure.root array for 'config/' entry]",
    "modules": "[check structure.root array for 'modules/' entry]",
    "public": "[same as web_root]"
  }
}]

[If architecture.yaml doesn't exist or web_root is missing, note this as a critical uncertainty]
END OF COMMENTED SECTION -->

### 2. Analyzing Task & Identifying Uncertainties

Based on your task, I've identified these initial uncertainties:

[Analyze the task to create specific uncertainties with format:
{
  "id": "TECH-001",
  "description": "What is the technology stack?",
  "status": "unresolved",
  "priority": "high|medium|low|blocking",
  "phase": "discovery"
}]

### 3. Scanning Available Discovery Chains

[Read and analyze all chain YAML files from:
- /mnt/d/YFEventsCopy/guided-discovery/chains/common/*.yaml
- /mnt/d/YFEventsCopy/guided-discovery/chains/specialized/**/*.yaml

For each chain, evaluate:
- Description and purpose
- optimal_for criteria
- targets_uncertainties 
- prompt_sequence]

### 4. Chain Matching Analysis

Based on task analysis and available chains:

[Show relevance scoring:
- Chain name: Score (0-100) - Reason
- List top 3 matches with explanations]

### 5. Recommended Discovery Strategy

**Best Match**: [chain_name]
- **Why**: [explanation based on task alignment]
- **What it explores**: [key areas covered]
- **Expected outcomes**: [what you'll learn]

**Alternative Approaches**:
1. **Custom chain**: `/chain --prompts [prompt1,prompt2,prompt3]`
   - For more targeted discovery
   
2. **Sequential chains**: Run multiple chains
   - Start with: [chain1]
   - Follow with: [chain2]

### 6. Next Steps

**Context ID**: `[context_id]`
**Current Phase**: Discovery

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
- Always include the context ID when running commands
- Complete discovery before moving to analysis phase
- Follow the systematic process - no shortcuts to implementation

The discovery context is now active and ready for exploration!