<?php
// tests/ValidatorTest.php
// Unit tests for the extensible Core Validator (Zero\Core\Validator)

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\Validator;

echo "=== Validator Component Tests ===\n";

// 1. Test Required Rule
echo "Testing 'required' validation...\n";
$validator1 = new Validator(['username' => ''], ['username' => 'required']);
assert_test(!$validator1->validate(), "Empty string fails required rule");
assert_test($validator1->getFirstError('username') === "The username field is required.", "Returns correct default required error message");

$validator2 = new Validator(['username' => 'zero_purist'], ['username' => 'required']);
assert_test($validator2->validate(), "Non-empty string passes required rule");

$validator3 = new Validator(['tags' => []], ['tags' => 'required']);
assert_test(!$validator3->validate(), "Empty array fails required rule");

// 2. Test Email Rule
echo "Testing 'email' validation...\n";
$validatorEmail1 = new Validator(['email' => 'invalid-email'], ['email' => 'required|email']);
assert_test(!$validatorEmail1->validate(), "Invalid email format fails email rule");

$validatorEmail2 = new Validator(['email' => 'admin@d6laptop.zero'], ['email' => 'required|email']);
assert_test($validatorEmail2->validate(), "Valid email format passes email rule");

// 3. Test Phone Rule
echo "Testing 'phone' validation...\n";
$validatorPhone1 = new Validator(['phone' => 'abc-123-xyz'], ['phone' => 'required|phone']);
assert_test(!$validatorPhone1->validate(), "Invalid phone characters fail phone rule");

$validatorPhone2 = new Validator(['phone' => '+1 (555) 123-4567'], ['phone' => 'required|phone']);
assert_test($validatorPhone2->validate(), "Valid international phone layout passes phone rule");

$validatorPhone3 = new Validator(['phone' => '0987654321'], ['phone' => 'required|phone']);
assert_test($validatorPhone3->validate(), "Simple numerical string of length 10 passes phone rule");

// 4. Test Numeric & Integer Rules
echo "Testing 'numeric' and 'integer' validation...\n";
$validatorInt1 = new Validator(['age' => 'twenty-one', 'price' => '12.50'], ['age' => 'integer', 'price' => 'numeric']);
assert_test(!$validatorInt1->validate(), "Non-numeric/non-integer values fail numeric/integer checks");

$validatorInt2 = new Validator(['age' => 42, 'price' => '12.50'], ['age' => 'integer', 'price' => 'numeric']);
assert_test($validatorInt2->validate(), "Valid integer and float numeric string pass validation checks");

// 5. Test Min & Max Rules (on Strings and Numbers)
echo "Testing 'min' and 'max' validations...\n";
$validatorMinStr = new Validator(['username' => 'ab'], ['username' => 'min:3']);
assert_test(!$validatorMinStr->validate(), "String shorter than min length fails min rule");

$validatorMinStrPass = new Validator(['username' => 'abc'], ['username' => 'min:3']);
assert_test($validatorMinStrPass->validate(), "String matching min length passes min rule");

$validatorMaxNum = new Validator(['quantity' => 15], ['quantity' => 'numeric|max:10']);
assert_test(!$validatorMaxNum->validate(), "Number greater than max limit fails max rule");

$validatorMaxNumPass = new Validator(['quantity' => 8], ['quantity' => 'numeric|max:10']);
assert_test($validatorMaxNumPass->validate(), "Number within max limit passes max rule");

// 6. Test Custom Extensible Rules Registration
echo "Testing custom rule extension...\n";
Validator::registerRule('even_number', function($value, $param, $data) {
    return is_numeric($value) && ((int)$value % 2 === 0);
});

$validatorCustom1 = new Validator(['number' => 7], ['number' => 'required|even_number']);
assert_test(!$validatorCustom1->validate(), "Custom rule evaluating odd number fails");

$validatorCustom2 = new Validator(['number' => 8], ['number' => 'required|even_number']);
assert_test($validatorCustom2->validate(), "Custom rule evaluating even number passes successfully");

// 7. Test Get Validated Data Filter
echo "Testing validated data payload filtering...\n";
$inputData = [
    'title' => 'New In-Between Sidebar Layout',
    'status' => 'published',
    'untrusted_unvalidated_field' => 'injection_attempt_value'
];
$validatorFilter = new Validator($inputData, [
    'title' => 'required|min:5',
    'status' => 'required'
]);
assert_test($validatorFilter->validate(), "Declarative fields validate successfully");
$validatedData = $validatorFilter->getValidatedData();
assert_test(isset($validatedData['title']) && isset($validatedData['status']), "Validated payload contains expected fields");
assert_test(!isset($validatedData['untrusted_unvalidated_field']), "Validated payload strictly filters out non-declared fields");

echo "Validator component tests completed successfully!\n";
