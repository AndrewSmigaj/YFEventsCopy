#!/usr/bin/env python3
"""
Manual DOM Analysis - Focus on Real Mismatches
"""

import os
import re

def analyze_specific_files():
    """Analyze specific files for real DOM mismatches"""
    
    files_to_check = [
        '/home/robug/YFEvents/www/html/refactor/admin/users-original.php',
        '/home/robug/YFEvents/www/html/refactor/admin/users.php',
        '/home/robug/YFEvents/www/html/refactor/admin/events.php',
        '/home/robug/YFEvents/www/html/refactor/admin/shops.php',
        '/home/robug/YFEvents/www/html/refactor/admin/claims.php',
        '/home/robug/YFEvents/www/html/refactor/public/buyer/auth.php',
        '/home/robug/YFEvents/www/html/refactor/public/seller/register.php',
        '/home/robug/YFEvents/www/html/refactor/public/seller/login.php'
    ]
    
    mismatches = []
    
    for file_path in files_to_check:
        if os.path.exists(file_path):
            result = check_file_for_mismatches(file_path)
            if result['mismatches']:
                mismatches.append(result)
    
    return mismatches

def check_file_for_mismatches(file_path):
    """Check a single file for DOM mismatches"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        return {'file': file_path, 'error': str(e), 'mismatches': []}
    
    # Extract getElementById calls with string literals only
    js_pattern = r"document\.getElementById\(['\"]([^'\"]+)['\"]\)"
    js_calls = re.findall(js_pattern, content)
    
    # Extract HTML id attributes  
    html_pattern = r'id=["\']([^"\']+)["\']'
    html_ids = re.findall(html_pattern, content)
    
    # Find missing elements
    missing = []
    for js_id in js_calls:
        if js_id not in html_ids:
            # Find line number
            lines = content.split('\n')
            for i, line in enumerate(lines, 1):
                if f"getElementById('{js_id}')" in line or f'getElementById("{js_id}")' in line:
                    missing.append({
                        'id': js_id,
                        'line': i,
                        'context': line.strip()
                    })
                    break
    
    return {
        'file': file_path,
        'js_calls': js_calls,
        'html_ids': html_ids,
        'mismatches': missing
    }

def main():
    print("=== Manual DOM Element Mismatch Analysis ===")
    print("Focusing on real mismatches with string literals only")
    print()
    
    mismatches = analyze_specific_files()
    
    if not mismatches:
        print("✅ No DOM element mismatches found!")
        return
    
    for result in mismatches:
        print(f"🔍 FILE: {result['file']}")
        print("=" * 60)
        
        print(f"JavaScript calls: {len(result['js_calls'])}")
        print(f"HTML elements: {len(result['html_ids'])}")
        print(f"Mismatches found: {len(result['mismatches'])}")
        print()
        
        if result['mismatches']:
            print("❌ MISMATCHES:")
            for mismatch in result['mismatches']:
                print(f"  Line {mismatch['line']}: getElementById('{mismatch['id']}')")
                print(f"  Context: {mismatch['context']}")
                print()
        
        print("-" * 60)
        print()

if __name__ == "__main__":
    main()