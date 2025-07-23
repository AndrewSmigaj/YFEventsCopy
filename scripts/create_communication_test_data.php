<?php
/**
 * Create test data for communication system
 */

echo "=== Communication System Test Data Creator ===\n\n";

// This script would need database access to work
echo "To create test data, run these SQL commands on your database:\n\n";

// Create test channels
echo "-- Create test channels\n";
echo "INSERT INTO communication_channels (name, slug, description, type, created_by_user_id) VALUES\n";
echo "('General Support', 'general-support', 'General support and questions', 'public', 1),\n";
echo "('Estate Sales Help', 'estate-sales-help', 'Help with estate sales and claims', 'public', 1),\n";
echo "('Seller Announcements', 'seller-announcements', 'Important announcements for sellers', 'announcement', 1);\n\n";

// Create test participants
echo "-- Add admin as participant to all channels\n";
echo "INSERT INTO communication_participants (channel_id, user_id, role, can_send_messages) \n";
echo "SELECT id, 1, 'admin', TRUE FROM communication_channels;\n\n";

// Create test messages
echo "-- Create test messages\n";
echo "INSERT INTO communication_messages (channel_id, user_id, content, content_type) VALUES\n";
echo "(1, 1, 'Welcome to YFEvents communication system! Feel free to ask any questions.', 'text'),\n";
echo "(1, 1, 'This is a test message to show the chat is working.', 'text'),\n";
echo "(2, 1, 'Need help with your estate sale? Post your questions here.', 'text'),\n";
echo "(3, 1, 'Important: New features have been added to the seller dashboard!', 'announcement');\n\n";

// Update channel stats
echo "-- Update channel statistics\n";
echo "UPDATE communication_channels c SET \n";
echo "  message_count = (SELECT COUNT(*) FROM communication_messages WHERE channel_id = c.id),\n";
echo "  participant_count = (SELECT COUNT(*) FROM communication_participants WHERE channel_id = c.id),\n";
echo "  last_activity_at = NOW();\n\n";

echo "To run these commands:\n";
echo "1. Save the SQL commands above to a file: test_data.sql\n";
echo "2. Import to your database: mysql -u [username] -p [database_name] < test_data.sql\n";
echo "\nOr run them directly in your MySQL client.\n";