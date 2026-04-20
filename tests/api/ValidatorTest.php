<?php
/**
 * ValidatorTest – unit tests for ApiValidator.
 *
 * These tests exercise all validation rules without touching the database.
 */

use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    // ── required ─────────────────────────────────────────────────────────────

    public function testRequiredPassesWhenPresent(): void
    {
        $errors = ApiValidator::validate(['name' => 'Alice'], ['name' => 'required']);
        $this->assertEmpty($errors);
    }

    public function testRequiredFailsWhenAbsent(): void
    {
        $errors = ApiValidator::validate([], ['name' => 'required']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testRequiredFailsOnEmptyString(): void
    {
        $errors = ApiValidator::validate(['name' => ''], ['name' => 'required']);
        $this->assertArrayHasKey('name', $errors);
    }

    // ── nullable ──────────────────────────────────────────────────────────────

    public function testNullableSkipsRulesWhenAbsent(): void
    {
        $errors = ApiValidator::validate([], ['phone' => 'nullable|string|min:7']);
        $this->assertEmpty($errors);
    }

    public function testNullableSkipsRulesOnEmptyString(): void
    {
        $errors = ApiValidator::validate(['phone' => ''], ['phone' => 'nullable|string|min:7']);
        $this->assertEmpty($errors);
    }

    public function testNullableAppliesRulesWhenPresent(): void
    {
        $errors = ApiValidator::validate(['phone' => 'ab'], ['phone' => 'nullable|string|min:7']);
        $this->assertArrayHasKey('phone', $errors);
    }

    // ── email ─────────────────────────────────────────────────────────────────

    public function testEmailPassesValid(): void
    {
        $errors = ApiValidator::validate(['email' => 'user@example.com'], ['email' => 'required|email']);
        $this->assertEmpty($errors);
    }

    public function testEmailFailsInvalid(): void
    {
        $errors = ApiValidator::validate(['email' => 'not-an-email'], ['email' => 'required|email']);
        $this->assertArrayHasKey('email', $errors);
    }

    // ── integer ───────────────────────────────────────────────────────────────

    public function testIntegerPassesNumericString(): void
    {
        $errors = ApiValidator::validate(['age' => '25'], ['age' => 'required|integer']);
        $this->assertEmpty($errors);
    }

    public function testIntegerFailsFloat(): void
    {
        $errors = ApiValidator::validate(['age' => '25.5'], ['age' => 'required|integer']);
        $this->assertArrayHasKey('age', $errors);
    }

    public function testIntegerFailsAlpha(): void
    {
        $errors = ApiValidator::validate(['age' => 'abc'], ['age' => 'required|integer']);
        $this->assertArrayHasKey('age', $errors);
    }

    // ── numeric ───────────────────────────────────────────────────────────────

    public function testNumericPassesFloat(): void
    {
        $errors = ApiValidator::validate(['price' => '9.99'], ['price' => 'required|numeric']);
        $this->assertEmpty($errors);
    }

    public function testNumericFailsAlpha(): void
    {
        $errors = ApiValidator::validate(['price' => 'nine'], ['price' => 'required|numeric']);
        $this->assertArrayHasKey('price', $errors);
    }

    // ── min / max (string length) ─────────────────────────────────────────────

    public function testMinPassesOnSufficientLength(): void
    {
        $errors = ApiValidator::validate(['name' => 'Alice'], ['name' => 'required|string|min:3']);
        $this->assertEmpty($errors);
    }

    public function testMinFailsOnShortString(): void
    {
        $errors = ApiValidator::validate(['name' => 'AB'], ['name' => 'required|string|min:3']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function testMaxPassesOnShortString(): void
    {
        $errors = ApiValidator::validate(['name' => 'Alice'], ['name' => 'required|string|max:10']);
        $this->assertEmpty($errors);
    }

    public function testMaxFailsOnLongString(): void
    {
        $errors = ApiValidator::validate(['name' => 'Alice in Wonderland'], ['name' => 'required|string|max:5']);
        $this->assertArrayHasKey('name', $errors);
    }

    // ── min / max (numeric) ───────────────────────────────────────────────────

    public function testMinPassesOnNumericValue(): void
    {
        $errors = ApiValidator::validate(['age' => '18'], ['age' => 'required|integer|min:18']);
        $this->assertEmpty($errors);
    }

    public function testMinFailsOnLowNumericValue(): void
    {
        $errors = ApiValidator::validate(['age' => '17'], ['age' => 'required|integer|min:18']);
        $this->assertArrayHasKey('age', $errors);
    }

    public function testMaxPassesOnNumericValue(): void
    {
        $errors = ApiValidator::validate(['score' => '100'], ['score' => 'required|numeric|max:100']);
        $this->assertEmpty($errors);
    }

    public function testMaxFailsOnHighNumericValue(): void
    {
        $errors = ApiValidator::validate(['score' => '101'], ['score' => 'required|numeric|max:100']);
        $this->assertArrayHasKey('score', $errors);
    }

    // ── in ────────────────────────────────────────────────────────────────────

    public function testInPassesAllowedValue(): void
    {
        $errors = ApiValidator::validate(['gender' => 'M'], ['gender' => 'required|in:M,F,O']);
        $this->assertEmpty($errors);
    }

    public function testInFailsDisallowedValue(): void
    {
        $errors = ApiValidator::validate(['gender' => 'X'], ['gender' => 'required|in:M,F,O']);
        $this->assertArrayHasKey('gender', $errors);
    }

    // ── date ──────────────────────────────────────────────────────────────────

    public function testDatePassesValidDate(): void
    {
        $errors = ApiValidator::validate(['dob' => '2000-01-15'], ['dob' => 'required|date']);
        $this->assertEmpty($errors);
    }

    public function testDateFailsInvalidDate(): void
    {
        $errors = ApiValidator::validate(['dob' => 'not-a-date'], ['dob' => 'required|date']);
        $this->assertArrayHasKey('dob', $errors);
    }

    // ── regex ─────────────────────────────────────────────────────────────────

    public function testRegexPassesMatchingValue(): void
    {
        $errors = ApiValidator::validate(['code' => 'ABC123'], ['code' => 'required|regex:^[A-Z0-9]+$']);
        $this->assertEmpty($errors);
    }

    public function testRegexFailsNonMatchingValue(): void
    {
        $errors = ApiValidator::validate(['code' => 'abc!'], ['code' => 'required|regex:^[A-Z0-9]+$']);
        $this->assertArrayHasKey('code', $errors);
    }

    // ── multiple fields ───────────────────────────────────────────────────────

    public function testMultipleFieldsAllValid(): void
    {
        $data = ['email' => 'a@b.com', 'name' => 'Alice', 'age' => '25'];
        $rules = [
            'email' => 'required|email',
            'name'  => 'required|string|min:2|max:50',
            'age'   => 'required|integer|min:18',
        ];
        $this->assertEmpty(ApiValidator::validate($data, $rules));
    }

    public function testMultipleFieldsMultipleErrors(): void
    {
        $data = ['email' => 'bad', 'name' => 'A', 'age' => '16'];
        $rules = [
            'email' => 'required|email',
            'name'  => 'required|string|min:2',
            'age'   => 'required|integer|min:18',
        ];
        $errors = ApiValidator::validate($data, $rules);
        $this->assertCount(3, $errors);
    }

    // ── first error wins per field ────────────────────────────────────────────

    public function testFirstRuleWinsForField(): void
    {
        // "required" must be reported, not "min"
        $errors = ApiValidator::validate(['name' => ''], ['name' => 'required|string|min:5']);
        $this->assertArrayHasKey('name', $errors);
        $this->assertStringContainsString('required', $errors['name']);
    }

    // ── url ───────────────────────────────────────────────────────────────────

    public function testUrlPassesValidUrl(): void
    {
        $errors = ApiValidator::validate(['link' => 'https://example.com/path?q=1'], ['link' => 'required|url']);
        $this->assertEmpty($errors);
    }

    public function testUrlFailsInvalidUrl(): void
    {
        $errors = ApiValidator::validate(['link' => 'not a url'], ['link' => 'required|url']);
        $this->assertArrayHasKey('link', $errors);
    }
}
