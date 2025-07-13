#!/bin/bash

# Remove hardcoded confidence values from all chain files

echo "Cleaning chain files..."

# Find all yaml files in chains directory
for file in $(find /mnt/d/YFEventsCopy/guided-discovery/chains -name "*.yaml" -type f); do
    echo "Processing: $file"
    
    # Remove effectiveness from metadata
    sed -i '/effectiveness: [0-9]\./d' "$file"
    
    # Remove min_confidence_gain
    sed -i '/min_confidence_gain: [0-9]\./d' "$file"
    
    # Remove confidence conditions but keep other conditions
    sed -i 's/ && confidence\.[a-z_]* [<>=]* [0-9]\.[0-9]*//g' "$file"
    sed -i 's/confidence\.[a-z_]* [<>=]* [0-9]\.[0-9]* && //g' "$file"
    
    # Remove lines that only had confidence conditions
    sed -i '/condition: "confidence\.[a-z_]* [<>=]* [0-9]\.[0-9]*"$/d' "$file"
    
    # Clean up any double spaces
    sed -i 's/  */ /g' "$file"
done

echo "Chain files cleaned!"