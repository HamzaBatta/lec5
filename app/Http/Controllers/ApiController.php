<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ApiController extends Controller
{
    private $usersFile = 'users.json';
    private $productsFile = 'products.json';

    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);
        $users = json_decode(Storage::get($this->usersFile), true);

        $userIndex = collect($users)->search(function ($user) use ($credentials) {
            return $user['email'] === $credentials['email'] && $user['password'] === $credentials['password'];
        });

        if ($userIndex === false) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Generate a token
        $token = base64_encode(uniqid());

        // Save the token in the users.json file
        $users[$userIndex]['token'] = $token;
        Storage::put($this->usersFile, json_encode($users));

        return response()->json(['token' => $token]);
    }

    public function deleteProduct(Request $request, $id)
    {
        // Step 1: Get the token from the Authorization header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Step 2: Remove "Bearer " prefix to extract the token
        $token = substr($authHeader, 7); // Get everything after "Bearer "

        // Step 3: Fetch the users from users.json
        $users = json_decode(Storage::get($this->usersFile), true);

        // Step 4: Validate the token and find the user associated with it
        $user = collect($users)->first(function ($user) use ($token) {
            return isset($user['token']) && $user['token'] === $token;
        });

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Step 5: Fetch the products from products.json
        $products = json_decode(Storage::get($this->productsFile), true);

        // Step 6: Find the product by ID
        $product = collect($products)->firstWhere('id', $id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Step 7: Check if the user's email matches the product's owner email
        if ($product['owner_email'] !== $user['email']) {
            return response()->json(['message' => 'You are not authorized to delete this product'], 403);
        }

        // Step 8: Remove the product from the list
        $updatedProducts = collect($products)->reject(function ($item) use ($id) {
            return $item['id'] == $id;
        });

        // Step 9: Save the updated products list back to products.json
        Storage::put($this->productsFile, $updatedProducts->toJson());

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
