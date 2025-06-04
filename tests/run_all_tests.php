#!/usr/bin/env php
<?php
/**
 * Master Test Runner
 * Runs all test suites and provides comprehensive report
 */

echo "🧪 YFEvents Complete Test Suite\n";
echo "================================\n";
echo "Testing all functionality on host: " . gethostname() . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n\n";

$testFiles = [
    'test_core_functionality.php' => 'Core Functionality Tests',
    'test_web_interfaces.php' => 'Web Interface Tests', 
    'test_yfclaim.php' => 'YFClaim Module Tests'
];

$results = [];
$totalPassed = 0;
$totalFailed = 0;

foreach ($testFiles as $file => $description) {
    echo str_repeat("=", 70) . "\n";
    echo "🚀 Running: $description\n";
    echo str_repeat("=", 70) . "\n";
    
    $testPath = __DIR__ . '/' . $file;
    
    if (!file_exists($testPath)) {
        echo "❌ ERROR: Test file not found: $file\n\n";
        continue;
    }

    // Capture output and execute test
    ob_start();
    $startTime = microtime(true);
    
    try {
        include $testPath;
        $output = ob_get_clean();
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        echo $output;
        echo "\n⏱️ Duration: {$duration} seconds\n\n";
        
        // Parse results from output
        preg_match('/✅ Passed: (\d+)/', $output, $passedMatch);
        preg_match('/❌ Failed: (\d+)/', $output, $failedMatch);
        
        $passed = isset($passedMatch[1]) ? (int)$passedMatch[1] : 0;
        $failed = isset($failedMatch[1]) ? (int)$failedMatch[1] : 0;
        
        $results[$description] = [
            'passed' => $passed,
            'failed' => $failed,
            'duration' => $duration
        ];
        
        $totalPassed += $passed;
        $totalFailed += $failed;
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ CRITICAL ERROR in $description: " . $e->getMessage() . "\n\n";
        $results[$description] = [
            'passed' => 0,
            'failed' => 1,
            'duration' => 0,
            'error' => $e->getMessage()
        ];
        $totalFailed++;
    }
}

// Overall summary
echo str_repeat("=", 70) . "\n";
echo "📊 OVERALL TEST RESULTS\n";
echo str_repeat("=", 70) . "\n";

foreach ($results as $testSuite => $result) {
    $status = $result['failed'] == 0 ? '✅' : '❌';
    $percentage = $result['passed'] + $result['failed'] > 0 ? 
        round(($result['passed'] / ($result['passed'] + $result['failed'])) * 100, 1) : 0;
    
    echo "$status $testSuite: {$result['passed']} passed, {$result['failed']} failed ({$percentage}%) [{$result['duration']}s]\n";
    
    if (isset($result['error'])) {
        echo "   Error: {$result['error']}\n";
    }
}

echo str_repeat("-", 70) . "\n";
echo "🎯 TOTAL: $totalPassed passed, $totalFailed failed\n";

$overallPercentage = $totalPassed + $totalFailed > 0 ? 
    round(($totalPassed / ($totalPassed + $totalFailed)) * 100, 1) : 0;

echo "📈 Overall Success Rate: {$overallPercentage}%\n";

// Final status
if ($overallPercentage >= 95) {
    echo "\n🎉 SYSTEM STATUS: EXCELLENT!\n";
    echo "✅ YFEvents is fully functional and ready for production.\n";
    echo "✅ YFClaim foundation is solid and ready for development.\n";
} elseif ($overallPercentage >= 85) {
    echo "\n✅ SYSTEM STATUS: GOOD\n";
    echo "✅ Core functionality is working with minor issues.\n";
    echo "🔧 Review failed tests for optimization opportunities.\n";
} elseif ($overallPercentage >= 70) {
    echo "\n⚠️ SYSTEM STATUS: NEEDS ATTENTION\n";
    echo "🔧 Several issues detected that should be addressed.\n";
    echo "📋 Review failed tests and fix critical issues.\n";
} else {
    echo "\n❌ SYSTEM STATUS: CRITICAL ISSUES\n";
    echo "🚨 Major problems detected - immediate attention required.\n";
    echo "🔧 Review all failed tests and fix before proceeding.\n";
}

// Next steps
echo "\n📋 NEXT STEPS:\n";
if ($totalFailed == 0) {
    echo "1. 🎯 Begin YFClaim model implementation\n";
    echo "2. 🌐 Test admin interfaces manually\n";
    echo "3. 🚀 Deploy to production environment\n";
} else {
    echo "1. 🔧 Fix failed tests identified above\n";
    echo "2. 🧪 Re-run tests to verify fixes\n";
    echo "3. 📋 Continue with development roadmap\n";
}

echo "\n📊 Test completed at: " . date('Y-m-d H:i:s T') . "\n";
echo "💾 Log this output for future reference.\n\n";