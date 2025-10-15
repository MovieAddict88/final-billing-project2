<?php
	// Start from getting the hader which contains some settings we need
	require_once 'includes/header.php';

	// Redirect visitor to the login page if he is trying to access
	// this page without being logged in
	if (!isset($_SESSION['admin_session']) )
	{
		$commons->redirectTo(SITE_PATH.'login.php');
	}

    if (!isset($_GET['id'])) {
        $commons->redirectTo(SITE_PATH.'customers.php');
    }

	require_once "includes/classes/admin-class.php";
	$admins = new Admins($dbh);

    $customerId = $_GET['id'];
    $customerDetails = $admins->fetchCustomerDetails($customerId);
    $customerInfo = $customerDetails['info'];
    $allBills = $customerDetails['bills'];
    $transactions = $customerDetails['transactions'];
    $employer = null;
    if ($customerInfo && $customerInfo->employer_id) {
        $employer = $admins->getEmployerById($customerInfo->employer_id);
    }
?>
<style>
    .completed-transaction {
        background-color: lightgreen !important;
    }
</style>
<div class="dashboard">
	<div class="col-md-12 col-sm-12" id="customer_details">
		<div class="panel panel-default">
			<div class="panel-heading">
			    <h4>Customer Details</h4>
			</div>
			<div class="panel-body">
                <?php if ($customerInfo): ?>
                    <?php
                        usort($allBills, function($a, $b) {
                            return strtotime($b->g_date) - strtotime($a->g_date);
                        });
                        $packageInfo = $admins->getPackageInfo($customerInfo->package_id);
                        $packageName = $packageInfo ? $packageInfo->name : 'N/A';
                    ?>
                    <div class="table-responsive">
                    <table class="table table-bordered">
                        <tr>
                            <th>Name</th>
                            <td><?= $customerInfo->full_name ?></td>
                        </tr>
                        <tr>
                            <th>Employer's Name</th>
                            <td><?= $employer ? $employer->full_name : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <th>NID</th>
                            <td><?= $customerInfo->nid ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?= $customerInfo->address ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?= $customerInfo->email ?></td>
                        </tr>
                        <tr>
                            <th>Contact</th>
                            <td><?= $customerInfo->contact ?></td>
                        </tr>
                        <tr>
                            <th>Connection Location</th>
                            <td><?= $customerInfo->conn_location ?></td>
                        </tr>
                        <tr>
                            <th>IP Address</th>
                            <td><?= $customerInfo->ip_address ?></td>
                        </tr>
                        <tr>
                            <th>Connection Type</th>
                            <td><?= $customerInfo->conn_type ?></td>
                        </tr>
                    </table>
                    </div>

                    <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead style="background-color: #008080; color: white;">
                            <tr>
                                <th>Package</th>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($allBills && count($allBills) > 0): ?>
                                <?php foreach ($allBills as $bill):
                                    $paidAmount = $bill->amount - $bill->balance;
                                    $balance = $bill->balance;
                                ?>
                                    <tr>
                                        <td><?= $packageName ?></td>
                                        <td><?= $bill->r_month ?></td>
                                        <td><?= $bill->amount ?></td>
                                        <td><?= $paidAmount ?></td>
                                        <td><?= $balance ?></td>
                                        <td><?= $bill->amount ?></td>
                                        <td><a href="pay.php?customer=<?= $customerId ?>&action=bill" class="btn btn-primary">Invoice</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No billing history found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>

                    <h3>Transaction History</h3>
                    <?php if ($allBills && count($allBills) > 0): ?>
                        <div class="table-responsive">
                        <table class="table table-striped">
                            <thead style="background-color: #008080; color: white;">
                                <tr>
                                    <th>Package</th>
                                    <th>Month</th>
                                    <th>Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allBills as $bill):
                                    $paidAmount = $bill->amount - $bill->balance;
                                    $balance = $bill->balance;
                                    $isCompleted = ($bill->status == 'Paid');
                                ?>
                                    <tr class="<?= $isCompleted ? 'completed-transaction' : '' ?>">
                                        <td><?= $packageName ?></td>
                                        <td><?= $bill->r_month ?></td>
                                        <td><?= $bill->amount ?></td>
                                        <td><?= $paidAmount ?></td>
                                        <td><?= $balance ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php else: ?>
                        <p>No transaction history found.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Customer not found.</p>
                <?php endif; ?>
			</div>
        </div>
    </div>
</div>

<?php
	include 'includes/footer.php';
?>