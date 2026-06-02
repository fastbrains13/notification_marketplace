<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Администратор магазинов</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .stores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .store-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: box-shadow 0.2s;
        }

        .store-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .store-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .store-name {
            font-size: 20px;
            font-weight: 600;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .marketplace-section {
            margin-bottom: 15px;
        }

        .marketplace-title {
            font-weight: 600;
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .marketplace-status {
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 4px;
            display: inline-block;
        }

        .status-configured {
            background: #d4edda;
            color: #155724;
        }

        .status-empty {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-edit {
            background: #3498db;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 6px;
            transition: background 0.2s;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-primary {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: #229954;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .modal-close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            background: none;
            border: none;
            line-height: 1;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-submit {
            flex: 1;
            background: #3498db;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #2980b9;
        }

        .btn-cancel {
            flex: 1;
            background: #95a5a6;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: #7f8c8d;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        .marketplace-icon {
            font-size: 18px;
            margin-right: 5px;
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Управление магазинами</h1>
            <p style="opacity: 0.8; margin-top: 5px;">Настройка API ключей для маркетплейсов</p>
        </div>
    </div>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2>Магазины</h2>
            <button class="btn-primary" onclick="openNewStoreModal()">+ Добавить магазин</button>
        </div>

        <div id="alertBox" class="alert"></div>

        <div id="storesContainer" class="stores-grid">
            <div class="empty-state">
                <p>Загрузка...</p>
            </div>
        </div>
    </div>

    <!-- New Store Modal -->
    <div id="newStoreModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('newStoreModal')">×</button>
            <div class="modal-header">Новый магазин</div>
            <form onsubmit="handleNewStore(event)">
                <div class="form-group">
                    <label class="form-label">Название магазина</label>
                    <input type="text" id="newStoreName" class="form-input" placeholder="e.g. AquaCam, MyShop" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Создать</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('newStoreModal')">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Marketplace Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('editModal')">×</button>
            <div class="modal-header" id="editModalTitle">Редактировать маркетплейс</div>
            <form onsubmit="handleEditMarketplace(event)" id="editForm">
                <div id="editFormFields"></div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Сохранить</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let stores = {};
        let currentEditMarketplace = null;
        let currentEditStore = null;

        const MARKETPLACE_CONFIG = {
            ozon: {
                icon: '📦',
                label: 'Ozon',
                fields: [
                    { name: 'client_id', label: 'Client ID', type: 'text' },
                    { name: 'api_key', label: 'API Key', type: 'password' }
                ]
            },
            wildberries: {
                icon: '🛒',
                label: 'Wildberries',
                fields: [
                    { name: 'token', label: 'API Token', type: 'password' }
                ]
            },
            yandex: {
                icon: '🔍',
                label: 'Yandex Market',
                fields: [
                    { name: 'campaign_id', label: 'Campaign ID', type: 'text' },
                    { name: 'business_id', label: 'Business ID', type: 'text' },
                    { name: 'oauth_token', label: 'OAuth Token', type: 'password' }
                ]
            }
        };

        async function loadStores() {
            try {
                const res = await fetch('/api/stores.php', { method: 'GET' });
                const data = await res.json();
                if (data.ok) {
                    stores = data.data || {};
                    renderStores();
                } else {
                    showAlert(data.error || 'Error loading stores', 'error');
                }
            } catch (err) {
                showAlert(`Error: ${err.message}`, 'error');
            }
        }

        function renderStores() {
            const container = document.getElementById('storesContainer');
            
            if (Object.keys(stores).length === 0) {
                container.innerHTML = '<div class="empty-state"><p>Нет добавленных магазинов</p><p style="font-size: 12px;">Нажмите кнопку выше, чтобы добавить первый магазин</p></div>';
                return;
            }

            container.innerHTML = Object.entries(stores).map(([name, store]) => {
                const marketplaces = Object.entries(MARKETPLACE_CONFIG).map(([market, config]) => {
                    const creds = store[market] || {};
                    const hasConfig = Object.keys(creds).some(k => creds[k]);
                    const status = hasConfig ? 'configured' : 'empty';
                    const statusText = hasConfig ? 'Настроен' : 'Не настроен';

                    return `
                        <div class="marketplace-section">
                            <div class="marketplace-title">${config.icon} ${config.label}</div>
                            <div style="margin-bottom: 8px;">
                                <span class="marketplace-status status-${status}">${statusText}</span>
                            </div>
                            <button type="button" class="btn-edit" onclick="openEditModal('${name}', '${market}')">
                                Редактировать
                            </button>
                        </div>
                    `;
                }).join('');

                return `
                    <div class="store-card">
                        <div class="store-header">
                            <div class="store-name">${name}</div>
                            <button class="btn-delete" onclick="deleteStore('${name}')">Удалить</button>
                        </div>
                        ${marketplaces}
                    </div>
                `;
            }).join('');
        }

        async function handleNewStore(e) {
            e.preventDefault();
            const name = document.getElementById('newStoreName').value.trim();
            
            if (!name) {
                showAlert('Введите название магазина', 'error');
                return;
            }

            try {
                const res = await fetch('/api/stores.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                });
                const data = await res.json();
                
                if (data.ok) {
                    showAlert('Магазин добавлен', 'success');
                    closeModal('newStoreModal');
                    document.getElementById('newStoreName').value = '';
                    await loadStores();
                } else {
                    showAlert(data.error || 'Error creating store', 'error');
                }
            } catch (err) {
                showAlert(`Error: ${err.message}`, 'error');
            }
        }

        function openEditModal(storeName, marketplace) {
            currentEditStore = storeName;
            currentEditMarketplace = marketplace;
            const config = MARKETPLACE_CONFIG[marketplace];
            const store = stores[storeName] || {};
            const creds = store[marketplace] || {};

            document.getElementById('editModalTitle').textContent = `${config.icon} ${config.label} - ${storeName}`;

            const fieldsHTML = config.fields.map(field => `
                <div class="form-group">
                    <label class="form-label">${field.label}</label>
                    <input 
                        type="${field.type}" 
                        name="${field.name}" 
                        class="form-input" 
                        value="${creds[field.name] || ''}"
                        placeholder="${field.label}"
                    >
                </div>
            `).join('');

            document.getElementById('editFormFields').innerHTML = fieldsHTML;
            openModal('editModal');
        }

        async function handleEditMarketplace(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('editForm'));
            const creds = Object.fromEntries(formData);

            try {
                const res = await fetch(`/api/stores.php`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        storeName: currentEditStore,
                        marketplace: currentEditMarketplace,
                        ...creds
                    })
                });
                const data = await res.json();
                
                if (data.ok) {
                    showAlert('Сохранено', 'success');
                    closeModal('editModal');
                    await loadStores();
                } else {
                    showAlert(data.error || 'Error saving', 'error');
                }
            } catch (err) {
                showAlert(`Error: ${err.message}`, 'error');
            }
        }

        async function deleteStore(name) {
            if (!confirm(`Вы уверены? Все данные магазина "${name}" будут удалены.`)) return;

            try {
                const res = await fetch('/api/stores.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                });
                const data = await res.json();
                
                if (data.ok) {
                    showAlert('Магазин удалён', 'success');
                    await loadStores();
                } else {
                    showAlert(data.error || 'Error deleting', 'error');
                }
            } catch (err) {
                showAlert(`Error: ${err.message}`, 'error');
            }
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openNewStoreModal() {
            openModal('newStoreModal');
        }

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = `alert show alert-${type}`;
            setTimeout(() => alertBox.classList.remove('show'), 3000);
        }

        // Load stores on page load
        loadStores();
    </script>
</body>
</html>
