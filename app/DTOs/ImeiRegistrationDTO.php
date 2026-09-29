<?php
namespace App\DTOs;

class ImeiRegistrationDTO {
    public function __construct(
        public string $imei1,
        public string $simType,
        public int $packageId,
        public int $userId,
        public ?string $imei2 = null
    ) {}
}