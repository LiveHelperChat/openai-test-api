<?php

/**
 * OpenAI Function Calling Test Framework
 * 
 * Usage examples:
 * php test.php                    - Run all tests
 * php test.php weather            - Run tests containing "weather" in the name
 * php test.php "basic_weather"    - Run specific test by exact name match
 * php test.php currency           - Run tests containing "currency" in the name
 */

class OpenAITestFramework {
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1/responses';
    private $structure;
    private $cases;
    private $currentTestCase;
    
    // ANSI color codes for console output
    private $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'bold' => "\033[1m",
        'reset' => "\033[0m"
    ];
    
    public function __construct() {
        $this->loadSettings();
        $this->loadStructure();
        $this->loadCases();
    }
    
    private function loadSettings() {
        $settings = include 'settings.ini.php';
        if (!$settings || !is_array($settings)) {
            throw new Exception("Failed to load settings from settings.ini.php");
        }
        
        if (!isset($settings['api_key']) || empty($settings['api_key'])) {
            throw new Exception("API key not found in settings.ini.php");
        }
        
        $this->apiKey = $settings['api_key'];
        
        // Store other settings for potential use
        $this->settings = $settings;
    }
    
    private function loadStructure() {
        $structureContent = file_get_contents('structure.json');
        if (!$structureContent) {
            throw new Exception("Failed to load structure.json");
        }
        $this->structure = json_decode($structureContent, true);
        if (!$this->structure) {
            throw new Exception("Failed to parse structure.json");
        }
    }
    
    private function loadCases() {
        $casesContent = file_get_contents('cases.json');
        if (!$casesContent) {
            throw new Exception("Failed to load cases.json");
        }
        $this->cases = json_decode($casesContent, true);
        if (!$this->cases) {
            throw new Exception("Failed to parse cases.json");
        }
    }
    
    private function colorize($text, $color) {
        return $this->colors[$color] . $text . $this->colors['reset'];
    }
    
    private function printHeader() {
        echo $this->colorize("╔══════════════════════════════════════════════════════════════╗\n", 'cyan');
        echo $this->colorize("║                  OpenAI Function Calling Test                ║\n", 'cyan');
        echo $this->colorize("╚══════════════════════════════════════════════════════════════╝\n", 'cyan');
        echo "\n";
    }
    
    private function makeApiRequest($messages) {
        // Prepare the request payload - dynamically set input from the current test case
        // Use model from settings if available, otherwise fall back to structure
        $model = isset($this->settings['model_core']) ? $this->settings['model_core'] : $this->structure['model'];
        
        if (isset($this->settings['system_prompt']) && !empty($this->settings['system_prompt'])) {
            $systemMessage = [
                'role' => 'system',
                'content' => $this->settings['system_prompt']
            ];
            // Prepend system message to the beginning of messages array
            array_unshift($messages, $systemMessage);
        }

        $payload = [
            'model' => $model,
            'input' => $messages,
            'tools' => $this->structure['tools'],
            'stream' => false, // Set to false for testing to get complete response
            'parallel_tool_calls' => $this->structure['parallel_tool_calls']
        ];
        
        // Encode payload and fix empty properties arrays to objects
        $jsonPayload = json_encode($payload);
        $jsonPayload = str_replace('"properties":[]', '"properties":{}', $jsonPayload);
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                "OpenAI-Beta: assistants=v1",
                "Authorization: Bearer " . $this->apiKey,
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("cURL Error: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error {$httpCode}: " . $response);
        }
        
        $decodedResponse = json_decode($response, true);
        if (!$decodedResponse) {
            throw new Exception("Failed to decode API response");
        }
        
        return $decodedResponse;
    }
    
    private function validateResponse($response, $expectedOutput) {

        if (!isset($response['output']) || !is_array($response['output'])) {
            return false;
        }
        
        if ($expectedOutput['type'] === 'toolcall') {
            // Check if any function_call type exists in the output array
            foreach ($response['output'] as $outputItem) {
                if (isset($outputItem['type']) && $outputItem['type'] === 'function_call' &&
                    isset($outputItem['name']) && $outputItem['name'] === $expectedOutput['tool']) {
                    
                    // If expected output has arguments defined, validate them too
                    if (isset($expectedOutput['arguments']) && !empty($expectedOutput['arguments'])) {
                        if (!isset($outputItem['arguments'])) {
                            return false;
                        }
                        
                        // Parse the arguments JSON string
                        $actualArgs = json_decode($outputItem['arguments'], true);
                        if ($actualArgs === null) {
                            return false;
                        }
                        
                        // Compare expected vs actual arguments
                        foreach ($expectedOutput['arguments'] as $key => $expectedValue) {
                            if (!isset($actualArgs[$key]) || $actualArgs[$key] !== $expectedValue) {
                                return false;
                            }
                        }
                    }
                    
                    return true;
                }
            }
            return false;
        }
        
        if ($expectedOutput['type'] === 'message') {
            // Check if there's a message type with output_text content and no function calls
            $hasMessage = false;
            $hasFunctionCall = false;
            $messageText = '';
            
            foreach ($response['output'] as $outputItem) {
                if (isset($outputItem['type'])) {
                    if ($outputItem['type'] === 'message' && isset($outputItem['content'])) {
                        // Check if content array contains output_text
                        foreach ($outputItem['content'] as $contentItem) {
                            if (isset($contentItem['type']) && $contentItem['type'] === 'output_text') {
                                $hasMessage = true;
                                if (isset($contentItem['text'])) {
                                    $messageText = $contentItem['text'];
                                }
                                break;
                            }
                        }
                    } elseif ($outputItem['type'] === 'function_call') {
                        $hasFunctionCall = true;
                    }
                }
            }
            
            // Basic validation: message response should have message with output_text and no function calls
            $basicValidation = $hasMessage && !$hasFunctionCall;
            
            // If there's a meaning attribute, validate the meaning using AI
            if ($basicValidation && isset($expectedOutput['meaning']) && !empty($messageText)) {
                return $this->validateMeaningWithAI($messageText, $expectedOutput['meaning'], $response);
            }
            
            return $basicValidation;
        }
        
        return false;
    }
    
    private function validateMeaningWithAI($actualText, $expectedMeaning, $originalResponse) {
        try {
            // Extract user message from the test case for context
            $userMessage = '';
            if (isset($this->currentTestCase['messages'])) {
                $messages = $this->currentTestCase['messages'];
                // Find the last user message
                for ($i = count($messages) - 1; $i >= 0; $i--) {
                    if (isset($messages[$i]['role']) && $messages[$i]['role'] === 'user') {
                        $userMessage = $messages[$i]['content'];
                        break;
                    }
                }
            }
            
            $validationPrompt = [
                [
                    'role' => 'system',
                    'content' => 'You are a text meaning validator. Your job is to determine if the actual AI response matches the expected meaning in context of the user\'s original question. Respond with only "YES" if the meaning matches, or "NO" if it does not match.'
                ],
                [
                    'role' => 'user',
                    'content' => "User's original question: {$userMessage}\n\nExpected meaning of AI response: {$expectedMeaning}\n\nActual AI response: {$actualText}\n\nDoes the actual AI response match the expected meaning in context of the user's question?"
                ]
            ];
            
            $model = isset($this->settings['model_meaning_verification']) ? $this->settings['model_meaning_verification'] : 'gpt-4.1-mini';

            // Store validation details for debugging
            $this->lastValidationPrompt = $validationPrompt;
            
            $payload = [
                'model' => $model,
                'messages' => $validationPrompt,
                'max_tokens' => 10,
                'temperature' => 0
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $this->apiKey,
                    "Content-Type: application/json"
                ],
                CURLOPT_TIMEOUT => 15
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $this->lastValidationError = "HTTP Error: {$httpCode}";
                return false; // Default to false if validation fails
            }

            $decodedResponse = json_decode($response, true);
            if (!isset($decodedResponse['choices'][0]['message']['content'])) {
                $this->lastValidationError = "No content in AI validation response";
                return false;
            }
            
            $validationResult = trim(strtoupper($decodedResponse['choices'][0]['message']['content']));
            $this->lastValidationResult = $decodedResponse['choices'][0]['message']['content'];
            return $validationResult === 'YES';
            
        } catch (Exception $e) {
            $this->lastValidationError = $e->getMessage();
            // If AI validation fails, default to false
            return false;
        }
    }
    
    private function printTestResult($testName, $success, $details = []) {
        $icon = $success ? "✓" : "✗";
        $color = $success ? "green" : "red";
        $status = $success ? "PASS" : "FAIL";
        
        echo $this->colorize("[$icon] ", $color);
        echo $this->colorize($testName, 'white');
        echo " - ";
        echo $this->colorize($status, $color);
        echo "\n";
        
        if (!empty($details)) {
            foreach ($details as $detail) {
                echo "    " . $this->colorize("→ $detail", 'yellow') . "\n";
            }
        }
        echo "\n";
    }
    
    private function runSingleTest($testCase) {
        $this->currentTestCase = $testCase; // Store current test case for context
        $testName = $testCase['name'];
        $messages = $testCase['messages'];
        $expectedOutput = $testCase['expected_output'];
        
        echo $this->colorize("Running test: ", 'blue') . $this->colorize($testName, 'bold') . "\n";
        
        if ($expectedOutput['type'] === 'toolcall') {
            echo $this->colorize("Expected tool: ", 'cyan') . $expectedOutput['tool'] . "\n";
        } else {
            echo $this->colorize("Expected type: ", 'cyan') . $expectedOutput['type'] . "\n";
            if (isset($expectedOutput['meaning'])) {
                echo $this->colorize("Expected meaning: ", 'cyan') . $expectedOutput['meaning'] . "\n";
            }
        }
        
        try {
            $response = $this->makeApiRequest($messages);
            
            $success = $this->validateResponse($response, $expectedOutput);
            
            $details = [];
            
            // Add the actual output to details
            if (isset($response['output'])) {
                $details[] = "Actual output: " . json_encode($response['output']);
            } else {
                $details[] = "No output found in response";
            }
            
            if (isset($response['output']) && is_array($response['output'])) {
                $calledTools = [];
                $messageTexts = [];
                
                foreach ($response['output'] as $outputItem) {
                    if (isset($outputItem['type'])) {
                        if ($outputItem['type'] === 'function_call' && isset($outputItem['name'])) {
                            $calledTools[] = $outputItem['name'];
                        } elseif ($outputItem['type'] === 'message' && isset($outputItem['content'])) {
                            // Extract text from message content
                            foreach ($outputItem['content'] as $contentItem) {
                                if (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text'])) {
                                    $messageTexts[] = substr($contentItem['text'], 0, 100) . (strlen($contentItem['text']) > 100 ? '...' : '');
                                }
                            }
                        }
                    }
                }
                
                if (!empty($calledTools)) {
                    $details[] = "Called tools: " . implode(', ', $calledTools);
                }
                
                if (!empty($messageTexts)) {
                    $details[] = "Message text: " . implode(' | ', $messageTexts);
                }
                
                // If meaning validation was performed, add it to details
                if (isset($expectedOutput['meaning']) && $expectedOutput['type'] === 'message') {
                    $details[] = "AI meaning validation: " . ($success ? "PASSED" : "FAILED");
                    
                    // Add validation details if available
                    if (isset($this->lastValidationPrompt)) {
                        $userPrompt = $this->lastValidationPrompt[1]['content'];
                        $details[] = "Validation prompt: " . $userPrompt;
                    }
                    
                    if (isset($this->lastValidationResult)) {
                        $details[] = "AI validator response: " . $this->lastValidationResult;
                    }
                    
                    if (isset($this->lastValidationError)) {
                        $details[] = "Validation error: " . $this->lastValidationError;
                    }
                }
                
                if (empty($calledTools) && empty($messageTexts)) {
                    $details[] = "No tool calls or message text found";
                }
            } else {
                $details[] = "No output array found";
            }
            
            $this->printTestResult($testName, $success, $details);
            
            return $success;
            
        } catch (Exception $e) {
            $details = [];
            $details[] = "Error: " . $e->getMessage();
            
            // Try to show any partial response data if available
            if (isset($response)) {
                $details[] = "Partial response: " . json_encode($response);
            }
            
            $this->printTestResult($testName, false, $details);
            return false;
        }
    }
    
    public function runAllTests($testName = null) {
        $this->printHeader();
        
        // Filter test cases if a specific test name is provided
        $testCases = $this->cases;
        if ($testName !== null) {
            $testCases = array_filter($this->cases, function($testCase) use ($testName) {
                return stripos($testCase['name'], $testName) !== false;
            });
            
            if (empty($testCases)) {
                echo $this->colorize("No tests found matching: {$testName}\n", 'red');
                echo $this->colorize("Available tests:\n", 'yellow');
                foreach ($this->cases as $case) {
                    echo $this->colorize("  - {$case['name']}\n", 'white');
                }
                return false;
            }
            
            echo $this->colorize("Running tests matching: {$testName}\n\n", 'yellow');
        }
        
        $totalTests = count($testCases);
        $passedTests = 0;
        
        echo $this->colorize("Starting test suite with {$totalTests} test cases...\n\n", 'blue');
        
        foreach ($testCases as $testCase) {
            if ($this->runSingleTest($testCase)) {
                $passedTests++;
            }
            
            // Add a small delay between tests to avoid rate limiting
            // sleep(1);
        }
        
        // Print summary
        echo $this->colorize("╔══════════════════════════════════════════════════════════════╗\n", 'cyan');
        echo $this->colorize("║                        Test Summary                          ║\n", 'cyan');
        echo $this->colorize("╚══════════════════════════════════════════════════════════════╝\n", 'cyan');
        echo "\n";
        
        $successRate = round(($passedTests / $totalTests) * 100, 1);
        $summaryColor = $passedTests === $totalTests ? 'green' : ($passedTests > 0 ? 'yellow' : 'red');
        
        echo $this->colorize("Total Tests: ", 'white') . $totalTests . "\n";
        echo $this->colorize("Passed: ", 'green') . $passedTests . "\n";
        echo $this->colorize("Failed: ", 'red') . ($totalTests - $passedTests) . "\n";
        echo $this->colorize("Success Rate: ", 'white') . $this->colorize("{$successRate}%", $summaryColor) . "\n";
        
        if ($passedTests === $totalTests) {
            echo "\n" . $this->colorize("🎉 All tests passed! 🎉", 'green') . "\n";
        } elseif ($passedTests > 0) {
            echo "\n" . $this->colorize("⚠️  Some tests failed. Check the results above.", 'yellow') . "\n";
        } else {
            echo "\n" . $this->colorize("❌ All tests failed. Please check your configuration.", 'red') . "\n";
        }
        
        return $passedTests === $totalTests;
    }
}

// Run the tests
try {
    $testFramework = new OpenAITestFramework();
    
    // Check for command line arguments
    $testName = null;
    if (isset($argv[1])) {
        $testName = $argv[1];
    }
    
    $success = $testFramework->runAllTests($testName);
    exit($success ? 0 : 1);
} catch (Exception $e) {
    echo "\033[31mFatal Error: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

?>