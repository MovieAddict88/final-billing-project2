<?php
include 'includes/header.php';
require_once "includes/classes/admin-class.php";

// Ensure only admins can access this page
if ($_SESSION['user_role'] !== 'admin') {
    // Redirect non-admin users to the dashboard
    echo '<script>window.location.href = "index.php";</script>';
    exit;
}

$admins = new Admins($dbh);
$monitoring_data = $admins->getEmployerMonitoringData();
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
    :root {
        --primary-blue: #3498db;
        --primary-green: #2ecc71;
        --primary-red: #e74c3c;
        --dark-green: #27ae60;
        --dark-red: #c0392b;
        --text-dark: #2c3e50;
        --text-medium: #34495e;
        --text-light: #7f8c8d;
        --border-color: #ecf0f1;
        --shadow-light: rgba(0, 0, 0, 0.08);
        --shadow-medium: rgba(0, 0, 0, 0.12);
        --card-radius: 12px;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f8f9fa;
        padding-bottom: 20px;
    }

    .page-title {
        text-align: center;
        font-size: clamp(1.8rem, 4vw, 2.2rem);
        font-weight: 600;
        color: var(--text-dark);
        margin: 15px 0 25px;
        padding: 0 15px;
    }

    .monitoring-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        padding: 0 20px 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .employer-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: var(--card-radius);
        box-shadow: 0 5px 15px var(--shadow-light);
        padding: 25px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        border: 1px solid rgba(255, 255, 255, 0.8);
        position: relative;
        overflow: hidden;
    }

    .employer-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary-blue), var(--primary-green));
    }

    .employer-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px var(--shadow-medium);
    }

    .employer-header {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .employer-avatar {
        position: relative;
        margin-right: 18px;
    }

    .employer-avatar img {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e9ecef;
        transition: border-color 0.3s ease;
    }

    .employer-card:hover .employer-avatar img {
        border-color: var(--primary-blue);
    }

    .employer-status {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid white;
    }

    .status-active {
        background-color: var(--primary-green);
    }

    .status-inactive {
        background-color: var(--text-light);
    }

    .employer-details {
        flex-grow: 1;
    }

    .employer-details h3 {
        margin: 0 0 5px 0;
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text-medium);
    }

    .employer-details .location {
        display: flex;
        align-items: center;
        font-size: 0.9rem;
        color: var(--text-light);
        margin: 0;
    }

    .location i {
        margin-right: 6px;
        font-size: 0.8rem;
    }

    .stats-container {
        width: 100%;
    }

    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid var(--border-color);
        transition: background-color 0.2s ease;
        border-radius: 6px;
        padding-left: 15px;
        padding-right: 10px;
        margin-bottom: 5px;
    }

    .stat-item:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .stat-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .stat-label {
        display: flex;
        align-items: center;
        font-size: 0.9rem;
        color: var(--text-medium);
        font-weight: 500;
    }

    .stat-label i {
        margin-right: 8px;
        width: 16px;
        text-align: center;
        font-size: 0.85rem;
    }

    .stat-value {
        font-size: 1.1rem;
        font-weight: 600;
    }

    /* Color coding for different stats */
    .total-customers .stat-value { color: var(--primary-blue); }
    .paid-customers .stat-value { color: var(--primary-green); }
    .unpaid-customers .stat-value { color: var(--primary-red); }
    .monthly-paid .stat-value { color: var(--dark-green); }
    .monthly-unpaid .stat-value { color: var(--dark-red); }
    .total-balance .stat-value { color: var(--dark-red); }
    .performance-indicator .stat-value { color: var(--primary-blue); }


    /* Colorful left borders */
    .stat-item {
        border-left: 4px solid transparent;
    }

    .total-customers { border-left-color: var(--primary-blue); }
    .paid-customers { border-left-color: var(--primary-green); }
    .unpaid-customers { border-left-color: var(--primary-red); }
    .monthly-paid { border-left-color: var(--dark-green); }
    .monthly-unpaid { border-left-color: var(--dark-red); }
    .total-balance { border-left-color: var(--dark-red); }
    .performance-indicator { border-left-color: var(--primary-blue); }
    
    /* Progress bar styling */
    .progress-container {
        position: relative;
        background-color: #e9ecef;
        border-radius: 10px;
        height: 20px;
        overflow: hidden;
        margin-top: 5px;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-green), var(--primary-blue));
        border-radius: 10px;
        transition: width 0.3s ease;
    }
    
    .progress-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-dark);
        text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
    }

    .no-data {
        text-align: center;
        font-size: 1.2rem;
        color: var(--text-light);
        margin: 50px auto;
        padding: 40px 20px;
        background-color: white;
        border-radius: var(--card-radius);
        box-shadow: 0 4px 12px var(--shadow-light);
        max-width: 500px;
        grid-column: 1 / -1;
    }

    .no-data i {
        font-size: 3rem;
        color: #bdc3c7;
        margin-bottom: 15px;
        display: block;
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .monitoring-container {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .monitoring-container {
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 0 15px 20px;
        }
        
        .employer-card {
            padding: 20px;
        }
        
        .employer-avatar img {
            width: 60px;
            height: 60px;
        }
        
        .employer-details h3 {
            font-size: 1.2rem;
        }
        
        .stat-item {
            padding: 12px 0;
            padding-left: 12px;
            padding-right: 8px;
        }
    }

    @media (max-width: 480px) {
        .monitoring-container {
            padding: 0 10px 15px;
        }
        
        .page-title {
            margin: 10px 0 20px;
        }
        
        .employer-header {
            flex-direction: column;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .employer-avatar {
            margin-right: 0;
            margin-bottom: 15px;
        }
        
        .employer-avatar img {
            width: 80px;
            height: 80px;
        }
        
        .stat-label, .stat-value {
            font-size: 0.95rem;
        }
    }

    /* Animation for card entrance */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .employer-card {
        animation: fadeInUp 0.5s ease forwards;
    }

    /* Stagger animation for multiple cards */
    .monitoring-container .employer-card:nth-child(1) { animation-delay: 0.1s; }
    .monitoring-container .employer-card:nth-child(2) { animation-delay: 0.2s; }
    .monitoring-container .employer-card:nth-child(3) { animation-delay: 0.3s; }
    .monitoring-container .employer-card:nth-child(4) { animation-delay: 0.4s; }

    /* Search and Controls Styling */
    .controls-container {
        background: white;
        border-radius: var(--card-radius);
        box-shadow: 0 2px 8px var(--shadow-light);
        margin-bottom: 25px;
        padding: 20px;
    }

    .search-box .input-group {
        box-shadow: 0 2px 4px var(--shadow-light);
        border-radius: 8px;
        overflow: hidden;
    }

    .search-box .input-group-addon {
        background: var(--primary-blue);
        color: white;
        border: none;
        padding: 12px 15px;
    }

    .search-box .form-control {
        border: none;
        padding: 12px 15px;
        font-size: 0.95rem;
    }

    .search-box .form-control:focus {
        box-shadow: none;
        border-color: var(--primary-blue);
    }

    .stats-summary .badge {
        margin-left: 8px;
        font-size: 0.85rem;
        padding: 8px 12px;
    }

    .badge-primary { background-color: var(--primary-blue); }
    .badge-success { background-color: var(--primary-green); }
    .badge-info { background-color: #17a2b8; }

    /* Hidden class for search filtering */
    .employer-card.hidden {
        display: none;
    }

    /* Loading and error states */
    .loading {
        text-align: center;
        padding: 40px;
        color: var(--text-light);
    }
    
    .loading i {
        font-size: 2rem;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .error-message {
        background-color: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin: 20px;
        text-align: center;
        border: 1px solid #f5c6cb;
    }
    
    /* Enhanced card hover effects */
    .employer-card:hover .progress-bar {
        transform: scaleX(1.05);
        transition: transform 0.3s ease;
    }
    
    /* Responsive controls */
    @media (max-width: 768px) {
        .controls-container {
            padding: 15px;
        }
        
        .stats-summary {
            text-align: left !important;
            margin-top: 15px;
        }
        
        .stats-summary .badge {
            display: block;
            margin: 5px 0;
            width: fit-content;
        }
        
        .progress-container {
            height: 16px;
        }
        
        .progress-text {
            font-size: 0.7rem;
        }
    }
</style>

<h1 class="page-title">Employee Monitoring</h1>

<!-- Search and Filter Controls -->
<div class="controls-container" style="max-width: 1400px; margin: 0 auto; padding: 0 20px 20px;">
    <div class="row">
        <div class="col-md-6">
            <div class="search-box">
                <div class="input-group">
                    <span class="input-group-addon"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="employee-search" placeholder="Search employees by name or location...">
                </div>
            </div>
        </div>
        <div class="col-md-6 text-right">
            <div class="stats-summary">
                <span class="badge badge-primary" id="total-employees"><?php echo count($monitoring_data); ?> Employees</span>
                <span class="badge badge-success" id="total-customers">0 Total Customers</span>
                <span class="badge badge-info" id="total-collection">₱0 Collection</span>
            </div>
        </div>
    </div>
</div>

<div class="monitoring-container">
    <?php if (!empty($monitoring_data)): ?>
        <?php foreach ($monitoring_data as $data): ?>
            <div class="employer-card">
                <div class="employer-header">
                    <div class="employer-avatar">
                        <img src="<?php echo !empty($data->info->profile_pic) ? htmlspecialchars($data->info->profile_pic) : '1112.jpg'; ?>" alt="Employer Avatar">
                        <div class="employer-status status-active"></div>
                    </div>
                    <div class="employer-details">
                        <h3><?php echo htmlspecialchars($data->info->full_name); ?></h3>
                        <p class="location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($data->info->location); ?>
                        </p>
                    </div>
                </div>

                <div class="stats-container">
                    <div class="stat-item total-customers">
                        <span class="stat-label">
                            <i class="fas fa-users"></i>
                            Total Customers
                        </span>
                        <span class="stat-value"><?php echo $data->stats['total_customers']; ?></span>
                    </div>
                    <div class="stat-item paid-customers">
                        <span class="stat-label">
                            <i class="fas fa-check-circle"></i>
                            Paid Customers
                        </span>
                        <span class="stat-value"><?php echo $data->stats['paid_customers']; ?></span>
                    </div>
                    <div class="stat-item unpaid-customers">
                        <span class="stat-label">
                            <i class="fas fa-exclamation-circle"></i>
                            Unpaid Customers
                        </span>
                        <span class="stat-value"><?php echo $data->stats['unpaid_customers']; ?></span>
                    </div>
                    <div class="stat-item monthly-paid">
                        <span class="stat-label">
                            <i class="fas fa-money-bill-wave"></i>
                            Month Paid Collection
                        </span>
                        <span class="stat-value">₱<?php echo number_format($data->stats['monthly_paid_collection'], 2); ?></span>
                    </div>
                    <div class="stat-item monthly-unpaid">
                        <span class="stat-label">
                            <i class="fas fa-money-bill"></i>
                            Month Unpaid Amount
                        </span>
                        <span class="stat-value">₱<?php echo number_format($data->stats['monthly_unpaid_collection'], 2); ?></span>
                    </div>
                    <div class="stat-item total-balance">
                        <span class="stat-label">
                            <i class="fas fa-balance-scale"></i>
                            Total Balance
                        </span>
                        <span class="stat-value">₱<?php echo number_format($data->stats['total_balance'], 2); ?></span>
                    </div>
                    
                    <!-- Performance Indicators -->
                    <?php 
                    $total_customers = $data->stats['total_customers'];
                    $paid_customers = $data->stats['paid_customers'];
                    $payment_rate = $total_customers > 0 ? ($paid_customers / $total_customers) * 100 : 0;
                    $collection_rate = $data->stats['monthly_paid_collection'] > 0 ? 
                        ($data->stats['monthly_paid_collection'] / ($data->stats['monthly_paid_collection'] + $data->stats['monthly_unpaid_collection'])) * 100 : 0;
                    ?>
                    
                    <div class="stat-item performance-indicator">
                        <span class="stat-label">
                            <i class="fas fa-chart-line"></i>
                            Payment Rate
                        </span>
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo min(100, $payment_rate); ?>%"></div>
                            <span class="progress-text"><?php echo number_format($payment_rate, 1); ?>%</span>
                        </div>
                    </div>
                    
                    <div class="stat-item performance-indicator">
                        <span class="stat-label">
                            <i class="fas fa-percentage"></i>
                            Collection Rate
                        </span>
                        <span class="stat-value"><?php echo number_format($collection_rate, 1); ?>%</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-user-slash"></i>
            <p>No employee data to display.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('employee-search');
    const cards = document.querySelectorAll('.employer-card');
    const totalEmployeesBadge = document.getElementById('total-employees');
    const totalCustomersBadge = document.getElementById('total-customers');
    const totalCollectionBadge = document.getElementById('total-collection');
    
    // Calculate and display summary statistics
    function updateSummaryStats() {
        let totalCustomers = 0;
        let totalCollection = 0;
        
        cards.forEach(card => {
            if (!card.classList.contains('hidden')) {
                const customers = parseInt(card.querySelector('.total-customers .stat-value').textContent) || 0;
                const collection = parseFloat(card.querySelector('.monthly-paid .stat-value').textContent.replace(/[₱,]/g, '')) || 0;
                totalCustomers += customers;
                totalCollection += collection;
            }
        });
        
        totalCustomersBadge.textContent = totalCustomers + ' Total Customers';
        totalCollectionBadge.textContent = '₱' + totalCollection.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Search functionality
    function filterCards(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        
        cards.forEach(card => {
            const name = card.querySelector('.employer-details h3').textContent.toLowerCase();
            const location = card.querySelector('.location').textContent.toLowerCase();
            
            if (term === '' || name.includes(term) || location.includes(term)) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
        
        updateSummaryStats();
    }
    
    // Event listeners
    searchInput.addEventListener('input', function() {
        filterCards(this.value);
    });
    
    // Initialize summary stats
    updateSummaryStats();
    
    // Add smooth scrolling for better UX
    document.querySelectorAll('.employer-card').forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
    
    // Add click handlers for card interactions
    cards.forEach(card => {
        card.addEventListener('click', function() {
            // Add a subtle click effect
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
    
    // Add keyboard navigation for search
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            filterCards('');
        }
    });
    
    // Add debounced search for better performance
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filterCards(this.value);
        }, 300);
    });
});
</script>

<?php include 'includes/footer.php'; ?>