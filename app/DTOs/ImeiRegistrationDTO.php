<?
namespace App\DTOs;

class ImeiRegistrationDTO {
    public function __construct(
        public string $imei1,
        public ?string $imei2 = null,
        public string $simType,
        public int $packageId,
        public int $userId
    ) {}
}