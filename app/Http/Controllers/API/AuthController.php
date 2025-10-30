<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * Creates a new user account and returns an authentication token.
     *
     * @group Authentication
     *
     * @bodyParam name string required The full name of the user. Example: John Doe
     * @bodyParam email string required The email address of the user. Must be unique. Example: johndoe@example.com
     * @bodyParam password string required The password (8–15 characters, must include at least one uppercase letter and one number). Example: MyPass123
     *
     * @response 201 {
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "johndoe@example.com",
     *     "created_at": "2025-10-29T12:00:00Z"
     *   },
     *   "token": "1|abc123xyz456tokenexample"
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     */
    public function register(Request $req)
    {
        try {
            $data = $req->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|min:8|max:15|regex:/^(?=.*[A-Z])(?=.*\d).+$/',
            ]);

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json(['user' => $user, 'token' => $token], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Registration failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Login user
     *
     * Authenticates a user and returns an access token.
     *
     * @group Authentication
     *
     * @bodyParam email string required The registered email of the user. Example: johndoe@example.com
     * @bodyParam password string required The user’s password. Example: MyPass123
     *
     * @response 200 {
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "johndoe@example.com"
     *     "email_verified_at": null,
     *     "created_at": "2025-10-29T02:14:22.000000Z",
     *     "updated_at": "2025-10-29T02:14:22.000000Z"
     *   },
     *   "token": "1|abc123xyz456tokenexample"
     * }
     * @response 422 {
     *   "message": "The provided credentials are incorrect.",
     *   "errors": {
     *     "email": ["The provided credentials are incorrect."]
     *   }
     * }
     */
    public function login(Request $req)
    {
        try {
            $data = $req->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $data['email'])->first();

            if (!$user || !Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
            }

            // Revoke previous tokens
            $user->tokens()->delete();

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json(['user' => $user, 'token' => $token]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Invalid credentials', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Login failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Logout user
     *
     * Revokes the currently active access token for the authenticated user.
     *
     * @authenticated
     * @group Authentication
     *
     * @header Authorization Bearer {token}
     *
     * @response 200 {
     *   "message": "Logged out"
     * }
     */
    public function logout(Request $req)
    {
        try {
            $req->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Logged out']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Logout failed', 'error' => $e->getMessage()], 500);
        }
    }
}
