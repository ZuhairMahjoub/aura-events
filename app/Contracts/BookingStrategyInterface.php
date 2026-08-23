<?php
// app/Contracts/BookingStrategyInterface.php

namespace App\Contracts;

use App\DTOs\BookingData;
use App\Models\Booking;

interface BookingStrategyInterface
{
   
    public function validate(BookingData $data): void;

    
    public function reserveCapacity(BookingData $data): void;

   
    public function buildTimeSnapshot(BookingData $data): array;

    
    public function buildTypeMetadata(BookingData $data): array;


    public function release(Booking $booking): void;
}