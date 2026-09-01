<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<style>
    .custom-nav-tabs {
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        padding: 0;
        margin-top: 20px;
        margin-bottom: 35px;
        list-style: none !important;
    }
    
    .custom-nav-tabs .nav-item {
        list-style: none !important;
        flex-grow: 1;
        text-align: center;
    }
    
    .custom-nav-tabs .nav-link {
        color: #333333;
        font-weight: 600;
        font-size: 16px;
        padding: 12px 15px;
        border: none;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
        display: inline-block;
        text-decoration: none;
        letter-spacing: 0.2px;
    }
    
    .custom-nav-tabs .nav-link:hover {
        color: #f8a100;
    }
    
    .custom-nav-tabs .nav-link.active {
        color: #f8a100;
        border-bottom: 2px solid #f8a100;
    }
    
    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        .custom-nav-tabs {
            overflow-x: auto;
            justify-content: flex-start;
            white-space: nowrap;
            padding-bottom: 5px;
        }
        .custom-nav-tabs .nav-item {
            flex-grow: 0;
        }
        .custom-nav-tabs .nav-link {
            padding: 10px 15px;
            font-size: 14px;
        }
    }
</style>

<div class="container">
    <ul class="custom-nav-tabs">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'home.php' || $current_page == 'dashboard.php') ? 'active' : ''; ?>" href="home.php">
                Home
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'rentbooks.php') ? 'active' : ''; ?>" href="rentbooks.php">
                Rent Books
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'explore.php') ? 'active' : ''; ?>" href="explore.php">
                Explore
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'libraries.php') ? 'active' : ''; ?>" href="libraries.php">
                Libraries
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'bookswap.php') ? 'active' : ''; ?>" href="bookswap.php">
                Book Swap
            </a>
        </li>
    </ul>
</div>