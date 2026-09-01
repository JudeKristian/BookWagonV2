<!-- BookWagon Global Footer Component -->
<style>
/* BookWagon Footer Styles */
.bw-footer {
    background-color: #ffffff;
    border-top: 1px solid #e2e8f0;
    margin-top: 60px;
    padding-top: 50px;
    padding-bottom: 30px;
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.bw-footer-heading {
    font-weight: 700;
    font-size: 15px;
    color: #f8a100;
    margin-bottom: 18px;
    letter-spacing: 0.2px;
}

.bw-footer-link {
    display: inline-block;
    color: #64748b;
    font-size: 14px;
    margin-bottom: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.bw-footer-link:hover {
    color: #f8a100 !important;
    transform: translateX(4px);
}

.bw-social-icon-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #f1f5f9;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 14px;
}

.bw-social-icon-btn:hover {
    background-color: #f8a100;
    color: #ffffff !important;
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(248, 161, 0, 0.35);
}

.bw-copyright-strip {
    background-color: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 16px 0;
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>

<footer class="bw-footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand Info & Mission -->
            <div class="col-lg-4 col-md-6 mb-3">
                <a href="home.php" class="d-inline-block mb-3">
                    <img src="images/logo.png" alt="BookWagon" style="height: 48px; object-fit: contain;">
                </a>
                <p class="text-muted small mb-3" style="line-height: 1.6; max-width: 320px;">
                    Davao City's premier online book rental and swap community. Empowering readers, connecting local libraries, and making literature affordable and accessible to everyone.
                </p>
                <div class="d-inline-flex align-items-center bg-light border rounded-pill px-3 py-1 text-muted" style="font-size: 12px;">
                    <i class="fa-solid fa-location-dot text-danger me-2"></i> Davao City, Philippines
                </div>
            </div>
            
            <!-- Discover & Read -->
            <div class="col-lg-2 col-md-3 col-6 mb-3">
                <h6 class="bw-footer-heading">Discover</h6>
                <ul class="list-unstyled mb-0">
                    <li><a href="rentbooks.php" class="bw-footer-link">Rent Books</a></li>
                    <li><a href="explore.php" class="bw-footer-link">Explore Catalog</a></li>
                    <li><a href="libraries.php" class="bw-footer-link">Davao Libraries</a></li>
                    <li><a href="bookswap.php" class="bw-footer-link">Book Swap Hub</a></li>
                    <li><a href="home.php" class="bw-footer-link">Most Popular</a></li>
                </ul>
            </div>
            
            <!-- Community & Sellers -->
            <div class="col-lg-3 col-md-3 col-6 mb-3">
                <h6 class="bw-footer-heading">Community</h6>
                <ul class="list-unstyled mb-0">
                    <li><a href="start_selling.php" class="bw-footer-link">Become a Book Owner</a></li>
                    <li><a href="forum_post.php" class="bw-footer-link">Readers Forum</a></li>
                    <li><a href="find_users.php" class="bw-footer-link">Find Book Mates</a></li>
                    <li><a href="welcome.php" class="bw-footer-link">How BookWagon Works</a></li>
                    <li><a href="start_selling.php" class="bw-footer-link">Seller Hub</a></li>
                </ul>
            </div>
            
            <!-- Support & Trust -->
            <div class="col-lg-3 col-md-6 mb-3">
                <h6 class="bw-footer-heading">Support & Trust</h6>
                <ul class="list-unstyled mb-0">
                    <li><a href="terms.php" class="bw-footer-link">Rental Guidelines</a></li>
                    <li><a href="terms.php" class="bw-footer-link">Terms & Conditions</a></li>
                    <li><a href="privacy.php" class="bw-footer-link">Privacy Policy</a></li>
                    <li><a href="forum_post.php" class="bw-footer-link">Help Center & FAQ</a></li>
                    <li><span class="text-muted small"><i class="fa-regular fa-envelope me-1 text-warning"></i> support@bookwagon.ph</span></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- Copyright & Social Strip -->
<div class="bw-copyright-strip">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-muted small text-center text-md-start">
                © <?php echo date('Y'); ?> <strong class="text-dark">BookWagon</strong>. All Rights Reserved. Made with <i class="fa-solid fa-heart text-danger"></i> for Davao City Readers.
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="https://facebook.com" target="_blank" class="bw-social-icon-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://twitter.com" target="_blank" class="bw-social-icon-btn" title="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                <a href="https://instagram.com" target="_blank" class="bw-social-icon-btn" title="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="https://youtube.com" target="_blank" class="bw-social-icon-btn" title="YouTube"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>
</div>
