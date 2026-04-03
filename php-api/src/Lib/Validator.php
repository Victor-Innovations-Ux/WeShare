<?php

namespace Lib;

class Validator {
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Check if field is required
     */
    public function required(string $field, string $message = null): self {
        if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
            $this->errors[$field][] = $message ?? "The $field field is required";
        }
        return $this;
    }

    /**
     * Check email format
     */
    public function email(string $field, string $message = null): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? "The $field must be a valid email";
        }
        return $this;
    }

    /**
     * Check minimum length
     */
    public function min(string $field, int $min, string $message = null): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field][] = $message ?? "The $field must be at least $min characters";
        }
        return $this;
    }

    /**
     * Check maximum length
     */
    public function max(string $field, int $max, string $message = null): self {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field][] = $message ?? "The $field must not exceed $max characters";
        }
        return $this;
    }

    /**
     * Check if field matches another field
     */
    public function matches(string $field, string $matchField, string $message = null): self {
        if (isset($this->data[$field]) && isset($this->data[$matchField])) {
            if ($this->data[$field] !== $this->data[$matchField]) {
                $this->errors[$field][] = $message ?? "The $field must match $matchField";
            }
        }
        return $this;
    }

    /**
     * Check if field is unique in database
     */
    public function unique(string $field, string $table, string $column = null, string $message = null): self {
        if (isset($this->data[$field])) {
            $column = $column ?? $field;
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = :value");
            $stmt->execute(['value' => $this->data[$field]]);
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                $this->errors[$field][] = $message ?? "The $field already exists";
            }
        }
        return $this;
    }

    /**
     * Check if validation passes
     */
    public function isValid(): bool {
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Get validated data
     */
    public function getValidatedData(): array {
        return $this->data;
    }
}