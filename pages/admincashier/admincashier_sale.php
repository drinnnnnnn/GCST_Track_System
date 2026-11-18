﻿<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GCST Admin Cashier Sales</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="../../assets/css/admincashier.css" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>
  
  <style>
    :root {
      --bg: #f4f7fb;
      --surface: #ffffff;
      --surface-soft: #f7f9ff;
      --text: #1d2939;
      --muted: #5f6c84;
      --primary: #4558ff;
      --success: #10b981;
      --danger: #ef4444;
      --border: #dbe3f0;
    }
    .action-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }
    .action-card {
      display: flex;
      align-items: center;
      gap: 12px;
      background: var(--surface);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      text-decoration: none;
      color: var(--text);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .action-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
    }
    .action-card i {
      font-size: 1.5rem;
      color: var(--primary);
    }
    .action-card h3 {
      margin: 0;
      font-size: 1rem;
    }
    .period-button {
      border-radius: 14px;
      border: 1px solid transparent;
      background: var(--surface-soft);
      color: var(--text);
      padding: 12px 18px;
      cursor: pointer;
      transition: background 0.2s ease, border-color 0.2s ease;
    }
    .date-range-control {
      display: flex;
      align-items: flex-end;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 24px;
    }
    .date-range-field {
      display: flex;
      flex-direction: column;
      gap: 4px;
      color: var(--muted);
      font-size: 0.8rem;
      font-weight: 600;
    }
    .date-range-field input {
      min-height: 44px;
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: var(--surface);
      color: var(--text);
      font: inherit;
    }
    .date-range-error {
      flex-basis: 100%;
      color: var(--danger);
      font-size: 0.85rem;
    }
    .category-sales-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 12px;
      margin: 0 0 20px;
    }
    .category-sales-card {
      padding: 16px;
      border: 1px solid var(--border);
      border-radius: 16px;
      background: var(--surface-soft);
      color: var(--text);
      text-align: left;
      cursor: pointer;
    }
    .category-sales-card.active {
      border-color: var(--primary);
      box-shadow: 0 0 0 2px rgba(69, 88, 255, 0.14);
    }
    .category-sales-card span {
      display: block;
      color: var(--muted);
      font-size: 0.8rem;
      font-weight: 600;
    }
    .category-sales-card strong {
      display: block;
      margin-top: 5px;
      color: var(--primary);
      font-size: 1.15rem;
    }
    .balance-section {
      margin-bottom: 24px;
    }
    .balance-section h2 {
      margin: 0 0 16px;
      font-size: 1.25rem;
      color: var(--text);
    }
    .balance-cards {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 20px;
    }
    .balance-card {
      background: var(--surface);
      border: 1px solid #edf2f7;
      border-radius: 20px;
      padding: 24px 24px 22px;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.07);
      min-height: 128px;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .balance-card::before {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 4px;
      background: #4558ff;
    }
    .balance-card:nth-child(2)::before { background: #10b981; }
    .balance-card:nth-child(3)::before { background: #f59e0b; }
    .balance-card:nth-child(4)::before { background: #ef4444; }
    .balance-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 36px rgba(15, 23, 42, 0.1);
    }
    .balance-card h3 {
      margin: 0 0 10px;
      font-size: 0.76rem;
      color: #64748b;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .balance-card p {
      margin: 0;
      font-size: 1.75rem;
      line-height: 1.15;
      color: #1e293b;
      font-weight: 800;
    }

    .balance-section .balance-card .amount {
      color: #1e293b;
    }

    .balance-section:not(#inventory-details) .balance-card .amount {
      color: #4558ff;
    }
    .activity-section {
      margin-bottom: 24px;
    }
    .activity-section h2 {
      margin: 0 0 16px;
      font-size: 1.25rem;
      color: var(--text);
    }
    .charts-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 20px;
    }
    .chart-container {
      background: var(--surface);
      border-radius: 24px;
      padding: 24px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
    }
    .chart-container h3 {
      margin: 0 0 18px;
      font-size: 1.05rem;
    }
    .chart-wrapper {
      position: relative;
      min-height: 300px;
    }
    .history-card {
      background: var(--surface);
      border-radius: 24px;
      padding: 24px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
    }
    .history-card h3 {
      margin-top: 0;
      margin-bottom: 18px;
    }
    .history-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
    }
    .history-table th,
    .history-table td {
      padding: 14px 12px;
      text-align: left;
      border-bottom: 1px solid #f1f5fb;
    }
    .history-table th {
      color: var(--muted);
      font-weight: 600;
    }
    .empty-state {
      padding: 24px;
      color: var(--muted);
      text-align: center;
    }
    .table-scroll-container {
      max-height: 500px;
      overflow-y: auto;
      overflow-x: auto;
      border: 1px solid #eef2f7;
      border-radius: 16px;
      background: #fff;
    }
    .table-scroll-container::-webkit-scrollbar {
      width: 10px;
      height: 10px;
    }
    .table-scroll-container::-webkit-scrollbar-track {
      background: #f8fafc;
    }
    .table-scroll-container::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 999px;
    }
    .table-scroll-container::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
    .pagination-info {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      color: var(--muted);
      font-size: 0.85rem;
      margin: 10px 0 8px;
    }
    .pagination-controls {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      justify-content: center;
      align-items: center;
      margin-top: 10px;
    }
    .pagination-controls button {
      min-width: 40px;
      padding: 8px 10px;
      border: 1px solid #dbe3f0;
      background: #fff;
      color: var(--text);
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }
    .pagination-controls button:hover:not(:disabled) {
      background: var(--surface-soft);
      border-color: var(--primary);
      color: var(--primary);
    }
    .pagination-controls button.active {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }
    .pagination-controls button:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }
    @media (max-width: 768px) {
      .pagination-controls button {
        min-width: 40px;
      }
      .pagination-info {
        justify-content: center;
      }
    }
    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(4px);
    }
    .modal.active {
      display: flex;
      align-items: flex-start;
    }
    .modal-content {
      background-color: var(--surface);
      margin: 5% auto;
      padding: 24px;
      border-radius: 24px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    .export-options-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin: 20px 0;
    }
    .export-options-grid select {
      min-height: 44px;
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: var(--surface);
      color: var(--text);
      font: inherit;
    }
    .export-options-error {
      margin: 0 0 12px;
      color: var(--danger);
      font-size: 0.9rem;
    }
    .product-tag-list {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
    }
    .product-tag {
      background: #eff6ff;
      color: #1e40af;
      padding: 2px 8px;
      border-radius: 6px;
      font-size: 0.8rem;
      border: 1px solid #dbeafe;
    }
    .view-details-btn {
      color: var(--primary);
      padding: 6px;
      border-radius: 8px;
      transition: background 0.2s;
    }
    .view-details-btn:hover {
      background: var(--surface-soft);
    }
    .status-badge {
      padding: 4px 10px;
      border-radius: 99px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    @media (max-width: 1100px) {
      .balance-cards,
      .charts-grid {
        grid-template-columns: 1fr;
      }
    }

    body.dark-mode {
      --bg: #0f172a;
      --surface: #172033;
      --surface-soft: #1e293b;
      --text: #e5edf8;
      --muted: #9aa9bf;
      --border: #334155;
      color-scheme: dark;
    }

    body.dark-mode .content-wrapper .action-card,
    body.dark-mode .content-wrapper .balance-card,
    body.dark-mode .content-wrapper .chart-container,
    body.dark-mode .content-wrapper .history-card,
    body.dark-mode .content-wrapper .table-scroll-container,
    body.dark-mode .content-wrapper .modal-content,
    body.dark-mode .content-wrapper #inventory-details > .bg-white {
      background: #172033 !important;
      border-color: #334155 !important;
      color: #e5edf8 !important;
      box-shadow: 0 14px 34px rgba(2, 6, 23, 0.28) !important;
    }

    body.dark-mode .content-wrapper .period-button,
    body.dark-mode .content-wrapper #historySearch,
    body.dark-mode .content-wrapper #salesCategoryFilter,
    body.dark-mode .content-wrapper #inventoryFilterCategory,
    body.dark-mode .content-wrapper #inventoryFilterCourse,
    body.dark-mode .content-wrapper #inventoryFilterStatus,
    body.dark-mode .content-wrapper #inventorySearch,
    body.dark-mode .content-wrapper .export-options-grid select {
      background: #0f172a !important;
      border-color: #475569 !important;
      color: #e5edf8 !important;
      outline: none !important;
      box-shadow: none !important;
    }

    body.dark-mode .content-wrapper .period-button:hover,
    body.dark-mode .content-wrapper #historySearch:focus,
    body.dark-mode .content-wrapper #salesCategoryFilter:focus,
    body.dark-mode .content-wrapper #inventoryFilterCategory:focus,
    body.dark-mode .content-wrapper #inventoryFilterCourse:focus,
    body.dark-mode .content-wrapper #inventoryFilterStatus:focus,
    body.dark-mode .content-wrapper #inventorySearch:focus {
      border-color: #818cf8 !important;
      box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.18) !important;
    }

    body.dark-mode .content-wrapper input::placeholder {
      color: #94a3b8 !important;
    }

    body.dark-mode .content-wrapper select option {
      background: #172033 !important;
      color: #e5edf8 !important;
    }

    body.dark-mode .content-wrapper .history-table th,
    body.dark-mode .content-wrapper .history-table td {
      border-color: #334155 !important;
      color: #cbd5e1 !important;
    }

    body.dark-mode .content-wrapper .history-table th {
      background: #1e293b !important;
      color: #a9b7cb !important;
    }

    body.dark-mode .content-wrapper .history-table tbody tr:hover {
      background: #1e293b !important;
    }

    body.dark-mode .content-wrapper .history-table tbody tr[style] {
      background: #172033 !important;
    }

    body.dark-mode .content-wrapper .balance-card p,
    body.dark-mode .content-wrapper .balance-card .amount {
      color: #e5edf8 !important;
    }

    body.dark-mode .content-wrapper .balance-card p.text-red-600,
    body.dark-mode .content-wrapper .balance-card .text-red-600 {
      color: #fca5a5 !important;
    }

    body.dark-mode .content-wrapper .text-gray-400,
    body.dark-mode .content-wrapper .text-gray-500,
    body.dark-mode .content-wrapper .text-gray-600,
    body.dark-mode .content-wrapper .text-gray-700,
    body.dark-mode .content-wrapper .text-gray-800,
    body.dark-mode .content-wrapper .text-gray-900,
    body.dark-mode .content-wrapper .text-slate-700,
    body.dark-mode .content-wrapper .text-slate-800 {
      color: #cbd5e1 !important;
    }

    body.dark-mode .content-wrapper .text-blue-500 {
      color: #93c5fd !important;
    }

    body.dark-mode .content-wrapper .text-green-500 {
      color: #86efac !important;
    }

    body.dark-mode .content-wrapper .text-yellow-500 {
      color: #fde68a !important;
    }

    body.dark-mode .content-wrapper .text-red-500,
    body.dark-mode .content-wrapper .text-red-600 {
      color: #fca5a5 !important;
    }

    body.dark-mode .content-wrapper .product-tag {
      background: #1e3a5f !important;
      border-color: #315b89 !important;
      color: #bfdbfe !important;
    }

    body.dark-mode .content-wrapper .bg-red-100 {
      background: #451a1a !important;
      color: #fecaca !important;
    }

    body.dark-mode .content-wrapper .bg-yellow-100 {
      background: #3d2f12 !important;
      color: #fde68a !important;
    }

    body.dark-mode .content-wrapper .bg-green-100 {
      background: #123b2d !important;
      color: #a7f3d0 !important;
    }

    body.dark-mode .content-wrapper .bg-red-700 {
      background: #991b1b !important;
      color: #fee2e2 !important;
    }

    body.dark-mode .content-wrapper .view-details-btn {
      color: #a5b4fc !important;
    }

    body.dark-mode .content-wrapper .view-details-btn:hover {
      background: #1e293b !important;
    }

    body.dark-mode .content-wrapper .table-scroll-container::-webkit-scrollbar-track {
      background: #111827 !important;
    }

    body.dark-mode .content-wrapper .table-scroll-container::-webkit-scrollbar-thumb {
      background: #475569 !important;
      border: 2px solid #111827 !important;
    }

    body.dark-mode .content-wrapper #receiptModal .text-gray-800,
    body.dark-mode .content-wrapper #receiptModal .text-gray-700,
    body.dark-mode .content-wrapper #receiptModal .text-gray-600,
    body.dark-mode .content-wrapper #receiptModal .text-gray-500,
    body.dark-mode .content-wrapper #receiptModal .text-gray-400 {
      color: #cbd5e1 !important;
    }

    body.dark-mode .content-wrapper #receiptModal .bg-gray-50,
    body.dark-mode .content-wrapper #receiptModal .bg-white {
      background: #1e293b !important;
      border-color: #334155 !important;
      color: #e5edf8 !important;
    }

    body.dark-mode .content-wrapper #receiptModal .border-gray-100,
    body.dark-mode .content-wrapper #receiptModal .border-gray-200 {
      border-color: #334155 !important;
    }

    body.dark-mode .content-wrapper #receiptModal .text-red-600 {
      color: #fca5a5 !important;
    }
  </style>
</head>

<body>
  <!-- Sidebar Component -->
  <div id="sidebar-container"></div>
  <div id="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="content-wrapper">

<main>
    <!-- Greeting Section -->
    <section class="greeting-section">
      <div class="greeting-content">
        <h1>Sales Management</h1>
        <p>Track and manage your sales performance and analytics.</p>
      </div>
      <div class="greeting-icon">👋</div>
    </section>

    <!-- Sales Date Range -->
    <div class="date-range-control">
      <label class="date-range-field" for="salesStartDate">
        Start Date
        <input type="date" id="salesStartDate" required>
      </label>
      <label class="date-range-field" for="salesEndDate">
        End Date
        <input type="date" id="salesEndDate" required>
      </label>
      <button class="period-button" type="button" id="applySalesDateRange">Apply</button>
      <span class="date-range-error" id="salesDateRangeError" role="alert" hidden></span>
      <button class="period-button admin-only-export" id="export-csv-btn" hidden style="margin-left: auto; background: var(--success); color: white;"><i class="fas fa-file-excel"></i> Export Excel</button>
    </div>

    <!-- Summary Section -->
    <section class="balance-section">
      <h2>Sales Summary</h2>
      <div class="balance-cards">
        <div class="balance-card">
          <h3>Total Sales</h3>
          <p class="amount" id="totalSales">₱0.00</p>
        </div>
        <div class="balance-card">
          <h3>Total Transactions</h3>
          <p class="amount" id="totalTransactions">0</p>
        </div>
        <div class="balance-card">
          <h3>Average Transaction Value</h3>
          <p class="amount" id="avgTransaction">₱0.00</p>
        </div>
        <div class="balance-card">
          <h3>Items Sold</h3>
          <p class="amount" id="itemsSold">0</p>
        </div>
      </div>
    </section>

     <!-- Charts Section -->
    <section class="activity-section">
      <h2>Analytics</h2>
      <div class="charts-grid">
        <div class="chart-container">
          <h3>Daily Sales Trends</h3>
          <div class="chart-wrapper">
            <canvas id="salesTrendChart"></canvas>
          </div>
        </div>
        <div class="chart-container">
          <h3>Top Selling Products</h3>
          <div class="chart-wrapper">
            <canvas id="topProductsChart"></canvas>
          </div>
        </div>
      </div>
    </section>

    <!-- Sales History Section -->
    <section class="history-card">
      <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
        <h3 class="m-0 text-xl font-bold">Sales History</h3>
        <div class="flex w-full sm:w-auto flex-wrap gap-3">
          <label class="date-range-field" for="salesCategoryFilter">
            Category
            <select id="salesCategoryFilter" class="w-full sm:w-48 p-2.5 border border-gray-200 rounded-2xl text-sm bg-gray-50">
              <option value="all">All Categories</option>
              <option value="Uniforms">Uniforms</option>
              <option value="Books">Books</option>
              <option value="Accessories">Accessories</option>
              <option value="Uncategorized">Uncategorized</option>
            </select>
          </label>
          <div class="relative w-full sm:w-72 self-end">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="historySearch" placeholder="Search transactions..." class="w-full pl-11 pr-4 py-2.5 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-sm bg-gray-50">
          </div>
        </div>
      </div>
      <div id="categorySalesSummary" class="category-sales-summary" aria-label="Sales totals by category"></div>
      <div class="table-scroll-container">
        <table class="history-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Transaction</th>
              <th>Student</th>
              <th>Category</th>
              <th>Product</th>
              <th>Quantity</th>
              <th>Amount</th>
              <th style="text-align: center;">Action</th>
            </tr>
          </thead>
          <tbody id="historyBody">
            <tr><td colspan="8" class="empty-state">Loading sales history...</td></tr>
          </tbody>
        </table>
      </div>
      <div id="salesPaginationInfo" class="pagination-info"></div>
      <div id="salesPaginationControls" class="pagination-controls"></div>
    </section>

    <!-- Comprehensive Inventory Report Module -->
    <section id="inventory-details" class="balance-section mt-12">
      <div class="flex justify-between items-center mb-6">
        <h2 class="m-0">Inventory Report</h2>
        <button class="period-button admin-only-export" onclick="window.exportInventoryToExcel()" hidden style="background: var(--primary); color: white;">
          <i class="fas fa-file-download mr-2"></i> Export Inventory Report
        </button>
      </div>
      
      <!-- Inventory Summary Cards -->
      <div class="balance-cards mb-8">
        <div class="balance-card" style="border-left: 4px solid var(--primary);">
          <h3>Total Product Lines</h3>
          <p id="totalInventoryProducts">0</p>
        </div>
        <div class="balance-card" style="border-left: 4px solid var(--success);">
          <h3>Total Stock Units</h3>
          <p id="totalStockQuantity">0</p>
        </div>
        <div class="balance-card" style="border-left: 4px solid #f59e0b;">
          <h3>Inventory Asset Value</h3>
          <p id="totalInventoryValue">₱0.00</p>
        </div>
        <div class="balance-card" style="border-left: 4px solid var(--danger);">
          <h3>Low Stock Alerts</h3>
          <p id="lowStockCount" class="text-red-600">0</p>
        </div>
      </div>

      <!-- Inventory Analytics Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="chart-container">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Top Valued Category</h3>
          <div id="topCategoryValue" class="text-2xl font-bold">₱0.00</div>
          <div id="topCategoryName" class="text-sm text-gray-400 mt-1">N/A</div>
        </div>
        <div class="chart-container">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Avg Stock Level</h3>
          <div id="avgStockLevel" class="text-2xl font-bold">0.0</div>
          <div class="text-sm text-gray-400 mt-1">Units per Product</div>
        </div>
        <div class="chart-container">
          <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Inventory Health</h3>
          <div id="inventoryHealthScore" class="text-2xl font-bold text-green-500">100%</div>
          <div class="text-sm text-gray-400 mt-1">Healthy vs Total Items</div>
        </div>
      </div>

      <!-- Inventory Filters -->
      <div class="bg-white p-6 rounded-3xl shadow-sm mb-6 border border-gray-100">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">Category</label>
            <select id="inventoryFilterCategory" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">All Categories</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">Course/Program</label>
            <select id="inventoryFilterCourse" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">All Programs</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">Stock Status</label>
            <select id="inventoryFilterStatus" class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">All Statuses</option>
              <option value="Healthy">Healthy</option>
              <option value="Low">Low</option>
              <option value="Out of Stock">Out of Stock</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">Search Inventory</label>
            <input type="text" id="inventorySearch" placeholder="Product name or detail..." class="w-full p-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500">
          </div>
        </div>
      </div>

      <!-- Inventory Table -->
      <div class="history-card">
        <div class="table-scroll-container">
          <table class="history-table">
            <thead>
              <tr>
                <th>Product Details</th>
                <th>Category</th>
                <th style="text-align: right;">Unit Price</th>
                <th style="text-align: right;">Current Stock</th>
                <th style="text-align: right;">Value</th>
                <th style="text-align: center;">Status</th>
              </tr>
            </thead>
            <tbody id="inventoryReportBody">
              <tr><td colspan="6" class="empty-state">Loading inventory data...</td></tr>
            </tbody>
          </table>
        </div>
        <div id="inventoryPaginationInfo" class="pagination-info"></div>
        <div id="inventoryPaginationControls" class="pagination-controls"></div>
      </div>
    </section>

    <div id="salesExportModal" class="modal" aria-hidden="true">
      <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="salesExportTitle">
        <h3 id="salesExportTitle" class="text-xl font-bold">Export Sales Report</h3>
        <div class="export-options-grid">
          <label class="date-range-field" for="exportStartDate">
            Start Date
            <input type="date" id="exportStartDate" required>
          </label>
          <label class="date-range-field" for="exportEndDate">
            End Date
            <input type="date" id="exportEndDate" required>
          </label>
          <label class="date-range-field" for="exportCashier">
            Cashier Name
            <select id="exportCashier">
              <option value="">All Cashiers</option>
            </select>
          </label>
          <label class="date-range-field" for="exportCategory">
            Category
            <select id="exportCategory">
              <option value="all">All</option>
              <option value="uniforms">Uniforms</option>
              <option value="books">Books</option>
              <option value="accessories">Accessories</option>
            </select>
          </label>
        </div>
        <p id="salesExportError" class="export-options-error" role="alert" hidden></p>
        <div class="flex justify-end gap-3">
          <button type="button" id="cancelSalesExport" class="period-button">Cancel</button>
          <button type="button" id="generateSalesExport" class="period-button" style="background: var(--success); color: white;">Export Excel</button>
        </div>
      </div>
    </div>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="modal">
      <div class="modal-content">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-bold text-gray-800">Transaction Details</h3>
          <button onclick="closeReceiptModal()" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times fa-lg"></i>
          </button>
        </div>
        <div id="receiptContent" class="space-y-4">
          <!-- Details will be injected here -->
        </div>
      </div>
    </div>

  </main>

  <script src="../../assets/js/admincashier.js"></script>
  <script>
    // =====================================================
    // SALES PAGE LOGIC
    // =====================================================
    let currentSalesStartDate = '';
    let currentSalesEndDate = '';
    let currentTotalSales = 0;
    let salesTrendChartInstance = null;
    let topProductsChartInstance = null;
    let salesPollInterval = null;
    let currentTotalTransactions = 0;
    let currentAvgTransaction = 0;
    let currentItemsSold = 0;
    let lastFetchedInventory = [];
    let lastFetchedSalesHistory = [];
    let salesCategoryTotals = [];
    let salesHistorySearchQuery = '';
    let currentTopProduct = 'N/A';
    let currentAdminName = 'Unknown Admin'; // New: Global variable to store admin name
    let canExportReports = false;
    const INVENTORY_ROWS_PER_PAGE = 10;
    const SALES_ROWS_PER_PAGE = 10;
    let inventoryPaginationState = { page: 1, totalPages: 1, totalItems: 0 };
    let salesPaginationState = { page: 1, totalPages: 1, totalItems: 0 };
    let salesSearchTimer = null;
    let salesThemeObserver = null;
    let cashierOptionsRequestId = 0;

    function getSafeText(value, fallback = '') {
      if (typeof value === 'string') {
        const trimmed = value.trim();
        return trimmed || fallback;
      }
      if (value === null || value === undefined) return fallback;
      return String(value);
    }

    function getSafeNumber(value, fallback = 0) {
      const numericValue = Number(value);
      return Number.isFinite(numericValue) ? numericValue : fallback;
    }

    function deriveTransactionAmount(items, fallbackAmount = 0) {
      let total = getSafeNumber(fallbackAmount);
      if (!Array.isArray(items)) return total;

      items.forEach(item => {
        const candidateFields = ['total_item_amount', 'total', 'line_total', 'amount', 'total_amount', 'price_total', 'item_total'];
        let itemAmount = null;

        for (const field of candidateFields) {
          if (item && item[field] !== undefined && item[field] !== null && item[field] !== '' && !Number.isNaN(Number(item[field]))) {
            itemAmount = getSafeNumber(item[field]);
            break;
          }
        }

        if (itemAmount === null) {
          const unitPrice = candidateFields.some(() => false) ? null : null;
          const priceCandidates = ['unit_price', 'price', 'unitPrice'];
          const qtyCandidates = ['quantity', 'qty', 'amount_qty'];
          const unitPriceValue = priceCandidates.reduce((acc, field) => acc ?? (item && item[field] !== undefined && item[field] !== null && item[field] !== '' && !Number.isNaN(Number(item[field])) ? getSafeNumber(item[field]) : null), null);
          const quantityValue = qtyCandidates.reduce((acc, field) => acc ?? (item && item[field] !== undefined && item[field] !== null && item[field] !== '' && !Number.isNaN(Number(item[field])) ? getSafeNumber(item[field]) : null), null);

          if (unitPriceValue !== null && quantityValue !== null) {
            itemAmount = unitPriceValue * quantityValue;
          }
        }

        if (itemAmount !== null) {
          total += itemAmount;
        }
      });

      return total;
    }

    function formatDate(value) {
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function getLocalDateInputValue(date = new Date()) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }

    function getLocalYearStartDate(date = new Date()) {
      return `${date.getFullYear()}-01-01`;
    }

    function getPageBounds(items, page, perPage) {
      const totalItems = Array.isArray(items) ? items.length : 0;
      const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
      const safePage = Math.min(page || 1, totalPages);
      const startIndex = (safePage - 1) * perPage;
      const endIndex = Math.min(startIndex + perPage, totalItems);
      return { totalItems, totalPages, safePage, startIndex, endIndex };
    }

    function updatePaginationInfo(infoId, bounds) {
      const infoEl = document.getElementById(infoId);
      if (!infoEl) return;
      if (!bounds.totalItems) {
        infoEl.textContent = 'Showing 0 records';
        return;
      }
      const start = bounds.startIndex + 1;
      const end = bounds.endIndex;
      infoEl.textContent = `Showing ${start}–${end} of ${bounds.totalItems} records`;
    }

    function updatePaginationControls(containerId, state, onPageChange) {
      const container = document.getElementById(containerId);
      if (!container) return;

      container.innerHTML = '';
      if (state.totalPages <= 1) return;

      const createButton = (label, page, disabled = false, isActive = false) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        button.className = isActive ? 'active' : '';
        button.addEventListener('click', () => {
          if (!disabled && page >= 1 && page <= state.totalPages) {
            onPageChange(page);
          }
        });
        return button;
      };

      const maxVisible = 5;
      let startPage = Math.max(1, state.page - 2);
      let endPage = Math.min(state.totalPages, startPage + maxVisible - 1);
      if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
      }

      container.appendChild(createButton('Previous', state.page - 1, state.page === 1));
      for (let i = startPage; i <= endPage; i += 1) {
        container.appendChild(createButton(String(i), i, false, i === state.page));
      }
      container.appendChild(createButton('Next', state.page + 1, state.page === state.totalPages));
    }

    async function loadSalesData(startDate, endDate) {
      const historyBody = document.getElementById('historyBody');
      if (historyBody && historyBody.innerHTML === '') {
        historyBody.innerHTML = '<tr><td colspan="8" class="empty-state">Loading data...</td></tr>';
      }

      try {
        const params = new URLSearchParams({
          start_date: startDate,
          end_date: endDate,
          limit: '50'
        });
        const payload = await fetchWithError(`../../actions/get_admincashier_sales.php?${params.toString()}`);
        const data = payload && typeof payload === 'object' && !Array.isArray(payload) ? payload : {};

        if (!data.success) {
            showError(historyBody, data.message || 'Failed to fetch sales data');
            return;
        }

        currentTotalSales = getSafeNumber(data.total_sales ?? data.total_sales_today);
        currentTotalTransactions = getSafeNumber(data.total_transactions);
        currentAvgTransaction = getSafeNumber(data.average_transaction_value, currentTotalTransactions > 0 ? (currentTotalSales / currentTotalTransactions) : 0);
        currentItemsSold = getSafeNumber(data.total_items_sold ?? data.books_sold);
        
        const topProducts = Array.isArray(data.top_products) ? data.top_products : [];
        if (topProducts.length > 0) {
            currentTopProduct = getSafeText(topProducts[0].name, 'N/A');
        } else {
            currentTopProduct = 'N/A';
        }

        document.getElementById('totalSales').textContent = formatCurrency(currentTotalSales);
        document.getElementById('totalTransactions').textContent = currentTotalTransactions;
        document.getElementById('avgTransaction').textContent = formatCurrency(currentAvgTransaction);
        document.getElementById('itemsSold').textContent = currentItemsSold;

        const salesLabels = Array.isArray(data.sales_labels) ? data.sales_labels : [];
        const salesData = Array.isArray(data.sales_data) ? data.sales_data : [];

        if (!salesTrendChartInstance) {
          salesTrendChartInstance = createChart('salesTrendChart', 'line', salesLabels, salesData, 'Daily Sales (₱)');
        } else {
          updateChart(salesTrendChartInstance, salesLabels, salesData, 'Daily Sales (₱)', 'line', 'rgba(69, 88, 255, 0.16)', '#4558ff');
        }

        const productLabels = topProducts.map(item => getSafeText(item.name, 'Unnamed Product'));
        const productQuantities = topProducts.map(item => getSafeNumber(item.quantity));

        if (!topProductsChartInstance) {
          topProductsChartInstance = createChart('topProductsChart', 'bar', productLabels, productQuantities, 'Units Sold');
        } else {
          updateChart(topProductsChartInstance, productLabels, productQuantities, 'Units Sold', 'bar', 'rgba(69, 88, 255, 0.16)', '#4558ff');
        }

        lastFetchedSalesHistory = Array.isArray(data.category_history)
          ? data.category_history
          : (Array.isArray(data.history) ? data.history : []);
        salesCategoryTotals = Array.isArray(data.category_totals) ? data.category_totals : [];
        renderSalesCategorySummary();
        applySalesHistoryFilters({ resetPage: true });

        // Sales trend polling complete. Inventory is handled by loadInventoryData.

      } catch (error) {
        console.error('Unable to load sales data:', error);
        if (historyBody) historyBody.innerHTML = '<tr><td colspan="8" class="empty-state">Unable to load sales history.</td></tr>';
      }
    }

    async function loadInventoryData() {
      const body = document.getElementById('inventoryReportBody');
      try {
        const products = await fetchWithError(`../../actions/get_admincashier_products.php`);
        const normalizedProducts = Array.isArray(products)
          ? products
          : (products && typeof products === 'object' && Array.isArray(products.data) ? products.data : []);
        lastFetchedInventory = normalizedProducts;
        renderInventoryTable(lastFetchedInventory, { resetPage: true });
        updateInventorySummary(lastFetchedInventory);
        populateInventoryFilters(lastFetchedInventory);
      } catch (error) {
        console.error('Unable to load inventory data:', error);
        if (body) body.innerHTML = '<tr><td colspan="6" class="empty-state">Unable to load inventory data.</td></tr>';
      }
    }

    function renderInventoryTable(products, options = {}) {
      const body = document.getElementById('inventoryReportBody');
      if (!body) return;

      const resetPage = options.resetPage !== false;
      if (resetPage) {
        inventoryPaginationState.page = 1;
      }

      const bounds = getPageBounds(products, inventoryPaginationState.page, INVENTORY_ROWS_PER_PAGE);
      inventoryPaginationState = {
        page: bounds.safePage,
        totalPages: bounds.totalPages,
        totalItems: bounds.totalItems
      };
      updatePaginationInfo('inventoryPaginationInfo', bounds);
      updatePaginationControls('inventoryPaginationControls', inventoryPaginationState, (page) => {
        inventoryPaginationState.page = page;
        renderInventoryTable(lastFetchedInventory, { resetPage: false });
      });

      body.innerHTML = '';
      if (bounds.totalItems === 0) {
        body.innerHTML = '<tr><td colspan="6" class="empty-state">No matching products found.</td></tr>';
        return;
      }

      const pageItems = products.slice(bounds.startIndex, bounds.endIndex);
      pageItems.forEach(item => {
        const stock = getSafeNumber(item.stock_count);
        const unitPrice = getSafeNumber(item.buy_price);
        const status = getStockStatus(stock);
        const value = stock * unitPrice;
        const productName = getSafeText(item.product_name, 'Unnamed Product');
        const category = getSafeText(item.product_category, 'General');
        const course = getSafeText(item.course_program || item.book_course, 'General');
        
        let metadata = '';
        if (category === 'Books') {
          metadata = `<div class="text-xs text-gray-400">Author: ${getSafeText(item.book_author, 'N/A')}</div>`;
        } else if (category === 'Uniform Fabrics') {
          metadata = `<div class="text-xs text-gray-400">${getSafeText(item.uniform_type, 'Fabric')} | ${getSafeText(item.uniform_color, 'Standard')}</div>`;
        }

        const row = document.createElement('tr');
        if (stock < 5) row.style.backgroundColor = 'rgba(239, 68, 68, 0.05)';
        
        row.innerHTML = `
          <td>
            <div class="font-bold text-gray-800">${productName}</div>
            ${metadata}
            <div class="text-xs text-blue-500">${course}</div>
          </td>
          <td class="text-sm font-medium text-gray-600">${category}</td>
          <td style="text-align: right;">${formatCurrency(unitPrice)}</td>
          <td style="text-align: right;" class="font-semibold">${stock}</td>
          <td style="text-align: right;">${formatCurrency(value)}</td>
          <td style="text-align: center;">
            <span class="status-badge ${status.class}">${status.label}</span>
          </td>
        `;
        body.appendChild(row);
      });
    }

    function getStockStatus(stock) {
      if (stock <= 0) return { label: 'Out of Stock', class: 'bg-red-700 text-white' };
      if (stock < 10) return { label: 'Critical Stock', class: 'bg-red-100 text-red-700' };
      if (stock < 20) return { label: 'Low Stock', class: 'bg-yellow-100 text-yellow-700' };
      return { label: 'Healthy Stock', class: 'bg-green-100 text-green-700' };
    }

    function updateInventorySummary(products) {
      const totalProducts = products.length;
      const totalStock = products.reduce((acc, p) => acc + getSafeNumber(p.stock_count), 0);
      const totalValue = products.reduce((acc, p) => acc + (getSafeNumber(p.stock_count) * getSafeNumber(p.buy_price)), 0);
      const lowStockCount = products.filter(p => getSafeNumber(p.stock_count) < 10).length;
      
      document.getElementById('totalInventoryProducts').textContent = totalProducts;
      document.getElementById('totalStockQuantity').textContent = totalStock;
      document.getElementById('totalInventoryValue').textContent = formatCurrency(totalValue);
      document.getElementById('lowStockCount').textContent = lowStockCount;

      // Group by category for top category
      const catValues = products.reduce((acc, p) => {
        const cat = getSafeText(p.product_category, 'General');
        acc[cat] = (acc[cat] || 0) + (getSafeNumber(p.stock_count) * getSafeNumber(p.buy_price));
        return acc;
      }, {});
      
      let topCat = 'N/A', topVal = 0;
      Object.entries(catValues).forEach(([name, val]) => {
        if (val > topVal) { topVal = val; topCat = name; }
      });

      document.getElementById('topCategoryValue').textContent = formatCurrency(topVal);
      document.getElementById('topCategoryName').textContent = topCat;
      document.getElementById('avgStockLevel').textContent = totalProducts > 0 ? (totalStock / totalProducts).toFixed(1) : '0.0';
      
      const health = totalProducts > 0 ? ((products.filter(p => getSafeNumber(p.stock_count) >= 10).length / totalProducts) * 100).toFixed(0) : 100;
      const healthEl = document.getElementById('inventoryHealthScore');
      healthEl.textContent = health + '%';
      healthEl.className = `text-2xl font-bold ${health > 70 ? 'text-green-500' : health > 40 ? 'text-yellow-500' : 'text-red-500'}`;
    }

    function populateInventoryFilters(products) {
      const categorySelect = document.getElementById('inventoryFilterCategory');
      const categories = [...new Set(products.map(i => i.product_category).filter(Boolean))].sort();
      if (categorySelect && categorySelect.options.length <= 1) {
        categories.forEach(c => categorySelect.add(new Option(c, c)));
      }

      const courseSelect = document.getElementById('inventoryFilterCourse');
      const courses = [...new Set(products.map(i => i.course_program || i.book_course).filter(Boolean))].sort();
      if (courseSelect.options.length <= 1) {
        courses.forEach(c => courseSelect.add(new Option(c, c)));
      }
    }
    function renderSalesCategorySummary() {
      const container = document.getElementById('categorySalesSummary');
      if (!container) return;

      const totalsByCategory = new Map(
        salesCategoryTotals.map(total => [getSafeText(total.category), total])
      );
      const categories = ['Uniforms', 'Books', 'Accessories', 'Uncategorized'];
      const filterSelect = document.getElementById('salesCategoryFilter');
      let selectedCategory = filterSelect.value;
      const availableCategories = new Set(categories);
      Array.from(filterSelect.options).forEach(option => {
        if (option.value !== 'all' && !availableCategories.has(option.value)) {
          option.remove();
        }
      });
      salesCategoryTotals.forEach(total => {
        const category = getSafeText(total.category);
        if (category && !availableCategories.has(category)) {
          availableCategories.add(category);
        }
      });
      categories.forEach(category => {
        if (!Array.from(filterSelect.options).some(option => option.value === category)) {
          filterSelect.add(new Option(category, category));
        }
      });
      salesCategoryTotals.forEach(total => {
        const category = getSafeText(total.category);
        if (category && !Array.from(filterSelect.options).some(option => option.value === category)) {
          filterSelect.add(new Option(category, category));
        }
      });
      if (selectedCategory !== 'all' && !availableCategories.has(selectedCategory)) {
        filterSelect.value = 'all';
        selectedCategory = 'all';
      }
      container.innerHTML = '';

      const addCard = (category, label, amount) => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = `category-sales-card${selectedCategory === category ? ' active' : ''}`;
        card.setAttribute('aria-pressed', String(selectedCategory === category));
        const title = document.createElement('span');
        title.textContent = label;
        const total = document.createElement('strong');
        total.textContent = formatCurrency(amount);
        card.append(title, total);
        card.addEventListener('click', () => {
          document.getElementById('salesCategoryFilter').value = category;
          applySalesHistoryFilters({ resetPage: true });
        });
        container.appendChild(card);
      };

      addCard('all', 'All Categories', currentTotalSales);
      categories.forEach(category => {
        const total = totalsByCategory.get(category);
        addCard(category, category, getSafeNumber(total?.total_sales));
      });
    }

    function getFilteredSalesHistory() {
      const category = document.getElementById('salesCategoryFilter').value;
      const searchQuery = salesHistorySearchQuery;
      return lastFetchedSalesHistory.filter(entry => {
        const matchesCategory = category === 'all' || getSafeText(entry.category) === category;
        const text = `${getSafeText(entry.transaction_id)} ${getSafeText(entry.transaction_number)} ${getSafeText(entry.student_id)} ${getSafeText(entry.student_name)} ${getSafeText(entry.item)} ${getSafeText(entry.date)} ${getSafeText(entry.category)}`.toLowerCase();
        return matchesCategory && text.includes(searchQuery);
      });
    }

    function applySalesHistoryFilters(options = {}) {
      renderSalesCategorySummary();
      renderSalesHistory(getFilteredSalesHistory(), options);
    }

    function renderSalesHistory(history, options = {}) {
      const historyBody = document.getElementById('historyBody');
      if (!historyBody) return;

      const resetPage = options.resetPage !== false;
      if (resetPage) {
        salesPaginationState.page = 1;
      }

      const bounds = getPageBounds(history, salesPaginationState.page, SALES_ROWS_PER_PAGE);
      salesPaginationState = {
        page: bounds.safePage,
        totalPages: bounds.totalPages,
        totalItems: bounds.totalItems
      };
      updatePaginationInfo('salesPaginationInfo', bounds);
      updatePaginationControls('salesPaginationControls', salesPaginationState, (page) => {
        salesPaginationState.page = page;
        renderSalesHistory(getFilteredSalesHistory(), { resetPage: false });
      });

      historyBody.innerHTML = '';
      if (bounds.totalItems === 0) {
        historyBody.innerHTML = '<tr><td colspan="8" class="empty-state">No sales history found.</td></tr>';
        return;
      }

      const pageItems = history.slice(bounds.startIndex, bounds.endIndex);
      pageItems.forEach(entry => {
        const items = getSafeText(entry.item).split(',').map(i => i.trim()).filter(Boolean);
        const itemsHtml = items.map(i => `<span class="product-tag">${i}</span>`).join('');
        const displayId = getSafeText(entry.transaction_id || entry.transaction_number, '—');
        const internalId = entry.id || entry.transaction_id || entry.transaction_number;
        const studentId = getSafeText(entry.student_id, '—');
        const studentName = getSafeText(entry.student_name);
        const studentInfo = studentId !== '—' || studentName ? `
            <div class="font-semibold text-gray-800">${studentId}</div>
            ${studentName ? `<div class="text-xs text-gray-500">${studentName}</div>` : ''}` : '—';
        
        const row = document.createElement('tr');
        const amountValue = getSafeNumber(entry.display_amount ?? entry.amount ?? entry.total_amount ?? entry.total ?? 0);
        row.innerHTML = `
          <td>${formatDate(entry.date)}</td>
          <td>${displayId}</td>
          <td>${studentInfo}</td>
          <td></td>
          <td><div class="product-tag-list">${itemsHtml || '<span class="text-gray-400">No items</span>'}</div></td>
          <td>${getSafeNumber(entry.quantity)}</td>
          <td>${formatCurrency(amountValue)}</td>
          <td style="text-align: center;">
            <button onclick="window.viewReceiptDetails('${internalId}')" class="view-details-btn" title="View Details">
              <i class="fas fa-eye"></i>
            </button>
          </td>
        `;
        row.children[3].textContent = getSafeText(entry.category, 'Uncategorized');
        historyBody.appendChild(row);
      });
    }

    function handleInventoryFiltering() {
      const category = document.getElementById('inventoryFilterCategory').value;
      const course = document.getElementById('inventoryFilterCourse').value;
      const statusFilter = document.getElementById('inventoryFilterStatus').value;
      const search = document.getElementById('inventorySearch').value.toLowerCase();

      const filtered = lastFetchedInventory.filter(item => {
        const stock = getSafeNumber(item.stock_count);
        const statusObj = getStockStatus(stock);
        const productName = getSafeText(item.product_name).toLowerCase();
        const author = getSafeText(item.book_author).toLowerCase();
        const description = getSafeText(item.product_description).toLowerCase();
        
        const matchesCategory = !category || getSafeText(item.product_category) === category;
        const matchesCourse = !course || (getSafeText(item.course_program) === course || getSafeText(item.book_course) === course);
        const matchesStatus = !statusFilter || statusObj.label.includes(statusFilter);
        const matchesSearch = !search || productName.includes(search) || author.includes(search) || description.includes(search);
        
        return matchesCategory && matchesCourse && matchesStatus && matchesSearch;
      });
      renderInventoryTable(filtered, { resetPage: true });
    }

    function getChartTheme() {
      const isDarkMode = document.body.classList.contains('dark-mode');
      return {
        text: isDarkMode ? '#cbd5e1' : '#1d2939',
        grid: isDarkMode ? '#334155' : '#eef2f7'
      };
    }

    function createChart(elementId, type, labels, data, label, backgroundColor, borderColor) {
      const ctx = document.getElementById(elementId).getContext('2d');
      const chartTheme = getChartTheme();
      return new Chart(ctx, {
        type,
        data: {
          labels,
          datasets: [{
            label,
            data,
            borderColor: borderColor || '#4558ff',
            backgroundColor: backgroundColor || 'rgba(69, 88, 255, 0.16)',
            fill: type === 'line',
            tension: 0.4,
            borderWidth: 2,
            borderRadius: 12,
            barThickness: 24
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true, labels: { color: chartTheme.text } } },
          scales: {
            x: { grid: { display: false }, ticks: { color: chartTheme.text } },
            y: { grid: { color: chartTheme.grid }, ticks: { beginAtZero: true, color: chartTheme.text } }
          }
        }
      });
    }

    function updateChart(chartRef, labels, data, label, type, backgroundColor, borderColor) {
      if (!chartRef) return;
      const chartTheme = getChartTheme();
      chartRef.data = {
        labels,
        datasets: [{
          label,
          data,
          borderColor: borderColor || '#4558ff',
          backgroundColor: backgroundColor || 'rgba(69, 88, 255, 0.16)',
          fill: type === 'line',
          tension: 0.4,
          borderWidth: 2,
          borderRadius: 12,
          barThickness: 24
        }]
      };
      chartRef.options.plugins.legend.labels.color = chartTheme.text;
      chartRef.options.scales.x.ticks.color = chartTheme.text;
      chartRef.options.scales.y.ticks.color = chartTheme.text;
      chartRef.options.scales.y.grid.color = chartTheme.grid;
      chartRef.update();
    }

    function refreshSalesChartTheme() {
      const chartTheme = getChartTheme();
      [salesTrendChartInstance, topProductsChartInstance].forEach(chart => {
        if (!chart) return;
        chart.options.plugins.legend.labels.color = chartTheme.text;
        chart.options.scales.x.ticks.color = chartTheme.text;
        chart.options.scales.y.ticks.color = chartTheme.text;
        chart.options.scales.y.grid.color = chartTheme.grid;
        chart.update();
      });
    }

    window.viewReceiptDetails = async function(transactionId) {
      const modal = document.getElementById('receiptModal');
      const content = document.getElementById('receiptContent');
      if (!modal || !content) return;
      modal.classList.add('active');
      content.innerHTML = `<div class="flex flex-col items-center py-12 text-gray-400"><i class="fas fa-circle-notch fa-spin text-2xl mb-3"></i><p>Fetching details...</p></div>`;

      try {
        const response = await fetch(`../../actions/get_transaction_details.php?id=${encodeURIComponent(transactionId)}`);
        const data = await response.json().catch(() => ({}));
        if (response.ok && data.success) {
          const t = data.transaction || {};
          const items = Array.isArray(t.items) ? t.items : [];
          const itemsHtml = items.map(item => `
            <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
              <div class="flex-1 pr-2">
                <p class="text-gray-800 font-medium">${getSafeText(item.product_name, 'Item')}</p>
                <p class="text-gray-500 text-xs">${getSafeNumber(item.quantity)} x ${formatCurrency(getSafeNumber(item.unit_price))}</p>
              </div>
              <span class="font-semibold text-gray-700">${formatCurrency(getSafeNumber(item.total_item_amount || item.total))}</span>
            </div>`).join('');
          const totalAmountValue = getSafeNumber(t.display_amount ?? t.total_amount ?? t.amount ?? t.subtotal ?? 0);
          const derivedAmount = deriveTransactionAmount(items, totalAmountValue);
          content.innerHTML = `<div class="space-y-6"><div class="bg-gray-50 p-4 rounded-xl text-sm"><p>Ref: ${getSafeText(t.transaction_number, 'N/A')}</p><p>Date: ${t.created_at ? new Date(t.created_at).toLocaleString() : 'N/A'}</p><p>Customer: ${getSafeText(t.student_name, 'N/A')} (ID: ${getSafeText(t.student_id || t.guest_school_id || t.user_id, 'N/A')})</p><p>Course: ${getSafeText(t.user_course, 'N/A')}</p><p>Cashier: ${getSafeText(t.cashier_name, 'N/A')}</p></div><div>${itemsHtml || '<p class="text-gray-500 text-sm">No item breakdown available.</p>'}</div><div class="bg-gray-50 p-4 rounded-xl text-sm font-bold"><p>Total: ${formatCurrency(derivedAmount)}</p></div></div>`;
        } else {
          content.innerHTML = `<div class="text-sm text-red-600">Unable to load transaction details.</div>`;
        }
      } catch (err) { console.error(err); content.innerHTML = `<div class="text-sm text-red-600">Unable to load transaction details.</div>`; }
    }

    window.closeReceiptModal = () => document.getElementById('receiptModal')?.classList.remove('active');

    function startSalesPolling() {
      if (salesPollInterval) clearInterval(salesPollInterval);
      salesPollInterval = setInterval(() => {
        Promise.allSettled([loadSalesData(currentSalesStartDate, currentSalesEndDate), loadInventoryData()]);
      }, 10000);
    }

    window.stopSalesPolling = () => { if (salesPollInterval) clearInterval(salesPollInterval); salesPollInterval = null; };

    window.exportSalesToExcel = function(exportRows, exportOptions) {
      if (!canExportReports) return;
      if (!Array.isArray(exportRows) || !exportRows.length) return;

      const HIGH_VALUE_THRESHOLD = 1000; // Highlight sales above this amount
      const COLOR_HIGH_VALUE = { rgb: "E2EFDA" }; // Light Green
      const COLOR_INVALID_QTY = { rgb: "FCE4D6" }; // Light Red
      const COLOR_HEADER_BG = { rgb: "4472C4" }; // Professional Blue
      const COLOR_SECTION_BG = { rgb: "D9E1F2" }; // Light Blue for section separators
      const COLOR_TOTAL_BG = { rgb: "F2F2F2" }; // Light Grey for summary totals
      
      const FIXED_OVERHEAD_PERCENT = 10.0; // Configurable Fixed Overhead Percentage
      // Configurable Financial Thresholds
      const LOW_PROFIT_THRESHOLD = 10; // Highlight margins below 10%
      const COLOR_LOW_PROFIT_BG = { rgb: "FFC7CE" }; // Standard Excel light red
      const COLOR_LOW_PROFIT_TEXT = { rgb: "9C0006" }; // Standard Excel dark red

      const now = new Date();
      const exportDateTime = now.toLocaleString('en-PH', { 
        year: 'numeric', month: 'long', day: 'numeric', 
        hour: '2-digit', minute: '2-digit', hour12: true 
      });
      const fileTimestamp = now.toISOString().split('T')[0];

      // --- 2. DATA AGGREGATION (CATEGORY SUMMARY) ---
      const summaryByCategory = {};
      let totalRevenueForContribution = 0;
      let exportTotalSales = 0;
      let exportItemsSold = 0;
      const exportTransactionIds = new Set();
      const productQuantities = {};
      exportRows.forEach(item => {
        const cat = item.category || 'Uncategorized';
        if (!summaryByCategory[cat]) {
          summaryByCategory[cat] = { qty: 0, revenue: 0, transactionIds: new Set() };
        }
        const quantity = getSafeNumber(item.quantity);
        const amt = getSafeNumber(item.amount);
        const transactionId = String(item.id ?? item.transaction_id ?? item.transaction_number);
        const productName = getSafeText(item.item, 'Unnamed Product');
        summaryByCategory[cat].qty += quantity;
        summaryByCategory[cat].revenue += amt;
        summaryByCategory[cat].transactionIds.add(transactionId);
        exportTransactionIds.add(transactionId);
        exportItemsSold += quantity;
        exportTotalSales += amt;
        productQuantities[productName] = (productQuantities[productName] || 0) + quantity;
        totalRevenueForContribution += amt;
      });
      const exportTotalTransactions = exportTransactionIds.size;
      const exportAverageTransaction = exportTotalTransactions ? exportTotalSales / exportTotalTransactions : 0;
      const exportTopProduct = Object.entries(productQuantities).sort((a, b) => b[1] - a[1])[0]?.[0] || 'N/A';
      const cashierLabel = exportOptions.cashierName || 'All Cashiers';
      const signatureSignerName = exportOptions.cashierId ? cashierLabel : (currentAdminName || 'Unknown Admin');
      const categoryLabel = exportOptions.categoryLabel || 'All';

      const dataRows = [];
      
      // --- 1. REPORT HEADER (High Hierarchy) ---
      dataRows.push(["GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY - SALES PERFORMANCE REPORT"]);
      dataRows.push(["SYSTEM: GCST TRACK SYSTEM (POS MODULE)"]);
      dataRows.push(["REPORT TYPE:", "Detailed Transaction Ledger"]);
      dataRows.push(["GENERATED BY:", currentAdminName]);
      dataRows.push(["EXPORT TIMESTAMP:", exportDateTime]);
      dataRows.push(["REPORTING PERIOD:", `${exportOptions.startDate} to ${exportOptions.endDate}`]);
      dataRows.push(["CASHIER:", cashierLabel]);
      dataRows.push(["CATEGORY:", categoryLabel]);
      dataRows.push([]); 
      
      // --- 2. CATEGORY-LEVEL PERFORMANCE SUMMARY ---
      dataRows.push(["CATEGORY PERFORMANCE SUMMARY"]);
      const summaryHeaders = ["Product Category", "Total Items Sold", "Total Sales Amount", "Revenue Contribution (%)", "No. of Transactions"];
      dataRows.push(summaryHeaders);
      
      let grandQty = 0;
      let grandRev = 0;
      let grandTxns = 0;

      Object.entries(summaryByCategory).sort().forEach(([cat, stats]) => {
        const contribution = totalRevenueForContribution > 0 
          ? (stats.revenue / totalRevenueForContribution) * 100 
          : 0;
          
        dataRows.push([cat, stats.qty, formatCurrency(stats.revenue), contribution.toFixed(2) + '%', stats.transactionIds.size]);
        grandQty += stats.qty;
        grandRev += stats.revenue;
      });
      grandTxns = exportTotalTransactions;
      dataRows.push(["GRAND TOTAL", grandQty, formatCurrency(grandRev), "100.00%", grandTxns]);
      dataRows.push([]); // Spacer
      dataRows.push([]); // Spacer

      // --- 2. EXECUTIVE PERFORMANCE SUMMARY ---
      dataRows.push(["EXECUTIVE SUMMARY"]);
      dataRows.push(["Metric Name", "Aggregated Value", "Details/Context"]);
      dataRows.push(["Total Gross Revenue", formatCurrency(exportTotalSales), "Sum of exported sales"]);
      dataRows.push(["Transaction Volume", exportTotalTransactions, "Total number of successful orders"]);
      dataRows.push(["Inventory Movement", exportItemsSold, "Total units sold/released from stock"]);
      dataRows.push(["Average Order Value", formatCurrency(exportAverageTransaction), "Mean revenue per unique transaction"]);
      dataRows.push(["Top Performing Item", exportTopProduct, "Most sold item by volume in this report"]);
      dataRows.push([]); 

      // --- 3. DETAILED TRANSACTION LEDGER ---
      dataRows.push(["DETAILED TRANSACTION RECORDS (GROUPED BY CATEGORY)"]);
      const tableHeaders = [
        'Category', 'Reference ID', 'Cashier', 'Process Date',
        'Products & Specifications', 'Qty', 'Unit Price', 'Discounts', 
        'Total Amount', 'Profit Margin (%)', 'Payment Method'
      ];
      dataRows.push(tableHeaders);

      // Sort history items by category for cleaner grouped reporting
      const sortedHistory = [...exportRows].sort((a, b) =>
        (a.category || 'General Sales').localeCompare(b.category || 'General Sales')
      );

      sortedHistory.forEach(item => {
        const qty = getSafeNumber(item.quantity, 1);
        const amt = getSafeNumber(item.amount);
        const disc = 0;
        const unitP = getSafeNumber(item.unit_price, qty > 0 ? amt / qty : 0);
        
        // --- CONSERVATIVE PROFIT CALCULATION ---
        const cost = parseFloat(item.cost || 0); // Original acquisition cost
        const revenue = amt;
        const overheadCost = revenue * (FIXED_OVERHEAD_PERCENT / 100);
        const netProfit = revenue - cost - overheadCost;
        const profitMarginValue = revenue > 0 ? (netProfit / revenue) * 100 : 0;
        const profitMarginDisplay = revenue > 0 ? profitMarginValue.toFixed(2) + '%' : '—';

        dataRows.push([
          item.category || 'Uncategorized',
          item.transaction_id || item.transaction_number || '---',
          item.cashier_name || 'Unknown Cashier',
          formatDate(item.date),
          item.item || 'N/A',
          qty,
          formatCurrency(unitP),
          formatCurrency(disc),
          formatCurrency(amt),
          profitMarginDisplay,
          item.payment_method || 'Cash Payment'
        ]);
      });
      dataRows.push([]); 

      // --- 4. REPORT FOOTER & AUDIT DATA ---
      dataRows.push(["END OF REPORT"]);
      dataRows.push(["Grand Total Revenue:", formatCurrency(exportTotalSales)]);
      dataRows.push(["Verified By:", currentAdminName]);
      dataRows.push(["Audit Notice:", "This report is an official system-generated document. Unauthorized alteration is prohibited."]);
      dataRows.push([]);
      dataRows.push(["CASHIER APPROVAL"]);
      dataRows.push([exportOptions.cashierId ? "Cashier Name:" : "Exported By:", signatureSignerName]);
      dataRows.push(["Signature:", signatureSignerName]);
      dataRows.push(["Reporting Date Range:", `${exportOptions.startDate} to ${exportOptions.endDate}`]);
      dataRows.push(["Date Signed:", getLocalDateInputValue(now)]);

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.aoa_to_sheet(dataRows);

      // --- 4. ADVANCED REPORT FORMATTING ---
      const range = XLSX.utils.decode_range(ws['!ref']);
      
      // Identify key row indices for context-aware styling
      const catSummaryIdx = dataRows.findIndex(r => r.includes('CATEGORY PERFORMANCE SUMMARY'));
      const execSummaryIdx = dataRows.findIndex(r => r.includes('EXECUTIVE SUMMARY'));
      const ledgerHeaderIdx = dataRows.findIndex(r => r.includes('Category') && r.includes('Reference ID'));
      const reportFooterIdx = dataRows.findIndex(r => r.includes('END OF REPORT'));
      const signatureBlockIdx = dataRows.findIndex(r => r.includes('CASHIER APPROVAL'));

      for (let R = range.s.r; R <= range.e.r; ++R) {
        // Identify data rows for conditional evaluation
        let isLedgerData = R > ledgerHeaderIdx && R < reportFooterIdx;
        let rowStyle = null;
        let lowProfitCellStyle = null;

        if (isLedgerData) {
          const qty = parseFloat(dataRows[R][5] || 0); // Qty is at Index 5 in ledger
          // Extract numeric value from currency string for threshold check
          const totalRaw = String(dataRows[R][8] || "0").replace(/[^0-9.-]+/g,"");
          const total = parseFloat(totalRaw);

          if (qty <= 0) {
            rowStyle = { fill: { fgColor: COLOR_INVALID_QTY } };
          } else if (total >= HIGH_VALUE_THRESHOLD) {
            rowStyle = { fill: { fgColor: COLOR_HIGH_VALUE } };
          }

          // Financial Analysis: Evaluate Profit Margin (%) threshold
          const marginStr = String(dataRows[R][9] || "");
          if (marginStr !== '—' && marginStr.includes('%')) {
            const marginVal = parseFloat(marginStr.replace('%', ''));
            if (!isNaN(marginVal) && marginVal < LOW_PROFIT_THRESHOLD) {
              lowProfitCellStyle = { fill: { fgColor: COLOR_LOW_PROFIT_BG }, font: { color: COLOR_LOW_PROFIT_TEXT, bold: true } };
            }
          }
        }

        for (let C = range.s.c; C <= range.e.c; ++C) {
          const cellRef = XLSX.utils.encode_cell({ r: R, c: C });
          if (!ws[cellRef]) continue;

          // Initialize style object
          ws[cellRef].s = {
            font: { name: "Arial", sz: 10 },
            alignment: { vertical: "center", indent: 1 }
          };

          // Apply Section Title Styling
          if (R === catSummaryIdx || R === execSummaryIdx || R === ledgerHeaderIdx - 1 || R === signatureBlockIdx) {
            ws[cellRef].s.font = { bold: true, sz: 11, color: { rgb: "000000" } };
            ws[cellRef].s.fill = { fgColor: COLOR_SECTION_BG };
          }

          // Apply Table Header Styling (Summary & Ledger)
          if (R === catSummaryIdx + 1 || R === execSummaryIdx + 1 || R === ledgerHeaderIdx) {
            ws[cellRef].s.font = { bold: true, color: { rgb: "FFFFFF" } };
            ws[cellRef].s.fill = { fgColor: COLOR_HEADER_BG };
            ws[cellRef].s.alignment = { horizontal: "center", vertical: "center" };
          }
          
          // Apply Main Title Styling
          if (R < catSummaryIdx && C === 0) {
            ws[cellRef].s.font = { bold: true, sz: 12 };
          }

          // Apply Summary Totals Styling
          if (R === execSummaryIdx - 3 || R >= reportFooterIdx) {
            ws[cellRef].s.font = { bold: true };
            if (R === execSummaryIdx - 3) ws[cellRef].s.fill = { fgColor: COLOR_TOTAL_BG };
          }

          // Apply Conditional Formatting
          if (C === 9 && lowProfitCellStyle) {
            // Specific Cell Formatting: Low Profit Highlight (Precedence over Row Style)
            ws[cellRef].s.fill = lowProfitCellStyle.fill;
            ws[cellRef].s.font = { ...ws[cellRef].s.font, ...lowProfitCellStyle.font };
          } else if (rowStyle) {
            // General Row Formatting: High Value or Invalid Qty
            ws[cellRef].s.fill = rowStyle.fill;
          }
        }
      }

      // --- 5. AUTOMATIC COLUMN SIZING LOGIC ---
      const colWidths = [];
      
      for (let i = 0; i < tableHeaders.length; i++) {
        let maxCharLen = tableHeaders[i].length;
        for (let j = 0; j < dataRows.length; j++) {
          if (dataRows[j][i]) {
            const cellLen = String(dataRows[j][i]).length;
            if (cellLen > maxCharLen) maxCharLen = cellLen;
          }
        }
        // Set width: Char count + padding, constrained between 10 and 60
        colWidths.push({ wch: Math.min(Math.max(maxCharLen, 12) + 2, 60) });
      }
      ws['!cols'] = colWidths;

      XLSX.utils.book_append_sheet(wb, ws, "Sales Report");
      XLSX.writeFile(wb, `Sales_Report_${exportOptions.startDate}_to_${exportOptions.endDate}_${exportOptions.category}_${fileTimestamp}.xlsx`);
    }

    function closeSalesExportModal() {
      const modal = document.getElementById('salesExportModal');
      modal.classList.remove('active');
      modal.setAttribute('aria-hidden', 'true');
    }

    async function loadSalesExportCashiers() {
      const startDate = document.getElementById('exportStartDate').value;
      const endDate = document.getElementById('exportEndDate').value;
      const cashierSelect = document.getElementById('exportCashier');
      const error = document.getElementById('salesExportError');
      const requestId = ++cashierOptionsRequestId;

      if (!startDate || !endDate || startDate > endDate) {
        cashierSelect.disabled = false;
        error.textContent = 'Choose a valid date range to load cashiers.';
        error.hidden = false;
        cashierSelect.replaceChildren(new Option('All Cashiers', ''));
        return;
      }

      const selectedCashier = cashierSelect.value;
      const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        cashier_options: '1'
      });
      cashierSelect.disabled = true;
      cashierSelect.replaceChildren(new Option('Loading cashiers...', ''));

      try {
        const data = await fetchWithError(`../../actions/get_admincashier_sales.php?${params.toString()}`);
        if (requestId !== cashierOptionsRequestId) return;
        if (!data || !data.success || !Array.isArray(data.cashiers)) {
          throw new Error(data?.message || 'Unable to load cashier names.');
        }

        cashierSelect.replaceChildren(new Option('All Cashiers', ''));
        data.cashiers.forEach(cashier => {
          cashierSelect.add(new Option(cashier.name, String(cashier.id)));
        });
        if (selectedCashier && Array.from(cashierSelect.options).some(option => option.value === selectedCashier)) {
          cashierSelect.value = selectedCashier;
        }
        error.hidden = true;
      } catch (loadError) {
        if (requestId !== cashierOptionsRequestId) return;
        console.error('Unable to load cashier options:', loadError);
        cashierSelect.replaceChildren(new Option('All Cashiers', ''));
        error.textContent = loadError.message || 'Unable to load cashier names.';
        error.hidden = false;
      } finally {
        if (requestId === cashierOptionsRequestId) cashierSelect.disabled = false;
      }
    }

    function openSalesExportModal() {
      const modal = document.getElementById('salesExportModal');
      const startDateInput = document.getElementById('exportStartDate');
      const endDateInput = document.getElementById('exportEndDate');
      startDateInput.value = currentSalesStartDate;
      endDateInput.value = currentSalesEndDate;
      endDateInput.min = currentSalesStartDate;
      startDateInput.max = currentSalesEndDate;
      document.getElementById('exportCategory').value = 'all';
      document.getElementById('exportCashier').replaceChildren(new Option('All Cashiers', ''));
      document.getElementById('salesExportError').hidden = true;
      modal.classList.add('active');
      modal.setAttribute('aria-hidden', 'false');
      loadSalesExportCashiers();
    }

    async function generateSalesExport() {
      const startDate = document.getElementById('exportStartDate').value;
      const endDate = document.getElementById('exportEndDate').value;
      const cashierId = document.getElementById('exportCashier').value;
      const categorySelect = document.getElementById('exportCategory');
      const category = categorySelect.value;
      const error = document.getElementById('salesExportError');
      const button = document.getElementById('generateSalesExport');

      if (!startDate || !endDate || startDate > endDate) {
        error.textContent = 'Choose a valid start and end date.';
        error.hidden = false;
        return;
      }

      const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        category,
        export_data: '1'
      });
      if (cashierId) params.set('cashier_id', cashierId);

      button.disabled = true;
      button.textContent = 'Preparing report...';
      error.hidden = true;
      try {
        const data = await fetchWithError(`../../actions/get_admincashier_sales.php?${params.toString()}`);
        if (!data || !data.success || !Array.isArray(data.export_data)) {
          throw new Error(data?.message || 'Unable to prepare the sales report.');
        }
        if (!data.export_data.length) {
          error.textContent = 'No sales match the selected export options.';
          error.hidden = false;
          return;
        }

        const selectedCashier = document.getElementById('exportCashier').selectedOptions[0];
        window.exportSalesToExcel(data.export_data, {
          startDate,
          endDate,
          cashierId,
          cashierName: cashierId ? selectedCashier.textContent : 'All Cashiers',
          category,
          categoryLabel: categorySelect.selectedOptions[0].textContent
        });
        closeSalesExportModal();
      } catch (exportError) {
        console.error('Unable to export sales report:', exportError);
        error.textContent = exportError.message || 'Unable to export the sales report.';
        error.hidden = false;
      } finally {
        button.disabled = false;
        button.textContent = 'Export Excel';
      }
    }

    window.exportInventoryToExcel = function() {
      if (!canExportReports) return;
      if (!lastFetchedInventory.length) return alert('No inventory data to export.');

      const headers = [
        'Product Name',
        'Category',
        'Course/Program',
        'Metadata',
        'Unit Price',
        'Stock Count',
        'Inventory Value',
        'Stock Status'
      ];

      const reportDate = new Date().toLocaleString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
      });
      const fileTimestamp = new Date().toISOString().split('T')[0];

      const rows = lastFetchedInventory.map(item => {
        const stock = parseFloat(item.stock_count || 0);
        const unitPrice = parseFloat(item.buy_price || 0);
        const inventoryValue = stock * unitPrice;
        const metadata = item.product_category === 'Books'
          ? `Author: ${item.book_author || 'N/A'}`
          : (item.uniform_type || 'N/A');
        let status = 'In Stock';
        if (stock <= 0) {
          status = 'Out of Stock';
        } else if (stock < 10) {
          status = 'Low Stock';
        }

        return [
          item.product_name || '',
          item.product_category || '',
          item.course_program || item.book_course || 'General',
          metadata,
          unitPrice,
          stock,
          inventoryValue,
          status
        ];
      });

      const totalProducts = rows.length;
      const totalQuantity = rows.reduce((sum, row) => sum + (parseFloat(row[5]) || 0), 0);
      const lowStockItems = rows.filter(row => row[7] === 'Low Stock').length;
      const outOfStockItems = rows.filter(row => row[7] === 'Out of Stock').length;

      const wsData = [
        ['INVENTORY REPORT'],
        ['Company Name', 'GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY'],
        ['Report Generated', reportDate],
        ['Generated By', currentAdminName || 'System'],
        ['Total Products', totalProducts],
        ['Total Inventory Quantity', totalQuantity],
        ['Low Stock Items', lowStockItems],
        ['Out of Stock Items', outOfStockItems],
        [],
        headers,
        ...rows
      ];

      const wb = XLSX.utils.book_new();
      const ws = XLSX.utils.aoa_to_sheet(wsData);
      const range = XLSX.utils.decode_range(ws['!ref']);
      const headerRow = 8;
      const dataStartRow = headerRow + 1;
      const statusCol = headers.length - 1;

      const headerFill = { fgColor: { rgb: '1F4E78' } };
      const summaryFill = { fgColor: { rgb: 'F5F7FA' } };
      const alternateFill = { fgColor: { rgb: 'F9FAFB' } };
      const inStockFill = { fgColor: { rgb: 'E8F5E9' } };
      const lowStockFill = { fgColor: { rgb: 'FFF3E0' } };
      const outOfStockFill = { fgColor: { rgb: 'FFEBEE' } };
      const border = {
        top: { style: 'thin', color: { rgb: 'D0D7DE' } },
        right: { style: 'thin', color: { rgb: 'D0D7DE' } },
        bottom: { style: 'thin', color: { rgb: 'D0D7DE' } },
        left: { style: 'thin', color: { rgb: 'D0D7DE' } }
      };

      for (let R = range.s.r; R <= range.e.r; R += 1) {
        for (let C = range.s.c; C <= range.e.c; C += 1) {
          const cellRef = XLSX.utils.encode_cell({ r: R, c: C });
          const cell = ws[cellRef];
          if (!cell) continue;

          const baseStyle = {
            font: { name: 'Arial', sz: 10 },
            alignment: { vertical: 'center', wrapText: true },
            border
          };

          if (R === 0) {
            cell.s = {
              ...baseStyle,
              font: { name: 'Arial', sz: 16, bold: true, color: { rgb: 'FFFFFF' } },
              fill: { fgColor: { rgb: '1F4E78' } },
              alignment: { horizontal: 'center', vertical: 'center' }
            };
          } else if (R >= 1 && R <= 7) {
            cell.s = {
              ...baseStyle,
              font: { name: 'Arial', sz: 10 },
              fill: summaryFill,
              alignment: { vertical: 'center' }
            };
            if (C === 0) {
              cell.s.font = { ...cell.s.font, bold: true };
            }
          } else if (R === headerRow) {
            cell.s = {
              ...baseStyle,
              font: { name: 'Arial', sz: 10, bold: true, color: { rgb: 'FFFFFF' } },
              fill: headerFill,
              alignment: { horizontal: 'center', vertical: 'center' }
            };
          } else if (R >= dataStartRow) {
            const isEvenRow = (R - dataStartRow) % 2 === 1;
            cell.s = {
              ...baseStyle,
              fill: isEvenRow ? alternateFill : { fgColor: { rgb: 'FFFFFF' } },
              alignment: { vertical: 'center' }
            };

            if (C === statusCol) {
              const statusValue = String(cell.v || '');
              if (statusValue === 'Out of Stock') {
                cell.s.fill = outOfStockFill;
                cell.s.font = { ...cell.s.font, bold: true, color: { rgb: '9A0007' } };
              } else if (statusValue === 'Low Stock') {
                cell.s.fill = lowStockFill;
                cell.s.font = { ...cell.s.font, bold: true, color: { rgb: '9A5B00' } };
              } else {
                cell.s.fill = inStockFill;
                cell.s.font = { ...cell.s.font, bold: true, color: { rgb: '166534' } };
              }
            } else if (C === 4) {
              cell.s.numFmt = '#,##0.00';
              cell.s.alignment = { ...cell.s.alignment, horizontal: 'right' };
            } else if (C === 5) {
              cell.s.numFmt = '#,##0';
              cell.s.alignment = { ...cell.s.alignment, horizontal: 'center' };
            } else if (C === 6) {
              cell.s.numFmt = '#,##0.00';
              cell.s.alignment = { ...cell.s.alignment, horizontal: 'right' };
            }
          }
        }
      }

      ws['!cols'] = headers.map((header, index) => {
        let maxLength = header.length;
        for (let rowIndex = 0; rowIndex < wsData.length; rowIndex += 1) {
          const value = wsData[rowIndex][index];
          const text = value === undefined || value === null ? '' : String(value);
          if (text.length > maxLength) {
            maxLength = text.length;
          }
        }
        return { width: Math.max(12, Math.min(40, maxLength + 2)) };
      });

      ws['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: headers.length - 1 } }
      ];
      ws['!freeze'] = { xSplit: 0, ySplit: 9 };
      ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: dataStartRow, c: 0 }, e: { r: range.e.r, c: headers.length - 1 } }) };
      ws['!pageSetup'] = { orientation: 'landscape', paperSize: 9 };
      ws['!margins'] = { left: 0.3, right: 0.3, top: 0.5, bottom: 0.5 };

      XLSX.utils.book_append_sheet(wb, ws, 'Inventory Report');
      XLSX.writeFile(wb, `Inventory_Report_${fileTimestamp}.xlsx`);
    };

    window.initAdminCashierSalesPage = function(userData) {
      currentSalesStartDate = getLocalYearStartDate();
      currentSalesEndDate = getLocalDateInputValue();
      document.getElementById('salesStartDate').value = currentSalesStartDate;
      document.getElementById('salesEndDate').value = currentSalesEndDate;
      canExportReports = ['admin', 'admincashier', 'superadmin'].includes(userData.role);
      document.querySelectorAll('.admin-only-export').forEach(button => {
        button.hidden = !canExportReports;
      });
      currentAdminName = userData.name || 'Unknown Admin'; // New: Set admin name from user data
      salesThemeObserver?.disconnect();
      salesThemeObserver = new MutationObserver(refreshSalesChartTheme);
      salesThemeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
      loadSalesData(currentSalesStartDate, currentSalesEndDate);
      loadInventoryData();
      startSalesPolling();

      const startDateInput = document.getElementById('salesStartDate');
      const endDateInput = document.getElementById('salesEndDate');
      const dateRangeError = document.getElementById('salesDateRangeError');
      endDateInput.min = currentSalesStartDate;
      startDateInput.max = currentSalesEndDate;
      startDateInput.addEventListener('change', () => {
        endDateInput.min = startDateInput.value;
        dateRangeError.hidden = true;
      });
      endDateInput.addEventListener('change', () => {
        startDateInput.max = endDateInput.value;
        dateRangeError.hidden = true;
      });
      document.getElementById('applySalesDateRange').addEventListener('click', () => {
        if (!startDateInput.value || !endDateInput.value || startDateInput.value > endDateInput.value) {
          dateRangeError.textContent = 'Choose a valid start and end date.';
          dateRangeError.hidden = false;
          return;
        }

        dateRangeError.hidden = true;
        currentSalesStartDate = startDateInput.value;
        currentSalesEndDate = endDateInput.value;
        loadSalesData(currentSalesStartDate, currentSalesEndDate);
        startSalesPolling();
      });

      const exportStartDateInput = document.getElementById('exportStartDate');
      const exportEndDateInput = document.getElementById('exportEndDate');
      exportEndDateInput.min = currentSalesStartDate;
      exportStartDateInput.max = currentSalesEndDate;
      exportStartDateInput.addEventListener('change', () => {
        exportEndDateInput.min = exportStartDateInput.value;
        document.getElementById('salesExportError').hidden = true;
        loadSalesExportCashiers();
      });
      exportEndDateInput.addEventListener('change', () => {
        exportStartDateInput.max = exportEndDateInput.value;
        document.getElementById('salesExportError').hidden = true;
        loadSalesExportCashiers();
      });
      document.getElementById('export-csv-btn').addEventListener('click', openSalesExportModal);
      document.getElementById('cancelSalesExport').addEventListener('click', closeSalesExportModal);
      document.getElementById('generateSalesExport').addEventListener('click', generateSalesExport);
      document.getElementById('salesCategoryFilter').addEventListener('change', () => {
        applySalesHistoryFilters({ resetPage: true });
      });
      document.getElementById('historySearch')?.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        if (salesSearchTimer) clearTimeout(salesSearchTimer);
        salesSearchTimer = setTimeout(() => {
          salesHistorySearchQuery = query;
          applySalesHistoryFilters({ resetPage: true });
        }, 120);
      });

      document.getElementById('inventoryFilterCategory')?.addEventListener('change', handleInventoryFiltering);
      document.getElementById('inventoryFilterCourse')?.addEventListener('change', handleInventoryFiltering);
      document.getElementById('inventoryFilterStatus')?.addEventListener('change', handleInventoryFiltering);
      document.getElementById('inventorySearch')?.addEventListener('input', handleInventoryFiltering);
    };

    initializeAdminCashierPage(window.initAdminCashierSalesPage);
  </script>
</body>
</html>