<?php
// tests/FormFieldTest.php
// Unit tests for the FormField component system (Zero\Support\Forms)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Support\Forms\Checkbox;
use Zero\Support\Forms\CheckboxGroup;
use Zero\Support\Forms\DateTimeInput;
use Zero\Support\Forms\EmailInput;
use Zero\Support\Forms\Hidden;
use Zero\Support\Forms\ModulesGridField;
use Zero\Support\Forms\NumberInput;
use Zero\Support\Forms\PasswordInput;
use Zero\Support\Forms\RadioGroup;
use Zero\Support\Forms\ReadonlyField;
use Zero\Support\Forms\Select;
use Zero\Support\Forms\Textarea;
use Zero\Support\Forms\TextInput;

echo "=== FormField Component System Tests ===\n";

App::bootstrap();

// 1. Registry
echo "Testing type registry...\n";
$types = App::getRegisteredFormFieldTypes();
assert_test(($types['text'] ?? null) === TextInput::class, "'text' type is registered to TextInput");
assert_test(($types['number'] ?? null) === NumberInput::class, "'number' type is registered to NumberInput");
assert_test(($types['int'] ?? null) === NumberInput::class, "'int' type is registered to NumberInput");
assert_test(($types['select'] ?? null) === Select::class, "'select' type is registered to Select");
assert_test(($types['checkbox'] ?? null) === Checkbox::class, "'checkbox' type is registered to Checkbox (single bool)");

App::registerFormFieldType('custom_widget', TextInput::class);
assert_test(App::getRegisteredFormFieldTypes()['custom_widget'] === TextInput::class, "A module can register a custom field type at runtime");

$field = App::makeFormField('text', 'username', ['label' => 'Username']);
assert_test($field instanceof TextInput, "App::makeFormField('text', ...) constructs a TextInput instance");

$unknown = App::makeFormField('totally_unregistered_type', 'foo', []);
assert_test($unknown instanceof TextInput, "An unregistered type string falls back to a plain text input");

// 2. TextInput / EmailInput / PasswordInput
echo "Testing TextInput, EmailInput, PasswordInput...\n";
$text = new TextInput('title', ['label' => 'Title', 'value' => '<script>x</script>', 'required' => true]);
$html = $text->render();
assert_test(\strpos($html, 'type="text"') !== false, "TextInput renders type=\"text\"");
assert_test(\strpos($html, 'name="title"') !== false, "TextInput renders the correct name attribute");
assert_test(\strpos($html, '<script>') === false, "TextInput escapes its value (no raw <script> in output)");
assert_test(\strpos($html, 'required') !== false, "TextInput renders required when configured");
assert_test($text->castSubmittedValue(['title' => '  Hello World  ']) === 'Hello World', "TextInput::castSubmittedValue() trims submitted strings");
assert_test($text->castSubmittedValue([]) === '', "TextInput::castSubmittedValue() returns '' when absent and no default given");

$email = new EmailInput('customer_email', ['label' => 'Email']);
assert_test(\strpos($email->render(), 'type="email"') !== false, "EmailInput renders type=\"email\"");

$password = new PasswordInput('password', ['label' => 'Password']);
$passwordHtml = $password->render();
assert_test(\strpos($passwordHtml, 'type="password"') !== false, "PasswordInput renders type=\"password\"");
assert_test(\strpos($passwordHtml, 'value=') === false, "PasswordInput never reflects a value back into the markup");

// 3. NumberInput -- min/max clamping (the fix for the negative/zero-value bug class)
echo "Testing NumberInput min/max clamping...\n";
$number = new NumberInput('posts_per_page', ['label' => 'Posts Per Page', 'default' => 6, 'min' => 1, 'max' => 100]);
assert_test($number->castSubmittedValue(['posts_per_page' => '-5']) == 1, "NumberInput clamps a negative submission up to 'min'");
assert_test($number->castSubmittedValue(['posts_per_page' => '0']) == 1, "NumberInput clamps a zero submission up to 'min'");
assert_test($number->castSubmittedValue(['posts_per_page' => '999999']) == 100, "NumberInput clamps an oversized submission down to 'max'");
assert_test($number->castSubmittedValue(['posts_per_page' => '12']) == 12, "NumberInput passes an in-range submission through unchanged");
assert_test($number->castSubmittedValue([]) == 6, "NumberInput falls back to 'default' when the field is absent");
assert_test(\strpos($number->render(), 'type="number"') !== false, "NumberInput renders type=\"number\"");
assert_test(\strpos($number->render(), 'min="1"') !== false, "NumberInput renders the configured min attribute");

$intField = new NumberInput('precedence', ['label' => 'Precedence', 'type' => 'int']);
$castInt = $intField->castSubmittedValue(['precedence' => '3.7']);
assert_test($castInt === 3 && \is_int($castInt), "NumberInput configured as 'int' casts to a true PHP int");

// 4. Checkbox (single bool) vs CheckboxGroup (array)
echo "Testing Checkbox and CheckboxGroup...\n";
$checkbox = new Checkbox('enabled', ['label' => 'Enabled']);
assert_test($checkbox->castSubmittedValue(['enabled' => '1']) === true, "Checkbox casts a present POST key to true");
assert_test($checkbox->castSubmittedValue([]) === false, "Checkbox casts an absent POST key to false");
$checkboxHtml = $checkbox->render();
assert_test(\strpos($checkboxHtml, '<label') !== false && \strpos($checkboxHtml, 'type="checkbox"') !== false, "Checkbox renders its own wrapping <label>");

$checkboxGroup = new CheckboxGroup('interests', ['label' => 'Interests', 'options' => ['a' => 'Alpha', 'b' => 'Beta'], 'value' => ['a']]);
assert_test($checkboxGroup->castSubmittedValue(['interests' => ['a', 'b']]) === ['a', 'b'], "CheckboxGroup::castSubmittedValue() returns the submitted array unchanged");
assert_test($checkboxGroup->castSubmittedValue([]) === [], "CheckboxGroup::castSubmittedValue() returns an empty array when absent");
$groupHtml = $checkboxGroup->render();
assert_test(\substr_count($groupHtml, 'type="checkbox"') === 2, "CheckboxGroup renders one checkbox per option");

// 5. RadioGroup -- only the first input carries 'required'
echo "Testing RadioGroup...\n";
$radioGroup = new RadioGroup('variant_id', ['label' => 'Variant', 'options' => ['s' => 'Small', 'm' => 'Medium'], 'required' => true, 'value' => 'm']);
$radioHtml = $radioGroup->render();
assert_test(\substr_count($radioHtml, 'required') === 1, "RadioGroup puts 'required' on exactly one input, not every option");
assert_test($radioGroup->castSubmittedValue(['variant_id' => 's']) === 's', "RadioGroup::castSubmittedValue() passes the submitted scalar through");

// 6. Select -- single, multiple, and allow-list validation
echo "Testing Select (single + multiple)...\n";
$select = new Select('status', ['label' => 'Status', 'options' => ['draft' => 'Draft', 'published' => 'Published'], 'default' => 'draft']);
assert_test($select->castSubmittedValue(['status' => 'published']) === 'published', "Select accepts a value present in the options allow-list");
assert_test($select->castSubmittedValue(['status' => 'not_a_real_option']) === 'draft', "Select falls back to 'default' for an out-of-options submission");

$multiSelect = new Select('tags', ['label' => 'Tags', 'options' => ['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma'], 'multiple' => true]);
$multiCast = $multiSelect->castSubmittedValue(['tags' => ['a', 'c', 'bogus']]);
assert_test($multiCast === ['a', 'c'], "Multi-select filters out-of-options values while preserving valid ones");
$multiSelectHtml = $multiSelect->render();
assert_test(\strpos($multiSelectHtml, 'name="tags[]"') !== false, "Multi-select renders name=\"field[]\"");

$legacySelect = new Select('comment_notifiers', ['label' => 'Notifiers', 'options' => ['1' => 'One', '2' => 'Two'], 'multiple' => true, 'value' => '["1","2"]']);
$selectedHtml = $legacySelect->render();
assert_test(\substr_count($selectedHtml, 'selected') === 2, "Select decodes a legacy JSON-encoded-array-in-a-string current value correctly");

// 7. Textarea, Hidden, DateTimeInput
echo "Testing Textarea, Hidden, DateTimeInput...\n";
$textarea = new Textarea('bio', ['label' => 'Bio', 'value' => 'Line one']);
assert_test(\strpos($textarea->render(), '<textarea') !== false, "Textarea renders a <textarea> element");

$hidden = new Hidden('id', ['value' => 'abc-123']);
$hiddenHtml = $hidden->render();
assert_test(\strpos($hiddenHtml, 'type="hidden"') !== false && \strpos($hiddenHtml, 'abc-123') !== false, "Hidden renders its value into a hidden input");

$datetime = new DateTimeInput('published_at', ['label' => 'Published At']);
assert_test($datetime->castSubmittedValue(['published_at' => '2026-01-15T09:30']) === '2026-01-15 09:30:00', "DateTimeInput normalizes datetime-local format to MySQL DATETIME format");
assert_test($datetime->castSubmittedValue([]) === '', "DateTimeInput returns '' when absent and no default given");

// 8. ReadonlyField
echo "Testing ReadonlyField...\n";
$readonly = new ReadonlyField('user_id', ['value' => 'some-uuid-here']);
$readonlyHtml = $readonly->render();
assert_test(\strpos($readonlyHtml, 'readonly-field-card') !== false, "ReadonlyField renders its display card");
assert_test(\strpos($readonlyHtml, 'type="hidden"') !== false, "ReadonlyField pairs its display with a hidden passthrough input");
assert_test($readonly->castSubmittedValue(['user_id' => 'some-uuid-here']) === 'some-uuid-here', "ReadonlyField passes its hidden input's submitted value through");

// 9. ModulesGridField -- requires the module registry to be populated
echo "Testing ModulesGridField...\n";
$modulesField = new ModulesGridField('enabled_modules', ['value' => '["blog"]']);
$modulesHtml = $modulesField->render();
assert_test(\strpos($modulesHtml, 'admin-modules-container') !== false, "ModulesGridField renders the module toggle grid");
assert_test(\strpos($modulesHtml, 'value="admin"') === false, "ModulesGridField excludes the 'admin' system module from the toggle list");
assert_test($modulesField->castSubmittedValue(['enabled_modules' => ['blog', 'shop']]) === ['blog', 'shop'], "ModulesGridField::castSubmittedValue() returns the submitted array unchanged");

echo "FormField component system tests completed.\n\n";
