<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Dev/test stub for admin:setadmin — real implementation lives in the application.
 */
class UserModel
{
    public static ?object $response = null;

    /**
     * @return object{response_code: int, message?: string, service_message?: string}
     */
    public function firstAdminUser(string $email, string $password, string $name): object
    {
        /** @var object{response_code: int, message?: string, service_message?: string} $response */
        $response = self::$response ?? (object) [
            'response_code' => 201,
            'message' => 'created',
            'service_message' => 'Admin created',
        ];

        return $response;
    }

    public static function reset(): void
    {
        self::$response = null;
    }
}
