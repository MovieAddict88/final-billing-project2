<?php
session_start();
require_once 'includes/customer_header.php';
require_once 'includes/classes/admin-class.php';
?>

<style>
/* Payment Status Labels */
.label {
    display: inline-block;
    padding: 4px 8px;
    font-size: 0.75em;
    font-weight: bold;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 3px;
}

.label-success { background-color: #5cb85c; }
.label-danger { background-color: #d9534f; }
.label-warning { background-color: #f0ad4e; }
.label-default { background-color: #777; }
.label-info { background-color: #5bc0de; }

/* Enhanced table styling */
.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
}

/* Enhanced table styling */
.table tbody tr:hover {
    background-color: #f8f9fa;
}

.table th {
    position: sticky;
    top: 0;
    z-index: 10;
}

/* Payment status enhancements */
.payment-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.payment-status .status-icon {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.status-paid .status-icon { background-color: #5cb85c; }
.status-unpaid .status-icon { background-color: #777; }
.status-partial .status-icon { background-color: #f0ad4e; }
.status-rejected .status-icon { background-color: #d9534f; }
.status-info .status-icon { background-color: #5bc0de; }

/* Responsive improvements */
@media (max-width: 768px) {
    .table-responsive {
        border: none;
    }
    
    .btn {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
    
    .table th, .table td {
        padding: 8px 4px;
        font-size: 0.85rem;
    }
    
    .label {
        font-size: 0.7rem;
        padding: 2px 6px;
    }
}
</style>

<?php

$admins = new Admins($dbh);

if (isset($_POST['login_code'])) {
    $login_code = $_POST['login_code'];
    $customer = $admins->fetchCustomerByLoginCode($login_code);
    if ($customer) {
        $_SESSION['customer_id'] = $customer->id;
    } else {
        header('Location: customer_login.php?error=invalid_code');
        exit();
    }
}

if (!isset($_SESSION['customer_id'])) {
    header('Location: customer_login.php');
    exit();
}

$customer_id = $_SESSION['customer_id'];
$customer = $admins->getCustomerInfo($customer_id);
$package = $admins->getPackageInfo($customer->package_id);
$payments = $admins->fetchAllIndividualBill($customer_id);
$ledger = $admins->fetchPaymentHistoryByCustomer($customer_id);

?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card mt-5">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3>Welcome, <?php echo $customer->full_name; ?></h3>
                    <a href="customer_logout.php" class="btn btn-danger">Logout</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Customer Details</h4>
                            <p><strong>Name:</strong> <?php echo $customer->full_name; ?></p>
                            <p><strong>Email:</strong> <?php echo $customer->email; ?></p>
                            <p><strong>Contact:</strong> <?php echo $customer->contact; ?></p>
                            <p><strong>Address:</strong> <?php echo $customer->address; ?></p>
                        </div>
                        <div class="col-md-6">
                            <h4>Package Information</h4>
                            <p><strong>Package:</strong> <?php echo $package->name; ?></p>
                            <p><strong>Fee:</strong> <?php echo $package->fee; ?></p>
                        </div>
                    </div>
                    <hr>
                    <h4>Payment History</h4>
                    <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($payments): ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment->r_month; ?></td>
                                        <td><?php echo $payment->amount; ?></td>
                                        <td><?php echo number_format($payment->paid, 2); ?></td>
                                        <td><?php echo number_format($payment->balance, 2); ?></td>
                                        <td>
                                            <?php 
                                            $status = $payment->status;
                                            $statusClass = '';
                                            $statusText = '';
                                            $statusIcon = '';
                                            
                                            if ($status === 'Rejected') {
                                                $statusClass = 'status-rejected';
                                                $statusText = 'Rejected';
                                                $statusIcon = '<span class="status-icon"></span>';
                                            } elseif ($status === 'Paid' && $payment->balance == 0) {
                                                $statusClass = 'status-paid';
                                                $statusText = 'Paid';
                                                $statusIcon = '<span class="status-icon"></span>';
                                            } elseif ($status === 'Paid' && $payment->balance > 0) {
                                                $statusClass = 'status-partial';
                                                $statusText = 'Partial';
                                                $statusIcon = '<span class="status-icon"></span>';
                                            } elseif ($status === 'Unpaid') {
                                                $statusClass = 'status-unpaid';
                                                $statusText = 'Unpaid';
                                                $statusIcon = '<span class="status-icon"></span>';
                                            } else {
                                                $statusClass = 'status-info';
                                                $statusText = htmlspecialchars($status);
                                                $statusIcon = '<span class="status-icon"></span>';
                                            }
                                            
                                            echo '<span class="payment-status ' . $statusClass . '">' . $statusIcon . $statusText . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($payment->balance > 0): ?>
                                                <a href="payment_transaction.php?id=<?php echo $payment->id; ?>" class="btn btn-primary">Pay Balance</a>
                                            <?php elseif ($payment->status == 'Unpaid' || $payment->status == 'Rejected'): ?>
                                                <a href="payment_transaction.php?id=<?php echo $payment->id; ?>" class="btn btn-primary">Pay</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No payment history found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <hr>
                    <h4>Invoice Payment Ledger</h4>
                    <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Billing Month</th>
                                <th>Package</th>
                                <th>Amount</th>
                                <th>Paid Amount</th>
                                <th>Balance</th>
                                <th>Payment Method</th>
                                <th>Employer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ledger): ?>
                                <?php foreach ($ledger as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($row->paid_at))); ?></td>
                                        <td><?php echo htmlspecialchars($row->r_month); ?></td>
                                        <td><?php echo htmlspecialchars($row->package_name ?: 'N/A'); ?></td>
                                        <td><?php echo number_format((float)$row->amount, 2); ?></td>
                                        <td><?php echo number_format((float)$row->paid_amount, 2); ?></td>
                                        <td>
                                            <?php 
                                            $balance = (float)$row->balance_after;
                                            if ($balance == 0) {
                                                echo '<span class="text-success">₱' . number_format($balance, 2) . '</span>';
                                            } else {
                                                echo '<span class="text-danger">₱' . number_format($balance, 2) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row->payment_method ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row->employer_name ?: 'Admin'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No payment ledger yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/customer_footer.php';
?>