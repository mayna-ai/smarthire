<?php

class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public function required(string $field): self
    {
        if (empty($this->data[$field])) {
            $this->errors[$field] = "Le champ $field est requis.";
        }
        return $this;
    }

    public function email(string $field): self
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Le champ $field doit être un email valide.";
        }
        return $this;
    }

    public function minLength(string $field, int $length): self
    {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = "Le champ $field doit contenir au moins $length caractères.";
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        if (!empty($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "Le champ $field doit être l'une des valeurs : " . implode(', ', $allowed);
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
