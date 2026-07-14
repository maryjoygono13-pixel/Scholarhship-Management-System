<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="top-nav">
    <h2>Dashboard</h2>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>

    <div class="main-content">

        <div class="cards">

            <div class="card">
                <i class="bx bx-user"></i>
            <span class="text card-text">Total Applicants</span>
                <h1>250</h1>
            </div>

            <div class="card">
                <i class="bx bx-task"></i>
            <span class="text card-text">Under Evaluation</span>
                <h1>180</h1>
            </div>

            <div class="card">
                <i class="bx bxs-graduation"></i>
            <span class="text card-text">Active Scholars</span>
                <h1>35</h1>
            </div>

            <div class="card">
                <h3>Renewal Due</h3>
                <h1>20</h1>
            </div>

        </div>


  <div class="chart-section">

    <div class="chart-box">

        <h3>Scholarship Distribution</h3>

        <div class="chart-placeholder">
            <i class='bx bx-pie-chart-alt-2'></i>
            <p>Pie Chart</p>
        </div>

    </div>

    <div class="chart-box">

        <h3>Monthly Applications</h3>

        <div class="chart-placeholder">
            <i class='bx bx-bar-chart-alt-2'></i>
            <p>Bar Chart</p>
        </div>

    </div>

</div>
<div class="bottom-section">

    <!-- Recent Applications -->
    <div class="table-container">

        <div class="table-header">
            <h3>Recent Applications</h3>
            <a href="#">View All</a>
        </div>

        <table>

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Scholarship</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>

            </thead>

            <tbody>

                <tr>
                    <td>001</td>
                    <td>Maria Santos</td>
                    <td>Merit</td>
                    <td><span class="pending">Pending</span></td>
                    <td>Jul 14</td>
                </tr>

                <tr>
                    <td>002</td>
                    <td>John Reyes</td>
                    <td>Financial</td>
                    <td><span class="approved">Approved</span></td>
                    <td>Jul 13</td>
                </tr>

                <tr>
                    <td>003</td>
                    <td>Anna Cruz</td>
                    <td>Athletic</td>
                    <td><span class="rejected">Rejected</span></td>
                    <td>Jul 12</td>
                </tr>

                <tr>
                    <td>004</td>
                    <td>Kevin Lopez</td>
                    <td>Merit</td>
                    <td><span class="approved">Approved</span></td>
                    <td>Jul 11</td>
                </tr>

            </tbody>

        </table>

    </div>

    <!-- Notifications -->
    <div class="notification-container">

        <h3>Notifications</h3>

        <div class="notification-item">
            <i class='bx bx-bell'></i>

            <div>
                <h4>Renewal Deadline</h4>
                <p>July 31, 2026</p>
            </div>

        </div>

        <div class="notification-item">
            <i class='bx bx-user-plus'></i>

            <div>
                <h4>New Applicant</h4>
                <p>5 new applications submitted.</p>
            </div>

        </div>

        <div class="notification-item">
            <i class='bx bx-calendar'></i>

            <div>
                <h4>Evaluation Meeting</h4>
                <p>Tomorrow • 9:00 AM</p>
            </div>

        </div>

    </div>

</div>
    </div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
