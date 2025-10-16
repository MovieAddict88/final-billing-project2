<?php
include 'includes/header.php';
require_once "includes/classes/admin-class.php";

$admins = new Admins($dbh);

// Check user role from session
$user_role = $_SESSION['user_role'] ?? 'admin';

if ($user_role == 'employer') {
    // Employer Dashboard
    $employer_id = $_SESSION['user_id'];
    $customers = $admins->fetchCustomersByEmployer($employer_id);
    $products = $admins->fetchProductsByEmployer($employer_id);
?>
<h3>Employer Dashboard</h3>
<style>
    .table-custom thead {
        background-color: #008080;
        color: white;
    }
    .table-custom .btn-primary {
        background-color: #007bff;
        border-color: #007bff;
    }

    .action-btn {
        width: 80px;
        margin-bottom: 5px;
    }

    .progress-container {
        width: 100%;
        padding: 10px;
    }
    .progress-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    .progress-label {
        width: 80px;
        font-size: 16px;
    }
    .progress-bar-background {
        flex-grow: 1;
        background-color: #f0f0f0;
        height: 20px;
        border-radius: 5px;
        margin: 0 10px;
    }
    .progress-bar-fill {
        height: 100%;
        border-radius: 5px;
        text-align: right;
        color: white;
        font-weight: bold;
        padding-right: 5px;
    }
    .progress-value {
        width: 40px;
        font-size: 16px;
        text-align: right;
    }
</style>
<div class="row employer-stack-laptop">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">Your Assigned Customers</div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="table table-striped table-custom">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Login Code</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customers): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($customer->full_name); ?></td>
                                    <td><?php echo htmlspecialchars($customer->address); ?></td>
                                    <td><?php echo htmlspecialchars($customer->contact); ?></td>
                                    <td><?php echo htmlspecialchars($customer->login_code); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($customer->total_paid, 2)); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($customer->total_balance, 2)); ?></td>
                                    <td><?php echo htmlspecialchars($customer->status); ?></td>
                                    <td>
                                        <a href="pay.php?customer=<?php echo $customer->id; ?>&action=bill" class="btn btn-primary btn-sm action-btn">Invoice</a>
                                        <a href="pay.php?customer=<?php echo $customer->id; ?>" class="btn btn-info btn-sm action-btn">Bill</a>
                                        <?php if ($customer->total_balance > 0): ?>
                                            <a href="manual_payment.php?customer=<?php echo $customer->id; ?>" class="btn btn-warning btn-sm action-btn">Pay Balance</a>
                                        <?php elseif ($customer->status != 'Paid' && $customer->status != 'Partial'): ?>
                                            <a href="manual_payment.php?customer=<?php echo $customer->id; ?>" class="btn btn-success btn-sm action-btn">Pay</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No customers found for this location.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading">Products Availed by Your Customers</div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Package Name</th>
                            <th>Fee</th>
                            <th>Number of Customers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product->name); ?></td>
                                    <td><?php echo htmlspecialchars($product->fee); ?></td>
                                    <td><?php echo htmlspecialchars($product->customer_count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">No products found for this location.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                Overview - Subscribers Count
            </div>
            <div class="panel-body" id="customer-status-progress-bars">
                <!-- Progress bars will be generated here by JavaScript -->
            </div>
        </div>
    </div>
</div>
<?php
} else {
    // Admin Dashboard
?>
<style>
    .chart-legend {
        width: 100%;
    }
    
    .legend-items-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
        width: 100%;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        padding: 6px 0;
    }
    
    .legend-color {
        width: 20px;
        height: 10px;
        margin-right: 8px;
        border-radius: 3px;
        flex-shrink: 0;
    }
    
    .legend-text {
        flex: 1;
        min-width: 0;
        word-wrap: break-word;
    }

    /* === Tablet / Small Laptop (768px - 1024px) === */
    @media (min-width: 768px) {
        .legend-items-container {
            grid-template-columns: repeat(2, 1fr);
            max-height: 400px;
            overflow-y: auto;
        }
    }

    /* === Standard Laptop / Desktop (1024px and above) === */
    @media (min-width: 1024px) {
        .legend-items-container {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    /* === Large Desktop / Smart TV (1400px and above) === */
    @media (min-width: 1400px) {
        .legend-items-container {
            grid-template-columns: repeat(4, 1fr);
        }
        .chart-legend {
            font-size: 1.1em;
        }
    }
</style>

<div class="col-md-12">
    <div class="panel panel-default">
        <div class="panel-heading">
            Customer Connection Locations
        </div>
        <div class="panel-body">
            <canvas id="locationChart"></canvas>
        </div>
    </div>
</div>
<div class="col-md-6">
    <div class="panel panel-default">
        <div class="panel-heading">
        Balance &amp; Cash Collection Chart:
        </div>
        <div class="panel-body">
            <canvas id="myChart">
					
				</canvas>
        </div>
    </div>
</div>
<div class="col-md-6">
    <div class="panel panel-default">
        <div class="panel-heading">
        Monthly Bill Collection : 2016
        </div>
        <div class="panel-body">
            <canvas id="chart2">
					
				</canvas>
        </div>
    </div>
</div>
<?php
} // End of role check
include 'includes/footer.php';
?>
<script type="text/javascript">
    $(document).ready(function() {
        <?php if ($user_role == 'employer'): ?>
        $.ajax({
            url: "customer_status_chart_data.php",
            method: "GET",
            dataType: 'JSON',
            success: function(data) {
                var container = $('#customer-status-progress-bars');
                if (!container.length) return;

                var statusConfig = {
                    'Paid': { label: 'Paid', color: '#28a745', order: 1 },
                    'Balance': { label: 'Balance', color: '#fd7e14', order: 2 },
                    'Unpaid': { label: 'Unpaid', color: '#8B0000', order: 3 },
                    'Rejected': { label: 'Reject', color: '#dc3545', order: 4 },
                    'Prospects': { label: 'Prospect', color: '#6c757d', order: 5 },
                    'Pending': { label: 'Pending', color: '#ffc107', order: 6 }
                };

                var total = data.reduce((acc, item) => acc + parseInt(item.count), 0);

                var displayData = data.map(item => {
                    var config = statusConfig[item.status] || {
                        label: item.status,
                        color: '#333',
                        order: 99
                    };
                    return {
                        ...item,
                        ...config,
                        count: parseInt(item.count)
                    };
                });

                displayData.sort((a, b) => a.order - b.order);

                var progressHtml = '<div class="progress-container">';
                displayData.forEach(function(item) {
                    var percentage = (total > 0) ? (item.count / total) * 100 : 0;
                    progressHtml += `
                        <div class="progress-item">
                            <div class="progress-label">${item.label}</div>
                            <div class="progress-bar-background">
                                <div class="progress-bar-fill" style="width: ${percentage}%; background-color: ${item.color};"></div>
                            </div>
                            <div class="progress-value">${item.count}</div>
                        </div>
                    `;
                });
                progressHtml += '</div>';

                container.html(progressHtml);
            },
            error: function(data) {
                console.log(data);
            }
        });
        <?php else: ?>
        var ctx = document.getElementById('myChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Cash Collection',
                    data: [12, 19, 3, 17, 6, 3, 20],
                    backgroundColor: "rgba(153,255,51,0.6)"
                }, {
                    label: 'Balance',
                    data: [2, 29, 5, 5, 2, 3, 10],
                    backgroundColor: "rgba(245,0,0,0.6)"
                }]
            }
        });
        var ctx = document.getElementById('chart2').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Bill Collection',
                    data: [50000, 60000, 30000, 45000, 48000, 38000, 80000, 50000, 0],
                    backgroundColor: "rgba(0,255,51,0.6)"
                }]
            }
        });

        $.ajax({
            url: "location_chart_data.php",
            method: "GET",
            dataType: 'JSON',
            success: function(data) {
                if (!data || data.length === 0) {
                    $('#locationChart').hide();
                    return;
                }

                var locations = data.map(item => item.conn_location);
                var counts = data.map(item => item.count);

                var professionalColors = [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(199, 199, 199, 0.6)',
                    'rgba(83, 102, 255, 0.6)',
                    'rgba(100, 255, 100, 0.6)',
                    'rgba(255, 100, 100, 0.6)'
                ];

                var backgroundColors = data.map((_, index) => professionalColors[index % professionalColors.length]);

                var chartdata = {
                    labels: locations,
                    datasets: [{
                        label: 'Customer Count',
                        backgroundColor: backgroundColors,
                        borderColor: professionalColors.map(color => color.replace('0.6', '1')),
                        borderWidth: 1,
                        data: counts
                    }]
                };

                var ctx = $('#locationChart');
                if (!ctx.length) return;

                var barChart = new Chart(ctx, {
                    type: 'horizontalBar',
                    data: chartdata,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Customer Distribution by Location',
                            fontSize: 16,
                            fontColor: '#333'
                        },
                        scales: {
                            xAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0,
                                    fontColor: '#333'
                                },
                                gridLines: {
                                    color: "rgba(0, 0, 0, 0.05)",
                                }
                            }],
                            yAxes: [{
                                ticks: {
                                    fontColor: '#333'
                                },
                                gridLines: {
                                    display: false
                                }
                            }]
                        },
                        tooltips: {
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0,0,0,0.7)',
                            titleFontColor: '#fff',
                            bodyFontColor: '#fff',
                            borderColor: 'rgba(0,0,0,0.8)',
                            borderWidth: 1,
                            callbacks: {
                                label: function(tooltipItem, data) {
                                    var label = data.datasets[tooltipItem.datasetIndex].label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += tooltipItem.xLabel;
                                    return label;
                                }
                            }
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart',
                            onComplete: function() {
                                var chartInstance = this.chart;
                                var ctx = chartInstance.ctx;
                                ctx.textAlign = 'left';
                                ctx.font = "bold 12px Arial";
                                ctx.fillStyle = "#333";

                                this.data.datasets.forEach(function(dataset, i) {
                                    var meta = chartInstance.controller.getDatasetMeta(i);
                                    meta.data.forEach(function(bar, index) {
                                        var data = dataset.data[index];
                                        if (data > 0) {
                                            ctx.fillText(data, bar._model.x + 5, bar._model.y + 4);
                                        }
                                    });
                                });
                            }
                        }
                    }
                });
                var chartHeight = 50 + data.length * 30;
                ctx.closest('.panel-body').css('height', chartHeight + 'px');

            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                $('#locationChart').parent().html('<p class="text-center text-danger">Could not load chart data.</p>');
            }
        });

        $.ajax({
            url: "chart.php",
            method: "GET",
            dataType: 'JSON',
            success: function(data) {
                console.log(data);
                var raw = [];
                var qty = [];

                for (var i in data) {
                    raw.push(data[i].name);
                    qty.push(data[i].quantity);
                }
                console.log(raw);
                console.log(qty);
                var chartdata = {
                    labels: raw,
                    datasets: [{
                        label: 'Product Stock',
                        backgroundColor: 'rgba(0,200,225,.5)',
                        borderColor: 'rgba(200, 200, 200, 0.75)',
                        hoverBackgroundColor: 'rgba(200, 200, 200, 1)',
                        hoverBorderColor: 'rgba(200, 200, 200, 1)',
                        data: qty
                    }]
                };

                var ctx = $('#myChart2');

                var barGraph = new Chart(ctx, {
                    type: 'bar',
                    data: chartdata
                });
            },
            error: function(data) {
                console.log(data);
            }
        });
        <?php endif; ?>
    });
</script>