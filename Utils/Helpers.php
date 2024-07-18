<?php

namespace NW\WebService\References\Operations\Notification;

function getResellerEmailFrom(int $resellerId): string
{
    return 'contractor@example.com';
}

function getEmailsByPermit(int $resellerId, string $event): array
{
    // Mock data, replace with real data fetching logic
    return ['someemail@example.com', 'someemail2@example.com'];
}
