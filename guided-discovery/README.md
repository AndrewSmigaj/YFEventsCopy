# Uncertainty-Driven Discovery System (UDDS) v2

A Claude Code command system for phase-based, uncertainty-driven discovery. UDDS v2 helps you systematically explore and understand systems through a flexible workflow where Claude orchestrates discovery by analyzing your context and recommending actions.

## 🚀 Quick Start

Get started with UDDS v2:

```bash
# 1. Start a new discovery task
/discover Add JWT authentication to Express app

# 2. Claude analyzes and recommends what to run
/uncertainty analyze

# 3. Execute recommended chain
/chain general_discovery

# 4. Check your progress
/context status

# 5. When ready, transition phases
/context phase planning
```

## 📚 Core Concepts

### Phase-Based Workflow
UDDS v2 operates in four phases:
- **Discovery**: Understanding what exists
- **Planning**: Designing solutions
- **Implementation**: Building (outside UDDS)
- **Validation**: Verifying requirements are met

### Uncertainties
Unknowns that guide your exploration:
- **Status**: unresolved → partial → resolved
- **Priority**: blocking, high, medium, low
- **Phase-appropriate**: Different uncertainties for each phase
- **Dynamic**: Claude identifies new uncertainties as you progress

### Claude Orchestration
- Claude reads your context and discoveries
- Assesses which uncertainties remain
- Recommends specific chains or prompts
- Evaluates your readiness for phase transitions
- No hardcoded confidence values - dynamic assessment

### Discovery Chains
Predefined or custom sequences of prompts:

#### General Chains
- **general_discovery**: Initial exploration for any task
- **auth_discovery**: Deep dive into authentication systems
- **tech_analysis**: Technology stack and infrastructure analysis

#### PHP Clean Architecture Chains
- **php_clean_discovery**: PHP clean architecture exploration
- **php_domain_analysis**: Deep domain layer analysis for PHP
- **php_migration_planning**: Plan legacy PHP to clean architecture migration
- **laravel_clean_transformation**: Transform Laravel apps to clean architecture
- **php_quality_assessment**: Assess PHP clean architecture quality

### Context
Your discovery state stored as JSON:
- Current phase and phase history
- Uncertainties with resolution status
- Discoveries from prompts
- Chain execution history

## 🛠️ Command Reference

### `/discover <task description>`
Start a new discovery task and create initial context.

```bash
# Examples
/discover Implement OAuth2 with Google
/discover Migrate from MongoDB to PostgreSQL
/discover Add real-time notifications with WebSockets
```

### `/chain <chain_name>` or `/chain --prompts p1,p2,p3`
Execute a discovery chain or create a custom sequence.

```bash
# Run predefined chain
/chain general_discovery

# Create custom chain
/chain --prompts tech_stack_explorer,dependency_analyzer,architecture_mapper

# Available chains:
# General chains:
# - general_discovery: Basic exploration
# - auth_discovery: Authentication deep dive
# - tech_analysis: Technology stack analysis
# - security_analysis: Security requirements
# - performance_analysis: Performance patterns
#
# PHP Clean Architecture chains:
# - php_clean_discovery: PHP project exploration
# - php_domain_analysis: Domain layer deep dive
# - php_migration_planning: Legacy migration planning
# - laravel_clean_transformation: Laravel to clean
# - php_quality_assessment: Quality validation
# - php_architecture_validation: Comprehensive architecture validation
```

### `/context <subcommand>`
Manage and view discovery context.

```bash
# View current status and phase
/context status

# Request phase transition
/context phase planning

# Show discoveries made
/context discoveries

# View execution history
/context history

# List all contexts
/context list

# Reset context (careful!)
/context reset
```

### `/uncertainty <subcommand>`
Analyze uncertainties and get recommendations.

```bash
# Get Claude's analysis and recommendations
/uncertainty analyze

# List all uncertainties with status
/uncertainty status

# Deep dive into specific uncertainty
/uncertainty AUTH-001
```

### `/prompt <prompt_name>`
Execute a single discovery prompt.

```bash
# Run individual prompt
/prompt php_architecture_explorer
/prompt dependency_analyzer
```

### `/validate [architecture_type] [options]`
Run architecture validation chains to assess implementation quality.

```bash
# Standard PHP clean architecture validation
/validate php

# Quick validation for CI pipeline
/validate php --level=quick --output=ci --fail-below=70

# Deep Laravel validation
/validate laravel --level=deep

# Options:
# --level: quick (30-45min), standard (90-120min), deep (120-150min)
# --output: report, scorecard, ci
# --fail-below: Fail if score below threshold (0-100)
```

## 🎯 Workflow Examples

### Example 1: Adding JWT Authentication

```bash
# 1. Start discovery
/discover Add JWT authentication to Express app

# Claude identifies initial uncertainties in discovery phase

# 2. Get Claude's analysis
/uncertainty analyze

# Claude: "You have 4 blocking uncertainties. I recommend:
# 1. /chain general_discovery - Address TECH-001, ARCH-001
# 2. /chain auth_discovery - Address AUTH-001, AUTH-002"

# 3. Run recommended chain
/chain general_discovery

# Discoveries added to context automatically

# 4. Check progress
/context status

# Shows: Discovery phase, 2/4 uncertainties resolved

# 5. Continue with Claude's recommendations
/chain auth_discovery

# 6. When Claude indicates high confidence
/uncertainty analyze

# Claude: "All discovery uncertainties resolved. 
# Confidence ~85%. Ready for planning phase."

# 7. Transition to planning
/context phase planning
```

### Example 2: Database Migration

```bash
# 1. Start discovery
/discover Migrate user data from MongoDB to PostgreSQL

# 2. Understand current state
/chain general_discovery
/chain tech_analysis

# 3. Analyze data patterns
/chain data_migration_analysis

# 4. Check migration complexity
/context status

# 5. Review specific concerns
/uncertainty DATA-001  # Current schema structure
/uncertainty MIG-001   # Migration strategy options
```

### Example 3: Performance Optimization

```bash
# 1. Start discovery
/discover Optimize API response times

# 2. Profile current performance
/chain performance_analysis

# 3. Identify bottlenecks
/context discoveries

# 4. Deep dive into specific issues
/uncertainty PERF-001  # Current bottlenecks
/uncertainty CACHE-001 # Caching strategy
```

### Example 4: PHP Clean Architecture Implementation

```bash
# 1. Start discovery for PHP project
/discover Implement clean architecture in PHP e-commerce API

# Output: Created task-20240107-php-clean-arch
# Identified uncertainties: PHP-001, LAYER-001, DOMAIN-001, REPO-001

# 2. Run PHP-specific discovery
/chain php_clean_discovery

# Output: Discovered PHP 8.1, MVC pattern, Composer autoloading
# Confidence: 30% → 65%

# 3. Analyze domain layer potential
/chain php_domain_analysis

# Output: Found anemic models, business logic in controllers
# Confidence: 65% → 82%

# 4. Check specific PHP concerns
/uncertainty PHP-001   # PHP version and environment
/uncertainty PSR-001   # PSR standards compliance
/uncertainty REPO-001  # Repository pattern needs

# 5. Validate implementation
/chain php_quality_assessment
```

### Example 6: Validating Completed PHP Architecture

```bash
# 1. After implementing clean architecture
/discover Validate our completed order processing system

# 2. Run comprehensive validation
/validate php

# Output: Running validation through 6 phases...
# Phase 1: Structural validation ✓
# Phase 2: Design principles ✓
# Phase 3: Pattern implementation ✓
# Phase 4: Service quality ✓
# Phase 5: Quality assurance ✓
# Phase 6: Advanced analysis ✓
#
# Overall Score: 85/100 (🥈 Silver Certified)
# Critical Issues: 2
# Recommendations: 8

# 3. For CI/CD integration
/validate php --level=quick --output=ci --fail-below=70

# 4. Deep validation before production
/validate php --level=deep --output=report

# 5. Check specific concerns
/context discoveries
# Review validation findings for improvement areas
```

### Example 5: Laravel to Clean Architecture

```bash
# 1. Start Laravel transformation
/discover Transform Laravel monolith to clean architecture

# 2. Run Laravel-specific analysis
/chain laravel_clean_transformation

# Output: Identified Eloquent coupling, service layer opportunities
# Recommendations: Phase-based migration approach

# 3. Plan the migration
/chain php_migration_planning

# Output: 5-phase migration plan with risk assessment
# Phase 1: Extract service layer (2 weeks)
# Phase 2: Create domain entities (3 weeks)
# Phase 3: Implement repositories (2 weeks)
# Phase 4: Extract use cases (4 weeks)
# Phase 5: Clean up and optimize (1 week)

# 4. Check progress after Phase 1
/context status
/chain php_quality_assessment
```

## 💡 Best Practices

### 1. Start Broad, Then Focus
Always begin with `general_discovery` before specialized chains:
```bash
/discover <task>
/chain general_discovery
# Then run specialized chains based on findings
```

### 2. Follow Claude's Guidance
Claude dynamically assesses your progress:
- Reads your context and discoveries
- Identifies remaining uncertainties
- Recommends specific actions
- Evaluates phase readiness
- No predetermined confidence values

### 3. Trust the Phase System
Each phase has appropriate uncertainties:
- **Discovery**: What exists? How does it work?
- **Planning**: How to build? What patterns?
- **Implementation**: Exit UDDS to code
- **Validation**: Does it meet requirements?

### 4. Document Decisions
As you resolve uncertainties, decisions are recorded:
```bash
/context discoveries
# Shows all findings and decisions made
```

### 5. Leverage Dependencies
Some uncertainties enable others:
```bash
/uncertainty AUTH-001
# Resolving this enables AUTH-002, SEC-001, USER-001
```

## 🔧 Troubleshooting

### "No active discovery task"
```bash
# List all tasks
/context list

# Set active task
/discover <new task>
# OR work with existing:
/chain <chain_name> <task_id>
```

### "Chain not found"
```bash
# Check available chains
ls chains/common/
ls chains/specialized/

# Chains are YAML files in these directories
```

### "Low confidence after multiple chains"
```bash
# Check blocking uncertainties
/context uncertainties

# Focus on unresolved blockers
/uncertainty <blocking_id>

# Run targeted chains
/chain targeted_discovery
```

### "Context file corrupted"
```bash
# Backup current context
cp contexts/active/task-*.json contexts/backup/

# Reset if needed
/context reset <task_id>
```

## 📁 System Structure

```
guided-discovery/
├── .claude/commands/      # Command implementations
├── prompts/              # Discovery prompt templates
├── chains/               # Chain definitions
├── contexts/             # Task contexts and discoveries
├── uncertainties/        # Uncertainty templates
└── DESIGN.md            # System design documentation
```

## 🎨 Extending UDDS

### Adding New Prompts
Create YAML files in `prompts/discovery/`:
```yaml
name: my_custom_prompt
targets_uncertainties: ["CUSTOM-001"]
template: |
  # Custom Discovery Prompt
  Analyze the codebase for...
```

### Adding New Chains
Create YAML files in `chains/common/` or `chains/specialized/`:
```yaml
name: my_chain
prompt_sequence:
  - prompt: my_custom_prompt
    order: 1
    mandatory: true
```

### Adding Uncertainty Templates
Create YAML files in `uncertainties/templates/`:
```yaml
uncertainties:
  - id: CUSTOM-001
    name: Custom Uncertainty
    resolution_criteria:
      required:
        some_finding:
          description: What to find
```

### PHP Clean Architecture Support

UDDS includes comprehensive support for PHP clean architecture:

#### Available PHP Prompts
- **Discovery**: 22 prompts for exploring PHP architecture patterns
- **Analysis**: 10 prompts for quality, coupling, and SOLID analysis
- **Framework-specific**: Support for Laravel, Symfony, and Slim
- **Validation**: Architecture compliance checking with scoring
- **Pattern-specific**: CQRS, hexagonal, service layer, and DTO analyzers

#### PHP Uncertainty Template
The `php_clean_architecture.yaml` template includes:
- PHP environment and PSR standards (PHP-001, PSR-001)
- Architecture layers (LAYER-001, DOMAIN-001)
- Repository pattern (REPO-001)
- Framework integration (FRAMEWORK-001, LARAVEL-001)
- Testing and performance (TEST-001, PERF-001)
- CQRS and command/query separation (CQRS-001, CQS-001)
- Hexagonal architecture patterns (HEX-001, PORT-001, ADAPTER-001)
- Service layer and DTOs (SERVICE-001, DTO-001, SRP-001)

#### PHP Chains
- **php_clean_discovery**: Initial exploration (45-60 min)
- **php_domain_analysis**: Domain layer deep dive (45-60 min)
- **php_migration_planning**: Legacy migration roadmap (60-90 min)
- **laravel_clean_transformation**: Laravel-specific migration (90-120 min)
- **php_quality_assessment**: Quality evaluation (60-75 min)
- **php_architecture_validation**: Comprehensive validation with scoring (90-120 min)

#### Validation and Certification
The validation chain provides:
- **Scoring System**: 0-100 score across 4 categories
- **Certification Levels**: 
  - 🏆 Gold (90-100): Exemplary implementation
  - 🥈 Silver (80-89): Well-implemented with minor improvements
  - 🥉 Bronze (70-79): Acceptable, meeting minimum standards
  - ⚠️ Needs Improvement (<70): Significant issues requiring attention
- **CI/CD Integration**: Support for GitHub Actions, GitLab CI, Jenkins
- **Multiple Output Formats**: Full reports, JSON scorecards, CI-compatible output

#### Example PHP Discovery
```bash
# For a new PHP project
/discover Implement clean architecture for payment processing API
/chain php_clean_discovery
/chain php_domain_analysis
/validate php  # Validate when implementation is complete

# For Laravel migration
/discover Migrate Laravel app to clean architecture
/chain laravel_clean_transformation
/chain php_migration_planning
/validate laravel --level=deep  # Deep validation for migration

# For CI/CD pipeline
/validate php --level=quick --output=ci --fail-below=80
```

## 🤖 How Claude Orchestrates

Claude provides dynamic orchestration:
1. **Reads entire context** - All discoveries and uncertainties
2. **Assesses progress** - What's resolved vs unresolved
3. **Prioritizes by phase** - Phase-appropriate uncertainties first
4. **Recommends actions** - Specific chains, prompts, or transitions
5. **Explains reasoning** - Why certain actions are recommended

No hardcoded formulas - Claude evaluates based on actual discoveries.

## 🤝 Contributing

To improve UDDS:
1. Identify common uncertainty patterns
2. Create new prompt templates
3. Design specialized chains
4. Share successful discovery workflows

## 🔄 What's New in v2

- **Phase-based workflow**: Discovery → Planning → Implementation → Validation
- **Claude orchestration**: Dynamic recommendations based on context
- **No hardcoded confidence**: Claude assesses progress naturally
- **Flexible execution**: Chains, custom chains, or individual prompts
- **User-controlled transitions**: You decide when to change phases

## 📖 Additional Resources

- **Design Document**: See `UDDS_DESIGN_V2.md` for v2 architecture
- **Implementation Status**: Check `IMPLEMENTATION_STATUS.md`
- **Example Contexts**: Check `contexts/examples/` for scenarios
- **Prompt Writing**: Review `prompts/README.md` for guidelines

---

Remember: **Let uncertainty guide your discovery**. Claude helps you explore systematically!