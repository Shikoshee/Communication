<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
Auth::protect();
$user = Auth::getCurrentUser();
require_once 'includes/Settings.php';
$settings = Settings::all();
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
        <button class="tab-btn" data-tab="system">

    <i class="fa fa-cogs"></i>

    System

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

    <div class="settings-section">

        <h2>Organization Information</h2>

        <p class="section-description">
            Configure your organization's basic information.
        </p>

        <div class="settings-grid">

            <div class="form-group">
                <label>Organization Name</label>
                <input
                    type="text"
                    id="organization_name"
                    name="organization_name">
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input
                    type="email"
                    id="organization_email"
                    name="organization_email">
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input
                    type="text"
                    id="organization_phone"
                    name="organization_phone">
            </div>

            <div class="form-group">
                <label>Website</label>
                <input
                    type="url"
                    id="organization_website"
                    name="organization_website">
            </div>

        </div>

        <div class="form-group">

            <label>Address</label>

            <textarea
                rows="4"
                id="organization_address"
                name="organization_address"></textarea>

        </div>

        <button class="save-btn">
            <i class="fa fa-save"></i>
            Save Changes
        </button>

    </div>

</div>

        <!-- SECURITY -->

        <div class="tab-content" id="security">

            <h2>Security Settings</h2>
<label>
    <input
        type="checkbox"
        id="two_factor_auth"
        name="two_factor_auth"
        <?= !empty($settings['two_factor_auth']) ? 'checked' : '' ?>>

    Enable Two-Factor Authentication
</label>

<br><br>

<label>
    <input
        type="checkbox"
        id="password_expiry"
        name="password_expiry"
        <?= !empty($settings['password_expiry']) ? 'checked' : '' ?>>

    Password Expiry (90 Days)
</label>

<br><br>

<label>
<input
type="checkbox"
id="force_login_approval"
name="force_login_approval">

Force Login Approval
</label>
            <button class="save-btn" onclick="saveSettings()">
                Save Changes
            </button>

        </div>

        <!-- NOTIFICATIONS -->

       <div class="tab-content" id="notifications">

    <div class="settings-section">

        <h2>Notification Settings</h2>

        <p class="section-description">
            Manage how users receive system notifications and approval alerts.
        </p>


        <div class="notification-settings">


            <div class="setting-card">

                <div class="setting-info">

                    <h4>Email Notifications</h4>

                    <p>
                        Receive important system updates through email.
                    </p>

                </div>


                <label class="switch">

                    <input
                        type="checkbox"
                        id="email_notifications"
                        name="email_notifications">

                    <span class="slider"></span>

                </label>

            </div>



            <div class="setting-card">

                <div class="setting-info">

                    <h4>Browser Notifications</h4>

                    <p>
                        Show notifications directly inside the browser.
                    </p>

                </div>


                <label class="switch">

                    <input
                        type="checkbox"
                        id="browser_notifications"
                        name="browser_notifications">

                    <span class="slider"></span>

                </label>

            </div>




            <div class="setting-card">

                <div class="setting-info">

                    <h4>Approval Alerts</h4>

                    <p>
                        Notify users when documents require approval.
                    </p>

                </div>


                <label class="switch">

                    <input
                        type="checkbox"
                        id="approval_notifications"
                        name="approval_notifications">

                    <span class="slider"></span>

                </label>

            </div>




            <div class="setting-card">

                <div class="setting-info">

                    <h4>Document Approval Workflow</h4>

                    <p>
                        Require documents to be approved before publishing.
                    </p>

                </div>


                <label class="switch">

                    <input
                        type="checkbox"
                        id="require_document_approval"
                        name="require_document_approval">

                    <span class="slider"></span>

                </label>

            </div>


        </div>


        <button class="save-btn">

            <i class="fa fa-save"></i>

            Save Notification Settings

        </button>


    </div>

</div>
        <!-- EMAIL -->

        <div class="tab-content" id="email">

    <div class="settings-section">

        <h2>Email Configuration</h2>

        <p class="section-description">
            Configure your SMTP server for sending emails.
        </p>

        <div class="settings-grid">

            <div class="form-group">
                <label>SMTP Host</label>
                <input
                    type="text"
                    id="smtp_host"
                    name="smtp_host">
            </div>

            <div class="form-group">
                <label>SMTP Port</label>
                <input
                    type="number"
                    id="smtp_port"
                    name="smtp_port">
            </div>

            <div class="form-group">
                <label>SMTP Username</label>
                <input
                    type="text"
                    id="smtp_username"
                    name="smtp_username">
            </div>

            <div class="form-group">
                <label>SMTP Password</label>
                <input
                    type="password"
                    id="smtp_password"
                    name="smtp_password">
            </div>

            <div class="form-group">
                <label>Encryption</label>

                <select
                    id="smtp_encryption"
                    name="smtp_encryption">

                    <option value="">None</option>
                    <option value="ssl">SSL</option>
                    <option value="tls">TLS</option>

                </select>

            </div>

            <div class="form-group">
                <label>From Name</label>
                <input
                    type="text"
                    id="smtp_from_name"
                    name="smtp_from_name">
            </div>

            <div class="form-group">
                <label>From Email</label>
                <input
                    type="email"
                    id="smtp_from_email"
                    name="smtp_from_email">
            </div>

        </div>

        <button class="save-btn">
            <i class="fa fa-save"></i>
            Save Email Settings
        </button>

    </div>

</div>
        <!-- APPEARANCE -->

      <div class="tab-content" id="appearance">

    <div class="settings-section">

        <h2>Appearance Settings</h2>

        <p class="section-description">
            Customize the interface theme and default document visibility.
        </p>


        <div class="settings-grid">


            <div class="form-group">

                <label>System Theme</label>

                <select 
                    id="theme"
                    name="theme">

                    <option value="light">
                        Light
                    </option>

                    <option value="dark">
                        Dark
                    </option>

                </select>

            </div>



            <div class="form-group">

                <label>
                    Default Document Visibility
                </label>


                <select
                    id="default_visibility"
                    name="default_visibility">


                    <option value="private">
                        Private
                    </option>


                    <option value="department">
                        Department
                    </option>


                    <option value="public">
                        Public
                    </option>


                </select>

            </div>


        </div>



        <button class="save-btn">

            <i class="fa fa-save"></i>

            Save Appearance Settings

        </button>


    </div>


</div>

        <div class="tab-content" id="system">


    <div class="settings-section">


        <h2>System Preferences</h2>


        <p class="section-description">

            Manage document uploads and allowed file formats.

        </p>



        <div class="settings-grid">


            <div class="form-group">

                <label>
                    Maximum Upload Size (MB)
                </label>


                <input
                    type="number"
                    id="max_upload_size"
                    name="max_upload_size">

            </div>



            <div class="form-group">

                <label>
                    Allowed Extensions
                </label>


                <input
                    type="text"
                    id="allowed_extensions"
                    name="allowed_extensions">

                <small>
                    Example: pdf,docx,xlsx,jpg
                </small>


            </div>


        </div>


        <button class="save-btn">


            <i class="fa fa-save"></i>

            Save System Settings


        </button>


    </div>


</div>
        <!-- BACKUP -->

        <div class="tab-content" id="backup">

<h2>System Backup</h2>

<p class="section-description">
Create and manage database backups.
</p>


<button class="backup-btn" onclick="createBackup()">

<i class="fa fa-database"></i>

Create Backup

</button>


<div class="table-responsive">

<table class="backup-table">

<thead>

<tr>

<th>File</th>

<th>Size</th>

<th>Date</th>

<th>Actions</th>

</tr>

</thead>


<tbody id="backupBody">

<tr>

<td colspan="4">
Loading backups...
</td>

</tr>

</tbody>


</table>

</div>


</div>

        <!-- LOGS -->

        <div class="tab-content" id="logs">

            <div class="logs-header">

    <div>

        <h2>Audit Logs</h2>

        <p class="section-description">
            View recent system activity.
        </p>

    </div>

    <input
        type="text"
        id="auditSearch"
        placeholder="Search logs...">

</div>

            <div class="table-responsive">

<table id="auditTable">

<thead>

<tr>

<th>Date</th>

<th>User</th>

<th>Module</th>

<th>Action</th>

</tr>

</thead>

<tbody id="auditBody">

<tr>

<td colspan="4">

Loading...

</td>

</tr>

</tbody>

</table>

</div>

        </div>

    </div>

</div>

<script src="assets/js/settings.js"></script>

<?php

include "includes/footer.php";

?>