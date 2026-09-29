<?php
namespace App\Contracts;

interface PaymentGatewayInterface {
    public function createTransaction(array $data);
    public function verifyPayment(string $reference);
}