#!/usr/bin/env python3
import os
import yaml
import json

# Mapping of prompt categories and their typical context update patterns
CONTEXT_UPDATE_TEMPLATES = {
    'discovery': '''
## Context Update

After discovery, I'll update the task's context file with:

### DISCOVERIES
```json
{
  "discoveries": {
    // New discoveries will be added here based on findings
  }
}
```

### UNCERTAINTY_UPDATES
- {uncertainties} based on findings
''',
    'analysis': '''
## Context Update

After analysis, I'll update the task's context file with:

### DISCOVERIES
```json
{
  "discoveries": {
    // Analysis results will be added here
  }
}
```

### UNCERTAINTY_UPDATES
- {uncertainties} based on analysis results
''',
    'planning': '''
## Context Update

After planning, I'll update the task's context file with:

### DISCOVERIES
```json
{
  "discoveries": {
    "plan": {
      // Implementation plan details
    }
  }
}
```

### UNCERTAINTY_UPDATES
- {uncertainties} based on planning outcomes
''',
    'implementation': '''
## Context Update

After implementation, I'll update the task's context file with:

### DISCOVERIES
```json
{
  "discoveries": {
    "implementation": {
      // Implementation details and results
    }
  }
}
```

### UNCERTAINTY_UPDATES
- {uncertainties} based on implementation results
''',
    'validation': '''
## Context Update

After validation, I'll update the task's context file with:

### DISCOVERIES
```json
{
  "discoveries": {
    "validation": {
      // Validation results and findings
    }
  }
}
```

### UNCERTAINTY_UPDATES
- {uncertainties} based on validation results
'''
}

def fix_prompt_file(filepath):
    """Fix a single prompt file to ensure it has context update instructions."""
    with open(filepath, 'r') as f:
        content = f.read()
    
    try:
        data = yaml.safe_load(content)
    except Exception as e:
        print(f"Error parsing {filepath}: {e}")
        return False
    
    if not isinstance(data, dict):
        print(f"Skipping {filepath}: not a valid prompt file")
        return False
    
    # Get the template content
    template = data.get('template', '')
    if not template:
        print(f"Skipping {filepath}: no template found")
        return False
    
    # Check if context update section already exists
    if '## Context Update' in template or '### Context Update' in template:
        print(f"Skipping {filepath}: already has context update section")
        return False
    
    # Get category and uncertainties
    category = data.get('category', 'discovery')
    uncertainties = data.get('targets_uncertainties', [])
    
    # Build uncertainty update list
    uncertainty_list = []
    for u in uncertainties:
        uncertainty_list.append(f"{u}")
    uncertainty_str = ', '.join(uncertainty_list) if uncertainty_list else 'Related uncertainties'
    
    # Get the appropriate template
    update_template = CONTEXT_UPDATE_TEMPLATES.get(category, CONTEXT_UPDATE_TEMPLATES['discovery'])
    update_section = update_template.format(uncertainties=uncertainty_str)
    
    # Add the context update section to the template
    data['template'] = template.rstrip() + '\n' + update_section.lstrip()
    
    # Write back the file
    with open(filepath, 'w') as f:
        yaml.dump(data, f, default_flow_style=False, allow_unicode=True, sort_keys=False)
    
    print(f"Updated {filepath}")
    return True

def main():
    prompts_dir = '/mnt/d/YFEventsCopy/guided-discovery/prompts'
    updated_count = 0
    
    for root, dirs, files in os.walk(prompts_dir):
        for file in files:
            if file.endswith('.yaml'):
                filepath = os.path.join(root, file)
                if fix_prompt_file(filepath):
                    updated_count += 1
    
    print(f"\nTotal prompts updated: {updated_count}")

if __name__ == '__main__':
    main()