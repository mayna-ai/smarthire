<?php

use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredFieldPassesWhenPresent(): void
    {
        $validator = new Validator(['email' => 'sara@example.com']);
        $validator->required('email');

        $this->assertFalse($validator->fails());
    }

    public function testRequiredFieldFailsWhenMissing(): void
    {
        $validator = new Validator([]);
        $validator->required('email');

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->getErrors());
    }

    public function testRequiredFieldFailsWhenEmptyString(): void
    {
        $validator = new Validator(['email' => '']);
        $validator->required('email');

        $this->assertTrue($validator->fails());
    }

    public function testEmailValidatesFormat(): void
    {
        $validator = new Validator(['email' => 'not-an-email']);
        $validator->email('email');

        $this->assertTrue($validator->fails());
    }

    public function testEmailPassesWithValidFormat(): void
    {
        $validator = new Validator(['email' => 'sara@example.com']);
        $validator->email('email');

        $this->assertFalse($validator->fails());
    }

    public function testEmailIsSkippedWhenFieldEmpty(): void
    {
        // email() ne doit pas se déclencher sur un champ absent — c'est le
        // rôle de required() de forcer la présence, pas celui d'email().
        $validator = new Validator([]);
        $validator->email('email');

        $this->assertFalse($validator->fails());
    }

    public function testMinLengthFailsWhenTooShort(): void
    {
        $validator = new Validator(['password' => 'abc']);
        $validator->minLength('password', 8);

        $this->assertTrue($validator->fails());
    }

    public function testMinLengthPassesWhenLongEnough(): void
    {
        $validator = new Validator(['password' => 'motdepasse123']);
        $validator->minLength('password', 8);

        $this->assertFalse($validator->fails());
    }

    public function testInFailsWhenValueNotAllowed(): void
    {
        $validator = new Validator(['role' => 'superadmin']);
        $validator->in('role', ['candidate', 'recruiter', 'admin']);

        $this->assertTrue($validator->fails());
    }

    public function testInPassesWhenValueAllowed(): void
    {
        $validator = new Validator(['role' => 'candidate']);
        $validator->in('role', ['candidate', 'recruiter', 'admin']);

        $this->assertFalse($validator->fails());
    }

    public function testChainedRulesAccumulateErrorsAcrossFields(): void
    {
        $validator = (new Validator(['email' => 'bad', 'password' => '123']))
            ->required('email')
            ->email('email')
            ->required('password')
            ->minLength('password', 8);

        $this->assertTrue($validator->fails());
        $errors = $validator->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }
}
