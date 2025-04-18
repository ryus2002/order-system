<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class OrderController extends Controller
{
    /**
     * 訂單服務
     *
     * @var OrderService
     */
    protected $orderService;

    /**
     * 建立控制器實例
     *
     * @param OrderService $orderService
     * @return void
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * 獲取訂單列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $userId = $request->input('user_id');
        $status = $request->input('status');
        $perPage = $request->input('per_page', 15);

        $query = Order::query();

        // 根據用戶ID篩選
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // 根據狀態篩選
        if ($status) {
            $query->where('status', $status);
        }

        // 獲取分頁結果
        $orders = $query->with(['product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * 創建訂單
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // 創建訂單
            $order = $this->orderService->createOrder($request->all());

            return response()->json([
                'success' => true,
                'message' => '訂單創建成功',
                'data' => $order,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 獲取指定訂單
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $order = Order::with(['product', 'statusLogs'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '訂單不存在',
            ], 404);
        }
    }

    /**
     * 更新訂單狀態
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        // 驗證請求數據
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,processing,completed,failed,cancelled',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // 更新訂單狀態
            $order = $this->orderService->updateOrderStatus(
                $id,
                $request->input('status'),
                $request->input('remarks')
            );

            return response()->json([
                'success' => true,
                'message' => '訂單狀態更新成功',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 取消訂單
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($id)
    {
        try {
            // 取消訂單
            $order = $this->orderService->cancelOrder($id);

            return response()->json([
                'success' => true,
                'message' => '訂單取消成功',
                'data' => $order,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}