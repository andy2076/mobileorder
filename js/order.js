// モバイルオーダーシステム - 顧客用JavaScript

class MobileOrder {
    constructor() {
        this.apiBase = window.API_BASE || '../api';
        this.tableNumber = null;
        this.cart = [];
        this.menuItems = [];
        this.categories = [];
        this.currentCategory = '';
        
        this.init();
    }
    
    async init() {
        await this.loadShopInfo();
        await this.loadMenu();
        this.setupEventListeners();
        this.updateCartDisplay();
        
        // URLパラメータからテーブル番号を取得
        const urlParams = new URLSearchParams(window.location.search);
        const tableFromUrl = urlParams.get('table');
        if (tableFromUrl) {
            document.getElementById('table-number').value = tableFromUrl;
            this.setTable();
        }
    }
    
    setupEventListeners() {
        // Enterキーでテーブル番号決定
        document.getElementById('table-number').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.setTable();
            }
        });
        
        // カートモーダルの背景クリックで閉じる
        document.getElementById('cart-modal').addEventListener('click', (e) => {
            if (e.target.classList.contains('cart-modal')) {
                this.closeCart();
            }
        });
        
        // 注文完了モーダルの背景クリックで閉じる
        document.getElementById('order-complete-modal').addEventListener('click', (e) => {
            if (e.target.classList.contains('cart-modal')) {
                this.closeOrderComplete();
            }
        });
    }
    
    async loadShopInfo() {
        try {
            // TODO: 店舗情報取得APIを実装後に追加
            // const response = await fetch(`${this.apiBase}/settings.php`);
            // const data = await response.json();
            // document.getElementById('shop-name').textContent = data.shop_name || 'モバイルオーダー';
        } catch (error) {
            console.error('店舗情報の読み込みに失敗:', error);
        }
    }
    
    async loadMenu() {
        try {
            const response = await fetch(`${this.apiBase}/menu.php?available_only=true`);
            if (!response.ok) throw new Error('メニューの取得に失敗しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'メニューの取得に失敗しました');
            
            this.menuItems = data.items;
            this.categories = data.categories;
            
            this.renderCategories();
            this.renderMenu();
            
        } catch (error) {
            console.error('Menu loading error:', error);
            this.showMessage('メニューの読み込みに失敗しました', 'error');
        }
    }
    
    renderCategories() {
        const headerCats = document.getElementById('header-categories');
        if (!headerCats) return;
        headerCats.innerHTML = '';

        const allBtn = document.createElement('button');
        allBtn.className = 'cat-btn active';
        allBtn.dataset.category = '';
        allBtn.textContent = 'すべて';
        allBtn.addEventListener('click', () => this.filterByCategory(''));
        headerCats.appendChild(allBtn);

        this.categories.forEach(category => {
            const btn = document.createElement('button');
            btn.className = 'cat-btn';
            btn.dataset.category = category;
            btn.textContent = category;
            btn.addEventListener('click', () => this.filterByCategory(category));
            headerCats.appendChild(btn);
        });
    }
    
    filterByCategory(category) {
        this.currentCategory = category;
        
        // アクティブなボタンを更新
        document.querySelectorAll('.cat-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.category === category);
        });
        
        this.renderMenu();
    }
    
    renderMenu() {
        const menuGrid = document.getElementById('menu-grid');
        menuGrid.innerHTML = '';
        
        const filteredItems = this.currentCategory 
            ? this.menuItems.filter(item => item.category === this.currentCategory)
            : this.menuItems;
        
        if (filteredItems.length === 0) {
            menuGrid.innerHTML = '<p class="message message-info">該当する商品がありません</p>';
            return;
        }
        
        filteredItems.forEach(item => {
            const menuItem = this.createMenuItemElement(item);
            menuGrid.appendChild(menuItem);
        });
    }
    
    isVideo(url) {
        if (!url) return false;
        return /\.(mp4|webm)(\?|$)/i.test(url);
    }

    createMenuItemElement(item) {
        const cartItem = this.cart.find(cartItem => cartItem.id === item.id);
        const quantity = cartItem ? cartItem.quantity : 0;
        
        const div = document.createElement('div');
        div.className = 'menu-item';
        div.dataset.itemId = item.id;
        div.innerHTML = `
            ${this.isVideo(item.image_url)
                ? '<video src="' + item.image_url + '" class="menu-item-image" autoplay muted loop playsinline></video>'
                : '<img src="' + (item.image_url || '/mobileorder/images/no-image.jpg') + '" alt="' + item.name + '" class="menu-item-image" onerror="this.onerror=null;this.src=\'/mobileorder/images/no-image.jpg\'">'
            }
            <div class="menu-item-content">
                <h3 class="menu-item-name">${item.name}</h3>
                <p class="menu-item-description">${item.description || ''}</p>
                <div class="menu-item-price">${this.formatPrice(item.price)}</div>
                <div class="menu-item-actions">
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="mobileOrder.decreaseQuantity(${item.id})" ${quantity === 0 ? 'disabled' : ''}>
                            −
                        </button>
                        <span class="quantity-display">${quantity}</span>
                        <button class="quantity-btn" onclick="mobileOrder.increaseQuantity(${item.id})">
                            ＋
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        return div;
    }
    
    increaseQuantity(itemId) {
        const item = this.menuItems.find(item => item.id === itemId);
        if (!item) return;
        
        const cartItem = this.cart.find(cartItem => cartItem.id === itemId);
        
        if (cartItem) {
            cartItem.quantity++;
        } else {
            this.cart.push({
                id: item.id,
                name: item.name,
                price: item.price,
                quantity: 1
            });
        }
        
        this.updateCartDisplay();
        this.updateItemQuantity(itemId);
    }
    
    decreaseQuantity(itemId) {
        const cartItemIndex = this.cart.findIndex(cartItem => cartItem.id === itemId);
        if (cartItemIndex === -1) return;
        
        const cartItem = this.cart[cartItemIndex];
        cartItem.quantity--;
        
        if (cartItem.quantity <= 0) {
            this.cart.splice(cartItemIndex, 1);
        }
        
        this.updateCartDisplay();
        this.updateItemQuantity(itemId);
    }

    updateItemQuantity(itemId) {
        const el = document.querySelector(`.menu-item[data-item-id="${itemId}"]`);
        if (!el) return;
        
        const cartItem = this.cart.find(ci => ci.id === itemId);
        const quantity = cartItem ? cartItem.quantity : 0;
        
        el.querySelector('.quantity-display').textContent = quantity;
        const decreaseBtn = el.querySelector('.quantity-btn');
        decreaseBtn.disabled = quantity === 0;
    }
    
    updateCartDisplay() {
        const cartButton = document.getElementById('cart-button');
        const cartCount = document.getElementById('cart-count');
        
        const totalItems = this.cart.reduce((sum, item) => sum + item.quantity, 0);
        
        if (totalItems > 0) {
            cartButton.classList.remove('hidden');
            cartCount.classList.remove('hidden');
            cartCount.textContent = totalItems;
        } else {
            cartButton.classList.add('hidden');
            cartCount.classList.add('hidden');
        }
    }
    
    setTable() {
        const tableInput = document.getElementById('table-number');
        const tableNumber = parseInt(tableInput.value);
        
        if (!tableNumber || tableNumber < 1 || tableNumber > 99) {
            this.showMessage('正しいテーブル番号を入力してください（1-99）', 'error');
            return;
        }
        
        this.tableNumber = tableNumber;
        document.getElementById('table-selection').classList.add('hidden');
        document.getElementById('menu-area').classList.remove('hidden');
        
        this.showMessage(`テーブル ${tableNumber} でご注文を承ります`, 'success');
    }
    
    openCart() {
        if (this.cart.length === 0) {
            this.showMessage('カートに商品がありません', 'info');
            return;
        }
        
        this.renderCartItems();
        document.getElementById('cart-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    closeCart() {
        document.getElementById('cart-modal').classList.add('hidden');
        document.body.style.overflow = '';
    }
    
    renderCartItems() {
        const cartItems = document.getElementById('cart-items');
        const cartTotal = document.getElementById('cart-total');
        
        cartItems.innerHTML = '';
        let total = 0;
        
        this.cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            const cartItem = document.createElement('div');
            cartItem.className = 'cart-item';
            cartItem.innerHTML = `
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">${this.formatPrice(item.price)} × ${item.quantity}</div>
                </div>
                <div class="quantity-control">
                    <button class="quantity-btn" onclick="mobileOrder.decreaseQuantity(${item.id})">−</button>
                    <span class="quantity-display">${item.quantity}</span>
                    <button class="quantity-btn" onclick="mobileOrder.increaseQuantity(${item.id})">＋</button>
                </div>
                <div style="font-weight: 600; margin-left: 16px;">
                    ${this.formatPrice(itemTotal)}
                </div>
            `;
            
            cartItems.appendChild(cartItem);
        });
        
        cartTotal.textContent = this.formatPrice(total);
    }
    
    clearCart() {
        if (confirm('カートを空にしますか？')) {
            this.cart = [];
            this.updateCartDisplay();
            this.closeCart();
            this.renderMenu();
        }
    }
    
    async submitOrder() {
        if (this.cart.length === 0) {
            this.showMessage('カートに商品がありません', 'error');
            return;
        }
        
        if (!this.tableNumber) {
            this.showMessage('テーブル番号が設定されていません', 'error');
            return;
        }
        
        const submitButton = document.getElementById('submit-order-btn');
        submitButton.disabled = true;
        submitButton.textContent = '注文を送信中...';
        
        try {
            const specialRequests = document.getElementById('special-requests-input').value.trim();
            
            const orderData = {
                table_number: this.tableNumber,
                items: this.cart.map(item => ({
                    id: item.id,
                    quantity: item.quantity,
                    options: []
                })),
                special_requests: specialRequests
            };
            
            const response = await fetch(`${this.apiBase}/order.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orderData)
            });
            
            if (!response.ok) throw new Error('注文の送信に失敗しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || '注文の送信に失敗しました');
            
            this.showOrderComplete(data);
            this.cart = [];
            this.updateCartDisplay();
            this.closeCart();
            this.renderMenu();
            
        } catch (error) {
            console.error('Order submission error:', error);
            this.showMessage(error.message, 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = '注文を確定する';
        }
    }
    
    showOrderComplete(orderData) {
        const orderDetails = document.getElementById('order-details');
        orderDetails.innerHTML = `
            <div style="background-color: #f5f5f5; padding: 16px; border-radius: 8px; margin: 16px 0;">
                <div><strong>注文番号:</strong> #${orderData.order_id}</div>
                <div><strong>テーブル:</strong> ${orderData.table_number}番</div>
                <div><strong>合計金額:</strong> ${this.formatPrice(orderData.total_amount)}</div>
            </div>
        `;
        
        document.getElementById('order-complete-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    closeOrderComplete() {
        document.getElementById('order-complete-modal').classList.add('hidden');
        document.body.style.overflow = '';
    }
    
    formatPrice(price) {
        return new Intl.NumberFormat('ja-JP', {
            style: 'currency',
            currency: 'JPY'
        }).format(price);
    }
    
    showMessage(text, type = 'info') {
        const messageArea = document.getElementById('message-area');
        const message = document.createElement('div');
        message.className = `message message-${type}`;
        message.textContent = text;
        
        messageArea.innerHTML = '';
        messageArea.appendChild(message);
        
        setTimeout(() => {
            if (message.parentNode) {
                message.parentNode.removeChild(message);
            }
        }, 5000);
    }
}

// グローバルインスタンス
let mobileOrder;

// DOMが読み込まれた後に初期化
document.addEventListener('DOMContentLoaded', () => {
    mobileOrder = new MobileOrder();
});

// グローバル関数（HTMLから呼び出し用）
function setTable() {
    mobileOrder.setTable();
}

function openCart() {
    mobileOrder.openCart();
}

function closeCart() {
    mobileOrder.closeCart();
}

function submitOrder() {
    mobileOrder.submitOrder();
}

function clearCart() {
    mobileOrder.clearCart();
}

function closeOrderComplete() {
    mobileOrder.closeOrderComplete();
}