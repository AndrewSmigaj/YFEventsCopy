# Prompt System Status

## Overview
All prompts have been updated to follow the UDDS v2 design with proper context update sections.

## Key Updates Made

### 1. YAML Structure Fixed
- All prompts now use proper `template: |` format with newline
- Template content is properly indented with 2 spaces
- No more syntax errors from `template: |#`

### 2. Context Update Sections Added
Every prompt now includes a "Context Update" section at the END that shows:
- How discoveries will be merged into the context JSON
- Which uncertainties will be updated based on findings
- Structured JSON format for discoveries

### 3. Examples of Well-Structured Prompts

#### Discovery Prompts
- `general_explorer.yaml` - Complete pattern for general discovery
- `php_architecture_explorer.yaml` - PHP-specific discovery with detailed context updates
- `tech_stack_identifier.yaml` - Technology discovery with structured output
- `auth_pattern_finder.yaml` - Authentication discovery pattern
- `php_domain_explorer.yaml` - Domain layer exploration with entity/VO/aggregate discoveries

#### Analysis Prompts  
- `php_coupling_analyzer.yaml` - Coupling analysis with quality scores
- `dto_mapping_strategy_analyzer.yaml` - DTO mapping analysis (very detailed)
- `php_namespace_validator.yaml` - Namespace validation with recommendations

#### Validation Prompts
- `php_architecture_validator.yaml` - Architecture validation with scoring

## Design Principles Followed

1. **No Hardcoded Values** - Claude dynamically assesses based on findings
2. **Structured Output** - Each prompt defines clear output structure
3. **Context Integration** - All prompts update the task's context file
4. **Uncertainty Tracking** - Prompts specify which uncertainties they address
5. **Phase Appropriate** - Prompts are categorized by phase (discovery, analysis, etc.)

## Prompt Categories

### Discovery (17 prompts)
- General: 5 prompts
- PHP-specific: 12 prompts
Focus: Understanding what exists

### Analysis (10 prompts)
- All PHP-specific
Focus: Deep analysis of quality, patterns, coupling

### Planning (0 prompts currently)
- To be added as needed
Focus: Designing solutions

### Implementation (0 prompts)
- Implementation happens outside UDDS
Focus: N/A

### Validation (1 prompt)
- PHP architecture validation
Focus: Verifying implementation quality

### Migration (2 prompts)
- PHP-specific migration planning
Focus: Planning transitions

## Usage Pattern

1. Claude reads the prompt template
2. Claude performs the requested analysis/discovery
3. Claude provides findings in the structured format
4. Claude updates the context file as specified
5. Claude reports uncertainty status changes

## Quality Assurance

- All 35 prompts have been reviewed
- YAML syntax is valid
- Context update sections are present
- Uncertainties are properly targeted
- Output structures map to discoveries

The prompt system is now fully aligned with the UDDS v2 design where Claude orchestrates discovery through dynamic assessment rather than mechanical calculations.