#!/bin/bash
# Test script to verify the import worked

echo "Testing database import..."
mysql -u yfevents -pyfevents_pass yakima_finds -e "
SELECT 
    'yfa_auth_users' as 'Table',
    COUNT(*) as 'Count',
    'Main user authentication table' as 'Description'
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'yfa_auth_users'
UNION ALL
SELECT 
    'Total Tables' as 'Table',
    COUNT(*) as 'Count',
    'All tables in database' as 'Description'
FROM information_schema.tables 
WHERE table_schema = DATABASE();"