<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>高併發訂單系統</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        #loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255,255,255,0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <div id="loading" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">高併發訂單系統</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="#products">產品列表</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#orders">訂單查詢</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="alert alert-info">
            <h4>系統說明</h4>
            <p>這是一個高併發訂單處理系統，使用 Redis 分散式鎖防止超賣，Kafka 消息隊列處理訂單，並實現了 DB 讀寫分離架構。</p>
        </div>

        <section id="products" class="mb-5">
            <h2 class="mb-4">產品列表</h2>
            <div class="row" id="product-list">
                <!-- 產品列表將通過 API 動態加載 -->
                <div class="col-12 text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="order-form-section" class="mb-5" style="display: none;">
            <h2 class="mb-4">創建訂單</h2>
            <div class="card">
                <div class="card-body">
                    <form id="order-form">
                        <input type="hidden" id="product-id">
                        <input type="hidden" id="user-id" value="1">
                        <div class="mb-3">
                            <h5 id="product-name"></h5>
                            <p id="product-description" class="text-muted"></p>
                            <p>價格: <span id="product-price"></span></p>
                            <p>庫存: <span id="product-stock"></span></p>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">購買數量</label>
                            <input type="number" class="form-control" id="quantity" min="1" value="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">備註</label>
                            <textarea class="form-control" id="remarks" rows="2"></textarea>
                        </div>
                        <div id="order-form-errors" class="alert alert-danger" style="display: none;"></div>
                        <button type="submit" class="btn btn-primary">提交訂單</button>
                        <button type="button" class="btn btn-secondary" id="cancel-order">取消</button>
                    </form>
                </div>
            </div>
        </section>

        <section id="orders" class="mb-5">
            <h2 class="mb-4">訂單查詢</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="input-group">
                        <input type="text" class="form-control" id="order-id" placeholder="請輸入訂單 ID">
                        <button class="btn btn-primary" id="search-order">查詢</button>
                        <button class="btn btn-secondary" id="list-all-orders">查看所有訂單</button>
                    </div>
                </div>
            </div>

            <div id="order-details" class="card mb-4" style="display: none;">
                <div class="card-header">
                    訂單詳情
                </div>
                <div class="card-body" id="order-details-content">
                    <!-- 訂單詳情將通過 API 動態加載 -->
                </div>
            </div>

            <div id="all-orders" style="display: none;">
                <h3>所有訂單</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>訂單 ID</th>
                                <th>產品</th>
                                <th>數量</th>
                                <th>總價</th>
                                <th>狀態</th>
                                <th>創建時間</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="all-orders-list">
                            <!-- 訂單列表將通過 API 動態加載 -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <strong class="me-auto" id="toast-title">通知</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body" id="toast-message">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // API 基礎 URL
        const API_BASE_URL = '/api';
        
        // 獲取 CSRF Token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        // 顯示加載中
        function showLoading() {
            document.getElementById('loading').style.display = 'flex';
        }
        
        // 隱藏加載中
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
        
        // 顯示通知
        function showToast(title, message, type = 'success') {
            const toastEl = document.getElementById('toast');
            const toast = new bootstrap.Toast(toastEl);
            
            document.getElementById('toast-title').textContent = title;
            document.getElementById('toast-message').textContent = message;
            
            // 設置顏色
            toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-white');
            if (type === 'success') {
                toastEl.classList.add('bg-success', 'text-white');
            } else if (type === 'error') {
                toastEl.classList.add('bg-danger', 'text-white');
            } else if (type === 'warning') {
                toastEl.classList.add('bg-warning');
            }
            
            toast.show();
        }
        
        // 顯示表單錯誤
        function showFormErrors(errors) {
            const errorContainer = document.getElementById('order-form-errors');
            errorContainer.innerHTML = '';
            
            if (typeof errors === 'string') {
                errorContainer.innerHTML = `<p>${errors}</p>`;
            } else if (typeof errors === 'object') {
                const errorList = document.createElement('ul');
                errorList.style.marginBottom = '0';
                
                for (const field in errors) {
                    if (Array.isArray(errors[field])) {
                        errors[field].forEach(error => {
                            const li = document.createElement('li');
                            li.textContent = error;
                            errorList.appendChild(li);
                        });
                    } else if (typeof errors[field] === 'string') {
                        const li = document.createElement('li');
                        li.textContent = errors[field];
                        errorList.appendChild(li);
                    }
                }
                
                errorContainer.appendChild(errorList);
            }
            
            errorContainer.style.display = 'block';
        }
        
        // 隱藏表單錯誤
        function hideFormErrors() {
            const errorContainer = document.getElementById('order-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
        }
        
        // 安全獲取對象屬性值，避免 undefined
        function safeGet(obj, path, defaultValue = '無資料') {
            try {
                if (obj === null || obj === undefined) return defaultValue;
                
                const keys = path.split('.');
                let result = obj;
                
                for (const key of keys) {
                    if (result === null || result === undefined || typeof result !== 'object') {
                        return defaultValue;
                    }
                    result = result[key];
                }
                
                return result !== null && result !== undefined ? result : defaultValue;
            } catch (e) {
                console.error('Error in safeGet:', e);
                return defaultValue;
            }
        }
        
        // 安全格式化日期
        function formatDate(dateString) {
            if (!dateString) return '無日期資料';
            
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '無效日期';
                
                return date.toLocaleString('zh-TW', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } catch (e) {
                console.error('Error formatting date:', e);
                return '無效日期';
            }
        }
        
        // 獲取產品列表
        async function fetchProducts() {
            try {
                showLoading();
                const response = await fetch(`${API_BASE_URL}/products`);
                if (!response.ok) {
                    throw new Error('獲取產品列表失敗');
                }
                
                const responseData = await response.json();
                // 正確處理分頁數據結構
                const products = Array.isArray(responseData) ? responseData : 
                               (responseData.data && Array.isArray(responseData.data) ? responseData.data : 
                               (responseData.data && responseData.data.data && Array.isArray(responseData.data.data) ? responseData.data.data : []));
                
                const productList = document.getElementById('product-list');
                productList.innerHTML = '';
                
                if (products.length === 0) {
                    productList.innerHTML = '<div class="col-12"><div class="alert alert-info">暫無產品數據</div></div>';
                    return;
                }
                
                products.forEach(product => {
                    const productCard = document.createElement('div');
                    productCard.className = 'col-md-4';
                    productCard.innerHTML = `
                        <div class="card product-card">
                            <div class="card-body">
                                <h5 class="card-title">${safeGet(product, 'name')}</h5>
                                <p class="card-text">${safeGet(product, 'description', '無描述')}</p>
                                <p class="card-text">價格: $${safeGet(product, 'price', '0')}</p>
                                <p class="card-text">庫存: ${safeGet(product, 'stock', '0')}</p>
                                <button class="btn btn-primary buy-btn" data-id="${safeGet(product, 'id')}">購買</button>
                            </div>
                        </div>
                    `;
                    productList.appendChild(productCard);
                });
                
                // 綁定購買按鈕事件
                document.querySelectorAll('.buy-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = this.getAttribute('data-id');
                        showOrderForm(productId);
                    });
                });
            } catch (error) {
                console.error('Error:', error);
                showToast('錯誤', error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        // 顯示訂單表單
        async function showOrderForm(productId) {
            try {
                showLoading();
                hideFormErrors();
                const response = await fetch(`${API_BASE_URL}/products/${productId}`);
                if (!response.ok) {
                    throw new Error('獲取產品詳情失敗');
                }
                
                const responseData = await response.json();
                // 處理可能的數據結構差異
                const product = responseData.data || responseData;
                
                document.getElementById('product-id').value = safeGet(product, 'id');
                document.getElementById('product-name').textContent = safeGet(product, 'name');
                document.getElementById('product-description').textContent = safeGet(product, 'description', '無描述');
                document.getElementById('product-price').textContent = `$${safeGet(product, 'price', '0')}`;
                document.getElementById('product-stock').textContent = safeGet(product, 'stock', '0');
                document.getElementById('quantity').max = safeGet(product, 'stock', '1');
                document.getElementById('quantity').value = 1;
                document.getElementById('remarks').value = '';
                
                document.getElementById('order-form-section').style.display = 'block';
                document.getElementById('order-form-section').scrollIntoView({ behavior: 'smooth' });
            } catch (error) {
                console.error('Error:', error);
                showToast('錯誤', error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        // 提交訂單
        async function submitOrder(event) {
            event.preventDefault();
            hideFormErrors();
            
            const productId = document.getElementById('product-id').value;
            const quantity = document.getElementById('quantity').value;
            const userId = document.getElementById('user-id').value;
            const remarks = document.getElementById('remarks').value;
            
            if (!productId || !quantity || quantity < 1) {
                showFormErrors('請選擇產品並輸入有效數量');
                return;
            }
            
            try {
                showLoading();
                const response = await fetch(`${API_BASE_URL}/orders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: parseInt(userId),
                        product_id: parseInt(productId),
                        quantity: parseInt(quantity),
                        remarks: remarks
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    console.error('Order creation failed:', data);
                    
                    if (response.status === 422) {
                        // 處理驗證錯誤
                        if (data.errors) {
                            showFormErrors(data.errors);
                        } else if (data.message) {
                            showFormErrors(data.message);
                        } else {
                            showFormErrors('訂單創建失敗，請檢查輸入資料');
                        }
                        return;
                    }
                    
                    throw new Error(safeGet(data, 'message', '創建訂單失敗'));
                }
                
                // 嘗試多種可能的屬性名稱來獲取訂單ID
                const orderId = safeGet(data, 'data.id') || safeGet(data, 'id') || safeGet(data, 'order_id');
                showToast('成功', `訂單創建成功! 訂單 ID: ${orderId}`);
                document.getElementById('order-form-section').style.display = 'none';
                
                // 刷新產品列表
                fetchProducts();
                
                // 顯示訂單詳情
                if (orderId) {
                    fetchOrderDetails(orderId);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('錯誤', error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        // 獲取訂單詳情
        async function fetchOrderDetails(orderId) {
            try {
                showLoading();
                const response = await fetch(`${API_BASE_URL}/orders/${orderId}`);
                
                if (!response.ok) {
                    throw new Error('獲取訂單詳情失敗');
                }
                
                const responseData = await response.json();
                // 處理可能的數據結構差異
                const order = responseData.data || responseData;
                
                const orderDetailsContent = document.getElementById('order-details-content');
                orderDetailsContent.innerHTML = `
                    <h5>訂單 ID: ${safeGet(order, 'id')}</h5>
                    <p>產品: ${safeGet(order, 'product.name', '未知產品')}</p>
                    <p>數量: ${safeGet(order, 'quantity', '0')}</p>
                    <p>總價: $${safeGet(order, 'total_price', '0')}</p>
                    <p>狀態: <span class="badge bg-${getStatusColor(safeGet(order, 'status', '未知'))}">${safeGet(order, 'status', '未知')}</span></p>
                    <p>創建時間: ${formatDate(safeGet(order, 'created_at'))}</p>
                    <p>備註: ${safeGet(order, 'remarks', '無')}</p>
                    
                    ${safeGet(order, 'status') === 'pending' || safeGet(order, 'status') === 'processing' ? 
                        `<button class="btn btn-danger cancel-order-btn" data-id="${safeGet(order, 'id')}">取消訂單</button>` : ''}
                `;
                
                document.getElementById('order-details').style.display = 'block';
                document.getElementById('order-details').scrollIntoView({ behavior: 'smooth' });
                
                // 綁定取消訂單按鈕事件
                const cancelBtn = orderDetailsContent.querySelector('.cancel-order-btn');
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        cancelOrder(this.getAttribute('data-id'));
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('錯誤', error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        // 獲取所有訂單
        async function fetchAllOrders() {
            try {
                showLoading();
                const response = await fetch(`${API_BASE_URL}/orders`);
                
                if (!response.ok) {
                    throw new Error('獲取訂單列表失敗');
                }
                
                const responseData = await response.json();
                
                // 處理可能的數據結構差異
                const orders = Array.isArray(responseData) ? responseData : 
                              (responseData.data && Array.isArray(responseData.data) ? responseData.data : 
                              (responseData.data && responseData.data.data && Array.isArray(responseData.data.data) ? responseData.data.data : []));
                
                const ordersListEl = document.getElementById('all-orders-list');
                ordersListEl.innerHTML = '';
                
                if (orders.length === 0) {
                    ordersListEl.innerHTML = '<tr><td colspan="7" class="text-center">暫無訂單數據</td></tr>';
                } else {
                    orders.forEach(order => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${safeGet(order, 'id')}</td>
                            <td>${safeGet(order, 'product.name', '未知產品')}</td>
                            <td>${safeGet(order, 'quantity', '0')}</td>
                            <td>$${safeGet(order, 'total_price', '0')}</td>
                            <td><span class="badge bg-${getStatusColor(safeGet(order, 'status', '未知'))}">${safeGet(order, 'status', '未知')}</span></td>
                            <td>${formatDate(safeGet(order, 'created_at'))}</td>
                            <td>
                                <button class="btn btn-sm btn-info view-order-btn" data-id="${safeGet(order, 'id')}">查看</button>
                                ${safeGet(order, 'status') === 'pending' || safeGet(order, 'status') === 'processing' ? 
                                    `<button class="btn btn-sm btn-danger cancel-order-btn" data-id="${safeGet(order, 'id')}">取消</button>` : ''}
                            </td>
                        `;
                        ordersListEl.appendChild(row);
                    });
                    
                    // 綁定查看和取消按鈕事件
                    document.querySelectorAll('.view-order-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            fetchOrderDetails(this.getAttribute('data-id'));
                        });
                    });
                    
                    document.querySelectorAll('.cancel-order-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            cancelOrder(this.getAttribute('data-id'));
                        });
                    });
                }
                
                document.getElementById('all-orders').style.display = 'block';
                document.getElementById('all-orders').scrollIntoView({ behavior: 'smooth' });
            } catch (error) {
                console.error('Error:', error);
                showToast('錯誤', error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        // 取消訂單
        async function cancelOrder(orderId) {
            if (!confirm('確定要取消此訂單嗎？')) {
                return;
            }
            
            try {
                showLoading();
                const response = await fetch(`${API_BASE_URL}/orders/${orderId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(safeGet(data, 'message', '取消訂單失敗'));
                }
                
                showToast('成功', '訂單已成功取消');
                
                // 刷新訂單詳情
                fetchOrderDetails(orderId);
                
                // 如果顯示了所有訂單，也刷新它
                if (document.getElementById('all-orders').style.display !== 'none') {
                    fetchAllOrders();
                }
                
                // 刷新產品列表
                fetchProducts();
            } catch (error) {
                console.error('Error:', error);
                showToast('錯誤', error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        // 獲取狀態顏色
        function getStatusColor(status) {
            switch (status) {
                case 'pending': return 'warning';
                case 'processing': return 'primary';
                case 'completed': return 'success';
                case 'cancelled': return 'danger';
                case 'failed': return 'danger';
                default: return 'secondary';
            }
        }
        
        // 頁面加載完成後執行
        document.addEventListener('DOMContentLoaded', function() {
            // 獲取產品列表
            fetchProducts();
            
            // 綁定表單提交事件
            document.getElementById('order-form').addEventListener('submit', submitOrder);
            
            // 綁定取消按鈕事件
            document.getElementById('cancel-order').addEventListener('click', function() {
                document.getElementById('order-form-section').style.display = 'none';
                hideFormErrors();
            });
            
            // 綁定訂單查詢事件
            document.getElementById('search-order').addEventListener('click', function() {
                const orderId = document.getElementById('order-id').value;
                if (orderId) {
                    fetchOrderDetails(orderId);
                } else {
                    showToast('警告', '請輸入訂單 ID', 'warning');
                }
            });
            
            // 綁定查看所有訂單事件
            document.getElementById('list-all-orders').addEventListener('click', fetchAllOrders);
        });
    </script>
</body>
</html>