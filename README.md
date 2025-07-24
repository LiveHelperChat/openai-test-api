# OpenAI Function Calling Test Framework

A comprehensive PHP-based testing framework for validating OpenAI function calling capabilities and message responses. This framework allows you to test whether your AI assistant correctly calls the right functions with the right parameters or provides appropriate text responses based on user inputs.

## 🚀 Features

- **Function Call Testing**: Validate that the AI calls the correct functions with expected parameters
- **Message Response Testing**: Verify that the AI provides appropriate text responses when no function calls are needed
- **AI-Powered Meaning Validation**: Uses another AI model to validate if response meanings match expectations
- **Colorized Console Output**: Easy-to-read test results with color-coded success/failure indicators
- **Flexible Test Filtering**: Run all tests or filter by test name patterns
- **Detailed Error Reporting**: Comprehensive output showing what was expected vs what was received

## 📁 Project Structure

```
├── test.php           # Main test framework and runner
├── cases.json         # Test case definitions
├── structure.json     # OpenAI API configuration (model, tools, etc.)
├── settings.ini.php   # API keys and configuration settings
├── README.md          # This documentation
└── LICENSE           # Project license
```

## ⚙️ Configuration

> **⚠️ Important Setup Notice**  
> Before running the tests, you must copy the default configuration files to their active versions:
> - Copy `settings.ini.default.php` → `settings.ini.php`
> - Copy `cases.default.json` → `cases.json` 
> - Copy `structure.default.json` → `structure.json`
> 
> Then customize these files with your specific API keys and test configurations.
> 
> **Note**: After copying `structure.default.json` to `structure.json`, you might want to edit the file and remove the vector storage section if you don't need it for your testing purposes.

### settings.ini.php
Configure your API keys and models:

```php
<?php
return [
    "api_key" => "your-openai-api-key",
    "model_meaning_verification" => "gpt-4.1-mini",
    "model_core" => "o3-mini",
    "system_prompt" => "Your system prompt here..."
];
?>
```

### structure.json
Defines the OpenAI API request structure including available tools and model configuration.

## 📝 Test Case Format

Test cases are defined in `cases.json` with the following structure:

```json
{
    "name": "test_name",
    "messages": [
        {
            "role": "user",
            "content": "User input message"
        }
    ],
    "expected_output": {
        "type": "toolcall|message",
        "tool": "function_name",           // For toolcall type
        "arguments": {"key": "value"},     // Optional, for validating function arguments
        "meaning": "Expected meaning"      // For message type with AI validation
    }
}
```

## 🧪 Test Examples

Here are the current test cases included in the framework:

### 1. Function Call Test - Check Support Price
```json
{
    "name": "support_price",
    "messages": [
        {
            "role": "user",
            "content": "What is paid support price?"
        }
    ],
    "expected_output": {
        "type": "toolcall",
        "tool": "support_price"
    }
}
```

**What it tests**: Verifies that when a user asks about support price, the AI correctly calls the `support_price` function.

### 2. Function Call Test with Arguments - Password Reminder
```json
{
    "name": "password_reminder",
    "messages": [
        {
            "role": "user", 
            "content": "I forgot my password, my e-mail is remdex@gmail.com"
        }
    ],
    "expected_output": {
        "type": "toolcall",
        "tool": "password_reminder",
        "arguments": {"email": "remdex@gmail.com"}
    }
}
```

**What it tests**: Ensures the AI calls the correct function with the right parameters when asked to remind password.

### 3. Message Response Test - Welcome
```json
{
    "name": "welcome",
    "messages": [
        {
            "role": "user",
            "content": "Hi"
        }
    ],
    "expected_output": {
        "type": "message"
    }
}
```

**What it tests**: Validates that the AI responds with a text message (not a function call) for greeting inputs.

### 4. Message Response with Meaning Validation - Unrelated Questions
```json
{
    "name": "unrelated",
    "messages": [
        {
            "role": "user",
            "content": "Who is the president of the USA?"
        }
    ],
    "expected_output": {
        "type": "message",
        "meaning": "Answer to user question should not be provided as it is not related to the Live Helper Chat."
    }
}
```

**What it tests**: Ensures the AI refuses to answer questions unrelated to the Live Helper domain and uses AI-powered validation to check if the response meaning matches expectations.

## 🏃‍♂️ Running Tests

### Run All Tests
```bash
php test.php
```

### Run Specific Test by Name
```bash
php test.php "password_reminder"
```

This will run all tests containing "password_reminder" in their name.

## 📊 Test Output

The framework provides detailed, colorized output:

```
╔══════════════════════════════════════════════════════════════╗
║                  OpenAI Function Calling Test                ║
╚══════════════════════════════════════════════════════════════╝

Running test: support_price
Expected tool: support_price
[✓] support_price - PASS
    → Called tools: support_price

Running test: password_reminder  
Expected tool: password_reminder
[✓] password_reminder - PASS
    → Called tools: password_reminder
    → Arguments matched: {"email": "remdex@gmail.com"}

╔══════════════════════════════════════════════════════════════╗
║                        Test Summary                          ║
╚══════════════════════════════════════════════════════════════╝

Total Tests: 2
Passed: 2
Failed: 0
Success Rate: 100.0%

🎉 All tests passed! 🎉
```

## 🔍 Validation Types

### Function Call Validation
- Verifies the correct function is called
- Validates function arguments match expected values
- Checks that no unexpected function calls occur

### Message Response Validation
- Ensures AI responds with text messages when appropriate
- Validates no function calls are made when not expected
- Uses AI-powered meaning validation for semantic correctness

### AI-Powered Meaning Validation
For message responses with `meaning` defined, the framework uses a separate AI model to validate if the actual response semantically matches the expected meaning in context of the original user question.

## 🛠️ Adding New Tests

1. Open `cases.json`
2. Add a new test case following the format above
3. Run the tests to validate your new case

Example of adding a new test:

```json
{
    "name": "check_account_balance", 
    "messages": [
        {
            "role": "user",
            "content": "What is my account balance?"
        }
    ],
    "expected_output": {
        "type": "toolcall",
        "tool": "get_account_balance"
    }
}
```

## 🔧 Error Handling

The framework provides comprehensive error handling:
- API connection errors
- Invalid JSON responses  
- Missing or malformed test cases
- Function call validation failures
- Meaning validation errors

## 📋 Requirements

- PHP 7.4 or higher
- cURL extension enabled
- Valid OpenAI API key
- Internet connection for API calls

## 🤝 Contributing

1. Add test cases to `cases.json`
2. Update function definitions in `structure.json` if needed
3. Run tests to ensure everything works
4. Submit your changes

## 📄 License

This project is licensed under the terms specified in the LICENSE file.