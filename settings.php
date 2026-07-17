<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
Auth::protect();
$user = Auth::getCurrentUser();
include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

$pageTitle = "System Settings";
$breadcrumb = "Dashboard / Settings";

include "includes/page-header.php";

?>

<link rel="stylesheet" href="assets/css/settings.css">

<div class="settings-container">

    <!-- LEFT MENU -->

    <div class="settings-menu">

        <button class="tab-btn active" data-tab="organization">
            <i class="fa fa-building"></i>
            Organization
        </button>

        <button class="tab-btn" data-tab="security">
            <i class="fa fa-shield-alt"></i>
            Security
        </button>

        <button class="tab-btn" data-tab="notifications">
            <i class="fa fa-bell"></i>
            Notifications
        </button>

        <button class="tab-btn" data-tab="email">
            <i class="fa fa-envelope"></i>
            Email
        </button>

        <button class="tab-btn" data-tab="appearance">
            <i class="fa fa-palette"></i>
            Appearance
        </button>

        <button class="tab-btn" data-tab="backup">
            <i class="fa fa-database"></i>
            Backup
        </button>

        <button class="tab-btn" data-tab="logs">
            <i class="fa fa-history"></i>
            Audit Logs
        </button>

    </div>

    <!-- CONTENT -->

    <div class="settings-content">

        <!-- ORGANIZATION -->

        <div class="tab-content active" id="organization">

            <h2>Organization Information</h2>

            <div class="form-group">
                <label>Organization Name</label>
                <input type="text" value="R&M Technologies">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea rows="3">Nairobi, Kenya</textarea>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" value="+254 716 582301">
            </div>

            <button class="save-btn" onclick="saveSettings()">
                Save Changes
            </button>

        </div>

        <!-- SECURITY -->

        <div class="tab-content" id="security">

            <h2>Security Settings</h2>

            <label><input type="checkbox" checked> Enable Two-Factor Authentication</label><br><br>

            <label><input type="checkbox" checked> Password Expiry (90 Days)</label><br><br>

            <label><input type="checkbox"> Force Login Approval</label><br><br>

            <button class="save-btn" onclick="saveSettings()">
                Save Changes
            </button>

        </div>

        <!-- NOTIFICATIONS -->

        <div class="tab-content" id="notifications">

            <h2>Notification Settings</h2>

            <label><input type="checkbox" checked> Email Notifications</label><br><br>

            <label><input type="checkbox" checked> Approval Alerts</label><br><br>

            <label><input type="checkbox"> SMS Alerts</label><br><br>

            <button class="save-btn" onclick="saveSettings()">
                Save Changes
            </button>

        </div>

        <!-- EMAIL -->

        <div class="tab-content" id="email">

            <h2>Email Configuration</h2>

            <div class="form-group">
                <label>SMTP Server</label>
                <input type="text" placeholder="smtp.example.com">
            </div>

            <div class="form-group">
                <label>SMTP Port</label>
                <input type="number" value="587">
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" placeholder="admin@gmail.com">
            </div>

            <button class="save-btn" onclick="saveSettings()">
                Save Changes
            </button>

        </div>

        <!-- APPEARANCE -->

        <div class="tab-content" id="appearance">

            <h2>Appearance</h2>

            <label>System Theme</label>

            <select>

                <option>Light</option>
                <option>Dark</option>

            </select>

            <br><br>

            <button class="save-btn" onclick="saveSettings()">
                Save Changes
            </button>

        </div>

        <!-- BACKUP -->

        <div class="tab-content" id="backup">

            <h2>System Backup</h2>

            <p>Create and restore system backups.</p>

            <button class="backup-btn" onclick="createBackup()">
                <i class="fa fa-download"></i>
                Create Backup
            </button>

        </div>

        <!-- LOGS -->

        <div class="tab-content" id="logs">

            <h2>Audit Logs</h2>

            <table>

                <tr>

                    <th>Date</th>
                    <th>User</th>
                    <th>Activity</th>

                </tr>

                <tr>

                    <td>10 Jul 2026</td>
                    <td>Administrator</td>
                    <td>Updated Security Settings</td>

                </tr>

                <tr>

                    <td>09 Jul 2026</td>
                    <td>Finance Manager</td>
                    <td>Uploaded Financial Report</td>

                </tr>

            </table>

        </div>

    </div>

</div>

<script src="assets/js/settings.js"></script>

<?php

include "includes/footer.php";

?>