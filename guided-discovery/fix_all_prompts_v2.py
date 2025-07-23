#!/usr/bin/env python3
import os
import re

def fix_prompt_file(filepath):
    """Fix a single prompt file to have proper structure."""
    with open(filepath, 'r') as f:
        content = f.read()
    
    # Check if it's already been fixed properly
    if 'template: |\n  #' in content:
        print(f"Already fixed: {filepath}")
        return False
    
    # Fix the template line - handle various cases
    if 'template: | ' in content:
        # Find the title after "template: | "
        match = re.search(r'template: \| (.+?)$', content, re.MULTILINE)
        if match:
            title = match.group(1).strip()
            # Replace with proper format
            content = re.sub(
                r'template: \| .+?$',
                f'template: |\n  # {title}',
                content,
                count=1,
                flags=re.MULTILINE
            )
    
    # Remove the generic context update at the beginning if it exists
    generic_update = r'\n\n  ## Context Update\n  \n  I will:\n  1\. Read the current task\'s context file from contexts/active/\n  2\. Merge discoveries into the existing discoveries object\n  3\. Update uncertainty statuses based on what was found\n  4\. Write the updated context back to the file'
    
    content = re.sub(generic_update, '', content)
    
    # Add proper indentation to the content after template: |
    lines = content.split('\n')
    fixed_lines = []
    in_template = False
    
    for i, line in enumerate(lines):
        if line.strip() == 'template: |':
            fixed_lines.append(line)
            in_template = True
        elif in_template and i > 0 and lines[i-1].strip() == 'template: |':
            # This is the line right after template: |
            # Should start with proper indentation
            if not line.startswith('  '):
                fixed_lines.append('  ' + line.lstrip())
            else:
                fixed_lines.append(line)
        elif in_template and line and not line.startswith('  '):
            # Add proper indentation to template content
            fixed_lines.append('  ' + line)
        else:
            fixed_lines.append(line)
    
    content = '\n'.join(fixed_lines)
    
    # Write the fixed content
    with open(filepath, 'w') as f:
        f.write(content)
    
    print(f"Fixed: {filepath}")
    return True

def main():
    prompts_dir = '/mnt/d/YFEventsCopy/guided-discovery/prompts'
    fixed_count = 0
    
    for root, dirs, files in os.walk(prompts_dir):
        for file in files:
            if file.endswith('.yaml') and file != 'PROMPT_TEMPLATE.yaml':
                filepath = os.path.join(root, file)
                if fix_prompt_file(filepath):
                    fixed_count += 1
    
    print(f"\nTotal prompts fixed: {fixed_count}")

if __name__ == '__main__':
    main()