#!/usr/bin/env python3
"""
DOM Element Mismatch Analysis Tool
Finds JavaScript getElementById calls and cross-references with HTML elements in PHP files
"""

import os
import re
import json
from pathlib import Path

def find_php_files(directory):
    """Find all PHP files in admin and public directories"""
    php_files = []
    for root, dirs, files in os.walk(directory):
        # Skip vendor directory
        if 'vendor' in root:
            continue
        # Include admin and public directories, plus src controllers
        if ('/admin/' in root or '/public/' in root or 
            ('admin' in root and root.endswith('admin')) or
            'Controllers' in root):
            for file in files:
                if file.endswith('.php'):
                    php_files.append(os.path.join(root, file))
    return php_files

def extract_getelementbyid_calls(content):
    """Extract all getElementById calls from file content"""
    # Pattern for direct string literals
    pattern_literal = r"document\.getElementById\(['\"]([^'\"]+)['\"]\)"
    # Pattern for variable references (we'll capture the variable name)
    pattern_variable = r"document\.getElementById\(([^)]+)\)"
    
    lines = content.split('\n')
    calls_with_lines = []
    
    for i, line in enumerate(lines, 1):
        # Look for literal string calls
        matches_literal = re.findall(pattern_literal, line)
        for match in matches_literal:
            calls_with_lines.append({
                'id': match,
                'line': i,
                'context': line.strip(),
                'type': 'literal'
            })
        
        # Look for variable calls (mark for manual review)
        if 'getElementById' in line and not re.search(pattern_literal, line):
            variable_match = re.search(pattern_variable, line)
            if variable_match:
                calls_with_lines.append({
                    'id': variable_match.group(1),
                    'line': i,
                    'context': line.strip(),
                    'type': 'variable'
                })
    
    return calls_with_lines

def extract_html_ids(content):
    """Extract all HTML elements with id attributes"""
    pattern = r'id=["\']([^"\']+)["\']'
    matches = re.findall(pattern, content)
    
    # Also find line numbers and element types
    lines = content.split('\n')
    ids_with_lines = []
    for i, line in enumerate(lines, 1):
        matches_in_line = re.findall(pattern, line)
        for match in matches_in_line:
            # Try to extract element type
            element_match = re.search(r'<(\w+)[^>]*id=["\']' + re.escape(match) + r'["\']', line)
            element_type = element_match.group(1) if element_match else 'unknown'
            
            ids_with_lines.append({
                'id': match,
                'line': i,
                'element_type': element_type,
                'context': line.strip()
            })
    
    return ids_with_lines

def analyze_file(file_path):
    """Analyze a single PHP file for DOM mismatches"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        return None
    
    js_calls = extract_getelementbyid_calls(content)
    html_ids = extract_html_ids(content)
    
    # Create sets for quick lookup
    html_id_set = {item['id'] for item in html_ids}
    js_id_set = {item['id'] for item in js_calls}
    
    # Find mismatches
    missing_elements = []
    for js_call in js_calls:
        if js_call['id'] not in html_id_set:
            missing_elements.append(js_call)
    
    unused_elements = []
    for html_id in html_ids:
        if html_id['id'] not in js_id_set:
            unused_elements.append(html_id)
    
    return {
        'file_path': file_path,
        'js_calls': js_calls,
        'html_ids': html_ids,
        'missing_elements': missing_elements,
        'unused_elements': unused_elements,
        'has_mismatches': len(missing_elements) > 0
    }

def main():
    directory = '/home/robug/YFEvents/www/html/refactor'
    php_files = find_php_files(directory)
    
    results = []
    mismatched_files = []
    
    for file_path in php_files:
        result = analyze_file(file_path)
        if result:
            results.append(result)
            if result['has_mismatches']:
                mismatched_files.append(result)
    
    # Print summary
    print("=== DOM Element Mismatch Analysis ===")
    print(f"Total PHP files analyzed: {len(results)}")
    print(f"Files with mismatches: {len(mismatched_files)}")
    print()
    
    # Print detailed results for files with mismatches
    for result in mismatched_files:
        print(f"FILE: {result['file_path']}")
        print("=" * 80)
        
        if result['missing_elements']:
            print("MISSING ELEMENTS (JavaScript calls elements that don't exist):")
            for missing in result['missing_elements']:
                print(f"  ❌ Line {missing['line']}: getElementById('{missing['id']}')")
                print(f"     Context: {missing['context']}")
            print()
        
        print(f"Total JavaScript calls: {len(result['js_calls'])}")
        print(f"Total HTML elements: {len(result['html_ids'])}")
        print(f"Missing elements: {len(result['missing_elements'])}")
        print(f"Unused elements: {len(result['unused_elements'])}")
        print("-" * 80)
        print()
    
    # Save detailed results to JSON
    output_file = '/home/robug/YFEvents/www/html/refactor/dom_analysis_results.json'
    with open(output_file, 'w') as f:
        json.dump(results, f, indent=2)
    
    print(f"Detailed results saved to: {output_file}")

if __name__ == "__main__":
    main()