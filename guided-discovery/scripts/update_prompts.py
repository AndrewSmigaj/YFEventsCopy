#!/usr/bin/env python3
"""
Update all prompts to remove old format and add context update instructions
"""

import os
import yaml
import re

def update_prompt(filepath):
    """Update a single prompt file to new format"""
    
    with open(filepath, 'r') as f:
        content = f.read()
    
    # Parse YAML to get the basic structure
    try:
        data = yaml.safe_load(content)
    except:
        print(f"Failed to parse {filepath}")
        return
    
    # Remove old fields
    if 'provides_context' in data:
        del data['provides_context']
    if 'requires_context' in data:
        del data['requires_context']
    if 'output_parser' in data:
        del data['output_parser']
    if 'estimated_duration' in data:
        del data['estimated_duration']
    if 'complexity' in data:
        del data['complexity']
    
    # Keep only essential fields
    new_data = {
        'name': data.get('name', ''),
        'category': data.get('category', 'discovery'),
        'targets_uncertainties': data.get('targets_uncertainties', [])
    }
    
    # Get the template
    template = data.get('template', '')
    
    # Add context update instructions if not present
    if 'Context Update' not in template and 'context' not in template.lower():
        template += """
  
  ## Context Update
  
  After completing this analysis, I will:
  1. Read the current task's context file from contexts/active/
  2. Update the discoveries section with findings
  3. Update uncertainty statuses based on what was discovered
  4. Write the updated context back to the file
"""
    
    # Create new content
    new_content = f"""name: {new_data['name']}
category: {new_data['category']}
targets_uncertainties: {new_data['targets_uncertainties']}

template: |{template}"""
    
    # Write back
    with open(filepath, 'w') as f:
        f.write(new_content)
    
    print(f"Updated {filepath}")

# Find all YAML files
prompts_dir = '/mnt/d/YFEventsCopy/guided-discovery/prompts'
for root, dirs, files in os.walk(prompts_dir):
    for file in files:
        if file.endswith('.yaml') and file != 'PROMPT_TEMPLATE.yaml':
            filepath = os.path.join(root, file)
            update_prompt(filepath)