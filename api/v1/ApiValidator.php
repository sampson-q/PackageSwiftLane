<?php
/**
 * ApiValidator – lightweight input validation for API endpoints.
 *
 * Usage:
 *   $errors = ApiValidator::validate($data, [
 *       'email'    => 'required|email',
 *       'name'     => 'required|string|max:100',
 *       'age'      => 'required|integer|min:18|max:120',
 *       'role'     => 'required|in:admin,user,driver',
 *       'price'    => 'required|numeric|min:0',
 *       'date'     => 'required|date',
 *       'phone'    => 'nullable|string|min:7',
 *   ]);
 *   if (!empty($errors)) { ... }
 *
 * Supported rules:
 *   required        – field must be present and non-empty
 *   nullable        – field may be absent or null (skips further rules if so)
 *   string          – must be castable to string
 *   integer         – must be an integer value
 *   numeric         – must be numeric (int or float)
 *   boolean         – must be 1/0/true/false
 *   email           – must be a valid e-mail address
 *   url             – must be a valid URL
 *   date            – must be parseable as a date
 *   min:<n>         – string length ≥ n  OR  numeric value ≥ n
 *   max:<n>         – string length ≤ n  OR  numeric value ≤ n
 *   in:<a,b,...>    – must be one of the listed values
 *   regex:<pattern> – must match the PCRE pattern
 */
class ApiValidator
{
    /**
     * Validate $data against $rules.
     *
     * @param array  $data  Associative input array
     * @param array  $rules Field => 'rule1|rule2|...' map
     * @return array Associative array of field => error message; empty means valid
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleStr) {
            $ruleParts = explode('|', $ruleStr);
            $nullable  = in_array('nullable', $ruleParts, true);
            $required  = in_array('required', $ruleParts, true);

            $value   = $data[$field] ?? null;
            $present = array_key_exists($field, $data);
            $empty   = ($value === null || $value === '');

            // required check
            if ($required && (!$present || $empty)) {
                $errors[$field] = "The {$field} field is required.";
                continue;
            }

            // nullable: skip remaining rules if absent/null
            if ($nullable && (!$present || $empty)) {
                continue;
            }

            // Skip remaining rules if not present (non-required, non-nullable)
            if (!$present || $empty) {
                continue;
            }

            foreach ($ruleParts as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                // Rules with parameters (e.g. min:5, in:a,b,c)
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $param] = explode(':', $rule, 2);
                } else {
                    $ruleName = $rule;
                    $param    = null;
                }

                $error = self::applyRule($field, $value, $ruleName, $param);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // stop on first error per field
                }
            }
        }

        return $errors;
    }

    /**
     * Apply a single rule to a value.
     *
     * @return string|null Error message or null if valid
     */
    private static function applyRule(string $field, $value, string $rule, ?string $param): ?string
    {
        switch ($rule) {
            case 'string':
                if (!is_scalar($value)) {
                    return "The {$field} must be a string.";
                }
                break;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    return "The {$field} must be an integer.";
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    return "The {$field} must be numeric.";
                }
                break;

            case 'boolean':
                if (!in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                    return "The {$field} must be boolean.";
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "The {$field} must be a valid email address.";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return "The {$field} must be a valid URL.";
                }
                break;

            case 'date':
                $ts = strtotime((string)$value);
                if ($ts === false || $ts === -1) {
                    return "The {$field} must be a valid date.";
                }
                break;

            case 'min':
                if ($param === null) {
                    break;
                }
                $n = (float)$param;
                if (is_numeric($value)) {
                    if ((float)$value < $n) {
                        return "The {$field} must be at least {$param}.";
                    }
                } else {
                    if (mb_strlen((string)$value, 'UTF-8') < (int)$n) {
                        return "The {$field} must be at least {$param} characters.";
                    }
                }
                break;

            case 'max':
                if ($param === null) {
                    break;
                }
                $n = (float)$param;
                if (is_numeric($value)) {
                    if ((float)$value > $n) {
                        return "The {$field} must not exceed {$param}.";
                    }
                } else {
                    if (mb_strlen((string)$value, 'UTF-8') > (int)$n) {
                        return "The {$field} must not exceed {$param} characters.";
                    }
                }
                break;

            case 'in':
                if ($param === null) {
                    break;
                }
                $allowed = explode(',', $param);
                if (!in_array((string)$value, $allowed, true)) {
                    $list = implode(', ', $allowed);
                    return "The {$field} must be one of: {$list}.";
                }
                break;

            case 'regex':
                if ($param === null) {
                    break;
                }
                $pattern = '/' . $param . '/u';
                if (!preg_match($pattern, (string)$value)) {
                    return "The {$field} format is invalid.";
                }
                break;

            default:
                // Unknown rule — ignore silently
                break;
        }

        return null;
    }
}
