<?php

namespace App\Domain\Crm\DTOs;

readonly class CrmUserOption
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $firstName = trim((string) ($data['FirstName'] ?? $data['first_name'] ?? ''));
        $lastName = trim((string) ($data['LastName'] ?? $data['last_name'] ?? ''));
        $fullName = trim($firstName.' '.$lastName);
        $title = (string) ($data['title'] ?? $data['Title'] ?? $data['name'] ?? $data['Name'] ?? $data['DisplayName'] ?? '');

        return new self(
            id: (string) ($data['id'] ?? $data['Id'] ?? $data['UserId'] ?? ''),
            name: $title !== '' ? $title : ($fullName !== '' ? $fullName : (string) ($data['Email'] ?? $data['email'] ?? 'بدون نام')),
            email: $data['email'] ?? $data['Email'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
