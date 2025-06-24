<nav class="admin-nav">
    <div class="nav-section">
        <h3>📅 Events</h3>
        <ul>
            <li><a href="<?= $this->url('admin/events') ?>">All Events</a></li>
            <li><a href="<?= $this->url('admin/events?status=pending') ?>">Pending Approval</a></li>
            <li><a href="<?= $this->url('admin/events?featured=true') ?>">Featured Events</a></li>
        </ul>
    </div>
    
    <div class="nav-section">
        <h3>🏪 Shops</h3>
        <ul>
            <li><a href="<?= $this->url('admin/shops') ?>">All Shops</a></li>
            <li><a href="<?= $this->url('admin/shops?status=pending') ?>">Pending Shops</a></li>
            <li><a href="<?= $this->url('admin/shops?verified=false') ?>">Verify Shops</a></li>
        </ul>
    </div>
    
    <div class="nav-section">
        <h3>💬 Communication</h3>
        <ul>
            <li><a href="<?= $this->url('admin/communication') ?>">Dashboard</a></li>
            <li><a href="<?= $this->url('admin/communication#channels') ?>">Channels</a></li>
            <li><a href="<?= $this->url('admin/communication#users') ?>">Users</a></li>
        </ul>
    </div>
    
    <div class="nav-section">
        <h3>🔧 System</h3>
        <ul>
            <li><a href="<?= $this->url('admin/users') ?>">Users</a></li>
            <li><a href="<?= $this->url('admin/settings') ?>">Settings</a></li>
            <li><a href="<?= $this->url('admin/modules') ?>">Modules</a></li>
        </ul>
    </div>
</nav>

<style>
.admin-nav {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.nav-section {
    margin-bottom: 25px;
}

.nav-section:last-child {
    margin-bottom: 0;
}

.nav-section h3 {
    font-size: 1.1rem;
    color: #343a40;
    margin-bottom: 10px;
}

.nav-section ul {
    list-style: none;
    padding-left: 15px;
}

.nav-section li {
    margin-bottom: 8px;
}

.nav-section a {
    color: #6c757d;
    text-decoration: none;
    display: block;
    padding: 5px 0;
    transition: color 0.2s;
}

.nav-section a:hover {
    color: #007bff;
}
</style>