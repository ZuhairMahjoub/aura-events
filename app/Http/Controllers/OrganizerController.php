<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

class OrganizerController extends Controller
{
   
public function getUserDetails(string $id): JsonResponse
{
    try {
        // البحث عن المستخدم أو إرجاع 404
        $user = User::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'    => $user->id,
                'name'  => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
            ]
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['message' => 'المستخدم غير موجود'], 404);
    }
}
}
