<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Resources\Cart\CartResource;
use App\Http\Resources\Cart\CartItemResource;
use App\DTOs\Cart\CartItemData;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {}

    
    public function show(Request $request): CartResource
    {
        $cart = $this->cartService->getOrCreateActiveCart($request->user()->id);
        
        return new CartResource(
            $cart->load(['items.listing', 'items.variant', 'items.slot'])
        );
    }

    
    public function addItem(StoreCartItemRequest $request): CartItemResource
    {
        $dto = CartItemData::fromArray($request->validated());
        
        $item = $this->cartService->addItem($request->user()->id, $dto);

        return new CartItemResource($item->load(['listing', 'variant', 'slot']));
    }


    public function updateQuantity(Request $request, string $cartItemId): CartItemResource
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $item = $this->cartService->updateQuantity(
            $request->user()->id, 
            $cartItemId, 
            $request->integer('quantity')
        );

        return new CartItemResource($item->load(['listing', 'variant', 'slot']));
    }

    
    public function removeItem(Request $request, string $cartItemId): JsonResponse
    {
        $this->cartService->removeItem($request->user()->id, $cartItemId);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العنصر من السلة بنجاح.'
        ]);
    }

   
    public function checkout(Request $request): JsonResponse
    {
        $result = $this->cartService->checkout($request->user()->id);

        return response()->json([
            'success' => empty($result['failed_items']),
            'message' => empty($result['failed_items']) 
                ? 'تمت عملية تأكيد الحجوزات بنجاح.' 
                : 'تمت العملية بنجاح جزئي، بعض العناصر لم تكتمل.',
            'data' => $result
        ]);
    }
}
