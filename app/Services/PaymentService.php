<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymentService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.shamcash.url');
        $this->token = config('services.shamcash.token');
    }

    // جلب الحسابات المربوطة (مهم للتحقق من أن الربط نشط)
    public function getAccounts()
    {
        return Http::withToken($this->token)
            ->get("{$this->baseUrl}/accounts")
            ->json();
    }

    // جلب المعاملات (للتحقق من الدفع)
    public function getTransactions(array $filters = [])
    {
        return Http::withToken($this->token)
            ->get("{$this->baseUrl}/transactions", $filters)
            ->json();
    }
}